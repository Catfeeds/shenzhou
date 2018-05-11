<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/7
 * Time: 15:10
 */

namespace Common\Common\Logic;

use Library\Crypt\Des;
use Library\Crypt\Rsa;

class CryptLogic extends BaseLogic
{
    public function xinyingyanDecrypt($sale_order_data, $des_key)
    {
        $private_key_content = file_get_contents(C('PEM_URL.XINYINGYAN_RSA_PRIVATE_KEY_PEM'));
        $decrypt_des_key = Rsa::privDecrypt($des_key, $private_key_content);
        return json_decode(Des::decrypt($sale_order_data, $decrypt_des_key), true);
    }

    public function xinyingyanEncrypt($data)
    {
        $string = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        $key = Des::getDesKey();
        $des_string = Des::encrypt($string, $key);
        $private_key_content = file_get_contents(C('PEM_URL.XINYINGYAN_RSA_PRIVATE_KEY_PEM'));
        $des_key = Rsa::privEncrypt($key, $private_key_content);
        return [
            'des_key'   => $des_key,
            'data'      => $des_string,
        ];
    }
}
