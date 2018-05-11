<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/19
 * Time: 16:21
 */

namespace Admin\Controller;

use Common\Common\Service\AuthService;

class AllowanceController extends BaseController
{
    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $is_export = I('is_export', 0, 'intval');

            $param = [
                'status'            => I('status'),
                'worker_order_status'   =>I('worker_order_status'),//工单状态
                'orno'              => I('orno'),
                'admin_group_id'    => I('admin_group_id', 0, 'intval'),
                'admin_ids'         => I('admin_ids'),
                'factory_group_ids' => I('factory_group_ids'),
                'date_from'         => I('date_from', 0, 'intval'),
                'date_to'           => I('date_to', 0, 'intval'),
                'fee_from'          => I('fee_from'),
                'fee_to'            => I('fee_to'),
                'limit'             => $this->page(),
                'is_export'         => $is_export,
            ];

            $result = D('Allowance', 'Logic')->getList($param);
            if (1 != $is_export) {
                $extra = ['total_fee' => $result['total_fee']];
                $this->paginate($result['data'], $result['cnt'], $extra);
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function batchStatus()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $param = [
                'allow_ids' => I('id_list'),
                'status'    => I('status', 0, 'intval'),
                'remark'    => I('remark'),
            ];

            M()->startTrans();
            D('Allowance', 'Logic')->statusBatch($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function status()
    {

        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'allow_id' => I('allow_id', 0, 'intval'),
                'status'   => I('status', 0, 'intval'),
                'remark'   => I('remark'),
            ];

            M()->startTrans();
            D('Allowance', 'Logic')->status($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function info()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'allow_id' => I('allow_id', 0, 'intval'),
            ];

            $data = D('Allowance', 'Logic')->info($param);

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function add()
    {

        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'type'            => I('type', 0, 'intval'),
                'apply_fee'       => I('apply_fee'),
                'remark'          => I('remark'),
                'worker_order_id' => I('worker_order_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('Allowance', 'Logic')->add($param);
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
                'worker_order_id' => I('worker_order_id', 0, 'intval'),
            ];

            $data = D('Allowance', 'Logic')->history($param);

            $this->response(['data_list' => $data['list'], 'total_fee' => $data['total_fee']]);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

}