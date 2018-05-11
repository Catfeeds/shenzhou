<?php
/**
 * Created by PhpStorm.
 * User: 嘉诚
 * Date: 2018/1/25
 * Time: 11:35
 */

namespace Admin\Logic;

use Common\Common\Repositories\Events\GroupNewsEvent;
use Common\Common\Service\GroupService;
use Common\Common\Service\OrderService;
use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Library\Common\Util;

class GroupLogic extends BaseLogic
{
    /*
     * 群列表
     */
    public function groupList($request, $user_id)
    {
        $where = [];
        if (!empty($request['group_name'])) {
            $where['wg.group_name'] = ['like', '%'.$request['group_name'].'%'];
        }
        if (!empty($request['nickname'])) {
            $where['wg.cp_owner_nickname'] = ['like', '%'.$request['nickname'].'%'];
        }
        if (!empty($request['worker_telephone'])) {
            $where['wg.cp_owner_telephone'] = ['like', '%'.$request['worker_telephone'].'%'];
        }
        if (!empty($request['province_id'])) {
            $where_worker['worker_area_ids'] = ['like', $request['province_id'].'%'];
        }
        if (!empty($request['city_id'])) {
            $where_worker['worker_area_ids'] = ['like', $request['province_id'].','.$request['city_id'].'%'];
        }
        if (!empty($request['area_id'])) {
            $where_worker['worker_area_ids'] = ['like', $request['province_id'].','.$request['city_id'].','.$request['area_id'].'%'];
        }
        if (!empty($where_worker)) {
            $worker_ids = BaseModel::getInstance('worker')->getFieldVal($where_worker, 'worker_id', true);
            if (!empty($worker_ids)) {
                $where['wg.owner_worker_id'] = ['in', implode(',', $worker_ids)];
            }
        }
        if (!empty($request['audit_status'])) {
            $where['wg.status'] = $request['audit_status'];
        }
        if (!empty($request['worker_number_min'])) {
            $where['wg.worker_number'] = ['egt', $request['worker_number_min']];
        }
        if (!empty($request['worker_number_max'])) {
            $where['wg.worker_number'] = ['elt', $request['worker_number_max']];
        }
        if (!empty($request['worker_number_min']) && !empty($request['worker_number_max'])) {
            $where['wg.worker_number'] = ['between', [$request['worker_number_min'], $request['worker_number_max']]];
        }
        if (!empty($request['finish_order_number_min'])) {
            $where['wg.finish_order_number'] = ['egt', $request['finish_order_number_min']];
        }
        if (!empty($request['finish_order_number_max'])) {
            $where['wg.finish_order_number'] = ['elt', $request['finish_order_number_max']];
        }
        if (!empty($request['finish_order_number_min']) && !empty($request['finish_order_number_max'])) {
            $where['wg.finish_order_number'] = ['between', [$request['finish_order_number_min'], $request['finish_order_number_max']]];
        }
        $group_model = BaseModel::getInstance('worker_group');
        $count = $group_model->getNum([
            'alias' => 'wg',
            'where' => $where
        ]);
        $list  = $group_model->getList([
            'alias' => 'wg',
            'where' => $where,
            'join'  => 'left join worker as w on w.worker_id=wg.owner_worker_id',
            'field' => 'wg.id, wg.group_no, wg.group_name, wg.owner_worker_id, wg.cp_owner_nickname, wg.cp_owner_telephone, wg.worker_number, w.worker_address as group_address, wg.status as audit_status, wg.reputation_total, wg.worker_order_number, wg.finish_order_number, (wg.wait_appoint_order_number + wg.wait_service_order_number + wg.servicing_order_number) as conducting_order_number',
            'order' => 'wg.create_time desc',
            'limit' => $this->page()
        ]);
        return [
            'list'  => $list,
            'count' => $count
        ];
    }

    /*
     * 群成员列表
     */
    public function groupWorkerList($worker_group_id, $user_id)
    {
        $relation_model = BaseModel::getInstance('worker_group_relation');
        $count = $relation_model->getNum([
            'worker_group_id' => $worker_group_id
        ]);
        $list  = $relation_model->getList([
            'alias' => 'wgr',
            'where' => [
                'wgr.worker_group_id' => $worker_group_id
            ],
            'join'  => 'left join worker as w on w.worker_id=wgr.worker_id',
            'field' => 'wgr.id, wgr.worker_id, w.nickname, w.worker_telephone, wgr.apply_time, wgr.user_type, wgr.status as audit_status, wgr.wait_appoint_order_number, wgr.wait_service_order_number, wgr.servicing_order_number, wgr.finish_order_number',
            'order' => 'wgr.user_type asc, wgr.apply_time desc',
            'limit' => $this->page()
        ]);
        return [
            'list'  => $list,
            'count' => $count
        ];
    }

