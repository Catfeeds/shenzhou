<?php
/**
 * Created by PhpStorm.
 * User: 嘉诚
 * Date: 2018/1/25
 * Time: 11:28
 */

namespace Qiye\Controller;

use Common\Common\ErrorCode;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderExtInfoService;
use Common\Common\Service\OrderService;
use Qiye\Logic\OrderLogic;
use Qiye\Model\BaseModel;

class GroupController extends BaseController
{
    public function setOrderTag()
    {
        $id = I('get.id', 0);
        $tag_type = I('post.tag_type', 0);
        try {
            $worker_id = $this->requireAuth(AuthService::ROLE_WORKER);

            $type = array_flip(OrderExtInfoService::WORKER_GROUP_SET_TAG_INDEX_KEY_VALUE)[$tag_type];
            empty($type) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR);

            $logic = new OrderLogic();
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($id, 'worker_id,worker_group_id,worker_order_status');

            switch ($type) {
                case OrderExtInfoService::WORKER_GROUP_SET_TAG_SETTLEMENT_ON_WORKER_MEMBER:
                    $logic->checkWorkerIsOrderGroupOwnerOrFail($id, $worker_id, $order);
                    !in_array($order['worker_order_status'], OrderService::getOrderCompleteInGroup()) && $this->fail(ErrorCode::ORDER_STATUS_IS_NOT_OPERATION);
                    break;
            }

            BaseModel::getInstance('worker_order_ext_info')->update($id, [
                'worker_group_set_tag' => ['exp', ' worker_group_set_tag | '.$type]
            ]);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群关联检索
     */
    public function checkGroup()
    {
        try {
            $data = D('Group', 'Logic')->checkGroup(I('get.type'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群关联检索
     */
    public function getWorkerStatus()
    {
        try {
            $data = D('Group', 'Logic')->getWorkerStatus($this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 创建群
     */
    public function add()
    {
        try {
            $data = D('Group', 'Logic')->add(I('post.'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 加入群
     */
    public function join()
    {
        try {
            $data = D('Group', 'Logic')->join(I('get.id'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工审核
     */
    public function auditWorker()
    {
        try {
            $data = D('Group', 'Logic')->auditWorker(I('get.id'), I('put.'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 系统自动审核技工
     */
    public function AutoAuditWorker()
    {
        try {
            $data = D('Group', 'Logic')->AutoAuditWorker();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工审核详情
     */
    public function auditWorkerInfo()
    {
        try {
            $data = D('Group', 'Logic')->auditWorkerInfo(I('get.id'), I('get.worker_id'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群内技工列表
     */
    public function groupWorkerList()
    {
        try {
            $data = D('Group', 'Logic')->groupWorkerList(I('get.id'), I('get.'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群详情
     */
    public function detail()
    {
        try {
            $data = D('Group', 'Logic')->detail(I('get.id'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 移除技工
     */
    public function remove()
    {
        try {
            $data = D('Group', 'Logic')->remove(I('get.id'), I('post.worker_id'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工完单统计
     */
    public function statisticsFinishOrders()
    {
        try {
            $data = D('Group', 'Logic')->statisticsFinishOrders(I('get.id'), I('get.'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工审核列表
     */
    public function auditWorkerList()
    {
        try {
            $data = D('Group', 'Logic')->auditWorkerList(I('get.id'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群主派发工单
     */
    public function distributeOrder()
    {
        try {
            $data = D('Group', 'Logic')->distributeOrder(I('get.id'), I('post.'), $this->requireAuth(), AuthService::getAuthModel());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群号检索
     */
    public function checkGroupNo()
    {
        try {
            $data = D('Group', 'Logic')->checkGroupNo(I('get.group_no'));
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 群内技工详情
     */
    public function groupWorkerInfo()
    {
        try {
            $data = D('Group', 'Logic')->groupWorkerInfo(I('get.id'), I('get.worker_id'));
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 恢复创建群数据
     */
    public function groupRecover()
    {
        try {
            $data = D('Group', 'Logic')->groupRecover($this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * fix-修复服务已完成工单数错误
     */
    public function fixFinishOrderNumber()
    {
        try {
            $data = D('Group', 'Logic')->fixFinishOrderNumber();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}