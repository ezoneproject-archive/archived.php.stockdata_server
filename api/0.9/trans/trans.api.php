<?php

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
// 자료 마스터 목록
// @return array(reportkey => reportname)
// ---------------------------------------------------------------- //
function get_samdata_list($request) {
    global $DB_CONN;

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
            die2(404, "Can't found report id.", $accessid.":".$sam_key);
        }
        $stmt->close();

        return;
    }
    else
        die2(500, "Internal Server Error (query:validate_sammast_id)", $DB_CONN->error);
}
