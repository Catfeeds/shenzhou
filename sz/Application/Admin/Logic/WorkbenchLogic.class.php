<?php
/**
 * File: WorkbenchLogic.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/12
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminGroupCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AuthService;
use Common\Common\Service\CostService;
use Common\Common\Service\OrderMessageService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerOrderAppointRecordService;

class WorkbenchLogic extends BaseLogic
{
    protected function getAdminIds($param)
    {
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        $admin_role_ids = AdminCacheModel::getRelation($admin_id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
        $receive_type = 0; // 客服接单类型
        $is_manager = false; // 主管接单类型
        $field = 'level,type,is_disable,is_delete';
        foreach ($admin_role_ids as $admin_role_id) {
            $admin_role = AdminRoleCacheModel::getOneOrFail($admin_role_id, $field);
            if (
                AdminRoleService::IS_DISABLE_YES == $admin_role['is_disable'] ||
                AdminRoleService::IS_DELETE_YES == $admin_role['is_delete']
            ) {
                continue;
            }
            $receive_type |= $admin_role['type'];
            if (AdminRoleService::LEVEL_CHARGE_ADMIN == $admin_role['level'] || AdminRoleService::LEVEL_GROUP_ADMIN == $admin_role['level']) {
                $is_manager = true;
            }
        }

        $is_super_admin = isSuperAdministrator($admin_id);

        if ($is_super_admin) {
            $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE | AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
        } elseif ($is_manager) {
            //客服组长只可以查看核实 派单 回访
            //财务组长只可以查看财务
            if (AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR & $receive_type) {
                $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
            } else {
                $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;
            }
        }

        $admin_ids = [];
        $group_id = $param['group_id'];
        $query_admin_id = $param['admin_id'];
        if ($query_admin_id == $admin_id) {
            $admin_ids = [$admin_id];
        } else {
            if ($is_super_admin || $is_manager) {
                $admin_group_logic = (new AdminGroupLogic());
                $admin_group_ids = $admin_group_logic->getManageGroupIds(AuthService::getAuthModel()
                    ->getPrimaryValue());
                $admin_group_ids = empty($admin_group_ids) ? [] : $admin_group_ids;

                if ($group_id > 0) {
                    if (!in_array($group_id, $admin_group_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有此管理组权限');
                    }

                    $group_admin_ids = $admin_group_logic->getGroupAdmins([$group_id]);
                    $group_admin_ids = empty($group_admin_ids) ? [] : $group_admin_ids;

                    if ($query_admin_id > 0) {
                        if (!in_array($query_admin_id, $group_admin_ids)) {
                            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '查询客服不在此管理组');
                        }
                        $admin_ids = [$query_admin_id];
                    } else {
                        $admin_ids = $group_admin_ids;
                    }

                } else {
                    //获取相关的组成员
                    $group_admin_ids = $admin_group_logic->getGroupAdmins($admin_group_ids);
                    $group_admin_ids = empty($group_admin_ids) ? [] : $group_admin_ids;
                    $admin_ids = $group_admin_ids;

                }
            } else {
                $admin_ids = [$admin_id];
            }
        }

        $admin_ids = empty($admin_ids) ? [] : array_unique($admin_ids);

        return [
            'admin_ids'      => $admin_ids,
            'receive_type'   => $receive_type,
            'is_super_admin' => $is_super_admin,
            'is_manager'     => $is_manager,
        ];
    }

    public function getList($param)
    {
        $limit = $this->page();

        $query_type = $param['query_type'];

        $admin_info = $this->getAdminIds($param);
        $admin_ids = $admin_info['admin_ids'];
        $receive_type = $admin_info['receive_type'];
        $is_super_admin = $admin_info['is_super_admin'];
        $is_manager = $admin_info['is_manager'];

        $data = [];
        switch ($query_type) {
            case 1:
                {//过期未核实
                    $data = $this->getOrderNoCheckExceed($admin_ids, $limit);
                }
                break;
            case 2:
                {//留言未回复
                    $data = $this->getMessageNoReply($admin_ids, $limit, $receive_type);
                }
                break;
            case 3:
                {//投诉单未处理
                    $data = $this->getComplaintNoReply($admin_ids, $limit, $receive_type);
                }
                break;
            case 4:
                {//过期未派发
                    $data = $this->getOrderNoDistributeExceed($admin_ids, $limit);
                }
                break;
            case 5:
                {//过期未跟进
                    $data = $this->getOrderNoFollowExceed($admin_ids, $limit);
                }
                break;
            case 6:
                {//过期未预约
                    $data = $this->getOrderNoAppointExceed($admin_ids, $limit);
                }
                break;
            case 7:
                {//过期未上门
                    $data = $this->getOrderNoVisitExceed($admin_ids, $limit);
                }
                break;
            case 8:
                {//客服过期未审核(配件单)
                    //客服过期未审核
                    $data = $this->getAccessoryAdminNoCheckExceed($admin_ids, $limit);
                }
                break;
            case 9:
                {//厂家过期未审核(配件单)
                    $data = $this->getAccessoryFactoryNoCheckExceed($admin_ids, $limit);
                }
                break;
            case 10:
                {//过期未发件(配件单)
                    $data = $this->getAccessoryFactorySendExceed($admin_ids, $limit);
                }
                break;
            case 11:
                {//过期未返件(配件单)
                    $data = $this->getAccessoryWorkerSendBackExceed($admin_ids, $limit);
                }
                break;
            case 12:
                {//客服过期未审核(费用单)
                    $data = $this->getCostAdminNoCheckExceed($admin_ids, $limit);
                }
                break;
            case 13:
                {//厂家过期未审核(费用单)
                    $data = $this->getCostFactoryNoCheckExceed($admin_ids, $limit);
                }
                break;
            case 14:
                {//回访客服退回
                    $data = $this->getOrderReturneeReturnBack($admin_ids, $limit);
                }
                break;
            case 15:
                {//过期未回访
                    $data = $this->getOrderNoReturnExceed($admin_ids, $limit);
                }
                break;
            case 16:
                {//财务退回
                    $data = $this->getOrderAuditorReturnBack($admin_ids, $limit);
                }
                break;
            case 17:
                {//过期未审核
                    $data = $this->getOrderNoAuditExceed($admin_ids, $limit);
                }
                break;
            case 18:
                {//厂家退回
                    $data = $this->getOrderFactoryReturnBack($admin_ids, $limit, $receive_type);
                }
                break;

            default :
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常查询项');
        }

        $list = $data['list'];
        $cnt = $data['cnt'];

        $order_ids = empty($list) ? '-1' : array_column($list, 'id');

        $operations = BaseModel::getInstance('worker_order_operation_record')
            ->getList([
                'field' => 'worker_order_id,create_time,content',
                'where' => ['worker_order_id' => ['IN', $order_ids]],
                'order' => 'id DESC',
            ]);
        $worker_order_id_operation_record_map = [];
        foreach ($operations as $item) {
            $worker_order_id_operation_record_map[$item['worker_order_id']][] = $item;
        }

        foreach ($list as $key => $val) {
            $checker_id = $val['checker_id'];
            $distributor_id = $val['distributor_id'];
            $auditor_id = $val['auditor_id'];
            $returnee_id = $val['returnee_id'];
            $worker_order_id = $val['id'];

            $checker = AdminCacheModel::getOne($checker_id, 'nickout');
            $distributor = AdminCacheModel::getOne($distributor_id, 'nickout');
            $returnee = AdminCacheModel::getOne($returnee_id, 'nickout');
            $auditor = AdminCacheModel::getOne($auditor_id, 'nickout');

            $val['admin'] = [
                'checker'     => null,
                'distributor' => null,
                'returnee'    => null,
                'auditor'     => null,
            ];

            if ($is_manager || $is_super_admin) {
                $val['admin'] = [
                    'checker'     => empty($checker) ? null : $checker['nickout'],
                    'distributor' => empty($distributor) ? null : $distributor['nickout'],
                    'returnee'    => empty($returnee) ? null : $returnee['nickout'],
                    'auditor'     => empty($auditor) ? null : $auditor['nickout'],
                ];
            }

            $val['operations'] = isset($worker_order_id_operation_record_map[$worker_order_id]) ? $worker_order_id_operation_record_map[$worker_order_id] : null;

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];

    }

    /**
     * 过期未核实
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoCheckExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;
        $config = (new WorkbenchConfigLogic())->getList();
        $checker_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_check']);
        $where = [
            'checker_receive_time' => ['lt', $checker_receive_time_deadline],
            'worker_order_status'  => OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
            'cancel_status'        => OrderService::CANCEL_TYPE_NULL,
            'checker_id'           => ['in', $admin_ids],
        ];

        $field = 'id,checker_receive_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        foreach ($list as $key => $val) {
            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_admin_check'] * 60);

            $list[$key] = $val;
        }

        $cnt = $model->getNum($where);

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
    }

    /**
     * 留言未回复
     *
     * @param $admin_ids
     * @param $limit
     * @param $receive_type
     *
     * @return array
     */
    protected function getMessageNoReply($admin_ids, $limit, $receive_type)
    {
        $admin_ids_str = empty($admin_ids) ? '-1' : implode(',', $admin_ids);

        $message_model = BaseModel::getInstance('worker_order_message');

        $sub_query = $message_model->field('worker_order_id')
            ->group('worker_order_id')
            ->buildSql();

        $condition = [];
        if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER & $receive_type) {
            //核实
            $condition[] = '(worker_order_status in (' . implode(',', [
                    OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
                    OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
                ]) . ') and cancel_status in (' . implode(',', [
                    OrderService::CANCEL_TYPE_NULL,
                ]) . ") and checker_id in({$admin_ids_str}))";
        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $receive_type) {
            //派单
            $condition[] = '(worker_order_status in (' . implode(',', [
                    OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
                    OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                    OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
                    OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
                    OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                    OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                ]) . ') and cancel_status in (' . implode(',', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]) . ") and distributor_id in ({$admin_ids_str}))";
        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE & $receive_type) {
            //回访
            $condition[] = '(worker_order_status in (' . implode(',', [
                    OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                ]) . ') and cancel_status in (' . implode(',', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]) . ") and returnee_id in ({$admin_ids_str}))";
        }
        if (empty($condition)) {
            $where['id'] = -1;
        } else {
            $where['_string'] = implode(' or ', $condition);
            $where['id'] = ['exp', "in ({$sub_query})"];
        }

        $order_model = BaseModel::getInstance('worker_order');
        $worker_order_ids_sub_query = $order_model->field('id')
            ->where($where)->buildSql();
        $where = [
            'worker_order_id' => ['exp', "in({$worker_order_ids_sub_query})"],
            'id'              => ['exp', "in (select max(id) from worker_order_message group by worker_order_id)"],
            'add_type'        => ['in', [
                OrderMessageService::ADD_TYPE_FACTORY,
                OrderMessageService::ADD_TYPE_FACTORY_ADMIN,
            ]],
            'create_time'     => ['egt', strtotime('20180401')], // 留言只显示2018.4.1后数据
        ];
        $opts = [
            'field' => 'worker_order_id as id,create_time as prompt_time',
            'where' => $where,
            'order' => 'create_time',
            'limit' => $limit,
        ];
        $list = $message_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $message_model->getNum($where),
        ];

    }

    /**
     * 投诉未处理
     *
     * @param $admin_ids
     * @param $limit
     * @param $receive_type
     *
     * @return array
     */
    protected function getComplaintNoReply($admin_ids, $limit, $receive_type)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $complaint_model = BaseModel::getInstance('worker_order_complaint');
        $where = [
            'replier_id' => ['in', $admin_ids],
            'reply_time' => 0,
        ];
        $opts = [
            'field' => 'worker_order_id as id,min(create_time) as prompt_time',
            'where' => $where,
            'order' => 'prompt_time',
            'group' => 'worker_order_id',
            'limit' => $limit,
        ];
        $list = $complaint_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $complaint_model->getNum($where, 'distinct worker_order_id'),
        ];
    }

    /**
     * 过期未派发
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoDistributeExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();
        $distributor_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_distribute']);
        $where = [
            'distributor_receive_time' => ['lt', $distributor_receive_time_deadline],
            'worker_order_status'      => OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
            'cancel_status'            => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id'           => ['in', $admin_ids],
        ];

        $field = 'id,distributor_receive_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        foreach ($list as $key => $val) {
            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_admin_distribute'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    /**
     * 过期未跟进
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoFollowExceed($admin_ids, $limit)
    {
        return [
            'list' => [],
            'cnt'  => 0,
        ];
    }

    /**
     * 过期未预约
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoAppointExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();
        $worker_appoint_deadline = $this->getExceedTimestamp($config['exceed_worker_appoint']);
        $where = [
            '_string'             => "worker_receive_time+extend_appoint_time*3600<{$worker_appoint_deadline}",
            'worker_order_status' => [
                OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
            ],
            'cancel_status'       => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id'      => ['in', $admin_ids],
        ];

        $field = 'id,worker_receive_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        foreach ($list as $key => $val) {
            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_worker_appoint'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    /**
     * 过期未上门
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoVisitExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $worker_visit_deadline = $this->getExceedTimestamp($config['exceed_worker_visit']);
        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'worker_order_status' => ['in', [
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                ]],
                'cancel_status'       => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id'      => ['in', $admin_ids],
            ])->buildSql();

        $appoint_model = BaseModel::getInstance('worker_order_appoint_record');
        $sub_query = $appoint_model->field('max(id)')->where([
            '_string' => "worker_order_id in ({$sub_query})",
        ])->group('worker_order_id')->buildSql();

        $where = [
            'appoint_status' => ['in', [
                WorkerOrderAppointRecordService::STATUS_WAIT_WORKER_SIGN_IN,
                WorkerOrderAppointRecordService::STATUS_EDIT_APPOINT_TIME,
                WorkerOrderAppointRecordService::STATUS_APPOINT_AGAIN_AND_WAIT,
            ]],
            'appoint_time'   => ['lt', $worker_visit_deadline],
            'is_over'        => WorkerOrderAppointRecordService::IS_OVER_NO,
            'is_sign_in'     => WorkerOrderAppointRecordService::SIGN_IN_DEFAULT,
            'id'             => ['exp', "in ({$sub_query})"],
        ];
        $opts = [
            'field' => 'worker_order_id as id,appoint_time as prompt_time',
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $appoint_model->getList($opts);

        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_worker_visit'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $appoint_model->getNum($where, 'distinct worker_order_id'),
        ];
    }

    /**
     * 客服过期未审核(配件单)
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getAccessoryAdminNoCheckExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $admin_check_accessory_deadline = $this->getExceedTimestamp($config['exceed_admin_check_accessory']);

        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');

        $where = [
            'create_time'      => ['lt', $admin_check_accessory_deadline],
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status' => AccessoryService::STATUS_WORKER_APPLY_ACCESSORY,
        ];
        $sub_query = $accessory_model->field('distinct worker_order_id')
            ->where($where)
            ->buildSql();

        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'id'             => ['exp', "in({$sub_query})"],
                'cancel_status'  => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id' => ['in', $admin_ids],
            ])->buildSql();

        $where = [
            'worker_order_id'  => ['exp', "in({$sub_query})"],
            'create_time'      => ['lt', $admin_check_accessory_deadline],
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status' => AccessoryService::STATUS_WORKER_APPLY_ACCESSORY,
        ];
        $opts = [
            'field' => 'worker_order_id as id,min(create_time) as prompt_time',
            'where' => $where,
            'group' => 'worker_order_id',
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $accessory_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_admin_check_accessory'] * 60);

            $list[$key] = $val;
        }

        $cnt = $accessory_model->getNum($where, 'distinct worker_order_id');

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
    }

    /**
     * 厂家过期未审核(配件单)
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getAccessoryFactoryNoCheckExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $factory_check_accessory_deadline = $this->getExceedTimestamp($config['exceed_factory_check_accessory']);

        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');

        $where = [
            'admin_check_time' => ['lt', $factory_check_accessory_deadline],
            'cancel_status'      => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status'   => AccessoryService::STATUS_ADMIN_CHECKED,
        ];
        $sub_query = $accessory_model->field('distinct worker_order_id')
            ->where($where)
            ->buildSql();

        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'id'             => ['exp', "in({$sub_query})"],
                'cancel_status'  => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id' => ['in', $admin_ids],
            ])->buildSql();

        $where = [
            'worker_order_id'    => ['exp', "in({$sub_query})"],
            'admin_check_time' => ['lt', $factory_check_accessory_deadline],
            'cancel_status'      => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status'   => AccessoryService::STATUS_ADMIN_CHECKED,
        ];
        $opts = [
            'field' => 'worker_order_id as id,min(admin_check_time) as prompt_time',
            'where' => $where,
            'group' => 'worker_order_id',
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $accessory_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_factory_check_accessory'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $accessory_model->getNum($where, 'distinct worker_order_id'),
        ];
    }

    /**
     * 厂家过期未发件(配件单)
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getAccessoryFactorySendExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $factory_send_accessory_deadline = $this->getExceedTimestamp($config['exceed_factory_send_accessory']);

        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');

        $where = [
            'factory_estimate_time' => ['lt', $factory_send_accessory_deadline],
            'cancel_status'         => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status'      => AccessoryService::STATUS_FACTORY_CHECKED,
            'is_giveup_return'      => AccessoryService::RETURN_ACCESSORY_PASS,
        ];
        $sub_query = $accessory_model->field('distinct worker_order_id')
            ->where($where)
            ->buildSql();

        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'id'             => ['exp', "in({$sub_query})"],
                'cancel_status'  => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id' => ['in', $admin_ids],
            ])->buildSql();

        $where = [
            'worker_order_id'       => ['exp', "in({$sub_query})"],
            'factory_estimate_time' => ['lt', $factory_send_accessory_deadline],
            'cancel_status'         => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status'      => AccessoryService::STATUS_FACTORY_CHECKED,
            'is_giveup_return'      => AccessoryService::RETURN_ACCESSORY_PASS,
        ];

        $opts = [
            'field' => 'worker_order_id as id,min(factory_estimate_time) as prompt_time',
            'where' => $where,
            'group' => 'worker_order_id',
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $accessory_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_factory_send_accessory'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $accessory_model->getNum($where, 'distinct worker_order_id'),
        ];
    }

    /**
     * 技工过期未返件(配件单)
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getAccessoryWorkerSendBackExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $worker_send_back_accessory_deadline = $this->getExceedTimestamp($config['exceed_worker_send_back_accessory']);

        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');

        $where = [
            'worker_receive_time' => ['lt', $worker_send_back_accessory_deadline],
            'cancel_status'       => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status'    => AccessoryService::STATUS_WORKER_TAKE,
            'is_giveup_return'    => AccessoryService::RETURN_ACCESSORY_PASS,
        ];
        $sub_query = $accessory_model->field('distinct worker_order_id')
            ->where($where)
            ->buildSql();

        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'id'             => ['exp', "in({$sub_query})"],
                'cancel_status'  => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id' => ['in', $admin_ids],
            ])->buildSql();
        $where = [
            'worker_order_id'     => ['exp', "in({$sub_query})"],
            'worker_receive_time' => ['lt', $worker_send_back_accessory_deadline],
            'cancel_status'       => AccessoryService::CANCEL_STATUS_NORMAL,
            'accessory_status'    => AccessoryService::STATUS_WORKER_TAKE,
            'is_giveup_return'    => AccessoryService::RETURN_ACCESSORY_PASS,
        ];

        $opts = [
            'field' => 'worker_order_id as id,min(worker_receive_time) as prompt_time',
            'where' => $where,
            'group' => 'worker_order_id',
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $accessory_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_worker_send_back_accessory'] * 60);

            $list[$key] = $val;
        }

        $cnt = $accessory_model->getNum($where, 'distinct worker_order_id');

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
    }

    /**
     * 客服过期未审核(费用单)
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getCostAdminNoCheckExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $admin_check_cost_deadline = $this->getExceedTimestamp($config['exceed_admin_check_cost']);

        $cost_model = BaseModel::getInstance('worker_order_apply_cost');

        $where = [
            'create_time' => ['lt', $admin_check_cost_deadline],
            'status'      => CostService::STATUS_APPLY,
        ];
        $sub_query = $cost_model->field('distinct worker_order_id')
            ->where($where)
            ->buildSql();

        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'id'             => ['exp', "in({$sub_query})"],
                'cancel_status'  => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id' => ['in', $admin_ids],
            ])->buildSql();

        $cost_model = BaseModel::getInstance('worker_order_apply_cost');
        $where = [
            'worker_order_id' => ['exp', "in({$sub_query})"],
            'create_time'     => ['lt', $admin_check_cost_deadline],
            'status'          => CostService::STATUS_APPLY,
        ];
        $opts = [
            'field' => 'worker_order_id as id,min(create_time) as prompt_time',
            'where' => $where,
            'group' => 'worker_order_id',
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $cost_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_admin_check_cost'] * 60);

            $list[$key] = $val;
        }

        $cnt = $cost_model->getNum($where, 'distinct worker_order_id');

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
    }

    /**
     * 厂家过期未审核(费用单)
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getCostFactoryNoCheckExceed($admin_ids, $limit)
    {
        $admin_ids_str = empty($admin_ids) ? '-1' : implode(',', $admin_ids);

        $config = (new WorkbenchConfigLogic())->getList();

        $factory_check_cost_deadline = $this->getExceedTimestamp($config['exceed_factory_check_cost']);

        $cost_model = BaseModel::getInstance('worker_order_apply_cost');

        $where = [
            'admin_check_time' => ['lt', $factory_check_cost_deadline],
            'status'           => CostService::STATUS_ADMIN_PASS,
        ];
        $sub_query = $cost_model->field('distinct worker_order_id')
            ->where($where)
            ->buildSql();

        $sub_query = BaseModel::getInstance('worker_order')->field('id')
            ->where([
                'id'             => ['exp', "in({$sub_query})"],
                'cancel_status'  => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id' => ['in', $admin_ids_str],
            ])->buildSql();

        $where = [
            'worker_order_id'  => ['exp', "in({$sub_query})"],
            'admin_check_time' => ['lt', $factory_check_cost_deadline],
            'status'           => CostService::STATUS_ADMIN_PASS,
        ];
        $opts = [
            'field' => 'worker_order_id as id,min(create_time) as prompt_time',
            'where' => $where,
            'group' => 'worker_order_id',
            'order' => 'prompt_time',
            'limit' => $limit,
        ];
        $list = $cost_model->getList($opts);
        $list = empty($list) ? [] : $list;

        $worker_order_ids = array_column($list, 'id');
        $orders = $this->getOrders($worker_order_ids);

        foreach ($list as $key => $val) {
            $order = $orders[$val['id']];

            $val['checker_id'] = $order['checker_id'];
            $val['distributor_id'] = $order['distributor_id'];
            $val['returnee_id'] = $order['returnee_id'];
            $val['auditor_id'] = $order['auditor_id'];
            $val['orno'] = $order['orno'];

            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_factory_check_cost'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $cost_model->getNum($where, 'distinct worker_order_id'),
        ];
    }

    /**
     * 回访客服退回
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderReturneeReturnBack($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;
        $where = [
            'worker_order_status' => ['in', [
                OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
            ]],
            'cancel_status'       => ['in', [
                OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id'      => ['in', $admin_ids],
        ];

        $field = 'id,return_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    /**
     * 工单过期未回访
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoReturnExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        $returnee_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_return']);
        $where = [
            'returnee_receive_time' => ['lt', $returnee_receive_time_deadline],
            'worker_order_status'   => ['in', [
                OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
            ]],
            'cancel_status'         => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'returnee_id'           => ['in', $admin_ids],
        ];

        $field = 'id,returnee_receive_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        foreach ($list as $key => $val) {
            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_admin_return'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    /**
     * 财务退回
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderAuditorReturnBack($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $where = [
            'worker_order_status' => OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
            'cancel_status'       => ['in', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
            'returnee_id'         => ['in', $admin_ids],
        ];

        $field = 'id,audit_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    /**
     * 财务过期未审核
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderNoAuditExceed($admin_ids, $limit)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $sub_query = BaseModel::getInstance('worker_order_apply_accessory')
            ->field('worker_order_id')->where([
                'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
                'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
                'accessory_status' => ['in', [
                    AccessoryService::STATUS_WORKER_TAKE,
                ]],
            ])->buildSql();

        $config = (new WorkbenchConfigLogic())->getList();

        $auditor_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_auditor']);
        $where = [
            'auditor_receive_time' => ['lt', $auditor_receive_time_deadline],
            'worker_order_status'  => ['in', [
                OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            ]],
            'cancel_status'        => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'auditor_id'           => ['in', $admin_ids],
            'id'                   => ['exp', "not in ({$sub_query})"],
        ];

        $field = 'id,auditor_receive_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        foreach ($list as $key => $val) {
            $val['prompt_time'] = (string)$this->getTriggerTime($val['prompt_time'], $config['exceed_admin_auditor'] * 60);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    /**
     * 厂家退回
     *
     * @param $admin_ids
     * @param $limit
     *
     * @return array
     */
    protected function getOrderFactoryReturnBack($admin_ids, $limit, $receive_type)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $receive_where = [
            '_logic' => 'or',
        ];

        AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $receive_type && $receive_where['distributor_id'] = ['in', $admin_ids];
        AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR & $receive_type && $receive_where['auditor_id'] = ['in', $admin_ids];

        $where = [
            'worker_order_status' => OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
            'cancel_status'       => ['in', OrderService::CANCEL_TYPE_NOT_CALCEL_LIST],
            '_complex'            => $receive_where,
        ];

        $field = 'id,factory_audit_time as prompt_time,orno,checker_id,distributor_id,auditor_id,returnee_id';

        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'prompt_time',
            'limit' => $limit,
        ];

        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($opts);
        $list = empty($list) ? [] : $list;

        return [
            'list' => $list,
            'cnt'  => $model->getNum($where),
        ];
    }

    protected function getOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $list = BaseModel::getInstance('worker_order')->getList([
            'id' => ['in', $worker_order_ids],
        ], 'id,orno,checker_id,distributor_id,returnee_id,auditor_id');
        $list = empty($list) ? [] : $list;

        $data = [];
        foreach ($list as $val) {
            $id = $val['id'];
            $data[$id] = $val;
        }

        return $data;

    }

    public function getStatsSummary($param)
    {
        $tpl = [
            'order_check_receive_num_day'        => '0', // 今天共接厂家新单
            'order_check_num_day'                => '0', // 今天已核实工单
            'order_check_num_month'              => '0', // 本月已核实工单
            'order_distribute_receive_num_day'   => '0', // 今天共接派单新单
            'order_distribute_num_day'           => '0', // 今天已派发工单
            'order_distribute_finish_num_day'    => '0', // 今天已完成工单
            'order_returnee_return_back_num_day' => '0', // 回访退回工单
            'order_distribute_finish_num_month'  => '0', // 本月已完成工单
            'order_return_receive_num_day'       => '0', // 共接回访新单
            'order_return_num_day'               => '0', // 完成回访工单
            'order_auditor_return_back_num_day'  => '0', // 被财务退回工单
            'order_return_num_month'             => '0', // 本月已回访
            'order_audit_receive_num_day'        => '0', // 共接财务新单
            'order_audit_num_day'                => '0', // 审核工单
            'order_factory_return_back_num_day'  => '0', // 厂家财务审核不通过
            'order_audit_num_month'              => '0', // 本月已审核工单
        ];

        //获取查询用户
        $admin_info = $this->getAdminIds($param);
        $receive_type = $admin_info['receive_type'];
        $admin_ids = $admin_info['admin_ids'];

        if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER & $receive_type) {
            //核实客服
            //今天厂家新单
            $tpl['order_check_receive_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_RECEIVE_DAY'));
            //今天已核实工单
            $tpl['order_check_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_CHECK_DAY'));
            //本月已核实工单
            $tpl['order_check_num_month'] = self::getStatsSumMonth($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_CHECK_MONTH'));

        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $receive_type) {
            //派单客服
            $tpl['order_distribute_receive_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_RECEIVE_DAY'));
            $tpl['order_distribute_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_DISTRIBUTE_DAY'));
            //今天已完成工单
            $tpl['order_distribute_finish_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_FINISH_DAY'));

            //回访退回工单
            $tpl['order_returnee_return_back_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_RETURN_DAY'));

            //本月已完成工单
            $tpl['order_distribute_finish_num_month'] = self::getStatsSumMonth($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_FINISH_MONTH'));

        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE & $receive_type) {
            //回访客服
            //回访新单
            $tpl['order_return_receive_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_RECEIVE_DAY'));
            //完成回访
            $tpl['order_return_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_FINISH_DAY'));

            //redis 被财务退回
            $tpl['order_auditor_return_back_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_RETURN_DAY'));

            //本月已回访
            $tpl['order_return_num_month'] = self::getStatsSumMonth($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_FINISH_MONTH'));

        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR & $receive_type) {
            //财务客服
            $tpl['order_audit_receive_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_RECEIVE_DAY'));

            //审核工单
            $tpl['order_audit_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_AUDIT_DAY'));

            //厂家财务不通过 redis
            $tpl['order_factory_return_back_num_day'] = self::getStatsSumDay($admin_ids, C('WORKBENCH_REDIS_KEY.FACTORY_AUDITOR_NOT_PASS_DAY'));

            //本月已审核
            $tpl['order_audit_num_month'] = self::getStatsSumMonth($admin_ids, C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_AUDIT_MONTH'));
        }

        return $tpl;
    }

    public function getStatsList($param)
    {
        $tpl = [
            'order_no_check_exceed'             => '0', // 过期未核实
            'message_no_reply'                  => '0', // 留言未回复
            'compliant_no_reply'                => '0', // 投诉未处理
            'order_no_check'                    => '0', // 待核实
            'order_no_distribute_exceed'        => '0', // 过期未派发
            'order_no_follow_exceed'            => '0', // 过期未跟进
            'order_no_appoint_exceed'           => '0', // 过期未预约
            'order_no_visit_exceed'             => '0', // 过期未上门
            'accessory_exception'               => '0', // 异常配件单
            'accessory_admin_no_check_exceed'   => '0', // 客服过期未审核
            'accessory_factory_no_check_exceed' => '0', // 厂家过期未审核
            'accessory_factory_send_exceed'     => '0', // 过期未发件
            'accessory_worker_send_back_exceed' => '0', // 过期未返件
            'cost_exception'                    => '0', // 异常费用单
            'cost_admin_no_check_exceed'        => '0', // 客服过期未审核
            'cost_factory_no_check_exceed'      => '0', // 厂家过期未审核
            'order_returnee_return_back'        => '0', // 回访客服退回
            'order_no_distribute'               => '0', // 待派单
            'order_worker_in_service'           => '0', // 服务中
            'order_no_return_exceed'            => '0', // 过期未回访
            'order_auditor_return_back'         => '0', // 财务退回
            'order_no_return'                   => '0', // 待回访
            'order_no_audit_exceed'             => '0', // 财务过期未审核
            'order_factory_return_back'         => '0', // 厂家退回: 区分身份（财务与派单客服 都允许看到此数据）
            'order_no_audit'                    => '0', // 待审核
        ];

        $admin_info = $this->getAdminIds($param);
        $receive_type = $admin_info['receive_type'];
        $admin_ids = $admin_info['admin_ids'];

        $order_model = BaseModel::getInstance('worker_order');
        $config = (new WorkbenchConfigLogic())->getList();

        $order_factory_return_back_where = [];
        if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER & $receive_type) {
            //核实客服
            //过期未核实
            $checker_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_check']);
            $tpl['order_no_check_exceed'] = $order_model->getNum([
                'checker_receive_time' => ['lt', $checker_receive_time_deadline],
                'worker_order_status'  => OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
                'cancel_status'        => OrderService::CANCEL_TYPE_NULL,
                'checker_id'           => ['in', $admin_ids],
            ]);
            //待核实
            $tpl['order_no_check'] = $order_model->getNum([
                'worker_order_status' => OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
                'cancel_status'       => OrderService::CANCEL_TYPE_NULL,
                'checker_id'          => ['in', $admin_ids],
            ]);

        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $receive_type) {
            //派单客服
            //过期未派发
            $distributor_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_distribute']);
            $tpl['order_no_distribute_exceed'] = $order_model->getNum([
                'distributor_receive_time' => ['lt', $distributor_receive_time_deadline],
                'worker_order_status'      => OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
                'cancel_status'            => OrderService::CANCEL_TYPE_NULL,
                'distributor_id'           => ['in', $admin_ids],
            ]);

            //待派单
            $tpl['order_no_distribute'] = $order_model->getNum([
                'worker_order_status' => OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
                'cancel_status'       => OrderService::CANCEL_TYPE_NULL,
                'distributor_id'      => ['in', $admin_ids],
            ]);

            //过期未跟进
            $tpl['order_no_follow_exceed'] = '0';

            //异常预约
            $appoint = $this->getExceptionAppoint($admin_ids);
            $tpl['order_no_appoint_exceed'] = $appoint['order_no_appoint_exceed'];
            $tpl['order_no_visit_exceed'] = $appoint['order_no_visit_exceed'];

            //异常配件单
            $accessory = $this->getExceptionAccessory($admin_ids);
            $tpl['accessory_admin_no_check_exceed'] = $accessory['accessory_admin_no_check_exceed'];
            $tpl['accessory_factory_no_check_exceed'] = $accessory['accessory_factory_no_check_exceed'];
            $tpl['accessory_factory_send_exceed'] = $accessory['accessory_factory_send_exceed'];
            $tpl['accessory_worker_send_back_exceed'] = $accessory['accessory_worker_send_back_exceed'];
            $tpl['accessory_exception'] = $accessory['accessory_exception'];

            //--异常费用单
            $cost = $this->getExceptionCost($admin_ids);
            $tpl['cost_admin_no_check_exceed'] = $cost['cost_admin_no_check_exceed'];
            $tpl['cost_factory_no_check_exceed'] = $cost['cost_factory_no_check_exceed'];
            $tpl['cost_exception'] = $cost['cost_exception'];

            //回访客服退回
            $tpl['order_returnee_return_back'] = $order_model->getNum([
                'worker_order_status' => ['in', [
                    OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
                ]],
                'cancel_status'       => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'distributor_id'      => ['in', $admin_ids],
            ]);

            //服务中
            $tpl['order_worker_in_service'] = $order_model->getNum([
                'worker_order_status' => ['in', [
                    OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                    OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
                ]],
                'cancel_status'       => ['in', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
                'distributor_id'      => ['in', $admin_ids],
            ]);

            $order_factory_return_back_where['distributor_id'] = ['in', $admin_ids];

        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE & $receive_type) {
            //回访客服
            //过期未回访
            $returnee_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_return']);
            $tpl['order_no_return_exceed'] = $order_model->getNum([
                'returnee_receive_time' => ['lt', $returnee_receive_time_deadline],
                'worker_order_status'   => ['in', [
                    OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                ]],
                'cancel_status'         => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'returnee_id'           => ['in', $admin_ids],
            ]);

            //待回访
            $tpl['order_no_return'] = $order_model->getNum([
                'worker_order_status' => ['in', [
                    OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                ]],
                'cancel_status'       => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'returnee_id'         => ['in', $admin_ids],
            ]);

            //财务退回
            $tpl['order_auditor_return_back'] = $order_model->getNum([
                'worker_order_status' => OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                'cancel_status'       => ['in', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
                'returnee_id'         => ['in', $admin_ids],
            ]);

        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR & $receive_type) {
            //财务客服
            $auditor_receive_time_deadline = $this->getExceedTimestamp($config['exceed_admin_auditor']);
            //过期未审核
            $sub_query = BaseModel::getInstance('worker_order_apply_accessory')
                ->field('worker_order_id')->where([
                    'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
                    'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
                    'accessory_status' => ['in', [
                        AccessoryService::STATUS_WORKER_TAKE,
                    ]],
                ])->buildSql();
            $tpl['order_no_audit_exceed'] = $order_model->getNum([
                'auditor_receive_time' => ['lt', $auditor_receive_time_deadline],
                'worker_order_status'  => OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                'cancel_status'        => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'auditor_id'           => ['in', $admin_ids],
                'id'                   => ['exp', "not in ({$sub_query})"],
            ]);

            //待审核
            $tpl['order_no_audit'] = $order_model->getNum([
                'worker_order_status' => OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                'cancel_status'       => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
                'auditor_id'          => ['in', $admin_ids],
            ]);

            $order_factory_return_back_where['auditor_id'] = ['in', $admin_ids];
        }

        //厂家退回
        if (!empty($order_factory_return_back_where)) {
            $order_factory_return_back_where['_logic'] = 'or';
            $tpl['order_factory_return_back'] = $order_model->getNum([
                'worker_order_status' => OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                'cancel_status'       => ['in', OrderService::CANCEL_TYPE_NOT_CALCEL_LIST],
                '_complex'          => $order_factory_return_back_where,
            ]);
        }

        $tpl['message_no_reply'] = $this->getMessageStats($receive_type, $admin_ids);
        $tpl['compliant_no_reply'] = $this->getComplaintStats($receive_type, $admin_ids);

        return $tpl;

    }

    /**
     * 异常预约
     *
     * @param $admin_ids
     *
     * @return array
     */
    protected function getExceptionAppoint($admin_ids)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;
        $config = (new WorkbenchConfigLogic())->getList();

        $order_model = BaseModel::getInstance('worker_order');
        $appoint_model = BaseModel::getInstance('worker_order_appoint_record');

        //过期未预约
        $worker_appoint_deadline = $this->getExceedTimestamp($config['exceed_worker_appoint']);
        $tpl['order_no_appoint_exceed'] = $order_model->getNum([
            '_string'             => "worker_receive_time+extend_appoint_time*3600<{$worker_appoint_deadline}",
            'worker_order_status' => [
                OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
            ],
            'cancel_status'       => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id'      => ['in', $admin_ids],
        ]);

        //过期未上门
        $worker_visit_deadline = $this->getExceedTimestamp($config['exceed_worker_visit']);
        $sub_query = $order_model->field('id')->where([
            'worker_order_status' => ['in', [
                OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
            ]],
            'cancel_status'       => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id'      => ['in', $admin_ids],
        ])->buildSql();

        $sub_query = $appoint_model->field('max(id)')->where([
            '_string' => "worker_order_id in ({$sub_query})",
        ])->group('worker_order_id')->buildSql();

        $where = [
            'appoint_status' => ['in', [
                WorkerOrderAppointRecordService::STATUS_WAIT_WORKER_SIGN_IN,
                WorkerOrderAppointRecordService::STATUS_EDIT_APPOINT_TIME,
                WorkerOrderAppointRecordService::STATUS_APPOINT_AGAIN_AND_WAIT,
            ]],
            'appoint_time'   => ['lt', $worker_visit_deadline],
            'is_over'        => WorkerOrderAppointRecordService::IS_OVER_NO,
            'is_sign_in'     => WorkerOrderAppointRecordService::SIGN_IN_DEFAULT,
            'id'             => ['exp', "in ({$sub_query})"],
        ];

        $tpl['order_no_visit_exceed'] = $appoint_model->getNum($where, 'distinct worker_order_id');

        return $tpl;
    }

    /**
     * 异常费用单
     *
     * @param $admin_ids
     *
     * @return mixed
     */
    protected function getExceptionCost($admin_ids)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $config = (new WorkbenchConfigLogic())->getList();

        //--异常费用单
        $cost_model = BaseModel::getInstance('worker_order_apply_cost');
        $order_model = BaseModel::getInstance('worker_order');

        //客服过期未审核
        $admin_check_cost_deadline = $this->getExceedTimestamp($config['exceed_admin_check_cost']);
        $worker_order_ids_sub_query = $cost_model->field('worker_order_id')
            ->where([
                'create_time' => ['lt', $admin_check_cost_deadline],
                'status'      => CostService::STATUS_APPLY,
            ])->buildSql();
        $tpl['cost_admin_no_check_exceed'] = $order_model->getNum([
            'id'             => ['exp', "in ({$worker_order_ids_sub_query})"],
            'cancel_status'  => OrderService::CANCEL_TYPE_NULL,
            'distributor_id' => ['in', $admin_ids],
        ]);

        //厂家过期未审核
        $factory_check_cost_deadline = $this->getExceedTimestamp($config['exceed_factory_check_cost']);
        $worker_order_ids_sub_query = $cost_model->field('worker_order_id')
            ->where([
                'admin_check_time' => ['lt', $factory_check_cost_deadline],
                'status'           => CostService::STATUS_ADMIN_PASS,
            ])->buildSql();
        $tpl['cost_factory_no_check_exceed'] = $order_model->getNum([
            'id'             => ['exp', "in ($worker_order_ids_sub_query)"],
            'cancel_status'  => OrderService::CANCEL_TYPE_NULL,
            'distributor_id' => ['in', $admin_ids],
        ]);

        $tpl['cost_exception'] = (string)($tpl['cost_admin_no_check_exceed'] + $tpl['cost_factory_no_check_exceed']);

        return $tpl;
    }

    /**
     * 异常配件单
     *
     * @param $admin_ids
     *
     * @return mixed
     */
    protected function getExceptionAccessory($admin_ids)
    {
        $config = (new WorkbenchConfigLogic())->getList();

        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');
        $order_model = BaseModel::getInstance('worker_order');

        //客服过期未审核
        $admin_check_accessory_deadline = $this->getExceedTimestamp($config['exceed_admin_check_accessory']);
        $worker_order_ids_sub_query = $accessory_model->field('worker_order_id')
            ->where([
                'create_time'      => ['lt', $admin_check_accessory_deadline],
                'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
                'accessory_status' => AccessoryService::STATUS_WORKER_APPLY_ACCESSORY,
            ])->buildSql();
        $tpl['accessory_admin_no_check_exceed'] = $order_model->getNum([
            'id'             => ['exp', "in ({$worker_order_ids_sub_query})"],
            'cancel_status'  => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id' => ['in', $admin_ids],
        ]);

        //厂家过期未审核
        $factory_check_accessory_deadline = $this->getExceedTimestamp($config['exceed_factory_check_accessory']);
        $worker_order_ids_sub_query = $accessory_model->field('worker_order_id')
            ->where([
                'admin_check_time' => ['lt', $factory_check_accessory_deadline],
                'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
                'accessory_status' => AccessoryService::STATUS_ADMIN_CHECKED,
            ])->buildSql();
        $tpl['accessory_factory_no_check_exceed'] = $order_model->getNum([
            'id'             => ['exp', "in ({$worker_order_ids_sub_query})"],
            'cancel_status'  => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id' => ['in', $admin_ids],
        ]);

        //过期未发件
        $factory_send_accessory_deadline = $this->getExceedTimestamp($config['exceed_factory_send_accessory']);
        $worker_order_ids_sub_query = $accessory_model->field('worker_order_id')
            ->where([
                'factory_estimate_time' => ['lt', $factory_send_accessory_deadline],
                'cancel_status'         => AccessoryService::CANCEL_STATUS_NORMAL,
                'accessory_status'      => AccessoryService::STATUS_FACTORY_CHECKED,
                'is_giveup_return'      => AccessoryService::RETURN_ACCESSORY_PASS,
            ])->buildSql();
        $tpl['accessory_factory_send_exceed'] = $order_model->getNum([
            'id'             => ['exp', "in ({$worker_order_ids_sub_query})"],
            'cancel_status'  => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id' => ['in', $admin_ids],
        ]);

        //过期未返件
        $worker_send_back_accessory_deadline = $this->getExceedTimestamp($config['exceed_worker_send_back_accessory']);
        $worker_order_ids_sub_query = $accessory_model->field('worker_order_id')
            ->where([
                'worker_receive_time' => ['lt', $worker_send_back_accessory_deadline],
                'cancel_status'       => AccessoryService::CANCEL_STATUS_NORMAL,
                'accessory_status'    => AccessoryService::STATUS_WORKER_TAKE,
                'is_giveup_return'    => AccessoryService::RETURN_ACCESSORY_PASS,
            ])->buildSql();
        $tpl['accessory_worker_send_back_exceed'] = $order_model->getNum([
            'id'             => ['exp', "in ({$worker_order_ids_sub_query})"],
            'cancel_status'  => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'distributor_id' => ['in', $admin_ids],
        ]);

        $tpl['accessory_exception'] = (string)($tpl['accessory_admin_no_check_exceed'] + $tpl['accessory_factory_no_check_exceed'] + $tpl['accessory_factory_send_exceed'] + $tpl['accessory_worker_send_back_exceed']);

        return $tpl;
    }

    /**
     * 投诉单统计
     *
     * @param $receive_type
     * @param $admin_ids
     *
     * @return bool|int
     */
    protected function getComplaintStats($receive_type, $admin_ids)
    {
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $complaint_model = BaseModel::getInstance('worker_order_complaint');

        $where = [
            'replier_id' => ['in', $admin_ids],
            'reply_time' => 0,
        ];

        $field = 'distinct worker_order_id';

        return $complaint_model->getNum($where, $field);
    }

    /**
     * 留言
     *
     * @param $receive_type
     * @param $admin_ids
     *
     * @return bool|int
     */
    protected function getMessageStats($receive_type, $admin_ids)
    {
        $condition = [];

        $message_model = BaseModel::getInstance('worker_order_message');
        $sub_query = $message_model->field('worker_order_id')
            ->group('worker_order_id')
            ->buildSql();

        if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER & $receive_type) {
            $condition[] = '(worker_order_status in (' . implode(',', [
                    OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
                    OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
                ]) . ') and cancel_status in (' . implode(',', [
                    OrderService::CANCEL_TYPE_NULL,
                ]) . ") and checker_id in (" . implode(',', $admin_ids) . "))";
        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $receive_type) {
            $condition[] = '(worker_order_status in (' . implode(',', [
                    OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
                    OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                    OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
                    OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
                    OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                    OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                ]) . ') and cancel_status in (' . implode(',', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]) . ") and distributor_id in(" . implode(',', $admin_ids) . "))";
        }
        if (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE & $receive_type) {
            $condition[] = '(worker_order_status in (' . implode(',', [
                    OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                ]) . ') and cancel_status in (' . implode(',', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]) . ") and returnee_id in (" . implode(',', $admin_ids) . "))";
        }
        $where = [];
        if (empty($condition)) {
            $where['id'] = -1;
        } else {
            $where['_string'] = '(' . implode(' or ', $condition) . ')';
            $where['id'] = ['exp', "in ({$sub_query})"];
        }

        $order_model = BaseModel::getInstance('worker_order');
        $worker_order_ids_sub_query = $order_model->field('id')
            ->where($where)->buildSql();
        $where = [
            'worker_order_id' => ['exp', "in({$worker_order_ids_sub_query})"],
            'id'              => ['exp', "in (select max(id) from worker_order_message group by worker_order_id)"],
            'add_type'        => ['in', [
                OrderMessageService::ADD_TYPE_FACTORY,
                OrderMessageService::ADD_TYPE_FACTORY_ADMIN,
            ]],
            'create_time'     => ['egt', strtotime('20180401')], // 留言只显示2018.4.1后数据
        ];

        return $message_model->getNum($where);
    }

    /**
     * @param int $exceed_len 时长,单位:分钟
     *
     * @return int
     */
    protected function getExceedTimestamp($exceed_len)
    {
        $exceed_len = $exceed_len * 60;
        $today_begin = strtotime(date('Ymd'), NOW_TIME);

        //工作开始时间 8:30
        $begin = 8 * 3600 + 1800;
        //工作结束时间 18:00
        $end = 18 * 3600;
        $today_workday_begin = $today_begin + $begin;
        $today_workday_end = $today_begin + $end;

        $yesterday_begin = $today_begin - 86400;
        $yesterday_workday_begin = $yesterday_begin + $begin;
        $yesterday_workday_end = $yesterday_begin + $end;

        $exceed_timestamp = NOW_TIME - $exceed_len;

        //过期时间点早于当天工作时间开始
        //过期时间计算要排除休息时间
        if ($exceed_timestamp < $today_workday_begin) {
            $yesterday_last = $exceed_len - (NOW_TIME - $today_workday_begin);

            return $yesterday_workday_end - $yesterday_last;
        }

        if (NOW_TIME > $today_workday_end) {
            return $today_workday_end - $exceed_len;
        }

        //未超过直接返回
        return $exceed_timestamp;

    }

    protected function getTriggerTime($trigger_time, $exceed_len)
    {
        $begin = 8 * 3600 + 1800; // 工作日开始时间 8:30
        $end = 18 * 3600; // 工作日结束时间 18:00
        $rest_len = (24 - 18) * 3600 + 8 * 3600 + 1800; // 休息时间 18:00-明天8:30

        while ($exceed_len > 0) {
            $trigger_date = strtotime(date('Ymd', $trigger_time));
            $trigger_begin = $trigger_date + $begin; // 工作时间开始
            $trigger_end = $trigger_date + $end; // 工作时间结束

            if ($trigger_time < $trigger_begin) {
                $trigger_time = $trigger_begin;
            } elseif ($trigger_time >= $trigger_begin && $trigger_time <= $trigger_end) {
            } else {
                $trigger_time = $trigger_end + $rest_len;
                $trigger_date = strtotime(date('Ymd', $trigger_time));
                $trigger_begin = $trigger_date + $begin;
                $trigger_end = $trigger_date + $end; // 工作时间结束
            }

            $exceed_time = $trigger_time + $exceed_len;
            if ($exceed_time < $trigger_begin || $exceed_time > $trigger_end) {
                $exceed_len -= ($trigger_end - $trigger_time);
                $trigger_time += ($trigger_end - $trigger_time) + $rest_len;
            } else {
                $trigger_time = $exceed_time;
                $exceed_len = 0;
            }

        }

        return $trigger_time;
    }

    public static function incStatsDay($admin_id, $key_tpl)
    {
        $key = sprintf($key_tpl, date('ynj'));
        $redis = RedisPool::getInstance();
        $num = $redis->hIncrBy($key, $admin_id, 1);

        if (1 == $num) {
            $today = strtotime(date('Ymd'));
            $expire = $today + 86400;
            $redis->expireAt($key, $expire);
        }
    }

    public static function incStatsMonth($admin_id, $key_tpl)
    {
        $key = sprintf($key_tpl, date('yn'));
        $redis = RedisPool::getInstance();
        $num = $redis->hIncrBy($key, $admin_id, 1);

        if (1 == $num) {
            list($year, $month) = explode('|', date('Y|m'));
            $next_year = $year;
            $next_month = $month + 1;
            if (12 == $month) {
                $next_year++;
                $next_month = 1;
            }

            $month_end = strtotime(sprintf('%04d%02d01', $next_year, $next_month));
            $redis->expireAt($key, $month_end);
        }
    }

    public static function decStatsDay($admin_id, $key_tpl)
    {
        $key = sprintf($key_tpl, date('ynj'));
        $redis = RedisPool::getInstance();
        $num = $redis->hIncrBy($key, $admin_id, -1);

        if (-1 == $num) {
            $today = strtotime(date('Ymd'));
            $expire = $today + 86400;
            $redis->expireAt($key, $expire);
        }
    }

    public static function decStatsMonth($admin_id, $key_tpl)
    {
        $key = sprintf($key_tpl, date('yn'));
        $redis = RedisPool::getInstance();
        $num = $redis->hIncrBy($key, $admin_id, -1);

        if (-1 == $num) {
            list($year, $month) = explode('|', date('Y|m'));
            $next_year = $year;
            $next_month = $month + 1;
            if (12 == $month) {
                $next_year++;
                $next_month = 1;
            }
            $month_end = strtotime(sprintf('%04d%02d01', $next_year, $next_month));
            $redis->expireAt($key, $month_end);
        }
    }

    public static function getStatsSumDay($admin_ids, $key_tpl)
    {
        $key = sprintf($key_tpl, date('ynj'));
        $redis = RedisPool::getInstance();

        $count = 0;
        $batch_len = 50;
        $len = count($admin_ids);
        $fields = [];
        $sum = 0;
        foreach ($admin_ids as $admin_id) {
            $fields[] = $admin_id;
            $count++;

            if (0 == $count % $batch_len || $len == $count) {
                $temp = $redis->hMGet($key, $fields);
                if (!empty($temp)) {
                    $sum += array_sum($temp);
                }
                $fields = [];
            }
        }

        return (string)$sum;
    }

    public static function getStatsSumMonth($admin_ids, $key_tpl)
    {
        $key = sprintf($key_tpl, date('yn'));
        $redis = RedisPool::getInstance();

        $count = 0;
        $batch_len = 50;
        $len = count($admin_ids);
        $fields = [];
        $sum = 0;
        foreach ($admin_ids as $admin_id) {
            $fields[] = $admin_id;
            $count++;

            if (0 == $count % $batch_len || $len == $count) {
                $temp = $redis->hMGet($key, $fields);
                if (!empty($temp)) {
                    $sum += array_sum($temp);
                }
                $fields = [];
            }
        }

        return (string)$sum;
    }

}