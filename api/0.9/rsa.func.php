<?php

// ---------------------------------------------------------------- //
// api: RSA 공개키를 응답한다.
// ---------------------------------------------------------------- //
function api_get_rsa_public_key($request) {
    global $RSA_KEY;

    if (strlen($request['_metadata']['ResourceInfo'] > 0))
        die2(400, "Bad Request(1)");

    if (isset($request['_request']) && count($request['_request']) > 0)
        die2(400, "Bad Request(2)");

    return array("publickey" => $RSA_KEY['public_key']);
}

/*
RSA Sample:
$encoded = rsa_encrypt("Test str", $RSA_KEY['public_key']);
echo "enc: $encoded <br/>\n";
$plain = rsa_decrypt($encoded, $RSA_KEY['private_key'], null);
echo "dec: ($plain) <br/>\n";
*/

// ---------------------------------------------------------------- //
// RSA 공개키/개인키를 생성한다.
function rsa_generate_keys($password, $bits = 2048, $digest_algorithm = 'sha256')
{
    $res = openssl_pkey_new(array(
        'digest_alg' => $digest_algorithm,
        'private_key_bits' => $bits,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ));

    openssl_pkey_export($res, $private_key, $password);

    $public_key = openssl_pkey_get_details($res);
    $public_key = $public_key['key'];

    return array(
        'private_key' => $private_key,
        'public_key' => $public_key,
    );
}

// RSA 공개키를 사용하여 문자열을 암호화한다.
// 암호화할 때는 비밀번호가 필요하지 않다.
// 오류가 발생할 경우 false를 반환한다.
function rsa_encrypt($plaintext, $public_key)
{
    // 용량 절감과 보안 향상을 위해 평문을 압축한다.
    //$plaintext = gzcompress($plaintext);

    // 공개키를 사용하여 암호화한다.
    $pubkey_decoded = @openssl_pkey_get_public($public_key);
    if ($pubkey_decoded === false) return false;

    $ciphertext = false;
    $status = @openssl_public_encrypt($plaintext, $ciphertext, $pubkey_decoded);
    if (!$status || $ciphertext === false) return false;

    // 암호문을 base64로 인코딩하여 반환한다.
    return base64_encode($ciphertext);
}

// RSA 개인키를 사용하여 문자열을 복호화한다.
// 복호화할 때는 비밀번호가 필요하다.
// 오류가 발생할 경우 false를 반환한다.
function rsa_decrypt($ciphertext, $private_key, $password)
{
    // 암호문을 base64로 디코딩한다.
    $ciphertext = @base64_decode($ciphertext, true);
    if ($ciphertext === false) return false;

    // 개인키를 사용하여 복호화한다.
    $privkey_decoded = @openssl_pkey_get_private($private_key, $password);
    if ($privkey_decoded === false) return false;

    $plaintext = false;
    $status = @openssl_private_decrypt($ciphertext, $plaintext, $privkey_decoded);
    @openssl_pkey_free($privkey_decoded);
    if (!$status || $plaintext === false) return false;

    // 압축을 해제하여 평문을 얻는다.
    //$plaintext = @gzuncompress($plaintext);
    //if ($plaintext === false) return false;

    // 이상이 없는 경우 평문을 반환한다.
    return $plaintext;
}
