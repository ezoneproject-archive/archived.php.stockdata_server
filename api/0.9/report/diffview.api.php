<?php
require_once "trans/trans.func.php";
require_once "master/master.func.php";

// ---------------------------------------------------------------- //
// 종목 변동 현황
// Resource: sam_key/date?query
// sam_key : 필수, 인증정보와 비교
// date : 선택, 범위지정가능 ('yyyymmdd' or 'yyyymmdd-yyyymmdd')
// query : 선택
//        stockcode : 주식종목코드 (여러개 조회할 경우 공백없이 , 로 구분)
// ---------------------------------------------------------------- //
function api_get_diffview($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
    $access_id = $request['_metadata']['ApiAccessId'];

    // 리소스 확인
    // resource_info = "sam_key/date"
    if (!isset($resource_info) || strlen($resource_info) == 0)
        die2(404, "Resource has not found.");

    // resource_info = "sam_key/date"
    //echo $resource_info;
    @list($sam_key, $data_date) = explode("/", $resource_info, 2);
    if (!isset($sam_key))
        die2(400, "Resource format error. (sam_key)", $resource_info);
    if (!isset($data_date))
        die2(400, "Resource format error. (date)", $resource_info);

    // sam_key 사용권한 확인
    validate_sammast_id($access_id, $sam_key);

    $query = 
        "SELECT GSDATE, STCODE,\n".
        "       (SELECT STNAME FROM STOCKCODE WHERE STOCKCODE.STCODE = TBL2.STCODE) STNAME,\n".
        "       MAX(ST0930) ST0930,\n".
        "       MAX(ST1000) ST1000,\n".
        "       MAX(ST1100) ST1100,\n".
        "       MAX(ST1200) ST1200,\n".
        "       MAX(ST1300) ST1300,\n".
        "       MAX(ST1400) ST1400,\n".
        "       MAX(ST1530) ST1530\n".
        "  FROM (\n".
        "  SELECT GSDATE, STCODE,\n".
        "         IF(STRCMP(GSTIME,'0930'),'',STCODE) ST0930,\n".
        "         IF(STRCMP(GSTIME,'1000'),'',STCODE) ST1000,\n".
        "         IF(STRCMP(GSTIME,'1100'),'',STCODE) ST1100,\n".
        "         IF(STRCMP(GSTIME,'1200'),'',STCODE) ST1200,\n".
        "         IF(STRCMP(GSTIME,'1300'),'',STCODE) ST1300,\n".
        "         IF(STRCMP(GSTIME,'1400'),'',STCODE) ST1400,\n".
        "         IF(STRCMP(GSTIME,'1530'),'',STCODE) ST1530 \n".
        "  FROM (\n".
        "    SELECT GSDATE, GSTIME, STCODE\n".
        "      FROM SAMDATA\n".
        "     WHERE SAM_KEY = ? \n";
    $params = array("i", $sam_key);

    // data_date 확인
    if (strlen($data_date) == 8) {
        $query .= "       AND GSDATE = ? \n";
        $params[0] .= "s";
        $params[] = $data_date;
    }
    else if (strlen($data_date) == 17) {
        @list($data_date1, $data_date2) = explode('-', $data_date, 2);
        if (!isset($data_date1, $data_date2))
            die2(400, "Bad request. Date format error (yyyymmdd-yyyymmdd)");

        $query .= "       AND GSDATE BETWEEN ? AND ? \n";
        $params[0] .= "ss";
        $params[] = $data_date1;
        $params[] = $data_date2;
    }
    else
        die2(400, "Bad request. Date format error (yyyymmdd or yyyymmdd-yyyymmdd)");

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
        $query .= "       AND STCODE IN ( ".$str." ) ";
    }

    $query .=
        "     GROUP BY GSDATE, GSTIME, STCODE\n".
        "    ) TBL1\n".
        "  ) TBL2\n".
        "GROUP BY GSDATE, STCODE\n".
        "ORDER BY STCODE, GSDATE\n";

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare($query)) {
        call_user_func_array(array($stmt, "bind_param"), refValues($params));
        $stmt->execute();

        $stmt->bind_result($r_gsDate, $r_stCode, $r_stName, $r_st0930, $r_st1000, $r_st1100,
            $r_st1200, $r_st1300, $r_st1400, $r_st1530);
        while ($stmt->fetch())
        {
            $result['dataList'][] = array(
                'masterId' => $sam_key,
                'gsDate' => $r_gsDate,
                'stockCode' => $r_stCode,
                'stockName' => $r_stName,
                'ST0930' => $r_st0930,
                'ST1000' => $r_st1000,
                'ST1100' => $r_st1100,
                'ST1200' => $r_st1200,
                'ST1300' => $r_st1300,
                'ST1400' => $r_st1400,
                'ST1530' => $r_st1530,
            );
        }
        $stmt->close();

        // 조회할 자료가 없으면 404 Not found 로 응답
        if (count($result['dataList']) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:api_get_diffview)", $DB_CONN->error);

    return $result;
}
