<?php
/**
 * File: Str.class.php
 * User: xieguoqiu
 * Date: 2016/5/20 17:20
 */

namespace Library\Common;

class Str
{

    public static function limit($value, $limit = 100, $end = '...')
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit, 'UTF-8') . $end;
    }

    public static function words($value, $words = 100, $end = '...')
    {
        preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $value, $matches);

        if (! isset($matches[0]) || strlen($value) === strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * 获取该字符串中所有的中文，但是不能捕获中文符号如，！等，
     * 如果不是连续中文，则会以数组形式返回
     * @param $str
     * @return mixed
     */
    public static function getChinese($str)
    {
        preg_match_all("/[\\x{4e00}-\\x{9fa5}]+/u", $str, $matches);
        return $matches;
    }

    /**
     * 将字符串分割为数组，支持中文等特殊字符
     * @param $string
     * @param int $splitLength
     * @return array
     */
    public static function split($string, $splitLength = 1)
    {
        if ($splitLength == 1) {
            return preg_split("//u", $string, -1, PREG_SPLIT_NO_EMPTY);
        }

        $return_value = [];
        $stringLength = mb_strlen($string, "UTF-8");
        for ($i = 0; $i < $stringLength; $i += $splitLength) {
            $return_value[] = mb_substr($string, $i, $splitLength, "UTF-8");
        }
        return $return_value;
    }


}
 