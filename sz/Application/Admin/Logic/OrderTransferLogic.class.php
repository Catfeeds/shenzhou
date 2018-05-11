<?php
/**
 * File: OrderTransferLogic.class.php
 * User: sakura
 * Date: 2017/11/20
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkbenchEvent;
use Carbon\Carbon;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AdminService;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\FaultTypeService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\OrderUserService;
use EasyWeChat\Payment\Order;
use Library\Common\Util;

class OrderTransferLogic extends BaseLogic
{

    const ROLE_CHECKER     = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER;       // 核实客服
    const ROLE_DISTRIBUTOR = AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR;   // 派单客服
    const ROLE_RETURNEE    = AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;      // 回访客服
    const ROLE_AUDITOR     = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;       // 财务客服

    protected $tableName = 'worker_order';

    public function delegate($param)
    {
        //获取参数
        $worker_order_id = $param['worker_order_id'];
        $admin_id = $param['admin_id'];

        if (empty($worker_order_id) || empty($admin_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取工单
        $order_model = BaseModel::getInstance($this->tableName);
        $order_info = $order_model->getOneOrFail($worker_order_id);
        $order_status = $order_info['worker_order_status'];
        $auditor_id = $order_info['auditor_id'];
        $distributor_id = $order_info['distributor_id'];
        $returnee_id = $order_info['returnee_id'];
        $checker_id = $order_info['checker_id'];
        $cancel_status = $order_info['cancel_status'];

        if (OrderService::CANCEL_TYPE_NULL != $cancel_status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单已取消');
        }

        $cur_admin = $this->getCurrentAdmin($order_status);
        $cur_admin_role = $cur_admin['role'];

        //转单指派客服
        $field = 'state,nickout';
        $admin_info = AdminCacheModel::getOneOrFail($admin_id, $field);
        $delegate_state = $admin_info['state'];
        $nickout = $admin_info['nickout'];

        if (AdminService::STATE_ENABLED != $delegate_state) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '指定代理账户被禁用');
        }

        //获取被委派客服可接单类型
        //$role_ids = AdminCacheModel::getAdminRoleRelation($admin_id);
        //$total_type = 0;
        //foreach ($role_ids as $role_id) {
        //    $field = 'type';
        //    $info = AdminRoleCacheModel::getOneOrFail($role_id, $field);
        //    $total_type = $total_type | $info['type'];
        //}

        $worker_order_id_checker = 0;
        $worker_order_id_distributor = 0;
        $worker_order_id_returnee = 0;
        $worker_order_id_auditor = 0;
        if (self::ROLE_CHECKER == $cur_admin_role) {
            //核实客服
            if ($checker_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单当前客服(核实)id为空');
            }
            if ($admin_id == $checker_id) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理工单');
            }
            //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER)) {
            //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
            //}
            $worker_order_id_checker = $worker_order_id;
        } elseif (self::ROLE_DISTRIBUTOR == $cur_admin_role) {
            //派单接单客服
            if ($distributor_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单当前客服(派单)id为空');
            }
            if ($admin_id == $distributor_id) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理工单');
            }
            //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR)) {
            //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
            //}
            $worker_order_id_distributor = $worker_order_id;
        } elseif (self::ROLE_RETURNEE == $cur_admin_role) {
            //回访客服
            if ($returnee_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单当前客服(回访)id为空');
            }
            if ($admin_id == $returnee_id) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理工单');
            }
            //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE)) {
            //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
            //}
            $worker_order_id_returnee = $worker_order_id;
        } elseif (self::ROLE_AUDITOR == $cur_admin_role) {
            //财务
            if ($auditor_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单当前客服(财务)id为空');
            }
            if ($admin_id == $auditor_id) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理工单');
            }
            //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR)) {
            //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
            //}
            $worker_order_id_auditor = $worker_order_id;
        }

        $model = BaseModel::getInstance('worker_order');
        $ope_model = BaseModel::getInstance('worker_order_operation_record');
        $remark = '';

        $last_transfer_ope_log = $ope_model->getOne([
            'field' => 'create_time',
            'order' => 'create_time desc',
            'where' => [
                'worker_order_id' => $worker_order_id,
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
            ],
        ]);
        $is_today = $last_transfer_ope_log['create_time'] ? Carbon::createFromTimestamp($last_transfer_ope_log['create_time'])->isToday() : false;

        if ($worker_order_id_checker > 0) {
            $where = ['id' => $worker_order_id_checker];
            $update_data = [
                'checker_id' => $admin_id,
            ];
            $model->update($where, $update_data);

            $result_arr = [];
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_CHECKER_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $checker_id : null,
                ],
            ]));

            $field = 'nickout';
            $checker_info = AdminCacheModel::getOneOrFail($checker_id, $field);
            $checker_nickout = $checker_info['nickout'];
            $remark = "核实客服由：{$checker_nickout}转到{$nickout}";
        }
        if ($worker_order_id_distributor > 0) {
            $where = ['id' => $worker_order_id_distributor];
            $update_data = [
                'distributor_id' => $admin_id,
            ];
            $model->update($where, $update_data);
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_DISTRIBUTOR_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $distributor_id : null,
                ],
            ]));

            $field = 'nickout';
            $distributor_info = AdminCacheModel::getOneOrFail($distributor_id, $field);
            $distributor_nickout = $distributor_info['nickout'];
            $remark = "派单客服由：{$distributor_nickout}转到{$nickout}";
        }
        if ($worker_order_id_returnee > 0) {
            $where = ['id' => $worker_order_id_returnee];
            $update_data = [
                'returnee_id' => $admin_id,
            ];
            $model->update($where, $update_data);
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $returnee_id : null,
                ],
            ]));

            $field = 'nickout';
            $returnee_info = AdminCacheModel::getOneOrFail($returnee_id, $field);
            $returnee_nickout = $returnee_info['nickout'];
            $remark = "回访客服由：{$returnee_nickout}转到{$nickout}";
        }
        if ($worker_order_id_auditor > 0) {
            $where = ['id' => $worker_order_id_auditor];
            $update_data = [
                'auditor_id' => $admin_id,
            ];
            $model->update($where, $update_data);
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $auditor_id : null,
                ],
            ]));

            $field = 'nickout';
            $auditor_info = AdminCacheModel::getOneOrFail($auditor_id, $field);
            $auditor_nickout = $auditor_info['nickout'];
            $remark = "财务客服由：{$auditor_nickout}转到{$nickout}";
        }

        //日志
        $extras = [
            'see_auth' => null,
            'remark'   => $remark,
        ];
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_TRANSFER_ORDER, $extras);

    }

    public function userList($param)
    {
        //获取参数
        $worker_order_id = $param['worker_order_id'];
        $name = $param['name'];
        $limit = $param['limit'];

        //检查参数
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $where = [];
        if (!empty($name)) {
            $where['nickout'] = ['like', '%' . $name . '%'];
        }

        //获取工单
        $order_info = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id);
        $order_status = $order_info['worker_order_status'];
        $cancel_status = $order_info['cancel_status'];

        if (OrderService::CANCEL_TYPE_NULL != $cancel_status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单已取消');
        }

        $cur_admin = $this->getCurrentAdmin($order_status);
        $cur_admin_role = $cur_admin['role'];

        $receive_type = 0;
        if (self::ROLE_CHECKER == $cur_admin_role) {
            //核实客服
            $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER;
        } elseif (self::ROLE_DISTRIBUTOR == $cur_admin_role) {
            //派单接单客服
            $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR;
        } elseif (self::ROLE_RETURNEE == $cur_admin_role) {
            //回访客服
            $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;
        } elseif (self::ROLE_AUDITOR == $cur_admin_role) {
            //财务
            $receive_type = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
        }

        $role_where = [
            'type'       => ['exp', "&{$receive_type}={$receive_type}"],
            'is_delete'  => AdminRoleService::IS_DELETE_NO,
            'is_disable' => AdminRoleService::IS_DISABLE_NO,
        ];
        $role_ids = BaseModel::getInstance('admin_roles')
            ->getFieldVal($role_where, 'id', true);
        $role_ids = empty($role_ids) ? ['-1'] : $role_ids;
        $role_ids = array_unique($role_ids);

        $role_where = ['admin_roles_id' => ['in', $role_ids]];
        $admin_ids = BaseModel::getInstance('rel_admin_roles')
            ->getFieldVal($role_where, 'admin_id', true);
        $admin_ids = empty($admin_ids) ? '-1' : $admin_ids;

        $where['state'] = AdminService::STATE_ENABLED;
        $where['id'] = ['in', $admin_ids];
        $field = 'id,nickout as nickname';
        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'id',
            'limit' => $limit,
        ];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($opts);

        $cnt = $model->getNum($where);

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
    }

    /**
     * 批量转单
     *
     * @param $param
     */
    public function delegateBatch($param)
    {
        $admin_id = $param['admin_id'];
        $worker_order_ids = $param['worker_order_ids'];

        if ($admin_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单id列表为空');
        }

        //获取待转客服信息
        $field = 'nickout,state';
        $admin_info = AdminCacheModel::getOneOrFail($admin_id, $field);
        $nickout = $admin_info['nickout'];
        $delegate_state = $admin_info['state'];

        if (AdminService::STATE_ENABLED != $delegate_state) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '指定代理账户被禁用');
        }

        //获取被委派客服可接单类型
        //$role_ids = AdminCacheModel::getAdminRoleRelation($admin_id);
        //$total_type = 0;
        //foreach ($role_ids as $role_id) {
        //    $field = 'type';
        //    $info = AdminRoleCacheModel::getOneOrFail($role_id, $field);
        //    $total_type = $total_type | $info['type'];
        //}

        $where = [];
        $where['worker_order.id'][] = ['in', $worker_order_ids];

        $model = BaseModel::getInstance('worker_order');
        $field = 'id,cancel_status,worker_order_status,auditor_id,distributor_id,checker_id,returnee_id';
        $order_opts = [
            'where' => $where,
            'field' => $field,
        ];
        $worker_orders = $model->getList($order_opts);
        if (empty($worker_orders)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单不存在');
        }

        $checker_ids = [];
        $distributor_ids = [];
        $auditor_ids = [];
        $returnee_ids = [];

        $log_checker = [];
        $log_distributor = [];
        $log_auditor = [];
        $log_returnee = [];

        $ok_worker_order_id = [];
        $type_admin_ids = $admin_ids = [];
        foreach ($worker_orders as $worker_order) {
            $worker_order_id = $worker_order['id'];
            $order_status = $worker_order['worker_order_status'];
            $cancel_status = $worker_order['cancel_status'];
            $auditor_id = $worker_order['auditor_id'];
            $distributor_id = $worker_order['distributor_id'];
            $checker_id = $worker_order['checker_id'];
            $returnee_id = $worker_order['returnee_id'];

            if (OrderService::CANCEL_TYPE_NULL != $cancel_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单状态为已取消');
            }

            $cur_admin = $this->getCurrentAdmin($order_status);
            $cur_admin_role = $cur_admin['role'];

            if (self::ROLE_CHECKER == $cur_admin_role) {
                //核实客服
                if ($checker_id <= 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单当前客服(核实)id为空');
                }
                if ($admin_id == $checker_id) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理部分工单');
                }

                //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER)) {
                //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
                //}
                $checker_ids[] = $worker_order_id;
                $ok_worker_order_id[] = $worker_order_id;

                $log_checker[] = [
                    'old'             => $checker_id,
                    'worker_order_id' => $worker_order_id,
                ];
                $type_admin_ids[$worker_order_id] = $checker_id;
                $admin_ids[] = $checker_id;
            } elseif (self::ROLE_DISTRIBUTOR == $cur_admin_role) {
                //派单接单客服
                if ($distributor_id <= 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单当前客服(派单)id为空');
                }
                if ($admin_id == $distributor_id) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理部分工单');
                }

                //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR)) {
                //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
                //}
                $distributor_ids[] = $worker_order_id;
                $ok_worker_order_id[] = $worker_order_id;

                $log_distributor[] = [
                    'old'             => $distributor_id,
                    'worker_order_id' => $worker_order_id,
                ];
                $type_admin_ids[$worker_order_id] = $distributor_id;
                $admin_ids[] = $distributor_id;
            } elseif (self::ROLE_RETURNEE == $cur_admin_role) {
                //回访客服
                if ($returnee_id <= 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单当前客服(回访)id为空');
                }
                if ($admin_id == $returnee_id) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理部分工单');
                }
                //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE)) {
                //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
                //}
                $returnee_ids[] = $worker_order_id;
                $ok_worker_order_id[] = $worker_order_id;

                $log_returnee[] = [
                    'old'             => $returnee_id,
                    'worker_order_id' => $worker_order_id,
                ];
                $type_admin_ids[$worker_order_id] = $returnee_id;
                $admin_ids[] = $returnee_id;
            } elseif (self::ROLE_AUDITOR == $cur_admin_role) {
                //财务
                if ($auditor_id <= 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单当前客服(财务)id为空');
                }
                if ($admin_id == $auditor_id) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该客服已受理部分工单');
                }
                //if (!($total_type & AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR)) {
                //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服没有权限接单');
                //}
                $auditor_ids[] = $worker_order_id;
                $ok_worker_order_id[] = $worker_order_id;

                $log_auditor[] = [
                    'old'             => $auditor_id,
                    'worker_order_id' => $worker_order_id,
                ];
                $type_admin_ids[$worker_order_id] = $auditor_id;
                $admin_ids[] = $auditor_id;
            }
        }

        //转换客服
        if (!empty($checker_ids)) {
            $where = ['id' => ['in', $checker_ids]];
            $update_data = [
                'checker_id' => $admin_id,
            ];
            $model->update($where, $update_data);
        }
        if (!empty($distributor_ids)) {
            $where = ['id' => ['in', $distributor_ids]];
            $update_data = [
                'distributor_id' => $admin_id,
            ];
            $model->update($where, $update_data);
        }
        if (!empty($returnee_ids)) {
            $where = ['id' => ['in', $returnee_ids]];
            $update_data = [
                'returnee_id' => $admin_id,
            ];
            $model->update($where, $update_data);
        }
        if (!empty($auditor_ids)) {
            $where = ['id' => ['in', $auditor_ids]];
            $update_data = [
                'auditor_id' => $admin_id,
            ];
            $model->update($where, $update_data);
        }

        //工单日志
        $admin_list = $this->collectAdmin($admin_ids);
        $opts = [];
        if (!empty($log_checker)) {
            foreach ($log_checker as $checker) {
                $admin_id = $checker['old'];
                $worker_order_id = $checker['worker_order_id'];

                $admin_name = empty($admin_list[$admin_id]) ? '' : $admin_list[$admin_id]['nickout'];

                $remark = "核实客服由：{$admin_name}转到{$nickout}";

                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['remark' => $remark],
                ];
            }
        }
        if (!empty($log_distributor)) {
            foreach ($log_distributor as $distributor) {
                $admin_id = $distributor['old'];
                $worker_order_id = $distributor['worker_order_id'];

                $admin_name = empty($admin_list[$admin_id]) ? '' : $admin_list[$admin_id]['nickout'];
                $remark = "派单客服由：{$admin_name}转到{$nickout}";

                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['remark' => $remark],
                ];
            }
        }
        if (!empty($log_returnee)) {
            foreach ($log_returnee as $returnee) {
                $admin_id = $returnee['old'];
                $worker_order_id = $returnee['worker_order_id'];

                $admin_name = empty($admin_list[$admin_id]) ? '' : $admin_list[$admin_id]['nickout'];

                $remark = "回访客服由：{$admin_name}转到{$nickout}";

                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['remark' => $remark],
                ];
            }
        }
        if (!empty($log_auditor)) {
            foreach ($log_auditor as $auditor) {
                $admin_id = $auditor['old'];
                $worker_order_id = $auditor['worker_order_id'];

                $admin_name = empty($admin_list[$admin_id]) ? '' : $admin_list[$admin_id]['nickout'];

                $remark = "财务客服由：{$admin_name}转到{$nickout}";

                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['remark' => $remark],
                ];
            }
        }
