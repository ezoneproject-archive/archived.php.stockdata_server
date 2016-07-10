<?php
require_once "global.conf.php";
require_once 'rsakey.conf.php';
require_once 'common.func.php';

// ---------------------------------------------------------------- //
// 요청 주소에서 리소스 추출
// ---------------------------------------------------------------- //
$resource_name = get_resource_name_from_uri();

// ---------------------------------------------------------------- //
// 사용 가능한 리소스인지 확인
// ---------------------------------------------------------------- //
list($resource_real, $resource_auth, $resource_module, $resource_function) = is_api_available($resource_name);

// 리소스 파일이 지정되어 있으면 include
if (!is_null($resource_module) && strlen($resource_module) > 0) {
    if (!is_file($resource_module) && !is_file($API_VERSION.'/'.$resource_module)) {
        die2(500, "Can not load module.", $resource_module);
    }

    require_once $resource_module;
}

// ---------------------------------------------------------------- //
// $request 변수 기본정보 생성
// ---------------------------------------------------------------- //
$resource_info = "";
if (strlen($resource_name) > strlen($resource_real)) {
    $resource_info = substr($resource_name, strlen($resource_real));
    // 경로 앞의 '/' 제거
    while (strcmp(substr($resource_info, 0, 1), "/") == 0) {
        $resource_info = substr($resource_info, 1);
    }
}

$request = array(
    '_metadata' => array(
        'RequestId' => $REQUEST_ID,
        'ResourceKey' => $resource_real,        // 리소스 키(기본값)
        'ResourceInfo' => $resource_info,       // 리소스 추가정보
    ),
);
unset($resource_info);

//
// 변수 정규화
//

//$_REQUEST[] 변수 뒤져서 '_request'로 정규화 한다
// GET/POST 는 여기에 포함됨
foreach ($_REQUEST as $rkey => $rvalue) {
    // RESTful 하지는 못하지만 호스팅 환경에서 PUT, DELETE 안 될 때를 대비하여
    // is_api_available() 에서 __method 를 예약어로 사용하므로 제외처리
    if (strcmp($rkey, "__method") == 0)
        continue;

    $request['_request'][$rkey] = $rvalue;
}
unset($rkey, $rvalue);

// ---------------------------------------------------------------- //
// http일 경우, 데이터 암호화 옵션
// ---------------------------------------------------------------- //
// http 헤더에 RSA Public key 로 encrypt 한 Session key를 보낸다.
// X-Encrypt-Key AES/256/CBC,<encrypted>
$aes_key = "";
if (isset($_SERVER['HTTP_X_ENCRYPT_KEY'])) {
    @list($enc_method, $rsa_enc_key) = explode(',', $_SERVER['HTTP_X_ENCRYPT_KEY'], 2);
    if (is_null($enc_method) || strlen($enc_method) == 0 ||
        is_null($rsa_enc_key) || strlen($rsa_enc_key) == 0)
        die2(400, "Invalid X-Encrypt-Key header.");

    if (strcmp($enc_method, "AES/256/CBC") != 0)
        die2(400, "Invalid X-Encrypt-Key encrypt method.", $enc_method);

    $aes_key = rsa_decrypt($rsa_enc_key, $RSA_KEY['private_key'], null);
}

$post_data = "";
if (strcasecmp($_SERVER['REQUEST_METHOD'],"POST") == 0 ||
    strcasecmp($_SERVER['REQUEST_METHOD'],"PUT") == 0) {
    $post_data = file_get_contents("php://input");

    if (strlen($aes_key) > 0) {
        // data를 aes decrypt 한다
        $iv = '                ';
        $post_data = openssl_decrypt($post_data, 'aes-256-cbc', $aes_key, 0, $iv);
    }

    post_data_handler($request, $post_data);
    //print_r($request['post_data']);
}
unset($post_data);

