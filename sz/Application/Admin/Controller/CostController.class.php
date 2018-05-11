<?php
/**
 * File: CostController.class.php
 * User: sakura
 * Date: 2017/11/9
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;

class CostController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'status'            => I('status'),
                'apply_cost_number' => I('apply_cost_number', ''),
                'type'              => I('type', 0, 'intval'),
                'orno'              => I('orno', ''),
                'date_from'         => I('date_from', 0, 'intval'),
                'date_to'           => I('date_to', 0, 'intval'),
                'worker_name'       => I('worker_name'),
                'worker_tel'        => I('worker_tel'),
                'fee_from'          => I('fee_from'),
                'fee_to'            => I('fee_to'),
                'admin_group_id'        => I('admin_group_id', 0, 'intval'),
                'admin_ids'             => I('admin_ids'),
                'factory_ids'           => I('factory_ids'),
                'factory_group_id_list' => I('factory_group_id_list'),
                'tag_id'                => I('tag_id'),

                'limit' => $this->page(),
            ];

            $result = D('Cost', 'Logic')->getList($param);

            $this->paginate($result['list'], $result['cnt']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getStatusCnt()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
            ];

            $stats = D('Cost', 'Logic')->getStatusCnt($param);

            $this->response($stats);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function info()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'cost_id' => I('cost_id', 0, 'intval'),
            ];

            $data = D('Cost', 'Logic')->getInfo($param);

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryCheck()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'cost_id'  => I('get.cost_id', 0, 'intval'),
                'is_check' => I('is_check', 0, 'intval'),
                'remark'   => I('remark'),
            ];

            M()->startTrans();
            D('Cost', 'Logic')->factoryCheck($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function adminCheck()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'cost_id'  => I('get.cost_id', 0, 'intval'),
                'is_check' => I('is_check', 0, 'intval'),
                'remark'   => I('remark'),
            ];

            M()->startTrans();
            D('Cost', 'Logic')->adminCheck($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function pendingTrial()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'cost_id'  => I('get.cost_id', 0, 'intval'),
                'is_check' => I('is_check', 0, 'intval'),
                'remark'   => I('remark'),
                'img'      => I('img'),
            ];

            M()->startTrans();
            D('Cost', 'Logic')->pendingTrial($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}