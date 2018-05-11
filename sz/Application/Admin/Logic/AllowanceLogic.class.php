<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/10
 * Time: 10:57
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AllowanceService;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\SystemMessageService;
use Library\Common\Util;

class AllowanceLogic extends BaseLogic
{

    protected $tableName = 'worker_order_apply_allowance';

    public function cancelOrderApplyAllowance($worker_order_id)
    {
        $where = ['worker_order_id' => $worker_order_id,];
        $update_data = [
            'status'       => AllowanceService::STATUS_SYS_CANCEL,
            'check_time'   => NOW_TIME,
            'check_remark' => '工单取消导致的补贴单取消',
        ];
        BaseModel::getInstance($this->tableName)->update($where, $update_data);
    }

    public function getList($param)
    {
        $orno = $param['orno'];
        $status = $param['status'];
        $factory_group_ids = $param['factory_group_ids'];
        $factory_group_ids = Util::filterIdList($factory_group_ids);

        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $fee_from = $param['fee_from'];
        $fee_to = $param['fee_to'];
        $admin_ids = $param['admin_ids'];
        $admin_ids = Util::filterIdList($admin_ids);

        //工单状态数组
        $worker_order_status = $param['worker_order_status'];
        $worker_order_status = Util::filterIdList($worker_order_status);

        $is_export = $param['is_export'];
        $limit = $param['limit'];

        $where = [];
        if (strlen($orno) > 0) {
            $where['worker_order_id'][] = ['exp', "in (select id from worker_order where orno like '%{$orno}%')"];
        }

        if (strlen($status) > 0) {
            $where['status'][] = ['eq', $status];
        }

        // 工单状态
        if ($worker_order_status) {
            $condition = [];
            foreach($worker_order_status as $key => $val) {
                switch ($val) {
                    case 10:     // 待客服接单
                        $condition['worker_order_status'][] = OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
                        break;
                    case 20:     // 待客服核实
                        $condition['worker_order_status'][] = OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK;
                        break;
                    case 30:     // 待派发客服接单
                        $condition['worker_order_status'][] = OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE;
                        break;
                    case 40:     // 待客服派单
                        $condition['worker_order_status'][] = OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE;
                        break;
                    case 50:     // 待维修商接单
                        $condition['worker_order_status'][] = OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL;
                        break;
                    case 60:     // 待维修商预约
                        $condition['worker_order_status'][] = OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT;
                        break;
                    case 64:    // 待维修商服务
                        $condition['worker_order_status'][] = OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE;
                        break;
                    case 67:    // 维修商服务中
                        $condition['worker_order_status'][] = OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE;
                        break;
                    case 70:     // 待回访客服接单
                        $condition['worker_order_status'][] = OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE;
                        break;
                    case 80:     // 待客服回访
                        $condition['worker_order_status'][] = OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT;
                        $condition['worker_order_status'][] = OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT;
                        break;
                    case 85:     // 回访不通过
                        $condition['worker_order_status'][] = OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE;
                        //                    $is_admin && $service_admin_ids && $where['returnee_id'] = ['IN', $service_admin_ids];
                        break;
                    case 90:     // 待平台财务接单
                        $condition['worker_order_status'][] = OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE;
                        break;
                    case 100:     // 待平台财务审核
                        $condition['worker_order_status'][] = OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT;
                        break;
                    case 105:     // 平台财务审核不通过
                        $condition['worker_order_status'][] = OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT;
                        //                    $is_admin && $service_admin_ids && $where['returnee_id'] = ['IN', $service_admin_ids];
                        break;
                    case 110:     // 待客厂家财务审核
                        $condition['worker_order_status'][] = OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT;
                        break;
                    case 120:     // 已完结
                        $condition['worker_order_status'][] = OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
                        break;
                    case 130:     // 厂家财务审核不通过
                        $condition['worker_order_status'][] = OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT;
                        break;
                    case 140:     // 厂家自行处理
                        $condition['worker_order_status'][] = OrderService::STATUS_FACTORY_SELF_PROCESSED;
                        break;
                    case 150:     // 已取消
                        $condition['cancel_status'][] =OrderService::CANCEL_TYPE_WX_USER;
                        $condition['cancel_status'][] =OrderService::CANCEL_TYPE_WX_DEALER;
                        $condition['cancel_status'][] =OrderService::CANCEL_TYPE_FACTORY;
                        $condition['cancel_status'][] =OrderService::CANCEL_TYPE_CS;
                        $condition['cancel_status'][] =OrderService::CANCEL_TYPE_FACTORY_ADMIN;
                        break;
                    case 160:     // 厂家取消
                        $condition['cancel_status'][] = OrderService::CANCEL_TYPE_FACTORY;
                        $condition['cancel_status'][] = OrderService::CANCEL_TYPE_FACTORY_ADMIN;
                        break;
                    case 170:     // 客服取消
                        $condition['cancel_status'][] = OrderService::CANCEL_TYPE_CS;
                        break;
                    case 180:     // 用户取消
//                        $condition['cancel_status'][] = ['IN', [OrderService::CANCEL_TYPE_WX_USER, OrderService::CANCEL_TYPE_WX_DEALER]];
                        $condition['cancel_status'][] = OrderService::CANCEL_TYPE_WX_USER;
                        $condition['cancel_status'][] = OrderService::CANCEL_TYPE_WX_DEALER;
                        break;
                    case 190:   // 待审核易码工单/待厂家审核下单
                        $condition['worker_order_status'][] = OrderService::STATUS_CREATED;
                        break;
                    default:
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态错误,请检查~');
                }
            }
            if (!empty($condition['worker_order_status']) && !empty($condition['cancel_status'])) {  //当两个状态下都存在时候
                $condition['worker_order_status']= implode(',', $condition['worker_order_status']);
                $string_worker_order = "worker_order_status IN ({$condition['worker_order_status']})";

                $condition['cancel_status'] =  implode(',', $condition['cancel_status']);
                $string_cancel = "cancel_status IN ({$condition['cancel_status']})";
                $where_string[] = "($string_worker_order and cancel_status = 0) or $string_cancel";

            } elseif (!empty($condition['worker_order_status'])) {  //如果只有worker_order_status状态下
                $condition['worker_order_status']= implode(',', $condition['worker_order_status']);
                $string_worker_order = "worker_order_status IN ({$condition['worker_order_status']})";
                $where_string[] = "$string_worker_order and cancel_status = 0";

            } elseif (!empty($condition['cancel_status'])){//如果只有cancel_status状态下
                $condition['cancel_status'] =  implode(',', $condition['cancel_status']);
                $string_cancel = "cancel_status IN ({$condition['cancel_status']})";
                $where_string[] = "$string_cancel";
            }


            $sub_query = BaseModel::getInstance('worker_order');
            $sub_query_str = $sub_query->field('id')
                ->where($where_string)->buildSql();
            $where['worker_order_id'][] = ['exp', "in ({$sub_query_str})"];
        }

        if (!empty($factory_group_ids)) {
            $factory_where = ['group_id' => ['in', $factory_group_ids]];
            $factory_ids = BaseModel::getInstance('factory')
                ->getFieldVal($factory_where, 'factory_id', true);
            $in = empty($factory_ids) ? '-1' : implode(',', $factory_ids);
            $where['worker_order_id'][] = ['exp', "in (select id from worker_order where factory_id in ({$in}))"];
        }
        if (!empty($admin_ids)) {
            $where['admin_id'] = ['in', $admin_ids];
        } else {
            $group_admin_ids = (new AdminGroupLogic())->getManageGroupAdmins($param['admin_group_id'] ? [$param['admin_group_id']] : []);
            $group_admin_ids && $where['admin_id'] = ['in', $group_admin_ids];
        }

        if ($date_from > 0) {
            $where['create_time'][] = ['gt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }

        if ($fee_from > 0) {
            $where['apply_fee'][] = ['egt', $fee_from];
        }
        if ($fee_to > 0) {
            $where['apply_fee'][] = ['elt', $fee_to];
        }

        $model = BaseModel::getInstance($this->tableName);

        if (1 == $is_export) {
            $export_opts = ['where' => $where];
            (new ExportLogic())->adminAllowance($export_opts);
        } else {
            $opts = [
                'field' => 'id,admin_id,auditor_id,apply_fee,apply_remark,'
                    . 'create_time,status,check_time,type,worker_order_id',
                'where' => $where,
                'order' => 'id desc',
                'limit' => $limit,
            ];

            $cnt = $model->getNum($where);
            $sum_where = $where;
            $sum_where['status'][] = ['in', [AllowanceService::STATUS_UNCHECKED, AllowanceService::STATUS_PASS]];
            $sum = $model->getSum($sum_where, 'apply_fee')?? 0;

            $opts['limit'] = $limit;
            $list = $model->getList($opts);
            
            $worker_order_ids = [];
            $admin_ids = [];

            foreach ($list as $key => $val) {
                $worker_order_id = $val['worker_order_id'];
                $admin_id = $val['admin_id'];
                $auditor_id = $val['auditor_id'];

                $worker_order_ids[] = $worker_order_id;
                $admin_ids[] = $admin_id;
                $admin_ids[] = $auditor_id;
            }

            $worker_orders = $this->getWorkerOrders($worker_order_ids);
            $admins = $this->getAdmins($admin_ids);

            foreach ($list as $key => $val) {
                $worker_order_id = $val['worker_order_id'];
                $admin_id = $val['admin_id'];
                $auditor_id = $val['auditor_id'];
                $check_time = $val['check_time'];

                $val['order'] = $worker_orders[$worker_order_id]?? null;
                $val['admin'] = $admins[$admin_id]?? null;
                $val['auditor'] = $admins[$auditor_id]?? null;
                $val['check_time'] = $check_time > 0 ? $check_time : null;

                $list[$key] = $val;
            }

            return [
                'data'      => $list,
                'cnt'       => $cnt,
                'total_fee' => $sum,
            ];
        }
    }

    protected function getWorkerOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,orno,worker_order_status,cancel_status,distributor_id';
        $where = ['id' => ['in', $worker_order_ids]];
        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_id = $val['id'];
            $worker_order_status = $val['worker_order_status'];
            $cancel_status = $val['cancel_status'];

            $val['status_str'] = OrderService::getStatusStr($worker_order_status, $cancel_status);

            $data[$worker_order_id] = $val;
        }

        return $data;
    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $admin_id = $val['id'];

            $data[$admin_id] = $val;
        }

