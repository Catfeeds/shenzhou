<?php
/**
 * File: RecruitController.class.php
 * Function: 开点单
 * User: sakura
 * Date: 2017/11/22
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;

class RecruitController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'status'    => I('status'),
                'is_valid'  => I('is_valid'),
                'date_from' => I('date_from', 0, 'intval'),
                'date_to'   => I('date_to', 0, 'intval'),
                'orno'      => I('orno'),
                'admin_ids' => I('admin_ids'),
                'area'      => I('area'),
                'factory_ids' => I('factory_ids'),
                'factory_group_ids' => I('factory_group_id_list'),
                'limit'     => $this->page(),
            ];

            $result = D('Recruit', 'Logic')->getList($param);

            $this->paginate($result['data'], $result['cnt']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function add()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_order_id' => I('worker_order_id', 0, 'intval'),
                'remark'          => I('remark'),
            ];

            M()->startTrans();
            D('Recruit', 'Logic')->add($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function userList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'name'  => I('name'),
                'limit' => $this->page(),
            ];

            $result = D('Recruit', 'Logic')->userList($param);

            $this->paginate($result['list'], $result['cnt']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function designate()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'apply_id'   => I('get.apply_id', 0, 'intval'),
                'auditor_id' => I('auditor_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('Recruit', 'Logic')->designate($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function evaluate()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'apply_id' => I('get.apply_id', 0, 'intval'),
                'is_valid' => I('is_valid', 0, 'intval'),
                'remark'   => I('remark'),
            ];

            M()->startTrans();
            D('Recruit', 'Logic')->evaluate($param);
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
                'worker_order_id' => I('order_id', 0, 'intval'),
            ];

            $data = D('Recruit', 'Logic')->history($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function workerList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'name'  => I('name'),
                'limit' => $this->page(),
            ];

            $result = D('Recruit', 'Logic')->workerList($param);

            $this->paginate($result['data'], $result['cnt']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function cancel()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'apply_id' => I('get.apply_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('Recruit', 'Logic')->cancel($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function feedback()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'apply_id'  => I('get.apply_id', 0, 'intval'),
                'result'    => I('result', 0, 'intval'),
                'worker_id' => I('worker_id', 0, 'intval'),
                'remark'    => I('remark'),
            ];

            M()->startTrans();
            D('Recruit', 'Logic')->feedback($param);
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
                'apply_id' => I('apply_id', 0, 'intval'),
            ];

            $data = D('Recruit', 'Logic')->info($param);

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

}