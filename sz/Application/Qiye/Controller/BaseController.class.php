<?php

/**
 * File: BaseController.class.php
 * User: huangyingjian
 * Date: 2017/11/1
 */

namespace Qiye\Controller;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;

class BaseController extends \Common\Common\Controller\BaseController
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


}
