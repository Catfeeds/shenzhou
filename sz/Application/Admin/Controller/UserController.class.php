<?php
/**
 * File: DrawController.class.php
 * Function:
 * User: cjy
 * Date: 2017/12/20
 */

namespace Admin\Controller;

use Common\Common\Service\AuthService;

class UserController extends BaseController
{

    //用户列表
    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $result = D('User', 'Logic')->getList();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //用户详情
    public function read()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('User', 'Logic')->read();
            $this->response($result);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    //激活产品列表
    public function products()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('User', 'Logic')->products();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //售后工单列表
    public function worker_orders()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('User', 'Logic')->worker_orders();
            $this->paginate($result['data'], $result['count']);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function workerUserRegister()
    {
        try {
            D('User', 'Logic')->workerUserRegister();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }
}