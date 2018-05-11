<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/7/31
 * Time: 下午6:29
 */

namespace Api\Controller;

class YimaController extends BaseController
{

    public function show()
    {
        try {
            $code = I('get.code');

            header('location:' . C('C_HOST_URL') . C('C_PRODUCT_INFO_URI') . $code . '&1');
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}