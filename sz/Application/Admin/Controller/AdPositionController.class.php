<?php
/**
 * File: DrawController.class.php
 * Function:
 * User: cjy
 * Date: 2017/12/22
 */

namespace Admin\Controller;

use Common\Common\Service\AuthService;

class AdPositionController extends BaseController
{

    //宣传图位置
    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('AdPosition', 'Logic')->getList();
            $this->response($result);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //宣传图位置详情
    public function read()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('AdPosition', 'Logic')->read();
            $this->response($result);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    //宣传图修改
    public function update()
    {
        $param = I('put.');
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('AdPosition', 'Logic')->update($param);
            $this->response($result);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }


}