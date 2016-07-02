<?php
require_once "trans/trans.func.php";
require_once "master/master.func.php";

// ---------------------------------------------------------------- //
// 집계표
// Resource: sam_key/date?query
// sam_key : 필수, 인증정보와 비교
// date : 필수, 단일
// query : 선택
//        stockcode : 주식종목코드 (여러개 조회할 경우 공백없이 , 로 구분)
// ---------------------------------------------------------------- //
function api_get_listview($request) {
    global $DB_CONN;

    $resource_info = $request['_metadata']['ResourceInfo'];
    $access_id = $request['_metadata']['ApiAccessId'];

    // 리소스 확인
    // resource_info = "sam_key/date"
    if (!isset($resource_info) || strlen($resource_info) == 0)
        die2(404, "Resource has not found.");

    // resource_info = "sam_key/date"
    @list($sam_key, $data_date) = explode("/", $resource_info, 2);
    if (!isset($sam_key))
        die2(400, "Resource format error. (sam_key)", $resource_info);

    // sam_key 사용권한 확인
    validate_sammast_id($access_id, $sam_key);

    $query = 
        "SELECT GSDATE, STCODE, STNAME,\n".
        "       MAX(FGN_0920) FGN_0920,\n".
        "       MAX(FGN_0950) FGN_0950,\n".
        "       MAX(INV_0950) INV_0950,\n".
        "       MAX(FGN_1100) FGN_1100,\n".
        "       MAX(INV_1100) INV_1100,\n".
        "       MAX(FGN_1320) FGN_1320,\n".
        "       MAX(INV_1320) INV_1320,\n".
        "       MAX(FGN_1505) FGN_1505,\n".
        "       MAX(INV_1505) INV_1505\n".
        "  FROM (\n".
        "  SELECT GSDATE, STCODE, STNAME,\n".
        "         IF(STRCMP(GSTIME,'0920'),'',FGN) FGN_0920,\n".
        "         IF(STRCMP(GSTIME,'0950'),'',FGN) FGN_0950,\n".
        "         IF(STRCMP(GSTIME,'0950'),'',INV) INV_0950,\n".
        "         IF(STRCMP(GSTIME,'1100'),'',FGN) FGN_1100,\n".
        "         IF(STRCMP(GSTIME,'1100'),'',INV) INV_1100,\n".
        "         IF(STRCMP(GSTIME,'1320'),'',FGN) FGN_1320,\n".
        "         IF(STRCMP(GSTIME,'1320'),'',INV) INV_1320,\n".
        "         IF(STRCMP(GSTIME,'1505'),'',FGN) FGN_1505,\n".
        "         IF(STRCMP(GSTIME,'1505'),'',INV) INV_1505\n".
        "    FROM (\n".
        "    SELECT GSDATE, STCODE, STNAME, GSTIME, MAX(INV) INV, MAX(FGN) FGN\n".
        "      FROM (\n".
        "      SELECT GSDATE, STCODE, STNAME, GSTIME,\n".
        "             IF(STRCMP(ITEM_NAME,'기관'),'',DATA) INV,\n".
        "             IF(STRCMP(ITEM_NAME,'외인'),'',DATA) FGN\n".
        "        FROM (\n".
        "        SELECT GSDATE, STCODE, GSTIME, DATA,\n".
        "               (SELECT LEFT(ITEM_NAME, 2)\n".
        "                  FROM SAMSTRUCT\n".
        "                 WHERE SAMSTRUCT.SAM_KEY = SAMDATA.SAM_KEY\n".
        "                   AND SAMSTRUCT.ITEM_KEY = SAMDATA.ITEM_KEY) ITEM_NAME,\n".
        "               (SELECT STNAME FROM STOCKCODE\n".
        "                 WHERE STOCKCODE.STCODE = SAMDATA.STCODE) STNAME\n".
        "          FROM SAMDATA\n".
        "         WHERE SAM_KEY = ?\n".
        "           AND ((GSTIME <> '1505'\n".
        "           AND ITEM_KEY IN (\n".
        "               SELECT ITEM_KEY\n".
        "                 FROM SAMSTRUCT\n".
        "                WHERE SAMSTRUCT.SAM_KEY = SAMDATA.SAM_KEY\n".
        "                  AND SAMSTRUCT.ITEM_NAME IN ('외인잠정', '기관잠정')\n".
        "               ))\n".
        "            OR (GSTIME = '1505'\n".
        "           AND ITEM_KEY IN (\n".
        "               SELECT ITEM_KEY\n".
        "                 FROM SAMSTRUCT\n".
        "                WHERE SAMSTRUCT.SAM_KEY = SAMDATA.SAM_KEY\n".
        "                  AND SAMSTRUCT.ITEM_NAME IN ('외인당일', '기관당일')\n".
        "               )))\n";

    $params = array("i", $sam_key);


    // data_date 확인
    if (isset($data_date) && strlen($data_date) == 8) {
        $query .= "   AND GSDATE = ? \n";
        $params[0] .= "s";
        $params[] = $data_date;
    }
    else
        die2(400, "Bad request. Date format error (yyyymmdd)");

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
        $query .= "   AND STCODE IN ( ".$str." ) \n";
    }

    $query .=
        "        ORDER BY GSDATE, STCODE, GSTIME\n".
        "        ) TBL1\n".
        "      ) TBL2\n".
        "      GROUP BY GSDATE, STCODE, STNAME, GSTIME\n".
        "    ) TBL3\n".
        "  ) TBL4\n".
        "GROUP BY GSDATE, STCODE, STNAME\n";

    $result = array('dataList' => array());

    if ($stmt = @$DB_CONN->prepare($query)) {
        call_user_func_array(array($stmt, "bind_param"), refValues($params));
        $stmt->execute();

        $stmt->bind_result($r_gsDate, $r_stCode, $r_stName,
            $r_fgn_0920, $r_fgn_0950, $r_inv_0950,
            $r_fgn_1100, $r_inv_1100, $r_fgn_1320, $r_inv_1320,
            $r_fgn_1505, $r_inv_1505);
        while ($stmt->fetch())
        {
            $result['dataList'][] = array(
                'gsDate' => $r_gsDate,
                'stockCode' => $r_stCode,
                'stockName' => $r_stName,
                'fgn_0920' => $r_fgn_0920,
                'fgn_0950' => $r_fgn_0950,
                'inv_0950' => $r_inv_0950,
                'fgn_1100' => $r_fgn_1100,
                'inv_1100' => $r_inv_1100,
                'fgn_1320' => $r_fgn_1320,
                'inv_1320' => $r_inv_1320,
                'fgn_1505' => $r_fgn_1505,
                'inv_1505' => $r_inv_1505,
            );
        }
        $stmt->close();

        // 조회할 자료가 없으면 404 Not found 로 응답
        if (count($result['dataList']) == 0)
            die2(404, "Not found");
    }
    else
        die2(500, "Internal Server Error (query:api_get_listview)", $DB_CONN->error);

    return $result;
}
