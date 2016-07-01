<?php

// samstruct (sam구조파일) 에서 필드명으로 item_key를 가져온다
// 일치하는 필드명이 있으면 필드번호(숫자) 리턴, 없을 경우 -1 리턴 (die 없음)
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
        die2(500, "Internal Server Error (query:get_item_key_from_name)", $DB_CONN->error);
}


// sam_key 에 대해 time 값 확인 (access_id 검증하지 않음)
// samtime 테이블에 해당 sam_key와 time_value 가 등록되지 않으면 die
// 리턴값 없음
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

