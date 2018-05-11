<?php
/**
 * @author fengmuhai
 * @date 2016-12-28
 */
namespace Common\Common\Service\YiLianService\util;

class TripleDES {
    public $iv = '0102030405060708';

    public static function encrypt($data, $privateKey) {
        $des = new TripleDES();
        $encData = $des->enc($data, $privateKey);
        return $encData;
    }
    public function decrypt($encData, $privateKey) {
        $des = new TripleDES();
        $decData = $des->enc($encData, $privateKey);
        return $decData;
    }

    function pkcs5_pad($text, $blocksize) {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }
    function pkcs5_unpad($text) {
        $pad = ord($text{strlen($text)-1});
        if ($pad > strlen($text)) {
        return false;
        }
        if( strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }

    //加密
    public function enc($data, $privateKey) {
        if($data) {
            $iv = pack("H16", $this->iv);
            $td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_ECB, '');
            mcrypt_generic_init($td, $privateKey, $iv);
            $encData = base64_encode(mcrypt_generic($td,$this->pkcs5_pad($data,8)));
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
            return $encData;
        }
        return '';
    }

    //解密
    public function dec($encData, $privateKey) {
        if($encData) {
            $iv = pack("H16", $this->iv);
            $td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_ECB, '');
            mcrypt_generic_init($td, $privateKey, $iv);
            $decData  = pkcs5_unpad(mdecrypt_generic($td, base64_decode($encData)));
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
            return $decData;
        }
        return '';
    }
}
?>