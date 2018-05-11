<?php
/**
 * File: Arr.class.php
 * User: xieguoqiu
 * Date: 2016/5/16 10:32
 */

namespace Library\Common;

class Arr
{

    public static function only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function except($array, $keys)
    {
        static::forget($array, $keys);

        return $array;
    }

    public static function forget(&$array, $keys)
    {
        $original = &$array;

        $keys = (array) $keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            $parts = explode('.', $key);

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    $parts = [];
                }
            }

            unset($array[array_shift($parts)]);

            // clean up after each pass
            $array = &$original;
        }
    }

    public static function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, static::dot($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    public static function sortByField(&$data, $field, $order = 0)
    {
        //使用闭包设置需要比较的字段
        function cmpArray($field, $order) {
            return $order ? function($a, $b) use($field, $order) {  //设置闭包中需要使用到的变量
                return ($a[$field] == $b[$field]) ? 0 : (($a[$field] > $b[$field]) ? -1 : 1);
            } : function($a, $b) use($field, $order) {
                return ($a[$field] == $b[$field]) ? 0 : (($a[$field] < $b[$field]) ? -1 : 1);
            };
        }
        function cmpObject($field, $order) {
            return $order ? function($a, $b) use($field, $order) {  //设置闭包中需要使用到的变量
                return ($a->$field == $b->$field) ? 0 : (($a->$field > $b->$field) ? -1 : 1);
            } : function($a, $b) use($field, $order) {
                return ($a->$field == $b->$field) ? 0 : (($a->$field < $b->$field) ? -1 : 1);
            };
        }
        usort($data, is_array(current($data))?cmpArray($field, $order):cmpObject($field, $order));
    }

}
 