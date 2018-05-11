<?php
/**
 * File: Base62Convert.class.php
 * User: xieguoqiu
 * Date: 2016/5/5 16:25
 */

namespace Library\Crypt;

class Base62Convert
{
    const BASE62CODE = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';


    public static function encode($val)
    {
        $len = strlen(self::BASE62CODE);
        $base_code = self::BASE62CODE;
        $str = '';
        while($val>0)
        {
            $str = $base_code{($val%$len)} . $str;
            $val = floor($val/$len);
        }
        return $str;
    }

    public static function decode($encode_str)
    {
        $length = strlen($encode_str);
        $str_arr = explode($encode_str,'');
        $hash_length = strlen(self::BASE62CODE);
        $val = 0;
        foreach ($str_arr as $key => $c) {
            $val += strpos(self::BASE62CODE, $c) * pow($hash_length, $length - $key - 1);
        }
        return $val;
    }

}
 