<?php
$API_LIST = array(
    // RSA publickey
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'GET',
        'resource' => 'publickey',
        'authorize' => 'N',
        'module' => '',
        'function' => 'api_get_rsa_public_key',
    ),
    // 클라이언트 버전 체크
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'GET',
        'resource' => 'clientversion',
        'authorize' => 'N',
        'module' => '',
        'function' => 'api_get_client_version',
    ),
    // SAM파일 구조 정보 ----------------------------------------------
    // SAM파일 목록 및 시간정보, 파일구조
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'GET',
        'resource' => 'master',
        'authorize' => 'Y',
        'module' => 'master/master.api.php',
        'function' => 'api_get_sammast',
    ),
    // SAM파일 데이터 조회
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'GET',
        'resource' => 'data',
        'authorize' => 'Y',
        'module' => 'trans/trans.api.php',
        'function' => 'api_get_samdata',
    ),
    // SAM파일 업로드
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'POST',
        'resource' => 'data',
        'authorize' => 'Y',
        'module' => 'trans/trans.api.php',
        'function' => 'api_create_samdata',
    ),
    // SAM파일 삭제
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'DELETE',
        'resource' => 'data',
        'authorize' => 'Y',
        'module' => 'trans/trans.api.php',
        'function' => 'api_delete_samdata',
    ),

    // 레포트 구성 정보 ----------------------------------------------
    // 집계표 리포트 구성
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'GET',
        'resource' => 'report/listview',
        'authorize' => 'Y',
        'module' => 'report/listview.api.php',
        'function' => 'api_get_listview',
    ),

    // 종목변동현황표 리포트 구성
    array(
        'type' => 'api',
        'version' => '0.9',
        'method' => 'GET',
        'resource' => 'report/diffview',
        'authorize' => 'Y',
        'module' => 'report/diffview.api.php',
        'function' => 'api_get_diffview',
    ),

);