// ---------------------------------------------------------------- //
// API 인증 체크
// ---------------------------------------------------------------- //
if (strcmp($resource_auth, 'N')) {
    $authorization = "";

    $headers = apache_request_headers();
    foreach ($headers as $header => $value) {
        // echo "$header => $value</br>\n";
        if (strcasecmp($header, "Authorization") == 0) {
            $authorization = $value;
            break;
        }
        // HttpWebRequest에서는 Authorization 헤더를 스스로 삭제하는 경우가 있어서
        // X-Authorization 헤더로 대신하도록 함
        if (strcasecmp($header, "X-Authorization") == 0) {
            $authorization = $value;
            break;
        }
    }
    unset($header, $value, $headers);

    if (strlen($authorization) == 0)
        die2(401, "Authorization header required.");

    // 인증정보 검증 처리
    list($access_id, $api_key) = validate_authentication($authorization, $resource_name);
    // api 키 set
    $request['_metadata']['ApiAccessId'] = $access_id;
    $request['_metadata']['ApiKey'] = $api_key;
    unset($authorization);
    unset($access_id, $api_key);
}
else {
    $request['_metadata']['ApiAccessId'] = -1;
    $request['_metadata']['ApiKey'] = "";
}

// ---------------------------------------------------------------- //
// function 실행
// ---------------------------------------------------------------- //
if (is_null($resource_function) || strlen($resource_function) == 0) {
    // 기능 이름이 지정되지 않았으면 기본 이름 사용
    $resource_function = "action";
}

if (function_exists($resource_function)) {
    // GET 이 아닐 경우, API 이용로그를 남긴다.
    if (isset($_REQUEST['__method']))
        $method = $_REQUEST['__method'];
    else
        $method = $_SERVER['REQUEST_METHOD'];

    if (strcmp($method, "GET") != 0) {
        $msgjson = html_entity_decode(json_encode($request), ENT_COMPAT, "UTF-8");

        if ($stmt = @$DB_CONN->prepare("INSERT INTO APILOG (DATE, TIME, API_KEY, REQUEST_ID, METHOD, RESOURCE, MESSAGE) VALUES (CURDATE(), CURTIME(), ?, ?, ?, ?, ?)")) {
            @$stmt->bind_param("sssss", $request['_metadata']['ApiKey'], $REQUEST_ID, $method,
                $request['_metadata']['ResourceKey'], $msgjson);
            @$stmt->execute();
            //echo $stmt->affected_rows;
            @$stmt->close();
        }
        unset($msgjson, $stmt);
    }
    unset($method);

    // 실행하는 부분
    $result = $resource_function($request);

    if (is_array($result)) {
        $result['_metadata'] = array(
            'statusCode' => 200,
            'ServerName' => $_SERVER['SERVER_NAME'],
            //'ServerName' => $_SERVER['HTTP_HOST'],
            'ClientAddr' => $_SERVER['REMOTE_ADDR'],
            'RequestMethod' => $_SERVER["REQUEST_METHOD"],
            'RequestPath' => $_SERVER['REQUEST_URI'],     // query string 노출
            //'RequestPath' => $_SERVER['REDIRECT_URL'],  // query string 숨김
            'RequestId' => $REQUEST_ID,
        );

        $json_result = html_entity_decode(json_encode($result), ENT_COMPAT, "UTF-8");
    }
    else {
        // json 컨버전 불가능한 경우는 오류...
        $result2 = array(
            "result" => $result,
            '_metadata' => array(
                'statusCode' => 200,
                'ServerName' => $_SERVER['SERVER_NAME'],
                //'ServerName' => $_SERVER['HTTP_HOST'],
                'ClientAddr' => $_SERVER['REMOTE_ADDR'],
                'RequestMethod' => $_SERVER["REQUEST_METHOD"],
                'RequestPath' => $_SERVER['REQUEST_URI'],     // query string 노출
                //'RequestPath' => $_SERVER['REDIRECT_URL'],  // query string 숨김
                'RequestId' => $REQUEST_ID,
            )
        );

        $json_result = html_entity_decode(json_encode($result2), ENT_COMPAT, "UTF-8");
    }
}
else
    die2(500, "Can not execute function.", $resource_function);

// ---------------------------------------------------------------- //
// 응답 전송
// ---------------------------------------------------------------- //
if (strlen($aes_key) > 0 && !headers_sent()) {
    set_header('json');
    header('X-Encrypted: enabled');

    // data를 aes encrypt 한다
    $iv = '                ';
    $data = openssl_encrypt($json_result, 'aes-256-cbc', $aes_key, 0, $iv);

    echo $data;
}
else {
    set_header('json');
    echo $json_result;
}

// ---------------------------------------------------------------- //
disconnect_database();
// ---------------------------------------------------------------- //
