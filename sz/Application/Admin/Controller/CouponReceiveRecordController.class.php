<?php
/**
 * File: DrawController.class.php
 * Function:
 * User: cjy
 * Date: 2017/12/07
 */

namespace Admin\Controller;

use Common\Common\Service\AuthService;

class CouponReceiveRecordController extends BaseController
{

    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('CouponReceiveRecord', 'Logic')->getList();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function view()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $data = D('CouponReceiveRecord', 'Logic')->view();
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function operate()
    {
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('CouponReceiveRecord', 'Logic')->operate($adminId);

            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

}