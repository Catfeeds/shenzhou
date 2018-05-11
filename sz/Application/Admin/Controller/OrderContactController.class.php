<?php
/**
 * Function:技工联系记录
 * File: OrderContactController.class.php
 * User: sakura
 * Date: 2017/11/15
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;
use Common\Common\Service\OrderContactService;

class OrderContactController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $is_export = I('is_export', 0, 'intval');

            $param = [
                'admin_name'   => I('admin_name'),
                'worker_name'  => I('worker_name'),
                'worker_phone' => I('worker_phone'),
                'date_from'    => I('date_from', 0, 'intval'),
                'date_to'      => I('date_to', 0, 'intval'),
                'limit'        => $this->page(),
                'is_export'    => $is_export,
            ];

            $result = D('OrderContact', 'Logic')->getList($param);

            if (1 != $is_export) {
                $this->paginate($result['data'], $result['cnt']);
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function add()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id'            => I('worker_id', 0, 'intval'),
                'contact_method'       => I('contact_method', 0, 'intval'),
                'contact_type'         => I('contact_type', 0, 'intval'),
                'contact_result'       => I('contact_result', 0, 'intval'),
                'contact_report'       => I('contact_report', 0, 'intval'),
                'contact_remark'       => I('contact_remark'),
                'contact_object'       => OrderContactService::OBJECT_TYPE_WORKER,
                'contact_object_other' => I('contact_object_other'),
                'worker_order_id'      => I('worker_order_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('OrderContact', 'Logic')->add($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addAndRegister()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id'            => I('worker_id', 0, 'intval'),
                'contact_method'       => I('contact_method', 0, 'intval'),
                'contact_type'         => I('contact_type', 0, 'intval'),
                'contact_result'       => I('contact_result', 0, 'intval'),
                'contact_report'       => I('contact_report', 0, 'intval'),
                'contact_remark'       => I('contact_remark'),
                'contact_object'       => I('contact_object'),
                'contact_object_other' => I('contact_object_other'),
                'phone'                => I('phone'),
                'user_name'            => I('nickname'),
                'province_id'          => I('province_id', 0, 'intval'),
                'city_id'              => I('city_id', 0, 'intval'),
                'district_id'          => I('area_id', 0, 'intval'),
                'address'              => I('address'),
            ];

            M()->startTrans();
            D('OrderContact', 'Logic')->addAndRegister($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function history()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id' => I('worker_id', 0, 'intval'),
                'limit'     => $this->page(),
            ];

            $result = D('OrderContact', 'Logic')->history($param);

            $this->paginate($result['data'], $result['cnt']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
}