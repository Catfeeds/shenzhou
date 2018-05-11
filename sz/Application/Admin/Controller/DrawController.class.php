<?php
/**
 * File: DrawController.class.php
 * Function:
 * User: cjy
 * Date: 2017/12/07
 */

namespace Admin\Controller;

use Common\Common\Service\AuthService;

class DrawController extends BaseController
{

    //抽奖列表
    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            
            $result = D('Draw', 'Logic')->getList();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //抽奖新增
    public function add()
    {
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('Draw', 'Logic')->add($adminId);
            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    //抽奖编辑
    public function update()
    {
        $param = I('put.');
        
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('Draw', 'Logic')->update($param,$adminId);
            
            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    //抽奖详情
    public function read()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $data = D('Draw', 'Logic')->read();
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //抽奖发布、结束
    public function operate()
    {
        
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('Draw', 'Logic')->operate($adminId);

            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    //邮寄
    public function express()
    {

        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('Draw', 'Logic')->express($adminId);

            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    //中奖名单
    public function winList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $result = D('Draw', 'Logic')->winList();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //抽奖名单
    public function drawList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $result = D('Draw', 'Logic')->drawList();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //抽奖数据
    public function drawData()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $data = D('Draw', 'Logic')->drawData();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //获奖情况
    public function prizeList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $data = D('Draw', 'Logic')->prizeList();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //奖品列表
    public function prizes()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $data = D('Draw', 'Logic')->prizes();
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*自动结束脚本*/
    public function statusScript()
    {
        try {
            D('Draw', 'Logic')->statusScript();

            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    /*每日统计脚本*/
    public function statisticsScript()
    {
        try {
            $data = D('Draw', 'Logic')->statisticsScript();

            $this->response($data);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

}