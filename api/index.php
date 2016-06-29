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
// http일 경우, 데이터 암호화 옵션
// ---------------------------------------------------------------- //
// http 헤더에 RSA Public key 로 encrypt 한 Session key를 보낸다.
// X-Encrypt-Key AES/256/CBC,<encrypted>
if (isset($_SERVER['HTTP_X_ENCRYPT_KEY'])) {
    @list($enc_method, $rsa_enc_key) = explode(',', $_SERVER['HTTP_X_ENCRYPT_KEY'], 2);
    if (is_null($enc_method) || strlen($enc_method) == 0 ||
        is_null($rsa_enc_key) || strlen($rsa_enc_key) == 0)
        die2(400, "Invalid X-Encrypt-Key header.");

    if (strcmp($enc_method, "AES/256/CBC") != 0)
        die2(400, "Invalid X-Encrypt-Key encrypt method.", $enc_method);

    $aes_key = rsa_decrypt($encoded, $RSA_KEY['private_key'], null);

    // data를 aes decrypt 한다
    $iv = '                ';
    //$data = openssl_decrypt($data, 'aes-256-cbc', $aes_key, 0, $iv);

    //echo "Encrypt enable ...<br/>";

    // $_REQUEST[] 변수와 정규화 데이터 합한다.

}
else {
    //
    // 변수 정규화
    //

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

    //$_REQUEST[] 변수 뒤져서 정규화 한다
    // GET/POST 는 여기에 포함됨
    foreach ($_REQUEST as $rkey => $rvalue) {
        // RESTful 하지는 못하지만 호스팅 환경에서 PUT, DELETE 안 될 때를 대비하여
        // is_api_available() 에서 method 를 예약어로 사용하므로 제외처리
        if (strcmp($rkey, "method") == 0)
            continue;

        $request['_request'][$rkey] = $rvalue;
    }
    unset($rkey, $rvalue);

//$putdata = fopen("php://input", "r");
//while ($data = fread($putdata, 1024))
/* 스트림 닫기 */
//fclose($putdata);

    // POST 모드일 때 Content-type 헤더 체크
    if (isset($_SERVER['CONTENT_TYPE'])) {
        // json decode
        if (strcasecmp($_SERVER['CONTENT_TYPE'], 'application/json')) {
        }
        // xml
        else if (strncasecmp($_SERVER['CONTENT_TYPE'], 'application/xml', 15)) {
            // none
        }
        // upload
        else if (strncasecmp($_SERVER['CONTENT_TYPE'], 'multipart/form-data', 19)) {
            // none
        }
        // application/x-www-form-urlencoded => 이미 $_REQUEST 로 포함
        else if (strcasecmp($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded')) {
            // none
        }
    }

// POST method 일 때 content-type 확인... json 인지 단순 form인지 체크하기 위함
// $_SERVER["CONTENT_TYPE"]              application/x-www-form-urlencoded
}

/*
// 한글은 UTF-8 사용
$sig = hash_hmac('sha256', "한1234567890", "1");
echo "signature: $sig\n";

                //ae453b9d38f547d6410f9ab1bfca5639e91df7d483c4abe8a367b120e62a9e21
                //ae453b9d38f547d6410f9ab1bfca5639e91df7d483c4abe8a367b120e62a9e21
                //D0D5864932853745D4BBD7B57FE20E567DA4592E0E1D02C15CA190AFA0CDF2BE
*/

/* in CSharp
            this.rijndael = new RijndaelManaged();
            this.rijndael.Mode = CipherMode.CBC;
            this.rijndael.Padding = PaddingMode.PKCS7;
            this.rijndael.KeySize = 256;
            this.rijndael.BlockSize = 128;

$data = "12345123451234512355";

echo "Enc[$encryption_key]\n";

$data = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
//echo $data . "\n";

// --------------------------------
$encryption_key = 'abcaadefabcaadefabcaadefabcaadef';
$iv = '                ';
$encoded = rsa_encrypt($encryption_key, $RSA_KEY['public_key']);
echo "encoded [[$encoded]]<br/>\n";

$plain = rsa_decrypt($encoded, $RSA_KEY['private_key'], null);
echo "dec: ($plain) <br/>\n";
$plain2 = @base64_decode($plain, true);
echo "dec: ($plain2) <br/>\n";
*/




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
    $access_id = validate_authentication($authorization, $resource_name);
    // api 키 set
    $request['_metadata']['ApiAccessId'] = $access_id;
    unset($authorization);
    unset($access_id);
}
else {
    $request['_metadata']['ApiAccessId'] = -1;
}

// ---------------------------------------------------------------- //
// 요청자료 정규화
// ---------------------------------------------------------------- //

// json 사용법
// https://opentutorials.org/course/1375/6844
// $data = json_decode(file_get_contents('php://input'), true);
/*
 switch(json_last_error())
    {
        case JSON_ERROR_DEPTH:
            echo ' - Maximum stack depth exceeded';
        break;
        case JSON_ERROR_CTRL_CHAR:
            echo ' - Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
            echo ' - Syntax error, malformed JSON';
        break;
        case JSON_ERROR_NONE:
            echo ' - No errors';
        break;
    }
    주의1.
    json_decode 후에는 반드시 json_last_error 를 호출해야 함
    주의2.
    json_decode 는 php 버전따라서 특성을 탐
*/

// ---------------------------------------------------------------- //
// function 실행
// ---------------------------------------------------------------- //
if (is_null($resource_function) || strlen($resource_function) == 0) {
    // 기능 이름이 지정되지 않았으면 기본 이름 사용
    $resource_function = "action";
}

if (function_exists($resource_function)) {
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

        $json_result = html_entity_decode(json_encode($result));
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

        $json_result = html_entity_decode(json_encode($result2));
    }
}
else
    die2(500, "Can not execute function.", $resource_function);

// ---------------------------------------------------------------- //
// 응답 전송
// ---------------------------------------------------------------- //
if (isset($_SERVER['HTTP_X_ENCRYPT_KEY'])) {
    set_header('text/plain');

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
