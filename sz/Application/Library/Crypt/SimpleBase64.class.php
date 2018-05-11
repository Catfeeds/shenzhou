<?php

namespace Library\Crypt;

class SimpleBase64
{

    /**
     * 加密
     * @param string $data 字符串
     * @param string $key 加密key
     * @param integer $expire 有效期（秒）
     * @return string
     */
    public static function encrypt($data, $key, $expire = 0)
    {
        $expire = sprintf('%010d', $expire ? $expire + $_SERVER['REQUEST_TIME'] : 0);
        $key = md5($key);
        $data = base64_encode($expire . $data);
        $x = 0;
        $data_len = strlen($data);
        $key_len = strlen($key);
        $str = '';
        for ($i = 0; $i < $data_len; ++$i) {
            $x == $key_len && $x = 0;
            /**
             * 1.依次取出$data每个字符对应的ASC码码值，
             * 2.依次获取$key每个字符对应的ASC码码值，
             * 3.将1和2中获得的ASC码码值相加（如果$key的长度不足则再从第一个字符开始取，直到$data结束）的值，
             * 4.将3中结果的码值转换为对应的ASC字符（可见或不可见字符）
             */
            $str .= chr(ord($data{$i}) + ord($key{$x}));
            ++$x;
        }
        // 将一些在URL上会导致编码的字符替换
        return strtr(base64_encode($str), array('+' => '-', '/' => '_', '=' => ''));
    }

    /**
     * 解密
     * @param string $data 加密的密文
     * @param string $key 加密key
     * @return bool|string
     */
    public static function decrypt($data, $key)
    {
        $key = md5($key);
        $data = base64_decode(strtr($data, array('-' => '+', '_' => '/')));
        $data_len = strlen($data);
        $key_len = strlen($key);
        $str = '';
        for ($i = 0, $x = 0; $i < $data_len; ++$i) {
            $x == $key_len && $x = 0;
            $c = ord($data{$i});
            $k = ord($key{$x});
            $str .= chr($c - $k);
            ++$x;
        }
        $str = base64_decode($str);
        $expire = substr($str, 0, 10);
        if ($expire > 0 && $expire < $_SERVER['REQUEST_TIME']) {
            return false;
        }
        return substr($str, 10);
    }

//    /**
//     * 加密字符串
//     * @param string $str 字符串
//     * @param string $key 加密key
//     * @param integer $expire 有效期（秒）
//     * @return string
//     */
//    public static function encrypt($data,$key,$expire=0) {
//        $expire = sprintf('%010d', $expire ? $expire + time():0);
//        $key    =   md5($key);
//        $data   =   base64_encode($expire.$data);
//        $x=0;
//        $len = strlen($data);
//        $l = strlen($key);
//        for ($i=0;$i< $len;$i++) {
//            if ($x== $l) $x=0;
//            $char   .=substr($key,$x,1);
//            $x++;
//        }
//
//        for ($i=0;$i< $len;$i++) {
//            $str    .=chr(ord(substr($data,$i,1))+(ord(substr($char,$i,1)))%256);
//        }
//        return $str;
//    }


//    /**
//     * 解密字符串
//     * @param string $str 字符串
//     * @param string $key 加密key
//     * @return string
//     */
//    public static function decrypt($data,$key) {
//        $key    =   md5($key);
//        $x=0;
//        $len = strlen($data);
//        $l = strlen($key);
//        for ($i=0;$i< $len;$i++) {
//            if ($x== $l) $x=0;
//            $char   .=substr($key,$x,1);
//            $x++;
//        }
//        for ($i=0;$i< $len;$i++) {
//            if (ord(substr($data,$i,1))<ord(substr($char,$i,1))) {
//                $str    .=chr((ord(substr($data,$i,1))+256)-ord(substr($char,$i,1)));
//            }else{
//                $str    .=chr(ord(substr($data,$i,1))-ord(substr($char,$i,1)));
//            }
//        }
//        $data = base64_decode($str);
//        $expire = substr($data,0,10);
//        if($expire > 0 && $expire < time()) {
//            return '';
//        }
//        $data   = substr($data,10);
//        return $data;
//    }

}