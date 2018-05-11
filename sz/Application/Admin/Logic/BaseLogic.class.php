<?php
/**
 * File: BaseLogic.class.php
 * User: xieguoqiu
 * Date: 2017/4/7 9:37
 */

namespace Admin\Logic;

use Common\Common\ErrorCode;
use Library\Common\Util;

class BaseLogic extends \Common\Common\Logic\BaseLogic
{
    /**
     * 表单数据验证
     * @param string $table 一般为表名(不包含前缀) agent
     * @param array $params 提交过来的数据 ['field'=>$value]
     */
    protected function formParamsCheck($table, $params, $action = 'add')
    {
        $err_msg = D('ValidateForm', 'Logic')->formParamsCheck($table, $params, $action);
        if (!empty($err_msg)) {
            $this->throwException(ErrorCode::SYS_REQUEST_METHOD_ERROR, $err_msg);
        }
        return $params;
    }

    /*
     * 操作记录图片处理
     */
    public function handleImage($image_json)
    {
        $images = !is_array($image_json) ? json_decode($image_json, true) : $image_json;
        $image_str = '';
        foreach ($images as $k => $v) {
            $image_str .= '<img src="'.Util::getServerFileUrl($v['url']).'" />';
        }
        return $image_str;
    }

}
