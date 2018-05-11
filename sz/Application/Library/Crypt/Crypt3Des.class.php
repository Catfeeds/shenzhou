<?php

namespace Library\Crypt;

class Crypt3Des
{

    protected static $mcrypt3DesModeList = array(
        MCRYPT_MODE_CBC,
        MCRYPT_MODE_CFB,
        MCRYPT_MODE_ECB,
        MCRYPT_MODE_NOFB,
        MCRYPT_MODE_OFB,
//        MCRYPT_MODE_STREAM
    );

    protected static $mcrypt3DesMode = MCRYPT_MODE_CBC;

    public static function encrypt($input, $key, $iv)
    {
        if (!isset($iv{7})) {
            throw new \Exception('初始向量最少为8位字符');
        }
        $size = mcrypt_get_block_size(MCRYPT_3DES, self::getMcrypt3DesMode());
        $input = self::pkcs5Pad($input, $size);
        // 打开算法和模式对应的模块
        $td = mcrypt_module_open(MCRYPT_3DES, '', self::getMcrypt3DesMode(), '');
        // 确保密钥长度大于模式所能支持的最长密钥（小于最大长度的数值都被视为非法参数）
        $key = str_pad($key, mcrypt_enc_get_key_size($td), '0');
        // 初始化加密所需的缓冲区
        mcrypt_generic_init($td, $key, $iv);
        // 加密数据
        $data = mcrypt_generic($td, $input);
        // 终止由加密描述符（td）指定的加密模块，清理缓冲区
        mcrypt_generic_deinit($td);
        // 关闭加密模块（释放资源）
        mcrypt_module_close($td);
        $data = base64_encode($data);
        return $data;
    }

    public static function decrypt($encrypted, $key, $iv)
    {
        if (!isset($iv{7})) {
            throw new \Exception('初始向量最少为8位字符');
        }
        $encrypted = base64_decode($encrypted);
        $td = mcrypt_module_open(MCRYPT_3DES, '', self::getMcrypt3DesMode(), '');
        $key = str_pad($key, mcrypt_enc_get_key_size($td), '0');
        mcrypt_generic_init($td, $key, $iv);
        $decrypted = mdecrypt_generic($td, $encrypted);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $y = self::pkcs5Unpad($decrypted);
        return $y;
    }

    public static function setMcrypt3DesMode($mcrypt_3des_mode)
    {
        if (in_array($mcrypt_3des_mode, self::$mcrypt3DesModeList)) {
            self::$mcrypt3DesMode = $mcrypt_3des_mode;
        } else {
            throw new \Exception('无效的加密模式');
        }
    }

    public static function getMcrypt3DesMode()
    {
        return self::$mcrypt3DesMode;
    }

//    function encrypt($input) {
//        $size = mcrypt_get_block_size(MCRYPT_3DES,MCRYPT_MODE_CBC);
//        $input = $this->pkcs5_pad($input, $size);
//        $key = str_pad($this->key,24,'0');
//        $td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_CBC, '');
//        if($this->iv == '') {
//            $iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
//        } else {
//            $iv = $this->iv;
//        }
//        @mcrypt_generic_init($td, $key, $iv);
//        $data = mcrypt_generic($td, $input);
//        mcrypt_generic_deinit($td);
//        mcrypt_module_close($td);
//        $data = base64_encode($data);
//        return $data;
//    }

//    function decrypt($encrypted){
//        $encrypted = base64_decode($encrypted);
//        $key = str_pad($this->key,24,'0');
//        $td = mcrypt_module_open(MCRYPT_3DES,'',MCRYPT_MODE_CBC,'');
//        if( $this->iv == '' ) {
//            $iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
//        } else {
//            $iv = $this->iv;
//        }
//        $ks = mcrypt_enc_get_key_size($td);
//        @mcrypt_generic_init($td, $key, $iv);
//        $decrypted = mdecrypt_generic($td, $encrypted);
//        mcrypt_generic_deinit($td);
//        mcrypt_module_close($td);
//        $y=$this->pkcs5_unpad($decrypted);
//        return $y;
//    }

    protected function pkcs5Pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    protected function pkcs5Unpad($text)
    {
        $pad = ord($text{strlen($text) - 1});
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }

    protected function PaddingPKCS7($data)
    {
        $block_size = mcrypt_get_block_size(MCRYPT_3DES, MCRYPT_MODE_CBC);
        $padding_char = $block_size - (strlen($data) % $block_size);
        $data .= str_repeat(chr($padding_char), $padding_char);
        return $data;
    }
}
