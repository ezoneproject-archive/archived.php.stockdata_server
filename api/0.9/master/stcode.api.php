<?php

// ---------------------------------------------------------------- //
// 종목코드 검색
// Resource: stockCode?query
// stockCode: 종목코드(선택가능)
// query : 선택
//        search : 검색 키워드(종목코드 또는 종목명)
// ---------------------------------------------------------------- //
function api_get_stockcode($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];

    $query = 
        "SELECT STCODE, STNAME, CATENAME\n".
        "  FROM STOCKCODE\n".
        " WHERE 1 = ? \n";
    $params = array("i", "1");

    // resource_info 종목코드
    if (strlen($resource_info) > 0) {
        $query .= "   AND STCODE = ? \n";
        $params[0] .= "s";
        $params[] = $resource_info;
    }

    // startno 추가
    if (isset($request['_request']['search'])) {
        $query .= "   AND (STCODE LIKE ? OR STNAME LIKE ?) \n";
        $params[0] .= "ss";
        $params[] = '%'.$request['_request']['search'].'%';
        $params[] = '%'.$request['_request']['search'].'%';
    }

    $query .= 
        " ORDER BY STCODE\n".
        " LIMIT 100 \n";

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare($query)) {
        call_user_func_array(array($stmt, "bind_param"), refValues($params));
        $stmt->execute();

        $stmt->bind_result($r_stcode, $r_stname, $r_catename);
        while ($stmt->fetch())
        {
            $result['dataList'][] = array(
                'stockCode' => $r_stcode,
                'stockName' => $r_stname,
                'cateName' => $r_catename,
            );
        }
        $stmt->close();

        // 조회할 자료가 없으면 404 Not found 로 응답
        if (count($result['dataList']) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:api_get_stockcode)", $DB_CONN->error);

    return $result;
}


// -------------------------------------------------------------------------------------
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

    if ($stmt = @$DB_CONN->prepare("UPDATE STOCKCODE SET STNAME = ?, CATENAME = ? WHERE STCODE = ?")) {
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
