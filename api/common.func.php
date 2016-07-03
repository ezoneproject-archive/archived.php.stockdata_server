<?php
date_default_timezone_set('Asia/Seoul');

// ------------------------------------------------------------------

// RequestID 채번
$REQUEST_ID = uniqid($API_TYPE, true);

// ---------------------------------------------------------------- //
// 요청 주소에서 API 버전 확인
// ---------------------------------------------------------------- //
function get_api_version_from_uri() {
    global $API_TYPE;

    $urlBase = $_SERVER['REDIRECT_URL'];

    // 중복된 // 제거
    while (strpos($urlBase, "//") !== FALSE) {
        $urlBase = str_replace("//", "/", $urlBase);
    }

    // 경로 뒤의 '/' 제거
    while (strcmp(substr($urlBase, -1), "/") == 0) {
        $urlBase = substr($urlBase, 0, strlen($urlBase) - 1);
    }

    $uriPath = explode('/', $urlBase);

    // [0] = '', [1] = API_TYPE('api'), [2] = API_VERSION, [3] = function name
    if (count($uriPath) < 4) {
        die2(400, 'Resource access uri format error.');
    }

    // API TYPE CHECK
    if (strcmp($API_TYPE, $uriPath[1]) != 0) {
        die2(403, 'Resource access mode error.');
    }

    if ($uriPath[2] != "" && is_dir($uriPath[2])) {
        return $uriPath[2];
    }
    else {
        die2(404, 'Not suppored API version.');
    }

    // never running
    return $uriPath[2];
}

// ---------------------------------------------------------------- //
// REST resource function
// ---------------------------------------------------------------- //
function get_resource_name_from_uri() {
    $urlBase = $_SERVER['REDIRECT_URL'];

    // 중복된 // 제거
    while (strpos($urlBase, "//") !== FALSE) {
        $urlBase = str_replace("//", "/", $urlBase);
    }

    // 경로 뒤의 '/' 제거
    while (strcmp(substr($urlBase, -1), "/") == 0) {
        $urlBase = substr($urlBase, 0, strlen($urlBase) - 1);
    }

    // [0] => '', [1] => API_TYPE('api'), [2] = API_VERSION [3] => aaa/bbb
    $uriPath = explode('/', $urlBase, 4);

    // 'api' / 'version' 뒤의 정보가 resource 시작
    if (count($uriPath) < 4 || strlen(@$uriPath[3]) == 0) {
        die2(404, 'Resource uri is null.');
    }


    return $uriPath[3];
}

// ---------------------------------------------------------------- //
// 입력자료 정규화 핸들러
// ---------------------------------------------------------------- //
function post_data_handler(&$request, $post_data) {
    // json decode
    if (strncasecmp(@$_SERVER['CONTENT_TYPE'], 'application/json', 16) == 0) {
        $request['post_data'] = json_decode($post_data, true);
        switch(json_last_error()) {
        case JSON_ERROR_DEPTH:
            die2(400, 'json_decode: Maximum stack depth exceeded');
            break;
        case JSON_ERROR_CTRL_CHAR:
            die2(400, 'json_decode: Unexpected control character found');
            break;
        case JSON_ERROR_SYNTAX:
            die2(400, 'json_decode: Syntax error, malformed JSON');
            break;
        case JSON_ERROR_NONE:
            break;
        }
        //print_r($request['post_data']);
    }
    // xml
    else if (strncasecmp(@$_SERVER['CONTENT_TYPE'], 'application/xml', 15) == 0) {
        // not supported
        die2(400, 'not supported content-type: application/xml');
    }
    /*
    // upload
    else if (strncasecmp(@$_SERVER['CONTENT_TYPE'], 'multipart/form-data', 19) == 0) {
        // do nothing
    }
    // application/x-www-form-urlencoded => 이미 $_REQUEST 로 포함
    else if (strncasecmp(@$_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded', 33) == 0) {
        // do nothing
    }
    */
}


// ---------------------------------------------------------------- //
// MySQL DB 접속 핸들러
// ---------------------------------------------------------------- //
function connect_database() {
    global $DB_CONN_STRING;

    $mysqli = @new mysqli($DB_CONN_STRING['url'],
        $DB_CONN_STRING['username'], $DB_CONN_STRING['password'],
        $DB_CONN_STRING['database']);

    if ($mysqli->connect_errno) {
        //$mysqli->connect_error
        die2(503, 'Database Unavailable - Check database is online or connection string.');
    }

    if (!$mysqli->set_charset("utf8")) {
        //$mysqli->character_set_name()
        $mysqli->close();
        die2(503, 'Database can not support UTF8.');
    }

    $DB_CONN_STRING['connected'] = 1;
    return $mysqli;
}

function disconnect_database() {
    global $DB_CONN_STRING, $DB_CONN;

    if (isset($DB_CONN_STRING['connected'])) {
        $DB_CONN->close();
    }
}

