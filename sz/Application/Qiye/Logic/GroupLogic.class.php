<?php
/**
 * Created by PhpStorm.
 * User: 嘉诚
 * Date: 2018/1/25
 * Time: 11:35
 */

namespace Qiye\Logic;

use Common\Common\Repositories\Events\GroupNewsEvent;
use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\Repositories\Listeners\UpdateOrderNumberNotification;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\GroupService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Common\Common\Service\AuthService;
use Qiye\Model\BaseModel;

class GroupLogic extends BaseLogic
{
    /*
     * 群关联检索
     */
    public function checkGroup($type, $user_id, $user_info)
    {
        if (!in_array($type, [GroupService::CHECK_TYPE_BY_USER_CREATE_GROUP, GroupService::CHECK_TYPE_BY_USER_JOIN_GROUP]))
        {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'type类型错误');
        }
        $status = GroupService::CHECK_TYPE_BY_USER_IS_NORMAL;
        if ($type == GroupService::CHECK_TYPE_BY_USER_CREATE_GROUP) {
            //检查用户能否创建群
            //$worker_quality = $user_info['quality_money_need'] - $user_info['quality_money'];
            if ($user_info['quality_money'] < 2000) {
                $status = GroupService::CHECK_TYPE_BY_USER_CREATE_GROUP;
            }
        } else {
            //检查用户能否加入群
            $num = BaseModel::getInstance('worker_order')->getNum([
                'worker_id' => $user_id,
                'worker_order_status' => ['lt', OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT],
                'cancel_status'       => OrderService::CANCEL_TYPE_NULL
            ]);
            if ($num > 0) {
                $status = GroupService::CHECK_TYPE_BY_USER_JOIN_GROUP;
            }
        }
        if ($status == GroupService::CHECK_TYPE_BY_USER_IS_NORMAL) {
            $worker_group_id = BaseModel::getInstance('worker_group_relation')->getFieldVal([
                'worker_id' => $user_id,
                'status'    => ['in', GroupService::WORKER_STATUS_IN_GROUP]
            ], 'worker_group_id');
            if (empty($worker_group_id)) {
                $worker_group_id = BaseModel::getInstance('worker_group')->getFieldVal([
                    'owner_worker_id' => $user_id,
                    'status'          => GroupService::GROUP_STATUS_AUDITING
                ], 'id');
            }
            if (!empty($worker_group_id)) {
                $status = GroupService::CHECK_TYPE_BY_USER_IN_GROUP;
            }
        }
        return [
            'status' => $status,
            'msg'    => GroupService::CHECK_TYPE_MSG[$status],
            'group_service_phone' => C('GROUP_SERVICE_PHONE') ?? null
        ];
    }

    /*
     * 获取当前用户状态
     */
    public function getWorkerStatus($user_id, $user_info)
    {
        $info = GroupService::getWorkerStatus($user_id, $user_info['type'], $user_info['group_apply_status']);
        if (!empty($info['worker_group_id'])) {
            $worker_group_info = BaseModel::getInstance('worker_group')->getOne($info['worker_group_id']);
            if ($info['type'] == GroupService::WORKER_TYPE_JOIN_GROUP_FAIL) {
                $owner_audit_remark = BaseModel::getInstance('worker_group_relation')->getFieldVal([
                    'worker_group_id' => $info['worker_group_id'],
                    'worker_id'       => $user_id
                ], 'audit_remark');
            }
        }
        return [
            'worker_group_id'     => $info['worker_group_id'] ?? null,
            'group_name'          => $worker_group_info['group_name'] ?? null,
            'cp_owner_telephone'  => $worker_group_info['cp_owner_telephone'] ?? null,
            'type'                => $info['type'],
            'admin_audit_remark'  => $worker_group_info['audit_remark'] ?? null,
            'owner_audit_remark'  => $owner_audit_remark ?? null,
            'group_service_phone' => C('GROUP_SERVICE_PHONE') ?? null
        ];
    }

    /*
     * 创建群
     */
    public function add($request, $user_id, $user_info)
    {
        $check = $this->checkGroup(GroupService::CHECK_TYPE_BY_USER_CREATE_GROUP, $user_id, $user_info);
        if ($check['status'] != GroupService::CHECK_TYPE_BY_USER_IS_NORMAL) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $check['msg']);
        }
        if (empty($request['group_name']) || empty($request['create_reason'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        M()->startTrans();
        $worker_group_id = BaseModel::getInstance('worker_group')->insert([
            'group_name'         => $request['group_name'],
            'owner_worker_id'    => $user_id,
            'create_time'        => NOW_TIME,
            'create_reason'      => $request['create_reason'],
            'cp_owner_nickname'  => $user_info['nickname'],
            'cp_owner_telephone' => $user_info['worker_telephone'],
            'business_license'   => $request['business_license'],
            'store_images'       => html_entity_decode(html_entity_decode($request['store_images']))
        ]);
        //操作记录
        GroupService::create($worker_group_id, GroupService::GROUP_RECORD_TYPE_CREATE_GROUP, [
            'record_operator_id' => $user_id,
            'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_WORKER,
            'content' => '技工'.$user_info['nickname'].'创建了群'.$request['group_name']
        ]);
        //修改技工状态
        BaseModel::getInstance('worker')->update([
            'worker_id' => $user_id
        ], [
            'group_apply_status' => GroupService::GROUP_APPLY_STATUS_CREATE_AUDITING
        ]);
        M()->commit();
    }

    /*
     * 加入群
     */
    public function join($worker_group_id, $user_id, $user_info)
    {
        $check = $this->checkGroup(GroupService::CHECK_TYPE_BY_USER_JOIN_GROUP, $user_id, $user_info);
        if ($check['status'] != GroupService::CHECK_TYPE_BY_USER_IS_NORMAL) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $check['msg']);
        }
        $worker_group_info = BaseModel::getInstance('worker_group')->getOne($worker_group_id, 'status');
        if ($worker_group_info['status'] != GroupService::GROUP_STATUS_AUDIT_PASS) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该群未审核通过，无法申请加入');
        }
        M()->startTrans();
        $group_relation_model = BaseModel::getInstance('worker_group_relation');
        if (!$group_relation_model->dataExist([
            'worker_id' => $user_id,
            'worker_group_id' => $worker_group_id
        ])) {
            $group_relation_model->insert([
                'worker_id' => $user_id,
                'worker_group_id' => $worker_group_id,
                'user_type' => GroupService::WORKER_RELATION_GROUP_NOT_IN_GROUP,
                'status'    => GroupService::WORKER_STATUS_AUDITING,
                'create_time' => NOW_TIME,
                'apply_time'  => NOW_TIME,
                'worker_proportion' => C('WORKER_PROPORTION')
            ]);
        } else {
            $group_relation_model->update([
                'worker_id' => $user_id,
                'worker_group_id' => $worker_group_id
            ], [
                'user_type'  => GroupService::WORKER_RELATION_GROUP_NOT_IN_GROUP,
                'status'     => GroupService::WORKER_STATUS_AUDITING,
                'apply_time' => NOW_TIME,
                'is_delete'  => 0
            ]);
        }
        //操作记录
        $record_id = GroupService::create($worker_group_id, GroupService::GROUP_RECORD_TYPE_APPLY_JOIN_GROUP, [
            'record_operator_id' => $user_id,
            'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_WORKER,
            'content' => '技工'.$user_info['nickname'].'申请加入群'
        ]);
        //修改技工状态
        BaseModel::getInstance('worker')->update($user_id, [
            'group_apply_status' => GroupService::GROUP_APPLY_STATUS_JOIN_AUDITING
        ]);
        //推送消息
        event(new GroupNewsEvent([
            'data_id' => $record_id,
            'type'    => GroupService::GROUP_RECORD_TYPE_APPLY_JOIN_GROUP
        ]));
        M()->commit();
    }

    /*
     * 获取群信息
     */
    public function getGroupInfo($group_id, $field = '*')
    {
        return BaseModel::getInstance('worker_group')->getOne([
            'where' => [
                'id' => $group_id
            ],
            'field' => $field
        ]);
    }

    /*
     * 技工审核
     */
    public function auditWorker($worker_group_id, $request, $user_id, $user_info)
    {
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);
        $worker_info = BaseModel::getInstance('worker')->getOne([
            'where' => [
                'worker_id' => $request['worker_id']
            ],
            'field' => 'type, nickname, group_apply_status'
        ]);
        $this->checkWorkerAndGroupRelation($worker_group_id, $request['worker_id'], GroupService::WORKER_STATUS_AUDITING, $worker_info['type'], $worker_info['group_apply_status']);



        M()->startTrans();

        if ($request['is_pass'] == '1') {
            $user_type   = GroupService::WORKER_RELATION_GROUP_MEMBER;
            $type        = GroupService::WORKER_TYPE_GROUP_MEMBER;
            $status      = GroupService::WORKER_STATUS_AUDIT_PASS;
            $record_type = GroupService::GROUP_RECORD_TYPE_ALLOW_JOIN_GROUP;
            $is_pass     = '通过';
            //群成员人数+1
            BaseModel::getInstance('worker_group')->setNumInc([
                'id' => $worker_group_id
            ], 'worker_number');
        } else {
            $user_type   = GroupService::WORKER_RELATION_GROUP_NOT_IN_GROUP;
            $type        = GroupService::WORKER_TYPE_ORDINARY_WORKER;
            $status      = GroupService::WORKER_STATUS_AUDIT_NOT_PASS;
            $record_type = GroupService::GROUP_RECORD_TYPE_NOT_ALLOW_JOIN_GROUP;
            $is_pass     = '不通过';
        }

        BaseModel::getInstance('worker_group_relation')->update([
            'worker_id'       => $request['worker_id'],
            'worker_group_id' => $worker_group_id
        ], [
            'user_type'    => $user_type,
            'audit_time'   => NOW_TIME,
            'audit_remark' => $request['audit_remark'],
            'status'       => $status
        ]);

        //操作记录
        $record_id = GroupService::create($worker_group_id, $record_type, [
            'record_operator_id'   => $user_id,
            'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_OWNER,
            'operated_worker_id'   => $request['worker_id'],
            'content' => '技工'.$worker_info['nickname'].'申请加入群审核'.$is_pass
        ]);

        //修改技工状态
        BaseModel::getInstance('worker')->update($request['worker_id'], [
            'type' => $type,
            'group_apply_status' => GroupService::GROUP_APPLY_STATUS_NULL
        ]);
        //推送消息
        event(new GroupNewsEvent([
            'data_id' => $record_id,
            'type'    => $record_type
        ]));

        M()->commit();
    }

    /*
     * 技工审核详情
     */
    public function auditWorkerInfo($worker_group_id, $worker_id, $user_id, $user_info)
    {
        if (empty($worker_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'worker_id不能为空');
        }
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);
        $info = BaseModel::getInstance('worker_group_relation')->getOne([
            'alias' => 'wgr',
            'where' => [
                'wgr.worker_id'       => $worker_id,
                'wgr.worker_group_id' => $worker_group_id
            ],
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id',
            'field' => 'wgr.worker_id, w.nickname, w.worker_telephone, w.thumb, wgr.apply_time, wgr.status, wgr.audit_time, wgr.audit_remark, wgr.is_delete'
        ]);
        if (!empty($info['thumb'])) {
            $info['thumb'] = Util::getServerFileUrl($info['thumb']);
        }
        return $info;
    }

    /*
     * 群内技工列表
     */
    public function groupWorkerList($worker_group_id, $request, $user_id, $user_info)
    {
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);
        if ($request['is_return_owner'] == '1') {
            $where['wgr.user_type'] = ['in', [GroupService::WORKER_RELATION_GROUP_OWNER, GroupService::WORKER_RELATION_GROUP_MEMBER]];
        } else {
            $where['wgr.user_type'] = GroupService::WORKER_RELATION_GROUP_MEMBER;
        }
        if (isset($request['keyword']) && !empty($request['keyword'])) {
            $where['w.nickname'] = ['like', '%'.$request['keyword'].'%'];
        }
        $where['wgr.worker_group_id'] = $worker_group_id;

        $relation_model = BaseModel::getInstance('worker_group_relation');
        $order_model = BaseModel::getInstance('worker_order');

