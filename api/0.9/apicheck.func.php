<?php

// ---------------------------------------------------------------- //
// API 등록정보 확인
// @return array(data영역을 제외한 resouce, 인증 필요 여부(Y/N), 모듈명(php파일명), 펑션명)
// ---------------------------------------------------------------- //
function is_api_available($resource_name) {
    global $API_TYPE, $API_VERSION, $DB_CONN;

    require_once "apilist.inc.php";

    //
    if (isset($_REQUEST['__method']))
        $method = $_REQUEST['__method'];
    else
        $method = $_SERVER['REQUEST_METHOD'];

    if (!strcmp($method, "GET") && !strcmp($method, "POST") &&
        !strcmp($method, "PUT") && !strcmp($method, "DELETE")) {
        die2(400, "Bad request method.", $method);
    }

    // resource 와 function 구분 필요
    // ex) GET /mastertable/      : mastertable 전체 구성
    // ex) GET /mastertable/abc   : mastertable 의 (PK)abc 항목에 대한 내용 => abc는 DB에 없음

    //echo "[$resource_name]\n";
    $resource_arr = explode('/', $resource_name);
    // Resource URL 마지막의 '/' 삭제
    if (strlen($resource_arr[count($resource_arr) - 1]) == 0) {
        unset($resource_arr[count($resource_arr) -1]);
    }

    while (count($resource_arr) > 0) {
        $resource_name2 = implode('/', $resource_arr);

        foreach ($API_LIST as $apiitem) {
            if (strcasecmp($apiitem['type'], $API_TYPE) == 0 &&
                strcasecmp($apiitem['version'], $API_VERSION) == 0 &&
                strcasecmp($apiitem['method'], $method) == 0 &&
                strcasecmp($apiitem['resource'], $resource_name2) == 0) {
                // 일치하는 항목 발견
                return array(
                    $resource_name2,        // 실제 resouce (data 영역 제외)
                    $apiitem['authorize'],  // auth 여부
                    $apiitem['module'],     // 구현된 module 명
                    $apiitem['function']);  // call function명
            }
        }

        // resource uri 의 마지막 항목 삭제
        unset($resource_arr[count($resource_arr) -1]);
    }

    die2(404, "Your requested resource does not found.");
}

// ---------------------------------------------------------------- //
// 클라이언트의 새 버전 확인
// @return array("clientversion" => "major.minor.build.revision")
// ---------------------------------------------------------------- //
function api_get_client_version($request) {
    if (strlen($request['_metadata']['ResourceInfo'] > 0))
        die2(400, "Bad Request(1)");

    if (isset($request['_request']) && count($request['_request']) > 0)
        die2(400, "Bad Request(2)");

    if (($versioninfo = @file("stockdata.txt")) === false)
        die2(500, "Internal Server Error (Client version file is not found)");

    return array(
        "clientversion" => chop($versioninfo[0]),
        "clientsetup" => chop($versioninfo[1]),
        "clientmd5" => chop($versioninfo[2]),
    );
}
