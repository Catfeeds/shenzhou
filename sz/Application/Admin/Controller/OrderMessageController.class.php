<?php
/**
 * File: OrderMessageController.class.php
 * User: sakura
 * Date: 2017/11/14
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;

class OrderMessageController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'last_query_id'   => I('last_query_id', 0, 'intval'),
                'worker_order_id' => I('worker_order_id', 0, 'intval'),
                'limit'           => $this->page(),
            ];

            M()->startTrans();
            $data = D('OrderMessage', 'Logic')->getList($param);
            M()->commit();

            $this->paginate($data['data'], $data['cnt']);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function add()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'role'            => AuthService::getModel(),
                'worker_order_id' => I('worker_order_id', 0, 'intval'),
                'content'         => I('content'),
            ];

            M()->startTrans();
            D('OrderMessage', 'Logic')->add($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


}