<?php
namespace Library\Crypt;

class Des {
    /**
     * DES加密
     * @param string $input json
     * @param string $key 密钥
     * @return string
     */
    public static function encrypt($input, $key, $cipher = MCRYPT_RIJNDAEL_128) {
        $size = mcrypt_get_block_size($cipher, MCRYPT_MODE_ECB);
        $input = self::pkcs5_pad($input, $size);
        $td = mcrypt_module_open($cipher, '', MCRYPT_MODE_ECB, '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);
        return $data;
    }

    /**
     *
     * @param $text
     * @param $blocksize
     * @return string
     */
    private static function pkcs5_pad ($text, $blocksize) {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * DES解密
     * @param string $sStr 待解密的串
     * @param string $sKey 密钥
     * @return bool|string
     */
    public static function decrypt($sStr, $sKey, $cipher = MCRYPT_RIJNDAEL_128) {
        $decrypted= mcrypt_decrypt(
        $cipher,
        $sKey,
        base64_decode($sStr),
        MCRYPT_MODE_ECB
    );

        $dec_s = strlen($decrypted);
        $padding = ord($decrypted[$dec_s-1]);
        $decrypted = substr($decrypted, 0, -$padding);
        return $decrypted;
    }

    /**
     * 生成随机的密钥
     */
    public static function getDesKey($nums = 0)
    {
        $str="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $strlen = strlen($str)-1;
        !$nums && $nums = 32;
        $key = "";
        for($i=0;$i<$nums;$i++){
            $key .= $str{mt_rand(0, $strlen)};
        }
        return $key;
    }

}