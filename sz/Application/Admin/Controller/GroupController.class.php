<?php
/**
 * Created by PhpStorm.
 * User: 嘉诚
 * Date: 2018/1/25
 * Time: 11:28
 */

namespace Admin\Controller;

use Common\Common\ErrorCode;
use Common\Common\Service\AuthService;

class GroupController extends BaseController
{
    /*
     * 群列表
     */
    public function groupList()
    {
        try {
            $data = D('Group', 'Logic')->groupList(I('get.'), $this->requireAuth());
            $this->paginate($data['list'], $data['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群内技工列表接口
     */
    public function groupWorkerList()
    {
        try {
            $data = D('Group', 'Logic')->groupWorkerList(I('get.id'), $this->requireAuth());
            $this->paginate($data['list'], $data['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 网点群详情
     */
    public function detail()
    {
        try {
            $data = D('Group', 'Logic')->detail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群审核
     */
    public function audit()
    {
        try {
            $data = D('Group', 'Logic')->audit(I('get.id'), I('post.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群信息修改
     */
    public function update()
    {
        try {
            $data = D('Group', 'Logic')->update(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}