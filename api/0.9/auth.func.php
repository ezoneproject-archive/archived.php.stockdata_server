<?php

/* 지원하는 해시 알고리즘 목록
$algos = hash_algos();
echo "-------------------<br/>\n";
foreach ($algos as $key => $value) {
    echo "$key => $value <br/>\n";
}
echo "-------------------<br/>\n";
*/

/*
AWS sample:

GET https://test.com/?Action=ListUsers&Version=2010-05-08 HTTP/1.1
Content-Type: application/x-www-form-urlencoded; charset=utf-8
Host: test.com
X-Amz-Date: 20150830T123600Z
Authorization: AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/iam/aws4_request, SignedHeaders=content-type;host;x-amz-date, Signature=5d672d79c15b13162d9279b0855cfba6789a8edb4c82c400e06b5924a6f2b5d7

// or 
GET https://test.com?Action=ListUsers&Version=2010-05-08&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIDEXAMPLE%2F20150830%2Fus-east-1%2Fiam%2Faws4_request&X-Amz-Date=20150830T123600Z&X-Amz-Expires=60&X-Amz-SignedHeaders=content-type%3Bhost&X-Amz-Signature=37ac2f4fde00b0ac9bd9eadeb459b1bbee224158d66e7ae5fcadb70b2d181d02 HTTP/1.1
*/


// ---------------------------------------------------------------- //
// 인증 방법 및 버전
// ---------------------------------------------------------------- //
function get_authentication_algorithm() {
    return "API1-HMAC-SHA256";
}

// ---------------------------------------------------------------- //
// 인증정보 검증
// authorization_raw: <인증방법> Credential=<API키>/요청일자('yyyymmdd' GMT)/리소스명(version 제외), Signature=<Credential>을 <API비밀키>로 HMAC hex값
// ex: Authorization: API1-HMAC-SHA256 Credential=testkey/20160621/publickey, Signature=qabcde
// ---------------------------------------------------------------- //
function validate_authentication($authorization_raw, $resource_name_uri) {
    global $DB_CONN;

    // 인증방법 분리
    list($auth_type, $auth_info) = explode(' ', $authorization_raw, 2);

    // 인증방법 검증
    if (is_null($auth_type) || strcmp($auth_type, "API1-HMAC-SHA256") != 0)
        die2(401, "Unknown authorization type.", $auth_type);

    // 인증데이터 유무 확인
    if (is_null($auth_info) || strlen($auth_info) == 0)
        die2(401, "Invalid authorization info.");

    // 인증데이터에서 인증정보(Credential)와 서명(Signature) 분리
    $auth_arr = explode(',', $auth_info);
    $credential = "";
    $signature = "";
    for ($i = 0; $i < count($auth_arr); $i++) {
        $j = trim($auth_arr[$i]);

        if (strncmp($j, "Credential=", 11) == 0) {
            list($drop, $credential) = explode('=', $j, 2);
            continue;
        }
        if (strncmp($j, "Signature=", 10) == 0) {
            list($drop, $signature) = explode('=', $j, 2);
            continue;
        }
    }

    // 인증정보 분해
    @list($api_key, $request_date, $resource_name) = explode('/', $credential, 3);
    if (is_null($api_key) || strlen($api_key) == 0 ||
        is_null($request_date) || strlen($request_date) == 0 ||
        is_null($resource_name) || strlen($resource_name) == 0)
        die2(401, "Credential format error.");

    // 요청 리소스 검증
    if (strcmp($resource_name_uri, $resource_name) != 0)
        die2(401, "Credential resource name error.");

    // 요청일자 검증
    $curdate = gmdate('Ymd');
    if (strncmp($curdate, $request_date, 8) != 0)
        die2(401, "Credential GMT/UTC date error.");

    // api key 검증
    if ($stmt = @$DB_CONN->prepare("SELECT ACCESS_ID, API_KEY, API_SECRET FROM ACCESSKEY WHERE API_KEY = ?")) {
        $stmt->bind_param("s", $api_key);
        $stmt->execute();

        //echo $stmt->field_count."<br>\n";
        $stmt->bind_result($r_access_id, $r_api_key, $r_secret);

        if (!$stmt->fetch()) {
            $stmt->close();
            die2(401, "Invalid API key1.");
        }
        $stmt->close();

        // SQL 이 대소문자 구분을 안 해서 강제 비교
        if (strcmp($api_key, $r_api_key) != 0)
            die2(401, "Invalid API key.");

        // Credential 과 비밀키를 HMAC 조합해서 Signature 생성
        // ToDO: 중복요청을 방지하기 위해 데이터를 hash한 값을 credential 에 추가할 필요 있음
        $signature2 = hash_hmac('sha256', $credential, $r_secret);

        // 인증값 비교
        // hash_equals 를 써도 되지만, 그냥 대문자로 바꿔서 비교하기로 함
        if (strcmp(strtoupper($signature), strtoupper($signature2)) != 0) {
            die2(401, "Invalid secret signature.", $signature2);
        }

        return $r_access_id;
    }
    else
        die2(500, "Internal Server Error (query)");
}