// $DBCONN->prepare() 에서 쿼리를 가변으로 생성할 때 사용
// ex) $params = array("iiss", 1, 2, "testarg", "testarg2");
//     call_user_func_array(array($stmt, "bind_param"), refValues($params));
function refValues($arr){
        if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
        {
            $refs = array();
            foreach($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }

/* bind에서도 사용 가능함
$meta = $stmt->result_metadata();

while ( $field = $meta->fetch_field() ) {
   $parameters[] = &$row[$field->name];
}  

call_user_func_array(array($stmt, 'bind_result'), refValues($parameters));

while ( $stmt->fetch() ) {  
    $x = array();  
    foreach( $row as $key => $val ) {  
        $x[$key] = $val;  
    }  
    $results[] = $x;  
}

//$result = $results;
$stmt->close();
*/

// ---------------------------------------------------------------- //
// 공통 에러 핸들러
// ---------------------------------------------------------------- //
function die2($statusCode, $message, $optional_variable = '') {
    global $REQUEST_ID, $DB_CONN_STRING, $DB_CONN, $request;

    if (!isset($request['_metadata']['ApiKey']))
        $request['_metadata']['ApiKey'] = "";

    $debugTrace1 = debug_backtrace();
    // 불필요하게 많은 trace는 배제
    if (count($debugTrace1) > 5)
        array_splice($debugTrace1, 5);

/* 이거 딱히 추가하지 않아도 die2 트레이스의 args에서 확인 가능함
    if (!is_null($optional_variable) && strlen($optional_variable) > 0) {
        $debugTrace1[] = array(
            'file' => 'ETC',
            'line' => '',
            'function' => '',
            'args' => array($optional_variable)
        );
    }
*/

    $debugTrace = html_entity_decode(json_encode($debugTrace1));
    if (strlen($debugTrace) > 65000) {
        $debugTrace = substr($debugTrace, 0, 65000);
    }

    //print_r($debugTrace1);

    http_response_code($statusCode);
    set_header('json');
    @header("Cache-Control: no-cache, must-revalidate");

    // 4xx : Client error
    // 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found
    if ($statusCode >= 400 && $statusCode < 500)
        $errorType = 'Sender';

    // 5xx : Server error
    // 500 Internal Server Error, 503 Service Unavailable, 504 Gateway Timeout
    else if ($statusCode >= 500 && $statusCode < 600)
        $errorType = 'Service';

    if ($statusCode == 401) {
        header('WWW-Authenticate: '.get_authentication_algorithm().' realm="API"');
    }

    $msgvar = array(
        '_metadata' => array(
            'statusCode' => $statusCode,
            'ServerName' => $_SERVER['SERVER_NAME'],
            //'ServerName' => $_SERVER['HTTP_HOST'],
            'ClientAddr' => $_SERVER['REMOTE_ADDR'],
            'RequestMethod' => $_SERVER["REQUEST_METHOD"],
            'RequestPath' => $_SERVER['REQUEST_URI'],     // query string 노출
            //'RequestPath' => $_SERVER['REDIRECT_URL'],  // query string 숨김
            'RequestId' => $REQUEST_ID,
        ),
        '_errorInfo' => array(
            'Type' => $errorType,
            //'Code' => $debugTrace1[1]['function'],
            'Message' => $message,
        ),
    );

    $msgjson = html_entity_decode(json_encode($msgvar));

    if (isset($DB_CONN_STRING['connected'])) {
        // DB에 접속되어 있으면 오류내역을 로그로 기록해둔다.
        if ($stmt = @$DB_CONN->prepare("INSERT INTO ERRORLOG (DATE, TIME, API_KEY, REQUEST_ID, MESSAGE, TRACE) VALUES (CURDATE(), CURTIME(), ?, ?, ?, ?)")) {
            @$stmt->bind_param("ssss", $request['_metadata']['ApiKey'], $REQUEST_ID, $msgjson, $debugTrace);
            @$stmt->execute();
            //echo $stmt->affected_rows;
            @$stmt->close();
        }

        disconnect_database();
    }

    echo $msgjson;
    exit;
}

// ---------------------------------------------------------------- //
// Content-type 헤더 전송 핸들러
// ---------------------------------------------------------------- //
function set_header($contentType = 'json', $charset = 'UTF-8') {
    if (!headers_sent()) {

        if (is_null($contentType) || strlen($contentType) == 0)
            $contentType = 'text/html';
        else if (strcasecmp($contentType, 'json') == 0)
            $contentType = 'application/json';
        else if (strcasecmp($contentType, 'xml') == 0)
            $contentType = 'text/xml';

        @header('Content-type: '.$contentType.'; charset='.$charset);
    }
}


// ---------------------------------------------------------------- //
// PHP 5.4.0 이하에서 http_response_code() 구현
// ---------------------------------------------------------------- //
if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {
        if ($code !== NULL) {
            switch ($code) {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 200: $text = 'OK'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default:
                exit('Unknown http status code "' . htmlentities($code) . '"');
                break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            @header($protocol . ' ' . $code . ' ' . $text);
            $GLOBALS['http_response_code'] = $code;
        } else {
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }
        return $code;
    }
}

// DB 접속
$DB_CONN = connect_database();

// 사용할 API 버전 설정
$API_VERSION = get_api_version_from_uri();

/////////////
// INCLUDE //
/////////////
set_include_path(get_include_path() . PATH_SEPARATOR . $API_VERSION);
require_once 'rsa.func.php';
require_once 'apicheck.func.php';
require_once 'auth.func.php';
