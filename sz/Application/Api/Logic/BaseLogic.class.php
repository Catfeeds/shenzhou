<?php
/**
* 
*/
namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;

class BaseLogic extends \Common\Common\Logic\BaseLogic
{

	protected function throwException($error_code, $error_msg = '')
    {
        if (is_array($error_msg)) {
            $msg_arr = $error_msg;
            $error_msg = ErrorCode::getMessage($error_code);
            foreach ($msg_arr as $search => $msg) {
                $error_msg = str_replace(':' . $search, $msg, $error_msg);
            }
        } else {
            empty($error_msg) && $error_msg = ErrorCode::getMessage($error_code);
        }
        throw new \Exception($error_msg, $error_code);
    }

    /**
     * 检查参数是否有空值(未设置该字段或为''),有则返回错误提示
     * @param $params
     */
    protected function checkEmpty($params)
    {
        (!is_array($params)) && $params = (array)$params;
        foreach ($params as $param) {
            (!isset($param) || $param == '') && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
    }

    /*
     * 禁用启用
     * @param intval $id 主键ID
     * @param intval $status 当前状态 0,1
     * @param string $table_name 表名
     * */
    protected function statusOperate($id, $status, $table_name)
    {
        $this->checkEmpty([$id, $status, $table_name]);
        $update_status = empty($status) ? 1 : 0;
        return BaseModel::getInstance($table_name)->update($id, ['is_disable'=>$update_status]);
    }

    /*
     * 删除
     * @param intval $id 主键ID
     * @param intval $operate_id 当前用户ID
     * @param string $table_name 表明
     * */
    public function delete($id, $table_name, $operate_id=0)
    {
        $this->checkEmpty([$id, $table_name]);
        $update['is_delete'] = time();
        if (!empty($operate_id)) {
            $update['operate_id'] = $operate_id;
        }
        return BaseModel::getInstance($table_name)->update($id, $update);
    }

    public function p($msg)
    {
        echo '<pre>';
        print_r($msg);
        echo '<pre>';
        die;
    }

}
