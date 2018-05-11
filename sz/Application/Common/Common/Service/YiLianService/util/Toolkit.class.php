<?php
/**
 * @author fengmuhai
 * @date 2016-12-28
 */
namespace Common\Common\Service\YiLianService\util;

use Common\Common\Service\YiLianService\util\RSA;
use Common\Common\Service\YiLianService\util\TripleDES;

class Toolkit {
	public static function random($length = 0,$onlynum = false){
        $length = is_numeric($length) ? $length : mt_rand(12,48);
        if (!$onlynum) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		} else {
			$chars = '0123456789';
		}
		$str_length = strlen($chars);
		$salt = '';
		for ($i = 0; $i < $length; $i++) {
			$salt .= substr($chars, mt_rand(0, $str_length - 1), 1);
		}
		return $salt;
    }

    public static function signWithMD5($keyPara, $srcPara, $pubKey){
        if(!$keyPara){
            $keyPara = Toolkit::random(24);
        }
        //RSA Base64
        $keyEncrypt = RSA::encrypt($keyPara, $pubKey);
        //$keyEncrypt = base64_encode($keyEncrypt);

        //3DES
        $srcEncrypt = TripleDES::encrypt($srcPara, $keyPara);
        //$srcEncrypt = base64_encode($srcEncrypt);

        //MD5
        $srcSign = base64_encode(strtoupper(md5($srcPara)));

        $version = base64_encode('MD5');
        $sign  = $version.'|'.'';
        $sign .= '|'.$keyEncrypt;
        $sign .= '|'.$srcEncrypt;
        $sign .= '|'.$srcSign;
        return $sign;
    }
}
