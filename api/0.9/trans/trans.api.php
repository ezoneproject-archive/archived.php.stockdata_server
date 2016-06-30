<?php
require_once "master/stcode.api.php";

// ---------------------------------------------------------------- //
// 자료 마스터 목록 또는 상세정보 분기
// ---------------------------------------------------------------- //
function api_get_samdata($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];

    $access_id = $request['_metadata']['ApiAccessId'];

    //validate_sammast_id($access_id, $sam_key);

    if (isset($request['_request']['start']))
        $start_idx = $request['_request']['start'];
    else
        $start_idx = 1;

    $sam_key = 1;

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare(
        "SELECT GSDATE, GSTIME, STCODE, ".
        "(SELECT STNAME FROM STOCKCODE WHERE STOCKCODE.STCODE = SAMDATA.STCODE) STNAME, ".
        "ITEM_KEY, (SELECT ITEM_NAME FROM SAMSTRUCT WHERE SAMSTRUCT.SAM_KEY = SAMDATA.SAM_KEY AND SAMSTRUCT.ITEM_KEY = SAMDATA.ITEM_KEY) ITEM_NAME, ".
        "DATA FROM SAMDATA WHERE SAM_KEY = ? ORDER BY GSDATE, GSTIME, ITEM_KEY LIMIT 100")) {
        $stmt->bind_param("i", $sam_key);
        $stmt->execute();

        $stmt->bind_result($r_gsdate, $r_gstime, $r_stcode, $r_stname, $r_itemkey, $r_itemname, $r_data);
        while ($stmt->fetch())
        {
            $result['dataList'][] = array(
                'masterId' => $sam_key,
                'gsDate' => $r_gsdate,
                'gsTime' => $r_gstime,
                'stockCode' => $r_stcode,
                'stockName' => $r_stname,
                'itemKey' => $r_itemkey,
                'itemName' => $r_itemname,
                'data' => $r_data,
            );
        }
        $stmt->close();

        // 조회할 자료가 없으면 404 Not found 로 응답
        if (count($result) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:get_sammast_list)", $DB_CONN->error);

    return $result;
}


// ---------------------------------------------------------------- //
// 자료 등록 처리
// 자료 등록시에는 반드시 sam_key/date/time 가 지정되어야 한다
// @return array(reportkey => reportname)
// ---------------------------------------------------------------- //
function api_create_samdata($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
    $access_id = $request['_metadata']['ApiAccessId'];

    if (!isset($resource_info) || strlen($resource_info) == 0)
        die2(404, "Resource has not found.");

    // resource_info = "sam_key/date/time"
    //echo $resource_info;
    @list($sam_key, $data_date, $data_time) = explode("/", $resource_info, 3);
    if (!isset($sam_key, $data_date, $data_time))
        die2(400, "Resource format error.", $resource_info);

    if (strlen($data_date) != 8)
        die2(400, "Resource format error. Date is 'YYYYMMDD'.", $resource_info);

    // sam_key 사용권한 확인
    validate_sammast_id($access_id, $sam_key);

    // data_time 확인
    validate_samtime($sam_key, $data_time);

    $result = array('FieldNotFound' => "", 'Rows' => 0, 'ErrorData' => array());
    $rowcnt = 0;
    foreach ($request['post_data'] as $row) {
        // 필수항목 추출
        $stockName = search_array_name($row, "종목명");    // 종목명
        $stockCode = search_array_name($row, "종목코드");  // 종목코드
        $stockCate = search_array_name($row, "소속업종");  // 소속업종

        if (strlen($stockCode) == 0)
            die2(400, "Data format error. Can't found Stock code.");
        if (strlen($stockName) == 0)
            die2(400, "Data format error. Can't found Stock name.");

        // 종목검색
        $stcode_res = get_stcode_stcode($stockCode);
        if (count($stcode_res) == 0) {
            // 미등록 종목코드 => 신규등록
            insert_stcode($stockCode, $stockName, $stockCate);
        }

        $colcnt = 0;
        foreach ($row as $column) {
            $fieldName = $column['FieldName'];
            $fieldValue = $column['FieldValue'];

            if (strcmp($fieldName,"종목명") == 0 ||
                strcmp($fieldName,"종목코드") == 0 ||
                strcmp($fieldName,"소속업종") == 0) {
                continue;
            }

            // 필드검색
            $item_key = get_item_key_from_name($sam_key, $fieldName);
            if ($item_key < 0) {
                // 없는 필드명
                if (strpos(@$result['FieldNotFound'], $fieldName) === FALSE) {
                    if (strlen(@$result['FieldNotFound']) > 0)
                        @$result['FieldNotFound'] .= ",";
                    @$result['FieldNotFound'] .= $fieldName;
                }
                continue;
            }

            // 자료 등록
            if ($stmt = @$DB_CONN->prepare("INSERT INTO SAMDATA (GSDATE, GSTIME, SAM_KEY, STCODE, ITEM_KEY, DATA) VALUES (?, ?, ?, ?, ?, ?)")) {
                $stmt->bind_param("ssisis", $data_date, $data_time, $sam_key, $stockCode, $item_key, $fieldValue);
                if (!$stmt->execute()) {
                    $stmt->close();

                    // ErrorData 별도관리
                    $result['ErrorData'][] = array(
                        'StockName' => $stockName,
                        'FieldName' => $fieldName,
                        'FieldValue' => $fieldValue,
                    );
                }
                else {
                    $stmt->close();
                    $colcnt++;
                }
            }
            else
                die2(500, "Internal Server Error (query:api_create_samdata)", $DB_CONN->error);
        }

        if ($colcnt > 0)
            $rowcnt++;
    }
    $result['Rows'] = $rowcnt;

    return $result;
}

