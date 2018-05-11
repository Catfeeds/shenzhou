<?php
/**
 * File: DrawController.class.php
 * Function:
 * User: cjy
 * Date: 2017/12/07
 */

namespace Api\Controller;

use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;

class DrawController extends BaseController
{

    public function getDraw()
    {
        try {

            if (I('get.token')) {
                $user_id = $this->requireAuth();
            }

            $data = D('Draw', 'Logic')->getDraw($user_id);
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function winList()
    {
        try {
            $result = D('Draw', 'Logic')->winList();
            $this->response($result['data']);
            //分页 $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function draw()
    {
        try {
            if (I('get.token')) {
                $user_id = $this->requireAuth();
            }

            $data = D('Draw', 'Logic')->draw($user_id);
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getCode()
    {
        try {
            $data = D('Draw', 'Logic')->getCode();
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function phoneValidate()
    {
        try {
            if (I('put.token')){
                $user_id = $this->requireAuth();
            }
            $data = D('Draw', 'Logic')->validate($user_id);
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function receiver()
    {
        try {
            $data = D('Draw', 'Logic')->receiver();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }


    public function userPrizes()
    {
        try {
            if (I('get.token')) {
                $user_id = $this->requireAuth();
            }
            $result = D('Draw', 'Logic')->userPrizes($user_id);

            $this->response($result['data']);
            //分页 $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function userCoupons()
    {
        try {
            $result = D('Draw', 'Logic')->userCoupons();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function goods()
    {
        try {
            $data = D('Draw', 'Logic')->goods();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function coupons()
    {
        try {
            $data = D('Draw', 'Logic')->coupons();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }


}