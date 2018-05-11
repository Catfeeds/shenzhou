<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/7/31
 * Time: 下午6:30
 */

namespace Script\Controller;

use Script\Common\ErrorCode;

class BaseController extends \Common\Common\Controller\BaseController
{

    function __construct()
    {
        I('get.script') !== 'script_zjz' && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS, '密钥');
        parent::__construct();
    }
}