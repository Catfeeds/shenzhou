<?php
/**
 * @author QiuQiu
 * 2015/5/15
 */

namespace Library\Crypt;

/**
 * Class Base64
 * @package Api\Common\Crypt
 * 根据Base64原理重写的Base64加密与解密
 *
 * 以下内容摘录自网络：
 * base64的编码都是按字符串长度，以每3个8bit的字符为一组，
 * 然后针对每组，首先获取每个字符的ASCII编码，
 * 然后将ASCII编码转换成8bit的二进制，得到一组3*8=24bit的字节
 * 然后再将这24bit划分为4个6bit的字节，并在每个6bit的字节前面都填两个高位0，得到4个8bit的字节
 * 然后将这4个8bit的字节转换成10进制，对照Base64编码字符表，得到对应编码后的字符。
 *
 * 注：1. 要求被编码字符是8bit的，所以须在ASCII编码范围内，\u0000-\u00ff，中文就不行。
 * 2. 如果被编码字符长度不是3的倍数的时候，则都用0代替，对应的输出字符为=
 */
class Base64 
{

    const BASE64HASH = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /**
     * Base64加密
     * @param string $data 需要的明文
     * @return string
     */
    public static function encrypt($data) 
    {
        $bin = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; ++$i) {
            $bin .= str_pad(decbin(ord($data{$i})), 8, '0', STR_PAD_LEFT);
        }
        $bin_len = strlen($bin);
        $str = '';
        $processed = 0;
        do {
            $sub = substr($bin, $processed, 24);
            $processed += 24;
            $c1 = self::conv(substr($sub, 0, 6));
            $c2 = self::conv(substr($sub, 6, 6));
            $c3 = self::conv(substr($sub, 12, 6));
            $c4 = self::conv(substr($sub, 18, 6));
            $str .= $c1 . $c2 . $c3 . $c4;
        } while($bin_len > $processed);
        return $str;
    }

    /**
     * Base64解密
     * @param string $data 密文
     * @return string
     */
    public static function decrypt($data) 
    {
        $data = str_replace('=', '', $data);
        $len = strlen($data);
        $bin = '';
        $str = '';
        for ($i = 0; $i < $len; ++$i) {
            $bin .= str_pad(decbin(strpos(self::BASE64HASH, $data{$i})), 6, '0', STR_PAD_LEFT);
            if (strlen($bin) >= 8) {
                $str .= chr(bindec(substr($bin, 0, 8)));
                $bin = substr($bin, 8);
            }
        }
        return $str;
    }

    /**
     * 将输入的二进制字符串返回对应的Base64字符
     * @param string $bin 二进制字符串
     * @return string
     */
    protected static function conv($bin) 
    {
        $len = strlen($bin);
        if ($len == 6) {
            return substr(self::BASE64HASH, bindec($bin), 1);
        } else if ($len > 0) {
            return substr(self::BASE64HASH, bindec(str_pad($bin, 6, '0', STR_PAD_RIGHT)), 1);
        } else {
            return '=';
        }
    }

}
