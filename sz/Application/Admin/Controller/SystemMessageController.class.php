<?php
/**
 * File: SystemMessageController.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/29
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;

class SystemMessageController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN, AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'limit' => $this->page(),
            ];

            $result = D('SystemMessage', 'Logic')->getList($param);

            $this->paginate($result['data'], $result['cnt'], ['unread_num' => $result['unread_num']]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function read()
    {

        try {
            $this->requireAuth([AuthService::ROLE_ADMIN, AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'msg_id' => I('get.msg_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('SystemMessage', 'Logic')->read($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function readAll()
    {

        try {
            $this->requireAuth([AuthService::ROLE_ADMIN, AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            M()->startTrans();
            D('SystemMessage', 'Logic')->readAll();
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

}