//        $ok_worker_order_id
        $last_transfer_ope_logs = $ok_worker_order_id ? BaseModel::getInstance('worker_order_operation_record force index(worker_order_id)')->getList([
            'field' => 'worker_order_id,substring_index(group_concat(create_time order by create_time), ",", 1) as last_time',
            'order' => 'create_time desc',
            'where' => [
                'worker_order_id' => ['in', $ok_worker_order_id],
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
            ],
            'group' => 'worker_order_id',
            'index' => 'worker_order_id',
        ]) : [];

        foreach ($checker_ids as $worker_order_id) {
            $last_transfer_ope_log = $last_transfer_ope_logs[$worker_order_id];
            $is_today = $last_transfer_ope_log['last_time'] ? Carbon::createFromTimestamp($last_transfer_ope_log['last_time'])->isToday() : false;
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_CHECKER_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $type_admin_ids[$worker_order_id] : null,
                ],
            ]));
        }
        foreach ($distributor_ids as $worker_order_id) {
            $last_transfer_ope_log = $last_transfer_ope_logs[$worker_order_id];
            $is_today = $last_transfer_ope_log['last_time'] ? Carbon::createFromTimestamp($last_transfer_ope_log['last_time'])->isToday() : false;
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_DISTRIBUTOR_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $type_admin_ids[$worker_order_id] : null,
                ],
            ]));
        }
        foreach ($returnee_ids as $worker_order_id) {
            $last_transfer_ope_log = $last_transfer_ope_logs[$worker_order_id];
            $is_today = $last_transfer_ope_log['last_time'] ? Carbon::createFromTimestamp($last_transfer_ope_log['last_time'])->isToday() : false;
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $type_admin_ids[$worker_order_id] : null,
                ],
            ]));
        }
        foreach ($auditor_ids as $worker_order_id) {
            $last_transfer_ope_log = $last_transfer_ope_logs[$worker_order_id];
            $is_today = $last_transfer_ope_log['last_time'] ? Carbon::createFromTimestamp($last_transfer_ope_log['last_time'])->isToday() : false;
            event(new WorkbenchEvent([
                'worker_order_id' => $worker_order_id,
                'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_RECEIVE'),
                'operation_type' => OrderOperationRecordService::CS_TRANSFER_ORDER,
                'receive_admin_id' => [
                    $worker_order_id => $is_today ? $type_admin_ids[$worker_order_id] : null,
                ],
            ]));
        }

        if (!empty($opts)) {
            OrderOperationRecordService::createMany($opts, OrderOperationRecordService::CS_TRANSFER_ORDER);
        }
    }

    protected function collectAdmin($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $data = [];
        $opts = [
            'where' => [
                'id'    => ['in', $admin_ids],
                'field' => 'id,nickout',
            ],
        ];
        $list = BaseModel::getInstance('admin')
            ->getList($opts);

        foreach ($list as $val) {
            $admin_id = $val['id'];

            $data[$admin_id] = $val;
        }

        return $data;

    }


    /**
     * 批量接单
     *
     * @param $param
     */
    public function receiveBatch($param)
    {
        $worker_order_ids = $param['worker_order_ids'];

        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = $admin['id'];
        $user_name = $admin['user_name'];

        $where = [];
        $where['worker_order.id'][] = ['in', $worker_order_ids];

        $model = BaseModel::getInstance('worker_order');
        $field = 'id,cancel_status,worker_order_status,auditor_id,distributor_id,checker_id';
        $worker_orders = $model->getList($where, $field);

        if (empty($worker_orders)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单不存在');
        }

        $order_logic = new OrderLogic();
        $valid_order_status = [
            OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE, // 核实
            OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE, // 派单
            OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE, // 回访
            OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE, // 财务
        ];

        $checker_ids = [];
        $distributor_ids = [];
        $returnee_ids = [];
        $auditor_ids = [];

        //收集各状态对应的工单
        foreach ($worker_orders as $worker_order) {
            $worker_order_id = $worker_order['id'];
            $worker_order_status = $worker_order['worker_order_status'];
            $cancel_status = $worker_order['cancel_status'];

            $checker_id = $worker_order['checker_id'];
            $distributor_id = $worker_order['distributor_id'];
            $returnee_id = $worker_order['returnee_id'];
            $auditor_id = $worker_order['auditor_id'];

            //检查权限
            $order_logic->checkAdminOrderOperatePermission($worker_order_status, $admin, '抱歉，该客服没权限接收您所勾选的所有工单');

            if (
                OrderService::CANCEL_TYPE_NULL != $cancel_status &&
                OrderService::CANCEL_TYPE_CS_STOP != $cancel_status
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单已取消');
            }

            if (!in_array($worker_order_status, $valid_order_status)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分工单状态异常');
            }

            //检查是否已接单
            if (OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE == $worker_order_status) {
                //核实
                if ($checker_id > 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前核实客服已接单');
                }
                $checker_ids[] = $worker_order_id;

            } elseif (OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE == $worker_order_status) {
                //派发
                if ($distributor_id > 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前派单客服已接单');
                }
                $distributor_ids[] = $worker_order_id;

            } elseif (OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE == $worker_order_status) {
                //回访
                if ($returnee_id > 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前回访客服已接单');
                }
                $returnee_ids[] = $worker_order_id;

            } elseif (OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE == $worker_order_status) {
                //财务
                if ($auditor_id > 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前财务客服已接单');
                }
                $auditor_ids[] = $worker_order_id;

            }
        }

        //核实客服
        if (!empty($checker_ids)) {
            $where = ['id' => ['in', $checker_ids]];
            $update_data = [
                'checker_id'           => $admin_id,
                'checker_receive_time' => NOW_TIME,
                'last_update_time'     => NOW_TIME,
                'worker_order_status'  => OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
            ];
            $model->update($where, $update_data);

            //日志
            $opts = [];
            foreach ($checker_ids as $worker_order_id) {
                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['content_replace' => [
                        'admin_name' => $user_name,
                    ]],
                ];
            }
            OrderOperationRecordService::createMany($opts, OrderOperationRecordService::CS_CHECKER_RECEIVED);
        }

        //派发客服
        if (!empty($distributor_ids)) {
            $where = ['id' => ['in', $distributor_ids]];
            $update_data = [
                'distributor_id'           => $admin_id,
                'distributor_receive_time' => NOW_TIME,
                'last_update_time'         => NOW_TIME,
                'worker_order_status'      => OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
            ];
            $model->update($where, $update_data);

            //日志
            $opts = [];
            foreach ($distributor_ids as $worker_order_id) {
                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['content_replace' => [
                        'admin_name' => $user_name,
                    ]],
                ];
            }
            OrderOperationRecordService::createMany($opts, OrderOperationRecordService::CS_DISTRIBUTOR_RECEIVED);
        }

        //回访客服
        if (!empty($returnee_ids)) {
            $where = ['id' => ['in', $returnee_ids]];
            $update_data = [
                'returnee_id'           => $admin_id,
                'returnee_receive_time' => NOW_TIME,
                'last_update_time'      => NOW_TIME,
                'worker_order_status'   => OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
            ];
            $model->update($where, $update_data);

            //日志
            $opts = [];
            foreach ($returnee_ids as $worker_order_id) {
                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['content_replace' => [
                        'admin_name' => $user_name,
                    ]],
                ];
            }
            OrderOperationRecordService::createMany($opts, OrderOperationRecordService::CS_RETURNEE_RECEIVED);
        }

        //财务客服
        if (!empty($auditor_ids)) {
            $where = ['id' => ['in', $auditor_ids]];
            $update_data = [
                'auditor_id'           => $admin_id,
                'auditor_receive_time' => NOW_TIME,
                'last_update_time'     => NOW_TIME,
                'worker_order_status'  => OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            ];
            $model->update($where, $update_data);

            //日志
            $opts = [];
            foreach ($auditor_ids as $worker_order_id) {
                $opts[] = [
                    'worker_order_id' => $worker_order_id,
                    'extras'          => ['content_replace' => [
                        'admin_name' => $user_name,
                    ]],
                ];
            }
            OrderOperationRecordService::createMany($opts, OrderOperationRecordService::CS_AUDITOR_RECEIVED);
        }
    }

    protected function getCurrentAdmin($worker_order_status)
    {
        //可接单状态: 1.工单待财务审核之后 2.待派发之后 工单待财务审核之前 3.工单待核实
        //核实合法状态
        $checked_valid_status = OrderService::DELEGATE_CHECKED_VALID_STATUS_LIST;

        //派单合法状态
        $distributor_valid_status = OrderService::DELEGATE_DISTRIBUTOR_VALID_STATUS_LIST;

        //回访合法状态
        $returnee_valid_status = OrderService::DELEGATE_RETURNEE_VALID_STATUS_LIST;

        //财务合法状态
        $auditor_valid_status = OrderService::DELEGATE_AUDITOR_VALID_STATUS_LIST;

        $valid_status = array_merge($checked_valid_status, $distributor_valid_status, $returnee_valid_status, $auditor_valid_status);

        if (!in_array($worker_order_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态不在允许更改客服范围内');
        }

        if (in_array($worker_order_status, $checked_valid_status)) {
            return [
                'role' => self::ROLE_CHECKER,
            ];
        } elseif (in_array($worker_order_status, $distributor_valid_status)) {
            return [
                'role' => self::ROLE_DISTRIBUTOR,
            ];
        } elseif (in_array($worker_order_status, $returnee_valid_status)) {
            return [
                'role' => self::ROLE_RETURNEE,
            ];
        } elseif (in_array($worker_order_status, $auditor_valid_status)) {
            return [
                'role' => self::ROLE_AUDITOR,
            ];
        }

        return false;

    }

    public function stop($param)
    {
        //获取参数
        $worker_order_id = $param['worker_order_id'];
        $remark = $param['remark'];

        //检查参数
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取用户信息
        $admin = AuthService::getAuthModel();
        $role_id = $admin['role_id'];

        //        $root = AdminRoleService::getRoleRoot();
        //        $admin_root = AdminRoleService::getRoleAdminRoot();
        //        $distributor = AdminRoleService::getRoleDistributor();
        //        $valid_role = array_merge($distributor, $admin_root, $root);
        //
        //        //权限
        //        if (!in_array($role_id, $valid_role)) {
        //            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '用户没有权限');
        //        }

        //获取工单
        $model = BaseModel::getInstance($this->tableName);
        $order_info = $model->getOneOrFail($worker_order_id);
        $order_status = $order_info['worker_order_status'];

        //检查工单
        $valid_status = [OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL, OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT, OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE, OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,];
        if (!in_array($order_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态不允许终止');
        }

        //检查配件单 费用单
        (new AccessoryLogic())->checkAllCompleted($worker_order_id);// 配件单
        (new CostLogic())->checkAllCompleted($worker_order_id); // 费用单

        //群内工单修改数量
        event(new UpdateOrderNumberEvent([
            'worker_order_id'              => $worker_order_id,
            'operation_type'               => OrderOperationRecordService::CS_ORDER_STOP,
            'original_worker_id'           => $order_info['worker_id'],
            'original_children_worker_id'  => $order_info['children_worker_id'],
            'original_worker_order_status' => $order_info['worker_order_status'],
        ]));

        //更新数据
        $update_data = [
            'cancel_status'       => OrderService::CANCEL_TYPE_CS_STOP,
            'worker_order_status' => OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
            'cancel_time'         => NOW_TIME,
            'cancel_remark'       => $remark,
            'last_update_time'    => NOW_TIME,
        ];

        $model->update($worker_order_id, $update_data);

        //日志
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_ORDER_STOP, ['remark' => $remark]);

    }


    public function workerOrderType($param)
    {
        $user_type = AuthService::getModel();

        $worker_order_id = $param['worker_order_id'];

        //检查参数
        if ($worker_order_id < 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //判断身份
        $pic_url = ''; // 图片
        $remark = ''; // 备注
        $order_where = [
            'id' => $worker_order_id,
        ];
        if (AuthService::ROLE_ADMIN == $user_type) {
            //客服
            //检查图片及备注
            $pic_url = $param['pic_url'];
            $pic_url = Util::filterIdList($pic_url);
            $remark = $param['remark'];
            if (strlen($remark) <= 0 || empty($pic_url)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

        } else {
            //厂家及子账号
            $remark = $param['remark']; // 选填
            $factory_id = AuthService::getAuthModel()->factory_id;
            $order_where['factory_id'] = $factory_id;
        }

        //获取工单
        $order_model = BaseModel::getInstance('worker_order');
        $field = 'worker_order_type,worker_order_status,cancel_status,factory_id,service_type';
        $order_info = $order_model->getOneOrFail($order_where, $field);
        $worker_order_type = $order_info['worker_order_type'];
        $worker_order_status = $order_info['worker_order_status'];
        $cancel_status = $order_info['cancel_status'];
        $factory_id = $order_info['factory_id'];
        $service_type = $order_info['service_type'];

        //检查工单状态(包括取消)
        //回访客服确认与维修商结算之前 或 被回访客服退回
        $valid_status = [
            OrderService::STATUS_CREATED,
            OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE,
            OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
            OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
            OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
            OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL,
            OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
            OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
            OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
            OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
            OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
            OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
        ];
        if (!in_array($worker_order_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态错误');
        }
        if (
            OrderService::CANCEL_TYPE_NULL != $cancel_status &&
            OrderService::CANCEL_TYPE_CS_STOP != $cancel_status
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消');
        }

        //判断售后类型
        $valid_order_type = [
            OrderService::ORDER_TYPE_FACTORY_OUT_INSURANCE,
            OrderService::ORDER_TYPE_WX_USER_OUT_INSURANCE,
            OrderService::ORDER_TYPE_FACTORY_EXPORT_OUT_INSURANCE,
            OrderService::ORDER_TYPE_REWORK_OUT_INSURANCE,
        ];
        if (!in_array($worker_order_type, $valid_order_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单售后类型暂不支持转换');
        }

        //获取保外单信息
        $user_order_model = BaseModel::getInstance('worker_order_user_info');
        $field = 'is_user_pay';
        $user_info = $user_order_model->getOneOrFail($worker_order_id, $field);
        $is_user_pay = $user_info['is_user_pay'];

        //判断是否支付
        if (OrderUserService::IS_USER_PAY_SUCCESS == $is_user_pay) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已支付');
        }

        //转换的工单类型
        $transfer_worker_order_type = 0;
        if (OrderService::ORDER_TYPE_FACTORY_OUT_INSURANCE == $worker_order_type) {
            $transfer_worker_order_type = OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE;
        } elseif (OrderService::ORDER_TYPE_FACTORY_EXPORT_OUT_INSURANCE == $worker_order_type) {
            $transfer_worker_order_type = OrderService::ORDER_TYPE_FACTORY_EXPORT_IN_INSURANCE;
        } elseif (OrderService::ORDER_TYPE_WX_USER_OUT_INSURANCE == $worker_order_type) {
            $transfer_worker_order_type = OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE;
        } elseif (OrderService::ORDER_TYPE_REWORK_OUT_INSURANCE == $worker_order_type) {
            $transfer_worker_order_type = OrderService::ORDER_TYPE_REWORK_IN_INSURANCE;
        }

        //获取厂家信息(余额)
        $field = 'money,default_frozen,frozen_money';
        $factory_info = BaseModel::getInstance('factory')
            ->getOneOrFail($factory_id, $field);
        $money = $factory_info['money'];
        $default_frozen = $factory_info['default_frozen'];
        $factory_frozen_money = OrderService\CreateOrderService::getFactoryLogic()
            ->getFrozenMoney($factory_id);

        //计算冻结金额 并且冻结厂家资金
        $order_product_model = BaseModel::getInstance('worker_order_product');
        $where = [
            'worker_order_id' => $worker_order_id,
        ];
        $order_products = $order_product_model->getList($where);
        $total_frozen = 0;

        $fault_type = FaultTypeService::getFaultType($service_type); //服务项类型 0维修 2维护 1安装
        $product_logic = new ProductLogic();
        $order_product_update = [];
        $total_worker_repair_fee = 0;
        $total_factory_repair_fee = 0;

        foreach ($order_products as $order_product) {
            $product_category_id = $order_product['product_category_id'];
            $product_standard_id = $order_product['product_standard_id'];
            $product_num = $order_product['product_nums'];
            $fault_id = $order_product['fault_id'];
            $id = $order_product['id'];

            //获取冻结金额
            $product_frozen_money = FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($service_type, $factory_id, $product_category_id, $product_standard_id, $default_frozen);

            $update_data = [
                'frozen_money'              => $product_frozen_money, // 厂家冻结金额
                'factory_repair_fee'        => $product_frozen_money, // 厂家维修金
                'factory_repair_fee_modify' => $product_frozen_money, // 厂家维修金(修改后)
            ];
            $repair_fee = $product_frozen_money;
            if ($fault_id > 0) {
                //技工提交服务项
                //获取保内价目表
                $price_info = $product_logic->getFactoryFaultPriceByCategoryIdAndStandardId($factory_id, $product_category_id, $product_standard_id, $fault_id, $fault_type);
                $worker_in_price = empty($price_info) ? 0 : $price_info[0]['worker_in_price'];
                $factory_in_price = empty($price_info) ? 0 : $price_info[0]['factory_in_price'];

                $update_data['worker_repair_fee'] = $worker_in_price;
                $update_data['worker_repair_fee_modify'] = $worker_in_price;
                //如果技工已提交维修项,按照保内价
                $update_data['factory_repair_fee'] = $factory_in_price;
                $update_data['factory_repair_fee_modify'] = $factory_in_price;

                $total_worker_repair_fee += $worker_in_price;
                $repair_fee = $factory_in_price;
            }
            $total_factory_repair_fee += $repair_fee;

            $order_product_update[] = [
                'where' => [
                    'worker_order_id' => $worker_order_id,
                    'id'              => $id,
                ],
                'data'  => $update_data,
            ];

            $total_frozen += $product_frozen_money * $product_num;
        }

        //服务费
        $service_fee = 0;
        if (in_array($service_type, [OrderService::TYPE_WORKER_INSTALLATION, OrderService::TYPE_PRE_RELEASE_INSTALLATION])) {
            $service_fee = C('ORDER_INSURED_SERVICE_FEE');
        }

        $total_frozen += $service_fee;
        $total_frozen = round($total_frozen, 2, PHP_ROUND_HALF_UP);

        //判断余额是否足够
        $diff = $money - $factory_frozen_money - $total_frozen;
        if ($diff < 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '您的可下单余额不足，请充值后再处理工单。');
        }

        M()->startTrans();

        //转换工单
        $order_model->update($worker_order_id, [
            'worker_order_type' => $transfer_worker_order_type,
        ]);

        //修改工单产品详情 资金记录
        if (!empty($order_product_update)) {
            $product_model = BaseModel::getInstance('worker_order_product');
            foreach ($order_product_update as $item) {
                $product_model->update($item['where'], $item['data']);
            }
        }

        //清空保外单数据,新增保内费用
        $insurance_fee = 1;
        $order_fee_model = BaseModel::getInstance('worker_order_fee');
        $total_factory_repair_fee = round($total_factory_repair_fee, 2, PHP_ROUND_HALF_UP);
        $total_worker_repair_fee = round($total_worker_repair_fee, 2, PHP_ROUND_HALF_UP);
        $update_fee_data = [
            'accessory_out_fee'         => 0,
            'user_discount_out_fee'     => 0,
            'factory_repair_fee'        => $total_factory_repair_fee,
            'factory_repair_fee_modify' => $total_factory_repair_fee,
            'service_fee'               => $service_fee,
            'service_fee_modify'        => $service_fee,
            'worker_repair_fee'         => $total_worker_repair_fee,
            'worker_repair_fee_modify'  => $total_worker_repair_fee,
            'insurance_fee'             => $insurance_fee,
            'coupon_reduce_money'       => 0,
        ];

        OrderSettlementService::doorFeeSettlement($worker_order_id, $update_fee_data);

        $order_fee_model->update($worker_order_id, $update_fee_data);

        OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id);

        $user_order_model->update($worker_order_id, [
            'pay_type'    => OrderUserService::PAY_TYPE_NULL,
            'is_user_pay' => OrderUserService::IS_USER_PAY_DEFAULT,
            'pay_time'    => 0,
        ]);

        //冻结厂家金额
        FactoryMoneyFrozenRecordService::process($worker_order_id, FactoryMoneyFrozenRecordService::TYPE_CS_OUT_TO_IN, $total_frozen);

        //添加工单日志
        $operate_type = 0;
        if (AuthService::ROLE_ADMIN == $user_type) {
            //客服
            $operate_type = OrderOperationRecordService::CS_TRANSFER_WORKER_ORDER_TYPE;
            foreach ($pic_url as $url) {
                $remark .= "<img width='40' height='40' class='toBig' src='{$url}' />";
            }
        } else {
            //厂家及子账号
            $operate_type = OrderOperationRecordService::FACTORY_TRANSFER_WORKER_ORDER_TYPE;
        }
        $extra = [
            'remark'          => $remark,
            'see_auth'        => null,
            'content_replace' => null,
        ];
        OrderOperationRecordService::create($worker_order_id, $operate_type, $extra);

        M()->commit();
    }


}