    /*
     * 网点群详情
     */
    public function detail($worker_group_id, $user_id)
    {
        $detail = BaseModel::getInstance('worker_group')->getOne($worker_group_id);
        if ($detail) {
            if (!empty($detail['business_license'])) {
                $detail['business_license'] = Util::getServerFileUrl($detail['business_license']);
            }
            if (!empty($detail['store_images'])) {
                if (strpos($detail['store_images'], 'quot;')) {
                    $detail['store_images'] = html_entity_decode($detail['store_images']);
                }
                $store_images = json_decode($detail['store_images'], true);
                unset($detail['store_images']);
                foreach ($store_images as $v) {
                    $detail['store_images'][] = Util::getServerFileUrl($v['url']);
                }
            }
            $detail['month_finish_order_number'] = GroupService::getWorkerMonthFinishOrderNumber($worker_group_id, $detail['owner_worker_id'], 0);
        }
        return $detail;
    }

    /*
     * 群审核
     */
    public function audit($worker_group_id, $request, $user_id)
    {
        $group_model = BaseModel::getInstance('worker_group');
        $group_info = $group_model->getOne($worker_group_id);
        if ($group_info['status'] != '1') {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该群无法进行审核');
        }
        if (!in_array($request['is_pass'], ['0', '1'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核状态不正确');
        }
        M()->startTrans();
        if ($request['is_pass'] == '1') {
            $status      = GroupService::GROUP_STATUS_AUDIT_PASS;
            $group_no    = GroupService::getGroupNo();
            $record_type = GroupService::GROUP_RECORD_TYPE_CREATE_GROUP_PASS;
            $is_pass     = '通过';
            $type        = GroupService::WORKER_TYPE_GROUP_OWNER;
            //添加群主与群的关联
            BaseModel::getInstance('worker_group_relation')->insert([
                'worker_id' => $group_info['owner_worker_id'],
                'worker_group_id' => $worker_group_id,
                'user_type' => GroupService::WORKER_RELATION_GROUP_OWNER,
                'status'    => GroupService::WORKER_STATUS_AUDIT_PASS,
                'create_time' => NOW_TIME,
                'apply_time'  => NOW_TIME,
                'audit_time'  => NOW_TIME,
            ]);
        } else{
            $status = GroupService::GROUP_STATUS_AUDIT_NOT_PASS;
            $record_type = GroupService::GROUP_RECORD_TYPE_CREATE_GROUP_NO_PASS;
            $is_pass     = '不通过';
            $type        = GroupService::WORKER_TYPE_ORDINARY_WORKER;
        }

        //更新群状态
        $group_model->update([
            'id' => $worker_group_id
        ], [
            'group_no'     => !empty($group_no) ? $group_no : null,
            'audit_time'   => NOW_TIME,
            'audit_remark' => $request['audit_remark'],
            'status'       => $status,
        ]);

        //操作记录
        $admin = BaseModel::getInstance('admin')->getFieldVal([
            'id' => $user_id
        ], 'nickout');
        $record_id = GroupService::create($worker_group_id, $record_type, [
            'record_operator_id'   => $user_id,
            'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_ADMIN,
            'operated_worker_id'   => $group_info['owner_worker_id'],
            'content' => '客服'.$admin.'审核群“'.$group_info['group_name'].'”'.$is_pass
        ]);

        //修改技工状态
        BaseModel::getInstance('worker')->update($group_info['owner_worker_id'], [
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
     * 群信息修改
     */
    public function update($worker_group_id, $request, $user_id)
    {
        if (empty($request['group_name'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '群名称不能为空');
        }
        $group_model = BaseModel::getInstance('worker_group');
        $group_info = $group_model->getOne($worker_group_id);
        if (!$group_info) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '没有找到对应群');
        }
        M()->startTrans();

        //更新群信息
        $group_model->update([
            'id' => $worker_group_id
        ], [
            'group_name' => $request['group_name']
        ]);

        //操作记录
        $admin = BaseModel::getInstance('admin')->getFieldVal([
            'id' => $user_id
        ], 'nickout');
        GroupService::create($worker_group_id, GroupService::GROUP_RECORD_TYPE_UPDATE_GROUP_NAME, [
            'record_operator_id'   => $user_id,
            'record_operator_type' => GroupService::GROUP_RECORD_OPERATOR_TYPE_BY_ADMIN,
            'content' => '客服'.$admin.'修改群名称为“'.$request['group_name'].'”'
        ]);

        M()->commit();
    }
}