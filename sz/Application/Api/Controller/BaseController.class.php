<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/7/31
 * Time: 下午6:30
 */

namespace Api\Controller;

use Api\Common\ErrorCode;

class BaseController extends \Common\Common\Controller\BaseController
{
    protected function okNull()
    {
        $this->response(null);
    }

    /**
     * 验证接口是否携带安全参数
     * @author zjz
     */
    protected function checkApiSecretParam()
    {
        if (I('get.'.API_SECRET_PARAM) !== API_SECRET_CODE) {
            $this->_empty();
        }
    }

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
}