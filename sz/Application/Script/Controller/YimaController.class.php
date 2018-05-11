<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/7/31
 * Time: 下午6:29
 */

namespace Script\Controller;

class YimaController extends BaseController
{

    public function show()
    {
        try {
            $encrypt_code = I('get.code');

            $code = decryptYima($encrypt_code);

            echo $code;
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}