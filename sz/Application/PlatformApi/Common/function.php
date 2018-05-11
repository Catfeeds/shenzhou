<?php
// 表ID加密
function tableIdEncrypt($table, $id, $min_leng = 11)
{
    if (!$id) {
        return $id;
    }
    $hash = new \Library\Crypt\Hashids($table, $min_leng);
    return $hash->encode($id);
}

//  表ID解密
function tableIdDecrypt($table, $string, $min_leng = 11)
{
    if (!$string) {
        return $string;
    }
    $hash = new \Library\Crypt\Hashids($table, $min_leng);
    return reset($hash->decode($string));
}