<?php

/**
 * File: BaseLogic.class.php
 * User: huangyingjian
 * Date: 2017/11/1
 */

namespace Qiye\Logic;

use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderOperationRecordService;
use Library\Common\Util;
use Qiye\Model\BaseModel;
use Qiye\Common\ErrorCode;
use Common\Common\Service\OrderService;
use Common\Common\Service\GroupService;
use Think\Auth;

class BaseLogic extends \Common\Common\Logic\BaseLogic
{

    protected $param = [];

    protected $rule = [];

    public function setParam($key, $val)
    {
        $this->param[$key] = $val;
    }

    public function getParam($key='')
    {
        if (empty($key)) {
            return $this->param;
        }

        if (isset($this->param[$key])) {
            return $this->param[$key];
        }

        return false;
    }

    protected function paginate($list, $number)
    {
        $response=[
            'page_no'  => I('page_no',   1,  'intval'),
            'page_num' => I('page_size', 10, 'intval'),
            'count' => !empty($number) ? $number : '0',
            'data_list' => !empty($list) ? $list : null,
        ];
        return $response;
    }

    /*
     * 操作记录图片处理
     */
    public function handleImage($image_json)
    {
        $images = json_decode($image_json, true);
        $image_str = '';
        foreach ($images as $k => $v) {
            $image_str .= '<img src="'.Util::getServerFileUrl($v['url']).'" />';
        }
        return $image_str;
    }

    /*
     * 检查最后一次预约是否签到
     */
    public function checkLastAppoint($order_id, $user_id)
    {
        $appoint_model = BaseModel::getInstance('worker_order_appoint_record');
        $order_model = BaseModel::getInstance('worker_order');
        $last_appoint = $appoint_model->getOne([
            'where' => [
                'worker_order_id' => $order_id,
                'worker_id'       => $user_id
            ],
            'order' => 'create_time desc',
            'field' => 'id, is_over'
        ]);
        if ($last_appoint['is_over'] == '0') {
            //如果没签到则签到
            $appoint_update = [
                'is_sign_in'    => 1,
                'sign_in_time' => NOW_TIME,
                'is_over' => 1,
                'over_time' => NOW_TIME,
                'appoint_status' => 4
            ];
            $appoint_model->update(['id' => $last_appoint['id']], $appoint_update);

            //检查工单状态
            $order_info = $order_model->getOne([
                'id' => $order_id
            ], 'worker_order_status, worker_first_sign_time');
            if ($order_info['worker_first_sign_time'] == '0') {
                $order_data['worker_first_sign_time'] = NOW_TIME;
            }
            if ($order_info['worker_order_status'] == OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE) {
                //签到成功修改工单状态
                $order_data['worker_order_status'] = OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE;
                $order_data['last_update_time'] = NOW_TIME;
                //群内工单修改数量
                event(new UpdateOrderNumberEvent([
                    'worker_order_id' => $order_id,
                    'operation_type'  => OrderOperationRecordService::WORKER_SIGN_SUCCESS
                ]));
            }
            if (!empty($order_data)) {
                $order_model->update([
                    'id' => $order_id
                ], $order_data);
            }
        }
    }


    /*
     * checkOrder
     */
    public function checkWorkerOrder($order_id, $user_id, $field = '*', $order_where = [])
    {
        $where = [
            'id' => $order_id,
            'cancel_status' => ['in', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]]
        ];
        if (!empty($order_where)) {
            $where = array_merge($where, $order_where);
        }
        $order_info = BaseModel::getInstance('worker_order')->getOne([
            'where' => $where,
            'field' => $field
        ]);
        if (AuthService::getModel() == AuthService::ROLE_WORKER && $order_info['worker_id'] != $user_id && $order_info['children_worker_id'] != $user_id) {
            $this->throwException(ErrorCode::SYS_NOT_POWER);
        }
        return $order_info;
    }

    /*
     * 检查师傅是否群主
     */
    public function checkIsGroupOwner($worker_group_id, $user_id, $user_type = 0, $group_apply_status = 0)
    {
        if (!BaseModel::getInstance('worker_group')->dataExist([
            'id' => $worker_group_id,
            'owner_worker_id' => $user_id,
            'is_delete' => 0,
            'status' => GroupService::GROUP_STATUS_AUDIT_PASS
        ])) {
            $user_type = GroupService::getWorkerStatus($user_id, $user_type, $group_apply_status);
            $this->throwException(GroupService::GROUP_ERROR_CODE[$user_type['type']]);
        }
        return;
    }

    /*
     * 检查师傅与群的关联
     */
    public function checkWorkerAndGroupRelation($worker_group_id, $user_id, $status = GroupService::WORKER_STATUS_AUDIT_PASS, $user_type = 0, $group_apply_status = 0)
    {
        if (!BaseModel::getInstance('worker_group_relation')->dataExist([
            'worker_group_id' => $worker_group_id,
            'worker_id' => $user_id,
            'is_delete' => 0,
            'status' => $status
        ])) {
            $user_type = GroupService::getWorkerStatus($user_id, $user_type, $group_apply_status);
            $this->throwException(GroupService::GROUP_ERROR_CODE[$user_type['type']]);
        }
        return;
    }

    /**
     * 检查订单是否是群组订单，并检查当前登陆用户是否是该订单的群主
     * @param $id int 订单id
     * @param $worker_id int 技工id
     * @param $order array 订单信息
     * @param string $field 检查的字段
     * @return array|bool 工单信息
     */
    public function checkWorkerIsOrderGroupOwnerOrFail($id, $worker_id = 0, &$order = [], $field = '')
    {
        if (!$worker_id) {
            AuthService::getModel() != AuthService::ROLE_WORKER && $this->throwException(ErrorCode::NOW_AUTH_IS_NOT_ADMIN);
            $worker_id = AuthService::getAuthModel()->getPrimaryValue();
        }

        $check_field = [
            'worker_id',
            'worker_group_id',
            'worker_order_status'
        ];

        if ($field) {
            $field = explode(',', $field);
            if (in_array('*', $field)) {
                $check_field = ['*'];
            } elseif (!empty($field)) {
                $check_field = array_unique(array_merge($check_field, $field));
            }
        }

        $order_key = array_keys($order);
        if (array_diff($check_field, $order_key)){
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($id, implode(',', $check_field));
        }
        // 是否是群组工单并且已完结
        !$order['worker_group_id'] && $this->throwException(ErrorCode::ORDER_IS_NOT_GROUP);
        $order['worker_id'] != $worker_id && $this->throwException(ErrorCode::WORKERID_IS_NOT_ORDERID_GROUP);

        return $order;
    }

}
