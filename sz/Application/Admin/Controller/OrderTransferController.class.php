<?php
/**
 * File: OrderTransferController.class.php
 * User: sakura
 * Date: 2017/11/21
 */

namespace Admin\Controller;


use Admin\Logic\OrderTransferLogic;
use Common\Common\Service\AuthService;

class OrderTransferController extends BaseController
{

    public function delegate()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_order_id' => I('get.order_id', 0, 'intval'),
                'admin_id'        => I('admin_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('OrderTransfer', 'Logic')->delegate($param);
            D()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function delegateBatch()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_order_ids' => I('worker_order_ids'),
                'admin_id'         => I('admin_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('OrderTransfer', 'Logic')->delegateBatch($param);
            D()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function receiveBatch()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_order_ids' => I('worker_order_ids'),
            ];

            M()->startTrans();
            D('OrderTransfer', 'Logic')->receiveBatch($param);
            D()->commit();

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
                'worker_order_id' => I('order_id', 0, 'intval'),
                'name'            => I('name'),
                'limit'           => $this->page(),
            ];

            $result = D('OrderTransfer', 'Logic')->userList($param);

            $this->paginate($result['list'], $result['cnt']);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function stop()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_order_id' => I('get.order_id', 0, 'intval'),
                'remark'          => I('remark'),
            ];

            M()->startTrans();
            D('OrderTransfer', 'Logic')->stop($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function workerOrderType()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN, AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'worker_order_id' => I('get.id', 0, 'intval'),
                'pic_url'         => I('pic_url'),
                'remark'          => I('remark'),
            ];

            (new OrderTransferLogic())->workerOrderType($param);

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}