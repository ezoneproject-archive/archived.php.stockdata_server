<?php

// ---------------------------------------------------------------- //
// 종목코드 관리
// ---------------------------------------------------------------- //
function api_get_stockcode($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
/*
    if (strlen($resource_info) == 0)
        return get_sammast_list($request);
    else
        die2(404, "Not found");   // 세부정보 보여주는 건 지원하지 않음
*/
}


// ---------------------------------------------------------------- //
// 종목코드검색
// ResourceInfo에 종목코드를 넣을 경우 해당 종목 검색(없을 경우 404 오류)
// 또는 query string 으로 code=코드, name=종목명 으로 검색
// ---------------------------------------------------------------- //
function api_get_stcode($request) {
    global $DB_CONN;

//
// TODO: develop now...
//

    $resource_info = $request['_metadata']['ResourceInfo'];

    if (isset($request['_request']['start']))
        $start_idx = $request['_request']['start'];
    else
        $start_idx = 1;

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare(
        "SELECT * FROM SAMDATA WHERE SAM_KEY = ? ORDER BY GSDATE, GSTIME, ITEM_KEY LIMIT 100")) {
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
        if (count($result['dataList']) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:api_get_stcode)", $DB_CONN->error);

    return $result;
}

// 종목코드로 단일코드 검색
function get_stcode_stcode($stockCode) {
    global $DB_CONN;

    $result = array();
    if ($stmt = @$DB_CONN->prepare("SELECT STCODE, STNAME, CATENAME FROM STOCKCODE WHERE STCODE = ?")) {
        $stmt->bind_param("s", $stockCode);
        $stmt->execute();

        $stmt->bind_result($r_stcode, $r_stname, $r_catename);
        while ($stmt->fetch())
        {
            $result[] = array(
                'stockCode' => $r_stcode,
                'stockName' => $r_stname,
                'cateName' => $r_catename,
            );
        }
        $stmt->close();
    }
    else
        die2(500, "Internal Server Error (query:get_stcode_stcode)", $DB_CONN->error);

    return $result;
}

// 종목코드 등록
function insert_stcode($stockCode, $stockName, $cateName) {
    global $DB_CONN;

    if ($stmt = @$DB_CONN->prepare("INSERT INTO STOCKCODE (STCODE, STNAME, CATENAME) VALUES (?, ?, ?)")) {
        $stmt->bind_param("sss", $stockCode, $stockName, $cateName);
        if (!$stmt->execute()) {
            $stmt->close();
            die2(500, "Internal Server Error, Insert data. (query:insert_stcode)", $DB_CONN->error);
        }
        $stmt->close();
    }
    else
        die2(500, "Internal Server Error (query:insert_stcode)", $DB_CONN->error);
}

// 종목코드명 변경
function update_stcode($stockCode, $stockName, $cateName) {
    global $DB_CONN;

    if ($stmt = @$DB_CONN->prepare("UPDATE STOCKCODE STNAME = ?, CATENAME = ? WHERE STCODE = ?")) {
        $stmt->bind_param("sss", $stockName, $cateName, $stockCode);
        if (!$stmt->execute()) {
            $stmt->close();
            die2(500, "Internal Server Error, Update data. (query:update_stcode)", $DB_CONN->error);
        }
        $stmt->close();
    }
    else
        die2(500, "Internal Server Error (query:update_stcode)", $DB_CONN->error);
}
