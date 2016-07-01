<?php
require_once "trans/trans.func.php";
require_once "master/master.func.php";
require_once "master/stcode.api.php";

// ---------------------------------------------------------------- //
// 자료 내역 조회
// Resource: sam_key/date/time?query
// sam_key : 필수, 인증정보와 비교
// date : 선택, 범위지정가능 ('yyyymmdd' or 'yyyymmdd-yyyymmdd')
// time : 선택 (1개 타임만 가능, TODO: 여러 시간대를 공백없이 ,로 구분하여 입력받도록)
// query : 선택
//        stockcode : 주식종목코드 (여러개 조회할 경우 공백없이 , 로 구분)
//        order : 정렬순서 (항목:gsDate, gsTime, stockCode, stockCode, itemKey) (추후 구현)
// ---------------------------------------------------------------- //
function api_get_samdata($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
    $access_id = $request['_metadata']['ApiAccessId'];

    // 리소스 확인
    // resource_info = "sam_key/date/time"
    if (!isset($resource_info) || strlen($resource_info) == 0)
        die2(404, "Resource has not found.");

    // resource_info = "sam_key/date/time"
    //echo $resource_info;
    @list($sam_key, $data_date, $data_time) = explode("/", $resource_info, 3);
    if (!isset($sam_key))
        die2(400, "Resource format error. (sam_key)", $resource_info);

    // sam_key 사용권한 확인
    validate_sammast_id($access_id, $sam_key);

    $query = "SELECT GSDATE, GSTIME, STCODE, ".
        "(SELECT STNAME FROM STOCKCODE WHERE STOCKCODE.STCODE = SAMDATA.STCODE) STNAME, ".
        "ITEM_KEY, (SELECT ITEM_NAME FROM SAMSTRUCT WHERE SAMSTRUCT.SAM_KEY = SAMDATA.SAM_KEY AND SAMSTRUCT.ITEM_KEY = SAMDATA.ITEM_KEY) ITEM_NAME, DATA FROM SAMDATA WHERE SAM_KEY = ? ";
    $params = array("i", $sam_key);

    // data_time 확인
    if (isset($data_time) && strlen($data_time) > 0) {
        validate_samtime($sam_key, $data_time);
        $query .= "AND GSTIME = ? ";
        $params[0] .= "s";
        $params[] = $data_time;
    }

    // data_date 확인
    if (isset($data_date)) {
        if (strlen($data_date) == 8) {
            $query .= "AND GSDATE = ? ";
            $params[0] .= "s";
            $params[] = $data_date;
        }
        else if (strlen($data_date) == 17) {
            @list($data_date1, $data_date2) = explode('-', $data_date, 2);
            if (!isset($data_date1, $data_date2))
                die2(400, "Bad request. Date format error (yyyymmdd-yyyymmdd)");

            $query .= "AND GSDATE BETWEEN ? AND ? ";
            $params[0] .= "ss";
            $params[] = $data_date1;
            $params[] = $data_date2;
        }
        else
            die2(400, "Bad request. Date format error (yyyymmdd or yyyymmdd-yyyymmdd)");
    }

    // 주식코드 추가 (여러개를 ,로 엮어 조회 가능)
    if (isset($request['_request']['stockcode'])) {
        $stcode = explode(',', $request['_request']['stockcode']);
        $str = "";
        foreach ($stcode as $item) {
            if (strlen($str) > 0)
                $str .= ", ";
            $str .= "?";
            $params[0] .= "s";
            $params[] = $item;
        }
        $query .= "AND STCODE IN ( ".$str." ) ";
    }

    // 정렬순서
    if (isset($request['_request']['order'])) {
        // 별도 지정한 순서 (date, time, stcode ... etc)
    }
    else {
        // 기본순서
        $query .= "ORDER BY GSDATE, GSTIME, STCODE, ITEM_KEY ";
    }

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare($query)) {
        call_user_func_array(array($stmt, "bind_param"), refValues($params));
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
        if (count($result['dataList']) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:api_get_samdata)", $DB_CONN->error);

    return $result;
}


// ---------------------------------------------------------------- //
// 자료 등록 처리
// 자료 등록시에는 반드시 sam_key/date/time 가 지정되어야 한다
// @return array('FieldNotFound' => 오류필드 목록, 'Rows' => 입력건수, 'ErrorData' => 미입력데이터array
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
        die2(400, "Resource format error. Date format is 'YYYYMMDD'.", $resource_info);

    // sam_key 사용권한 확인
    validate_sammast_id($access_id, $sam_key);

    // data_time 확인
    validate_samtime($sam_key, $data_time);

    $result = array('FieldNotFound' => '', 'Rows' => 0, 'ErrorData' => array());
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

            if (strcmp($fieldName, "종목명") == 0 ||
                strcmp($fieldName, "종목코드") == 0 ||
                strcmp($fieldName, "소속업종") == 0) {
                continue;
            }

            // 필드검색
            $item_key = get_item_key_from_name($sam_key, $fieldName);
            if ($item_key < 0) {
                // 없는 필드명 => 오류필드명 목록에 추가
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

                    // 입력오류는 ErrorData 에 처리
                    $result['ErrorData'][] = array(
                        'StockName' => $stockName,
                        'FieldName' => $fieldName,
                        'FieldValue' => $fieldValue,
                    );
                }
                else {
                    // $mysqli->affected_rows;
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

// 필드 어레이에서 특정 필드명이 있는지 확인하고 필드값을 리턴
function search_array_name($arr, $value) {
    foreach ($arr as $item) {
        $fieldName = $item['FieldName'];
        $fieldValue = $item['FieldValue'];

        if (strcmp($fieldName, $value) == 0)
            return $fieldValue;
    }
    return "";
}

// ---------------------------------------------------------------- //
// 자료 삭제 처리 (해당일시 자료 전체 삭제)
// 자료 삭제시에는 반드시 sam_key/date/time 가 지정되어야 한다
// @return array()
// ---------------------------------------------------------------- //
function api_delete_samdata($request) {
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

    $result = array();

    // 자료 삭제
    if ($stmt = @$DB_CONN->prepare("DELETE FROM SAMDATA WHERE GSDATE = ? AND GSTIME = ? AND SAM_KEY = ?")) {
        $stmt->bind_param("ssi", $data_date, $data_time, $sam_key);
        // 삭제한 row 가 없더라도 success 처리함
        if (!$stmt->execute()) {
            $stmt->close();
            die2(500, "Can't delete data.", $DB_CONN->error);
        }
        else {
            $stmt->close();
            if ($mysqli->affected_rows == 0) {
                die2(404, "No data for delete.");
            }
        }
    }
    else
        die2(500, "Internal Server Error (query:api_create_samdata)", $DB_CONN->error);

    return $result;
}