//        $list = $relation_model->getList([
//            'alias' => 'wgr',
//            'where' => $where,
//            'join'  => "left join worker as w on w.worker_id=wgr.worker_id
//                        left join worker_order as wo on case when wgr.user_type=1 then wo.worker_id=wgr.worker_id and wo.children_worker_id is null else wo.children_worker_id=wgr.worker_id end and wo.worker_order_status in {$worker_order_status} and wo.worker_repair_time between {$start_time} and {$end_time}",
//            'field' => 'wgr.worker_id, w.nickname, w.thumb, wgr.wait_appoint_order_number, wgr.wait_service_order_number, wgr.servicing_order_number, wgr.finish_order_number, wgr.user_type, count(wo.id) as month_finish_order_number',
//            'group' => 'wgr.worker_id',
//            'order' => 'wgr.user_type asc, wgr.audit_time desc',
//            'limit' => $this->page()
//        ]);
        $count = $relation_model->getNum([
            'alias' => 'wgr',
            'where' => $where,
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id'
        ]);
        $list = $relation_model->getList([
            'alias' => 'wgr',
            'where' => $where,
            'join'  => "left join worker as w on w.worker_id=wgr.worker_id",
            'field' => 'wgr.worker_id, w.nickname, w.thumb, wgr.wait_appoint_order_number, wgr.wait_service_order_number, wgr.servicing_order_number, wgr.finish_order_number, wgr.user_type',
            'order' => 'wgr.user_type asc, wgr.audit_time desc',
            'limit' => $this->page()
        ]);
        $worker_ids = implode(',', array_column($list, 'worker_id'));
        if (!empty($worker_ids)) {
            $start_time = strtotime(date('Y-m-1 00:00:00', time()));
            $end_time = strtotime(date('Y-m-'.date('t').'23:59:59',time()));
            $worker_order_status = implode(',', OrderService::getOrderCompleteInGroup());
            if ($list[0]['user_type'] == GroupService::WORKER_RELATION_GROUP_OWNER) {
                //如果第一个是群主
                $worker_order_infos = $order_model->getList([
                    'where' => [
                        'worker_group_id' => $worker_group_id,
                        'worker_id' => $list[0]['worker_id'],
                        'children_worker_id' => ['exp', 'is null'],
                        'worker_order_status' => ['in', $worker_order_status],
                        'worker_repair_time'  => ['between', [$start_time, $end_time]]
                    ],
                    'group' => 'worker_id',
                    'field' => 'worker_id, count(id) as month_finish_order_number',
                    'index' => 'worker_id'
                ]);
                $children_worker_ids = substr($worker_ids, (stripos($worker_ids, ',') + 1));
                $children_worker_ids = !empty($children_worker_ids) ? $children_worker_ids : '0';
            } else {
                $worker_order_infos = [];
                $children_worker_ids = $worker_ids;
            }
            $children_worker_order_infos = $order_model->getList([
                'where' => [
                    'worker_group_id' => $worker_group_id,
                    'children_worker_id' => ['in', $children_worker_ids],
                    'worker_order_status' => ['in', $worker_order_status],
                    'worker_repair_time'  => ['between', [$start_time, $end_time]]
                ],
                'index' => 'children_worker_id',
                'group' => 'children_worker_id',
                'field' => 'children_worker_id, count(id) as month_finish_order_number'
            ]);
            $order_infos = $worker_order_infos + $children_worker_order_infos;
            foreach ($list as &$v) {
//                $v['month_finish_order_number'] = GroupService::getWorkerMonthFinishOrderNumber($worker_group_id, $v['worker_id'], $v['user_type']);
                if (!empty($v['thumb'])) {
                    $v['thumb'] = Util::getServerFileUrl($v['thumb']);
                }
                $v['month_finish_order_number'] = !empty($order_infos[$v['worker_id']]['month_finish_order_number']) ? $order_infos[$v['worker_id']]['month_finish_order_number'] : '0';
            }
        }
        $group_info = BaseModel::getInstance('worker_group')->getOne($worker_group_id, 'group_name, worker_number');
        return [
            'group_name'        => $group_info['group_name'],
            'worker_number'     => $group_info['worker_number'],
            'group_worker_list' => $this->paginate($list, $count)
        ];
    }

    /*
     * 群详情
     */
    public function detail($worker_group_id, $user_id, $user_info)
    {
        $relation = BaseModel::getInstance('worker_group_relation')->getOne([
            'where' => [
                'worker_id' => $user_id,
                'worker_group_id' => $worker_group_id
            ]
        ]);
        if (!$relation) {
            $this->throwException(GroupService::GROUP_ERROR_CODE[$user_info['type']]);
        }
        $detail = BaseModel::getInstance('worker_group')->getOne([
            'alias' => 'wg',
            'where' => [
                'wg.id' => $worker_group_id
            ],
            'join'  => 'left join worker as w on w.worker_id=wg.owner_worker_id',
            'field' => 'wg.id, wg.group_no, wg.group_name, wg.owner_worker_id, wg.cp_owner_nickname as owner_worker_nickname, wg.cp_owner_telephone as owner_worker_telephone, w.thumb as owner_thumb, wg.worker_number, wg.worker_order_number, wg.wait_appoint_order_number, wg.wait_service_order_number, wg.servicing_order_number, wg.finish_order_number'
        ]);
        if (!empty($detail['owner_thumb'])) {
            $detail['owner_thumb'] = Util::getServerFileUrl($detail['owner_thumb']);
        }
        $detail['month_finish_order_number'] = GroupService::getWorkerMonthFinishOrderNumber($worker_group_id, $user_id, null);
        $detail['user_audit_time'] = $relation['audit_time'];
        $detail['user_group_achievement'] = [
            'user_finish_order_number'       => $relation['finish_order_number'],
            'user_month_finish_order_number' => (string)GroupService::getWorkerMonthFinishOrderNumber($worker_group_id, $user_id, $relation['user_type']),
            'user_conducting_order_number'   => (string)($relation['wait_appoint_order_number'] + $relation['wait_service_order_number'] + $relation['servicing_order_number']),
        ];
        return $detail;
    }

    /*
     * 移除技工
     */
    public function remove($worker_group_id, $worker_id, $user_id, $user_info)
    {
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);
        $relation_model = BaseModel::getInstance('worker_group_relation');
        $worker_relation_info = $relation_model->getOne([
            'where' => [
                'worker_id'       => $worker_id,
                'worker_group_id' => $worker_group_id,
                'status'          => GroupService::WORKER_STATUS_AUDIT_PASS,
                'user_type'       => GroupService::WORKER_RELATION_GROUP_MEMBER
            ]
        ]);
        if (!$worker_relation_info) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该技工不在群内，无法删除');
        }

        //判断技工是否还有负责的工单
        $handle_order_count = BaseModel::getInstance('worker_order')->getNum([
            'where' => [
                'worker_group_id'    => $worker_group_id,
                'children_worker_id' => $worker_id,
                'cancel_status'      => OrderService::CANCEL_TYPE_NULL,
                'audit_time'         => 0
            ]
        ]);
        if ($handle_order_count > 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该技工还有未完结工单，暂不可删除，请先改派工单');
        }

        M()->startTrans();

        $relation_model->update([
            'worker_id'       => $worker_id,
            'worker_group_id' => $worker_group_id
        ], [
            'status'    => GroupService::WORKER_STATUS_GROUP_CULL,
            'user_type' => GroupService::WORKER_RELATION_GROUP_NOT_IN_GROUP,
            'is_delete' => NOW_TIME
        ]);

        //操作记录
        $nickname = BaseModel::getInstance('worker')->getFieldVal([
            'worker_id' => $worker_id
        ], 'nickname');
        $record_id = GroupService::create($worker_group_id, GroupService::GROUP_RECORD_TYPE_REMOVE_FROM_GROUP, [
            'record_operator_id'   => $user_id,
            'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_OWNER,
            'operated_worker_id'   => $worker_id,
            'content' => '群主把'.$nickname.'剔出群'
        ]);

        //修改技工状态
        BaseModel::getInstance('worker')->update($worker_id, [
            'type' => GroupService::WORKER_TYPE_ORDINARY_WORKER
        ]);

        //群成员人数-1
        BaseModel::getInstance('worker_group')->setNumDec([
            'id' => $worker_group_id
        ], 'worker_number');

        //推送消息
        event(new GroupNewsEvent([
            'data_id' => $record_id,
            'type'    => GroupService::GROUP_RECORD_TYPE_REMOVE_FROM_GROUP
        ]));

        M()->commit();
    }

    /*
     * 技工完单统计
     */
    public function statisticsFinishOrders($worker_group_id, $request, $user_id, $user_info)
    {
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);

        $where['wgr.worker_group_id'] = $worker_group_id;
        $where['wgr.status'] = GroupService::WORKER_STATUS_AUDIT_PASS;
        if (isset($request['keyword']) && !empty($request['keyword'])) {
            $where['w.nickname'] = ['like', '%'.$request['keyword'].'%'];
        }

        $relation_model = BaseModel::getInstance('worker_group_relation');

        $count = $relation_model->getNum([
            'alias' => 'wgr',
            'where' => $where,
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id'
        ]);
        $list = $relation_model->getList([
            'alias' => 'wgr',
            'where' => $where,
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id',
            'field' => 'wgr.worker_id, w.nickname, w.thumb, wgr.user_type',
            'order' => 'wgr.user_type asc, wgr.audit_time desc',
            'limit' => $this->page()
        ]);
        foreach ($list as &$v) {
            $v['finish_order_number'] = GroupService::getWorkerMonthFinishOrderNumber($worker_group_id, $v['worker_id'], $v['user_type'], [
                'start_time' => $request['start_time'],
                'end_time'   => $request['end_time']
            ]);
            if (!empty($v['thumb'])) {
                $v['thumb'] = Util::getServerFileUrl($v['thumb']);
            }
        }
        return $this->paginate($list, $count);
    }

    /*
     * 技工审核列表
     */
    public function auditWorkerList($worker_group_id, $user_id, $user_info)
    {
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);

        $relation_model = BaseModel::getInstance('worker_group_relation');

        $where['wgr.worker_group_id'] = $worker_group_id;
        $where['wgr.user_type'] = ['neq', GroupService::WORKER_RELATION_GROUP_OWNER];
        $count = $relation_model->getNum([
            'alias' => 'wgr',
            'where' => $where,
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id'
        ]);
        $list = $relation_model->getList([
            'alias' => 'wgr',
            'where' => $where,
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id',
            'field' => 'wgr.worker_id, w.nickname, w.thumb, w.worker_telephone, wgr.status',
            'order' => 'wgr.audit_time desc',
            'limit' => $this->page()
        ]);
        foreach ($list as $k => $v) {
            if (!empty($v['thumb'])) {
                $list[$k]['thumb'] = Util::getServerFileUrl($v['thumb']);
            }
        }
        return $this->paginate($list, $count);
    }

    /*
     * 群主派发工单
     */
    public function distributeOrder($worker_group_id, $request, $user_id, $user_info)
    {
        $this->checkIsGroupOwner($worker_group_id, $user_id, $user_info['type'], $user_info['group_apply_status']);
        if (!GroupService::checkWorkerInGroup($worker_group_id, $request['worker_id'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该技工不在群内，无法派单给该技工');
        }
        $order_info = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'id'              => $request['order_id'],
                'worker_group_id' => $worker_group_id,
                'cancel_status'   => OrderService::CANCEL_TYPE_NULL
            ],
            'field' => 'worker_order_status, worker_id, children_worker_id'
        ]);
        if (!in_array($order_info['worker_order_status'], OrderService::getOrderStatusUnsettled())) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单状态不支持派发');
        }
        if ($order_info['children_worker_id'] == $request['worker_id']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已由该技工负责');
        }

        M()->startTrans();

        $handle_worker_id = $request['worker_id'];
        if ($order_info['worker_id'] == $request['worker_id']) {
            //如果把工单派回给群主，则清空children_worker_id
            $handle_worker_id = null;
        }
        BaseModel::getInstance('worker_order')->update([
            'id' => $request['order_id'],
            'worker_group_id' => $worker_group_id
        ], [
            'children_worker_id' => $handle_worker_id
        ]);

        //操作记录
        $nickname = BaseModel::getInstance('worker')->getFieldVal([
            'worker_id' => $request['worker_id']
        ], 'nickname');
        $content = '将工单派给'.$nickname.'师傅处理';
        OrderOperationRecordService::create($request['order_id'], OrderOperationRecordService::WORKER_OWNER_DISTRIBUTE_ORDER, [
            'operator_id' => $user_id,
            'content_replace' => [
                'content' => $content
            ]
        ]);

        //群内工单修改数量
        event(new UpdateOrderNumberEvent([
            'worker_order_id'              => $request['order_id'],
            'operation_type'               => OrderOperationRecordService::WORKER_OWNER_DISTRIBUTE_ORDER,
            'original_worker_id'           => null,
            'original_children_worker_id'  => $order_info['children_worker_id'],
            'original_worker_order_status' => null
        ]));

        // 技工信誉记录处理
        // 删除原有技工的信誉记录
        $worker_order_reputation_model = BaseModel::getInstance('worker_order_reputation');
        if ($request['worker_id'] != $order_info['children_worker_id']) {
            $worker_order_reputation_model->remove([
                'worker_order_id' => $request['order_id'],
                'worker_id'       => $order_info['children_worker_id'],
            ]);
        }
        // 修改技工信誉记录
        $worker_order_reputation = $worker_order_reputation_model->getOne([
            'worker_order_id' => $request['order_id'],
            'worker_id'       => $request['worker_id'],
        ], 'id');
        if ($worker_order_reputation && $order_info['worker_id'] != $request['worker_id']) {
            $worker_order_reputation_model->update($worker_order_reputation['id'], [
                'is_complete' => 0,
                'is_return'   => 0,
            ]);
        } elseif ($order_info['worker_id'] != $request['worker_id']) {
            $worker_order_reputation_model->insert([
                'worker_order_id' => $request['order_id'],
                'worker_id'       => $request['worker_id'],
                'addtime'         => NOW_TIME,
                'cp_worker_type'  => GroupService::WORKER_TYPE_GROUP_MEMBER
            ]);
        }

        //更新工单fee表技工分成比例
        $worker_proportion = BaseModel::getInstance('worker_group_relation')->getFieldVal([
            'worker_id' => $request['worker_id'],
            'worker_group_id' => $order_info['worker_group_id']
        ], 'worker_proportion');
        BaseModel::getInstance('worker_order_fee')->update([
            'worker_order_id' => $request['order_id']
        ], [
            'cp_worker_proportion' => !empty($handle_worker_id) ? $worker_proportion : null
        ]);

        M()->commit();
    }

    /*
     * 系统自动审核技工
     */
    public function AutoAuditWorker()
    {
        $relation_model = BaseModel::getInstance('worker_group_relation');
        $list = $relation_model->getList([
            'where' => [
                'status' => GroupService::WORKER_STATUS_AUDITING,
                'apply_time' => ['lt', (NOW_TIME - 7 * 86400)]
            ],
            'field' => 'id, worker_group_id, worker_id'
        ]);
        $ids = array_column($list, 'id');
        $worker_ids = array_column($list, 'worker_id');
        if (!empty($ids)) {
            $ids = implode(',', $ids);
            $worker_ids = implode(',', $worker_ids);
            //更新关联状态值
            M()->startTrans();
            $relation_model->update([
                'id' => ['in', $ids]
            ], [
                'status' => GroupService::WORKER_STATUS_AUTO_AUDIT_NOT_PASS,
                'audit_time' => NOW_TIME,
                'audit_remark' => '群管理员7天未审核，系统自动审核不通过'
            ]);
            //更新技工状态
            BaseModel::getInstance('worker')->update([
                'worker_id' => ['in', $worker_ids]
            ], [
                'type' => GroupService::WORKER_TYPE_ORDINARY_WORKER,
                'group_apply_status' => GroupService::GROUP_APPLY_STATUS_NULL
            ]);
            //更新操作记录
            foreach ($list as $v) {
                $record_id = GroupService::create($v['worker_group_id'], GroupService::GROUP_RECORD_TYPE_SYSTEM_AUTO_AUDIT, [
                    'record_operator_id' => 0,
                    'operated_worker_id' => $v['worker_id'],
                    'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_SYSTEM,
                    'content' => '群管理员7天未审核，系统自动审核不通过'
                ]);
                //推送
                event(new GroupNewsEvent([
                    'data_id' => $record_id,
                    'type' => GroupService::GROUP_RECORD_TYPE_SYSTEM_AUTO_AUDIT
                ]));
            }
            M()->commit();
        }
    }

    /*
     * 群号检索
     */
    public function checkGroupNo($group_no)
    {
        $detail = BaseModel::getInstance('worker_group')->getOne([
            'alias' => 'wg',
            'where' => [
                'wg.group_no' => $group_no
            ],
            'join'  => 'left join worker as w on w.worker_id=wg.owner_worker_id',
            'field' => 'wg.id, wg.group_no, wg.group_name, wg.owner_worker_id, wg.cp_owner_nickname as owner_worker_nickname, wg.cp_owner_telephone as owner_worker_telephone, w.thumb as owner_thumb, w.worker_address'
        ]);
        if (empty($detail)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '没有找到该网点群号，请核对后重新输入');
        }
        if (!empty($detail['owner_thumb'])) {
            $detail['owner_thumb'] = Util::getServerFileUrl($detail['owner_thumb']);
        }
        $worker_address = explode('-', $detail['worker_address']);
        $detail['province_name'] = $worker_address[0] ?? null;
        $detail['city_name'] = $worker_address[1] ?? null;
        $detail['area_name'] = $worker_address[2] ?? null;
        return $detail;
    }

    /*
     * 群内技工详情
     */
    public function groupWorkerInfo($worker_group_id, $worker_id)
    {
        return BaseModel::getInstance('worker_group_relation')->getOne([
            'where' => [
                'worker_group_id' => $worker_group_id,
                'worker_id' => $worker_id
            ],
            'field' => 'worker_id, wait_appoint_order_number, wait_service_order_number, servicing_order_number, finish_order_number'
        ]);
    }

    /*
     * 恢复创建群数据
     */
    public function groupRecover($user_id)
    {
        $detail = BaseModel::getInstance('worker_group')->getOne([
            'where' => [
                'owner_worker_id' => $user_id,
            ],
            'field' => 'group_name, create_reason, business_license as business_license_base_url, store_images',
            'order' => 'create_time desc'
        ]);
        $detail['business_license_url'] = Util::getServerFileUrl($detail['business_license_base_url']);
        $detail['store_images_url'] = [];
        $detail['store_images_base_url'] = [];
        if (!empty($detail['store_images'])) {
            if (strpos($detail['store_images'], 'quot;')) {
                $detail['store_images'] = html_entity_decode($detail['store_images']);
            }
            $store_images = json_decode($detail['store_images'], true);
            if (!empty($store_images)) {
                foreach ($store_images as $v) {
                    $detail['store_images_url'][] = Util::getServerFileUrl($v['url']);
                    $detail['store_images_base_url'][] = $v['url'];
                }
            }
            unset($detail['store_images']);
        }
        return $detail;
    }

    /*
     * fix-修复服务已完成工单数错误
     */
    public function fixFinishOrderNumber()
    {
        $group_model = BaseModel::getInstance('worker_group');
        $group_relation_model = BaseModel::getInstance('worker_group_relation');
        $order_model = BaseModel::getInstance('worker_order');

        $group_list = $group_model->getList([
            'where' => [
                'status' => GroupService::GROUP_STATUS_AUDIT_PASS,
                'is_delete' => 0
            ],
            'field' => 'id'
        ]);
        if (!empty($group_list)) {
            $group_ids = implode(',', array_column($group_list, 'id'));

            $servicing_order_numbers = $order_model->getList([
                'where' => [
                    'worker_group_id' => ['in', $group_ids],
                    'worker_order_status' => ['in', [OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]],
                    'cancel_status'  => 0
                ],
                'index' => 'worker_group_id',
                'group' => 'worker_group_id',
                'field' => 'worker_group_id, count(id) as servicing_order_number'
            ]);
            $finish_order_numbers = $order_model->getList([
                'where' => [
                    'worker_group_id' => ['in', $group_ids],
                    'worker_order_status' => ['in', OrderService::getOrderCompleteInGroup()],
                    'cancel_status'  => 0
                ],
                'index' => 'worker_group_id',
                'group' => 'worker_group_id',
                'field' => 'worker_group_id, count(id) as finish_order_number'
            ]);

            foreach ($group_list as $v) {
                $group_model->update([
                    'id' => $v['id']
                ], [
                    'servicing_order_number' => !empty($servicing_order_numbers[$v['id']]['servicing_order_number']) ? $servicing_order_numbers[$v['id']]['servicing_order_number'] : 0,
                    'finish_order_number'    => !empty($finish_order_numbers[$v['id']]['finish_order_number']) ? $finish_order_numbers[$v['id']]['finish_order_number'] : 0,
                ]);

                //获取群内的技工列表
                $worker_list = $group_relation_model->getList([
                    'where' => [
                        'worker_group_id' => $v['id'],
                        'status' => GroupService::WORKER_STATUS_AUDIT_PASS,
                        'is_delete' => 0,
                    ],
                    'field' => 'id, user_type, worker_id',
                    'order' => 'user_type'
                ]);
                if (!empty($worker_list)) {
                    if ($worker_list[0]['user_type'] == GroupService::WORKER_RELATION_GROUP_OWNER) {
                        $worker_servicing_order_number = $order_model->getNum([
                            'where' => [
                                'worker_id' => $worker_list[0]['worker_id'],
                                'worker_group_id' => $v['id'],
                                'worker_order_status' => ['in', [OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]],
                                'cancel_status'  => 0,
                                'children_worker_id' => ['exp', 'is null']
                            ],
                        ]);
                        $worker_finish_order_number = $order_model->getNum([
                            'where' => [
                                'worker_id' => $worker_list[0]['worker_id'],
                                'worker_group_id' => $v['id'],
                                'worker_order_status' => ['in', OrderService::getOrderCompleteInGroup()],
                                'cancel_status'  => 0,
                                'children_worker_id' => ['exp', 'is null']
                            ]
                        ]);
                        $group_relation_model->update([
                            'id' => $worker_list[0]['id']
                        ], [
                            'servicing_order_number' => !empty($worker_servicing_order_number) ? $worker_servicing_order_number : 0,
                            'finish_order_number'    => !empty($worker_finish_order_number) ? $worker_finish_order_number : 0,
                        ]);

                        $worker_ids = implode(',', array_column($worker_list, 'worker_id'));
                        $children_worker_ids = substr($worker_ids, (stripos($worker_ids, ',') + 1));
                    }
                    if (!empty($children_worker_ids)) {
                        $children_worker_servicing_order_numbers = $order_model->getList([
                            'where' => [
                                'worker_group_id' => $v['id'],
                                'worker_order_status' => ['in', [OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]],
                                'cancel_status'  => 0,
                                'children_worker_id' => ['in', $children_worker_ids]
                            ],
                            'index' => 'children_worker_id',
                            'group' => 'children_worker_id',
                            'field' => 'children_worker_id, count(id) as servicing_order_number'
                        ]);
                        $children_worker_finish_order_numbers = $order_model->getList([
                            'where' => [
                                'worker_group_id' => $v['id'],
                                'worker_order_status' => ['in', OrderService::getOrderCompleteInGroup()],
                                'cancel_status'  => 0,
                                'children_worker_id' => ['in', $children_worker_ids]
                            ],
                            'index' => 'children_worker_id',
                            'group' => 'children_worker_id',
                            'field' => 'children_worker_id, count(id) as finish_order_number'
                        ]);
                        foreach ($worker_list as $value) {
                            $group_relation_model->update([
                                'id' => $value['id'],
                                'user_type' => GroupService::WORKER_RELATION_GROUP_MEMBER
                            ], [
                                'servicing_order_number' => !empty($children_worker_servicing_order_numbers[$value['worker_id']]['servicing_order_number']) ? $children_worker_servicing_order_numbers[$value['worker_id']]['servicing_order_number'] : 0,
                                'finish_order_number'    => !empty($children_worker_finish_order_numbers[$value['worker_id']]['finish_order_number']) ? $children_worker_finish_order_numbers[$value['worker_id']]['finish_order_number'] : 0,
                            ]);
                        }
                    }

                }
            }

        }
    }

}