<?php

// ---------------------------------------------------------------- //
// 자료 마스터 목록 또는 상세정보 분기
// ---------------------------------------------------------------- //
function api_get_sammast($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];

    if (strlen($resource_info) == 0)
        return get_sammast_list($request);
    else
        die2(404, "Not found");   // 세부정보 보여주는 건 지원하지 않음
}

// ---------------------------------------------------------------- //
// 자료 마스터 목록
// @return array(reportkey => reportname)
// ---------------------------------------------------------------- //
function get_sammast_list($request) {
    global $DB_CONN;

    $access_id = $request['_metadata']['ApiAccessId'];

    if (isset($request['_request']['start']))
        $start_idx = $request['_request']['start'];
    else
        $start_idx = 0;

    $result = array('masterList' => array());

    if ($stmt = @$DB_CONN->prepare("SELECT SAM_KEY, SAM_NAME FROM SAMMAST WHERE ACCESS_ID = ? AND SAM_KEY > ? ORDER BY SAM_KEY LIMIT 100")) {
        $stmt->bind_param("ii", $access_id, $start_idx);
        $stmt->execute();

        $stmt->bind_result($r_sam_key, $r_sam_name);
        while ($stmt->fetch())
        {
            $result['masterList'][] = array(
                'id' => $r_sam_key,
                'name' => $r_sam_name,
            );
        }
        $stmt->close();

        // 조회할 자료가 없으면 404 Not found 로 응답
        if (count($result) == 0)
            die2(404, "Not found");

        // 세부정보 추가
        foreach ($result['masterList'] as $key => $value) {
            $result['masterList'][$key]['timeList'] = get_sammast_time($value['id']);
            $result['masterList'][$key]['dataHeader'] = get_sammast_struct($value['id']);
        }
    }
    else
        die2(500, "Internal Server Error (query:get_sammast_list)", $DB_CONN->error);

    return $result;
}

// 자료 마스터의 시각정보
// 주의: 인증하지 않음
function get_sammast_time($sam_key) {
    global $DB_CONN;

    $result = array();
    if ($stmt = @$DB_CONN->prepare("SELECT TIME_VALUE, TIME_DISPLAY FROM SAMTIME WHERE SAM_KEY = ? ORDER BY TIME_VALUE")) {
        $stmt->bind_param("i", $sam_key);
        $stmt->execute();

        $stmt->bind_result($r_time_value, $r_time_display);
        while ($stmt->fetch())
        {
            $result[] = array(
                'id' => $r_time_value,
                'name' => $r_time_display,
            );
        }
        $stmt->close();
    }
    else
        die2(500, "Internal Server Error (query:get_sammast_time)", $DB_CONN->error);

    return $result;
}

// 자료 마스터의 자료구조정보
// 주의: 인증하지 않음
function get_sammast_struct($sam_key) {
    global $DB_CONN;

    $result = array();
    if ($stmt = @$DB_CONN->prepare("SELECT ITEM_KEY, ITEM_NAME FROM SAMSTRUCT WHERE SAM_KEY = ? ORDER BY ITEM_KEY")) {
        $stmt->bind_param("i", $sam_key);
        $stmt->execute();

        $stmt->bind_result($r_item_key, $r_item_name);
        while ($stmt->fetch())
        {
            $result[] = array(
                'id' => $r_item_key,
                'name' => $r_item_name,
            );
        }
        $stmt->close();
    }
    else
        die2(500, "Internal Server Error (query:get_sammast_struct)", $DB_CONN->error);

    return $result;
}

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

