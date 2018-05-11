<?php
/**
 * @User fzy
 */
namespace Admin\Logic;

use Admin\Logic\BaseLogic;
use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Common\BaiDuLbsApi;
use Library\Crypt\AuthCode;
use Library\Common\Util;

class PublicsLogic extends BaseLogic
{
    //批量修改 data二维数组 field关键字段 参考ci 批量修改函数 传参方式
    public function updateAll($table_name = '', $data = [], $field = '')
    {
        if (!$table_name || !$data || !$field) {
            return false;
        } else {
            $sql = 'UPDATE ' .$table_name;
        }
        $con = $con_sql = $fields = [];
        foreach ($data as $key => $value) {
            $x=0;
            foreach ($value as $k => $v) {
                if ($k != $field&& !$con[$x] && $x==0) {
                    $con[$x]=" set {$k} = (CASE {$field} ";
                } elseif ($k != $field && !$con[$x] && $x > 0) {
                    $con[$x]=" {$k} = (CASE {$field} ";
                }
                if ($k != $field) {
                    $temp = $value[$field];
                    $con_sql[$x] .=  " WHEN '{$temp}' THEN '{$v}' ";
                    $x++;
                }
            }
            $temp = $value[$field];
            if (!in_array($temp,$fields)) {
                $fields[] = $temp;
            }
        }
        $num = count($con) - 1;
        foreach ($con as $key => $value) {
            foreach ($con_sql as $k => $v) {
                if ($k == $key && $key < $num) {
                    $sql.=$value.$v.' end),';
                } elseif ($k == $key && $key == $num) {
                    $sql.= $value.$v.' end)';
                }
            }
        }
        $str = implode(',',$fields);
        $sql.= " where {$field} in({$str})";
        $res = M($table_name)->execute($sql);
        return $res;
    }

}
