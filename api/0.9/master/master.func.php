<?php

// api access_id 와 sammast 검증 (sammast 에서 해당 테이블 이용 가능한지 검증)
// sammast 테이블에 해당 access_id 가 등록되지 않으면 die
// 리턴값 없음
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