        return $data;
    }

    /**
     * 批量审核
     *
     * @param $param
     */
    public function statusBatch($param)
    {
        $allow_ids = $param['allow_ids'];
        $allow_ids = array_filter($allow_ids);

        $status = $param['status'];
        $remark = $param['remark'];

        if (empty($allow_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        $valid_status = [AllowanceService::STATUS_PASS, AllowanceService::STATUS_NOT_PASS];
        if (!in_array($status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();
        $role_id = $admin['role_id'];

        //权限
//        $root = AdminRoleService::getRoleRoot();
//        $admin_root = AdminRoleService::getRoleAdminRoot();
//        $valid_role = array_merge($admin_root, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户角色没有权限审核');
//        }

        $model = BaseModel::getInstance($this->tableName);

        $field = 'id,status,worker_order_id,apply_fee';
        $where = ['id' => ['in', $allow_ids]];
        $list = $model->getList($where, $field);

        if (empty($list)) {
            $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
        }

        //工单状态
        $distribute = OrderService::getOrderDistribute();
        $appoint = OrderService::getOrderAppoint();
        $visit = OrderService::getOrderVisit();
        $return = OrderService::getOrderReturn();
        $platform_audit = OrderService::getOrderPlatformAudit();
        $valid_order_status = array_merge($distribute, $appoint, $visit, $return, $platform_audit, [OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]);

        $worker_order_ids = [];

        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];

            $worker_order_ids[] = $worker_order_id;
        }

        $orders = $this->getWorkerOrders($worker_order_ids);

        foreach ($list as $val) {
            $cur_status = $val['status'];
            $worker_order_id = $val['worker_order_id'];

            //检查工单状态
            if (empty($orders[$worker_order_id])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单不存在');
            }

            $order = $orders[$worker_order_id];
            $worker_order_status = $order['worker_order_status'];
            $cancel_status = $order['cancel_status'];

            //工单状态校验
            if (OrderService::CANCEL_TYPE_NULL != $cancel_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消');
            }
            if (!in_array($worker_order_status, $valid_order_status)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态异常');
            }
            //补贴单状态校验
            if (AllowanceService::STATUS_UNCHECKED != $cur_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '您选择批量审核的补贴单部分已审核，请重现选择');
            }
        }

        $where = ['id' => ['in', $allow_ids]];
        $update_data = [
            'auditor_id'   => $admin_id,
            'status'       => $status,
            'check_time'   => NOW_TIME,
            'check_remark' => $remark,
        ];

        $model->update($where, $update_data);

        $chg_money = [];

        //系统消息
        $opts = [];
        $sys_msg_tpl = '工单号%s申请补贴%s元，审核';
        $sys_type = 0;
        if (AllowanceService::STATUS_PASS == $status) {
            $sys_msg_tpl .= "通过";
            $sys_type = SystemMessageService::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_PASS;
        } else {
            $sys_msg_tpl .= "不通过";
            $sys_type = SystemMessageService::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_FORBIDDEN;
        }
        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];
            $apply_fee = $val['apply_fee'];
            $id = $val['id'];

            $chg_money[$worker_order_id] = ($chg_money[$worker_order_id]?? 0) + $apply_fee;

            //检查工单状态
            $order = $orders[$worker_order_id];
            $distributor_id = $order['distributor_id'];
            $orno = $order['orno'];

            $opts[] = [
                'receiver_id' => $distributor_id,
                'content' => sprintf($sys_msg_tpl, $orno, $apply_fee),
                'data_id' => $worker_order_id
            ];
        }

        SystemMessageService::createMany(SystemMessageService::USER_TYPE_ADMIN, $sys_type, $opts);

        //结算
        if (AllowanceService::STATUS_PASS == $status) {
            //结算
            $order_fees = $this->getWorkerOrderFees($worker_order_ids);

            foreach ($chg_money as $worker_order_id => $apply_fee) {
                $fee = $order_fees[$worker_order_id]['worker_allowance_fee'] + $apply_fee;
                $fee_modify = $order_fees[$worker_order_id]['worker_allowance_fee_modify'] + $apply_fee;
                $update_data = [
                    'worker_allowance_fee'        => $fee,
                    'worker_allowance_fee_modify' => $fee_modify,
                ];
                OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, $update_data);
            }
        }
    }

    protected function getWorkerOrderFees($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('worker_order_fee');

        $where = ['worker_order_id' => ['in', $worker_order_ids]];
        $field = 'worker_order_id,worker_allowance_fee,worker_allowance_fee_modify';
        $list = $model->getList($where, $field);

        $data = [];

        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        return $data;

    }

    public function status($param)
    {
        $allow_id = $param['allow_id'];
        $check_status = $param['status'];
        $remark = $param['remark'];

        if (empty($allow_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $valid_status = [AllowanceService::STATUS_PASS, AllowanceService::STATUS_NOT_PASS];
        if (!in_array($check_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();
        $role_id = $admin['role_id'];

//        $root = AdminRoleService::getRoleRoot();
//        $admin_root = AdminRoleService::getRoleAdminRoot();
//        $valid_role = array_merge($admin_root, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户角色没有权限审核');
//        }

        $model = BaseModel::getInstance($this->tableName);

        $field = 'status,worker_order_id,apply_fee';
        $info = $model->getOneOrFail($allow_id, $field);
        $status = $info['status'];
        $worker_order_id = $info['worker_order_id'];
        $apply_fee = $info['apply_fee'];

        //获取工单
        $field = 'worker_order_status,orno,distributor_id';
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order['distributor_id'];
        $orno = $order['orno'];

        $distribute = OrderService::getOrderDistribute();
        $appoint = OrderService::getOrderAppoint();
        $visit = OrderService::getOrderVisit();
        $return = OrderService::getOrderReturn();
        $platform_audit = OrderService::getOrderPlatformAudit();

        $worker_order_status = $order['worker_order_status'];
        $valid_order_status = array_merge($distribute, $appoint, $visit, $return, $platform_audit, [OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]);
        if (!in_array($worker_order_status, $valid_order_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态异常');
        }

        if (AllowanceService::STATUS_UNCHECKED != $status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '补贴单状态异常');
        }

        $where = [
            'id'     => $allow_id,
            'status' => AllowanceService::STATUS_UNCHECKED,
        ];
        $update_data = [
            'auditor_id'   => $admin_id,
            'status'       => $check_status,
            'check_time'   => NOW_TIME,
            'check_remark' => $remark,
        ];

        $model->update($where, $update_data);

        $sys_msg = "工单号{$orno}申请补贴".sprintf('%.2f', $apply_fee)."元，审核";
        $sys_type = 0;
        if (AllowanceService::STATUS_PASS == $check_status) {
            $sys_msg .= "通过";
            $sys_type = SystemMessageService::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_PASS;
        } else {
            $sys_msg .= "不通过";
            $sys_type = SystemMessageService::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_FORBIDDEN;
        }
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, $sys_msg, $worker_order_id, $sys_type);

        if (AllowanceService::STATUS_PASS == $check_status) {
            $order_fees = $this->getWorkerOrderFees([$worker_order_id]);

            $fee = $order_fees[$worker_order_id]['worker_allowance_fee'] + $apply_fee;
            $fee_modify = $order_fees[$worker_order_id]['worker_allowance_fee_modify'] + $apply_fee;
            $update_data = [
                'worker_allowance_fee'        => $fee,
                'worker_allowance_fee_modify' => $fee_modify,
            ];
            OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, $update_data);

        }
    }

    public function info($param)
    {
        $allow_id = $param['allow_id'];

        if (empty($allow_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $field = 'admin_id,auditor_id,worker_order_id,type,apply_fee,apply_remark,create_time,status,check_time,check_remark';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($allow_id, $field);
        $worker_order_id = $info['worker_order_id'];
        $admin_id = $info['admin_id'];
        $auditor_id = $info['auditor_id'];

        $admins = $this->getAdmins([$admin_id, $auditor_id]);
        $orders = $this->getWorkerOrders([$worker_order_id]);

        $info['order'] = $orders[$worker_order_id]?? null;
        $info['admin'] = $admins[$admin_id]?? null;
        $info['auditor'] = $admins[$auditor_id]?? null;

        return $info;
    }

    public function add($param)
    {
        $type = $param['type'];
        $apply_fee = $param['apply_fee'];
        $remark = $param['remark'];
        $worker_order_id = $param['worker_order_id'];

        if (empty($apply_fee) || $worker_order_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if ($apply_fee <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '输入金额异常');
        }
        $valid_type = [AllowanceService::TYPE_ADJUST_APPOINT_FEE, AllowanceService::TYPE_ADJUST_REPAIR_FEE, AllowanceService::TYPE_ORDER_AWARD];
        if (!in_array($type, $valid_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '类型错误');
        }

        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id);
        $worker_order_status = $order['worker_order_status'];
        $worker_order_type = $order['worker_order_type'];

        $distribute = OrderService::getOrderDistribute();
        $appoint = OrderService::getOrderAppoint();
        $visit = OrderService::getOrderVisit();
        $return = OrderService::getOrderReturn();

        $valid_order_status = array_merge($distribute, $appoint, $visit, $return, [OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]);
        if (!in_array($worker_order_status, $valid_order_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单对应状态不允许提交补贴单');
        }

        $valid_order_type = [OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE, OrderService::ORDER_TYPE_FACTORY_EXPORT_IN_INSURANCE, OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE];
        if (!in_array($worker_order_type, $valid_order_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '不是保内单');
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();
        $role_id = $admin['role_id'];

//        $root_role = AdminRoleService::getRoleRoot();
//        $admin_role = AdminRoleService::getRoleAdminRoot();
//        $distributor = AdminRoleService::getRoleDistributor();
//
//        $valid_role = array_merge($distributor, $admin_role, $root_role);
//
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $model = BaseModel::getInstance($this->tableName);
        $insert_data = [
            'admin_id'         => $admin_id,
            'type'             => $type,
            'apply_fee'        => $apply_fee,
            'apply_fee_modify' => $apply_fee,
            'apply_remark'     => $remark,
            'create_time'      => NOW_TIME,
            'worker_order_id'  => $worker_order_id,
            'status'           => AllowanceService::STATUS_UNCHECKED,
        ];
        $model->insert($insert_data);

        //工单日志
        $type_str = AllowanceService::getTypeStr($type);
        $remark = strlen($remark) > 0 ? '，' . $remark : '';
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_ALLOWANCE_APPLY, ['remark' => $type_str . $apply_fee . '元' . $remark]);

        //统计
        $stats = [
            'allowance_order_num' => ['exp', 'allowance_order_num+1'],
        ];
        BaseModel::getInstance('worker_order_statistics')
            ->update($worker_order_id, $stats);

    }

    public function history($param)
    {
        $worker_order_id = $param['worker_order_id'];

        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $where = ['worker_order_id' => $worker_order_id];
        $model = BaseModel::getInstance($this->tableName);
        $field = 'id,admin_id,auditor_id,apply_fee,apply_remark,create_time,status,check_time,type,check_remark,worker_order_id';
        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'id desc',
        ];
        $list = $model->getList($opts);

        $worker_order_ids = [];
        $admin_ids = [];

        foreach ($list as $val) {
            $order_id = $val['worker_order_id'];
            $admin_id = $val['admin_id'];
            $auditor_id = $val['auditor_id'];

            $worker_order_ids[] = $order_id;
            $admin_ids[] = $admin_id;
            $admin_ids[] = $auditor_id;
        }

        $worker_orders = $this->getWorkerOrders($worker_order_ids);
        $admins = $this->getAdmins($admin_ids);

        foreach ($list as $key => $val) {
            $admin_id = $val['admin_id'];
            $auditor_id = $val['auditor_id'];
            $check_time = $val['check_time'];
            $order_id = $val['worker_order_id'];

            $val['order'] = $worker_orders[$order_id]?? null;
            $val['admin'] = $admins[$admin_id]?? null;
            $val['auditor'] = $admins[$auditor_id]?? null;
            $val['check_time'] = $check_time > 0 ? $check_time : null;

            $list[$key] = $val;
        }

        $sum_where = [
            'status'          => ['in', [AllowanceService::STATUS_UNCHECKED, AllowanceService::STATUS_PASS]],
            'worker_order_id' => $worker_order_id,
        ];
        $total_fee = $model->getSum($sum_where, 'apply_fee');
        $total_fee = $total_fee?? 0;

        return [
            'list'      => $list,
            'total_fee' => $total_fee,
        ];

    }


}