// 필드속성에서 item_key를 가져온다
function get_item_key_from_name($sam_key, $name) {
    global $DB_CONN;

    $item_key = -1;
    if ($stmt = @$DB_CONN->prepare("SELECT ITEM_KEY FROM SAMSTRUCT WHERE SAM_KEY = ? AND ITEM_NAME = ?")) {
        $stmt->bind_param("is", $sam_key, $name);
        $stmt->execute();

        $stmt->bind_result($r_item_key);
        if ($stmt->fetch())
        {
            $item_key = $r_item_key;
        }
        $stmt->close();

        return $item_key;
    }
    else
        die2(500, "Internal Server Error (query:validate_sammast_id)", $DB_CONN->error);
}

// 필드 어레이에서 값을 추출한다
function search_array_name($arr, $value) {
    foreach ($arr as $item) {
        $fieldName = $item['FieldName'];
        $fieldValue = $item['FieldValue'];

        if (strcmp($fieldName, $value) == 0)
            return $fieldValue;
    }
    return "";
}

// api access id 와 sammast 검증 (sammast 에서 해당 테이블 이용 가능한지 검증)
function validate_sammast_id($access_id, $sam_key) {
    global $DB_CONN;

    if ($stmt = @$DB_CONN->prepare("SELECT SAM_KEY, SAM_NAME FROM SAMMAST WHERE ACCESS_ID = ? AND SAM_KEY = ?")) {
        $stmt->bind_param("ii", $access_id, $sam_key);
        $stmt->execute();

        $stmt->bind_result($r_sam_key, $r_sam_name);
        if (!$stmt->fetch())
        {
            $stmt->close();
            die2(404, "Can't found report id.", $access_id.":".$sam_key);
        }
        $stmt->close();

        return;
    }
    else
        die2(500, "Internal Server Error (query:validate_sammast_id)", $DB_CONN->error);
}

// sam_key 에 대해 time 값 확인
// access_id 검증하지 않음
function validate_samtime($sam_key, $data_time) {
    global $DB_CONN;

    if ($stmt = @$DB_CONN->prepare("SELECT TIME_DISPLAY FROM SAMTIME WHERE SAM_KEY = ? AND TIME_VALUE = ?")) {
        $stmt->bind_param("is", $sam_key, $data_time);
        $stmt->execute();

        $stmt->bind_result($r_time_display);
        if (!$stmt->fetch())
        {
            $stmt->close();
            die2(404, "Resource format error. Time not found.", $sam_key.":".$data_time);
        }
        $stmt->close();

        return;
    }
    else
        die2(500, "Internal Server Error (query:validate_samtime)", $DB_CONN->error);
}


