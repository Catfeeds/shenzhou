<?php
/**
 * File: OrderLogic.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 11:34
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkbenchEvent;
use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\Repositories\Events\WorkerReceiveOrderEvent;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AllowanceService;
use Common\Common\Service\ApplyCostService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\AreaService;
use Common\Common\Service\ComplaintService;
use Common\Common\Service\CostService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\GroupService;
use Common\Common\Service\OrderMessageService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\SMSService;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Common\Common\Service\UserTypeService;
use Common\Common\Service\WorkerAddApplyService;
use Common\Common\Service\WorkerMoneyRecordService;

use Common\Common\Service\WorkerService;

use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;

use EasyWeChat\Payment\Order;
use Illuminate\Support\Arr;
use Common\Common\Service\AuthService;
use Library\Common\ExcelExport;
use Library\Common\Util;

class OrderLogic extends BaseLogic
{
    const ORDER_PRODUCT_TABLE_NAME = 'worker_order_product';
    const PRODUCT_FAULT_TABLE_NAME = 'product_fault';
    // 技工 维修金（收入）记录 类型：0 工单结算技工自动收入钱包；1 平台客服手动调整质保金
    const WORKER_REPAIR_MONEY_RECORD_SETTLMENT_TYPE = 0;
    const WORKER_REPAIR_MONEY_RECORD_ADMIN_TYPE     = 1;

    // 工单完结处理，工单的技工信誉计算
    public function workerOrderCompleteReputation($order_id = 0, $order = [])
    {
        $model = BaseModel::getInstance('worker_order');
        if (!$order['id']) {
            $order = $model->getOneOrFail($order_id, 'worker_id,worker_first_appoint_time,worker_receive_time,worker_first_sign_time, worker_group_id, children_worker_id');
        }
        $factory_audit_time = NOW_TIME;

        $reput_model = BaseModel::getInstance('worker_order_reputation');
        $worker_reputation = $reput_model->getOneOrFail([
            'field' => 'id,revcode_fraction,quality_standard_fraction,repair_nums_fraction',
            'where' => [
                'worker_order_id' => $order_id,
                'worker_id' => $order['worker_id'],
            ],
        ]);

        $last_renturn_accessory = BaseModel::getInstance('worker_order_apply_accessory')
            ->getOne([
                'where' => [
                    // 'is_giveup_sendback' => 0,
                    'is_giveup_return' => 0,
                    'worker_order_id'    => $order_id,
                ],
                'order' => 'worker_return_time DESC',
            ]);

        // 快速预约 第一次预约时间 - 接单时间
        $first_appoint_min = timediff($order['worker_receive_time'], $order['worker_first_appoint_time']);
        // 快速上门时间差   第一次上门时间 - 接单时间
        $first_arrive_hour = timediff($order['worker_receive_time'], $order['worker_first_sign_time'], 'hour');
        // 按时上门   第一次上门时间 - 第一次预约上门时间
        $is_ontime_min = timediff($order['worker_first_appoint_time'], $order['worker_first_sign_time']);
        // 按时返件   完成时间 - 最后返件时间
        // $return_acce_hour = timediff($order['factory_audit_time'], $last_renturn_accessory['worker_return_time'], 'hour');
        $return_acce_hour = timediff($last_renturn_accessory['worker_return_time'], $factory_audit_time, 'hour');
        $times_config = [
            'appiont_time' => $first_appoint_min,
            'arrive_time'  => $first_arrive_hour,
            'ontime_time'  => $is_ontime_min,
            'return_time'  => $return_acce_hour,
        ];


        $reputation_update = [
            'totals'      => 0,
            'is_complete' => 1,
            'is_return'   => 0,
        ];
        // C('WORKER_REPUTATION_CONFING_TIME')  TODO 结构设计不够合理
        $c_arr = array_keys(C('WORKER_REPUTATION_CONFING_TIME'));
        $c_where = ['name' => ['in', implode(',', $c_arr)]];
//        $c_where['type'] = 1;
        $config = BaseModel::getInstance('admin_config')->getList([
            'field' => 'name,value,type',
            'where' => $c_where,
//            'order' => 'value asc',
            'index' => 'name',
        ]);
        foreach (C('WORKER_REPUTATION_CONFING_TIME') as $k => $v) {
//        foreach ($config as $k => $v) {
            if (isset($reputation_update[$v['time_field']])) {
                continue;
            }
            $value = $times_config[$v['time_field']];
            if ($value < $config[$k]['value']) {
                $reputation_update[$v['time_field']] = $value;
                $reputation_update[$v['score_field']] = $v['score'];
                $reputation_update['totals'] += $v['score'];
            }
        }

        // 服务码得分
        $service_evaluate_fraction = 0;
        $service_evaluate_score = ['A' => 10, 'B' => 5];
        $ext_info = BaseModel::getInstance('worker_order_ext_info')->getOne($order_id, 'service_evaluate');

            isset($service_evaluate_score[$ext_info['service_evaluate']])
        && $service_evaluate_fraction = $service_evaluate_score[$ext_info['service_evaluate']];
        // 服务质量   服务码得分 + 回访服务得分 + 服务规范统计 + 维修质量统计(维修次数得分)
        $service_fraction = $service_evaluate_fraction +
            $worker_reputation['revcode_fraction'] +
            $worker_reputation['quality_standard_fraction'] +
            $worker_reputation['repair_nums_fraction'];

        //总分 = 快速预约得分 + 快速上门得分 + 按时上门得分 + 配件返还得分 + 服务质量分 
        $reputation_update['totals'] += $service_fraction;
        if (!empty($order['worker_group_id'])) {
            //群内工单
            $reputation_update['cp_worker_type'] = GroupService::WORKER_TYPE_GROUP_OWNER;
            //更新群信誉总分
            $group_model = BaseModel::getInstance('worker_group');
            $group_reputation_total = $group_model->getFieldVal($order['worker_group_id'], 'reputation_total');
            $group_update['reputation_total'] = $group_reputation_total + $service_fraction;
            $group_model->update([
                'id' => $order['worker_group_id']
            ], $group_update);
        } else {
            $reputation_update['cp_worker_type'] = GroupService::WORKER_TYPE_ORDINARY_WORKER;
        }

        $reput_model->update($worker_reputation['id'], $reputation_update);

        //如果是技工子账号
        if (!empty($order['worker_group_id']) && $order['worker_id'] != $order['children_worker_id']) {

            //更新子账号信誉总分
            $children_worker_reputation = $reput_model->getOne([
                'field' => 'id,revcode_fraction,quality_standard_fraction,repair_nums_fraction',
                'where' => [
                    'worker_order_id' => $order_id,
                    'worker_id'       => $order['children_worker_id'],
                ],
            ]);
            $children_service_fraction = $service_evaluate_fraction +
                $children_worker_reputation['revcode_fraction'] +
                $children_worker_reputation['quality_standard_fraction'] +
                $children_worker_reputation['repair_nums_fraction'];
            $reputation_update['totals'] = $reputation_update['totals'] + $children_service_fraction - $service_fraction;
            $reputation_update['cp_worker_type'] = GroupService::WORKER_TYPE_GROUP_MEMBER;
            $reput_model->update($children_worker_reputation['id'], $reputation_update);
        }

        return true;
    }

    // 工单费用结算至技工钱包 并写入 技工收入记录
    public function workerOrderSettlementForWorkerById($order_id = 0, $remark = '', $order = [])
    {
        $model = BaseModel::getInstance('worker_order');
        if (!$order['id'] || $order['id'] != $order_id) {
            $order = $model->getOneOrFail($order_id, 'worker_id,factory_id,worker_order_type,worker_order_status,is_worker_pay');
        }

        $is_in_order = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;

        $worker_model = BaseModel::getInstance('worker');
        $worker = $worker_model->getOneOrFail($order['worker_id'], 'worker_id,money,quality_money,quality_money_need');

        $fee_data = OrderSettlementService::statisticsUpdateQualityFeeAndGet($order_id);

        $last_money = number_format($worker['money'] + $fee_data['worker_net_receipts'], 2, '.', '');
        $last_quality_money = number_format($worker['quality_money'] + $fee_data['quality_fee'], 2, '.', '');

        // 技工钱包
        $worker_model->update($order['worker_id'], [
            'money'         => $last_money,
            'quality_money' => $last_quality_money,
        ]);

        // 技工钱包记录写入
        $add = [
            'worker_order_id'    => $order_id,
            'worker_id'          => $order['worker_id'],
            'factory_id'         => $order['factory_id'],
            'order_money'        => $fee_data['worker_total_fee_modify'],
            'netreceipts_money'  => $fee_data['worker_net_receipts'],
            'insurance_fee'      => $fee_data['insurance_fee'],
            'quality_money'      => $fee_data['quality_fee'],
            'last_money'         => $last_money,
            'last_quality_money' => $last_quality_money,
            'create_time'        => NOW_TIME,
        ];
        BaseModel::getInstance('worker_repair_money_record')->insert($add);
//        if (ceil($fee_data['quality_fee'])) { //$fee_data['quality_fee'] != 0
        if (abs($fee_data['quality_fee']) > 0) { //$fee_data['quality_fee'] != 0
            // worker_quality_money_record
            BaseModel::getInstance('worker_quality_money_record')->insert([
                'worker_id'          => $order['worker_id'],
                'admin_id'           => AuthService::getAuthModel()->getPrimaryValue(),
                'worker_order_id'    => $order_id,
                'type'               => self::WORKER_REPAIR_MONEY_RECORD_SETTLMENT_TYPE,
                'quality_money'      => $fee_data['quality_fee'],
                'last_quality_money' => $last_quality_money,
                'remark'             => '',
                'create_time'        => NOW_TIME,
            ]);
        }
        BaseModel::getInstance('worker_money_record')->insert([
                'worker_id'     => $worker['worker_id'],
                'type'          => $is_in_order ? WorkerMoneyRecordService::TYPE_REPAIR_INCOME : WorkerMoneyRecordService::TYPE_REPAIR_OUT_ORDER,
                'data_id'       => $order_id,
                'money'         => $fee_data['worker_net_receipts'],
                'last_money'    => $last_money,
                'create_time'   => NOW_TIME,
            ]);
    }

    // 群内工单费用结算至技工钱包 并写入 技工收入记录
    public function groupWorkerOrderSettlementForWorkerById($order_id = 0, $remark = '', $order = [])
    {
        $model = BaseModel::getInstance('worker_order');
        if (!$order['id'] || $order['id'] != $order_id) {
            $order = $model->getOneOrFail($order_id, 'worker_id, factory_id, worker_order_type, worker_order_status, worker_group_id, children_worker_id');
        }
        if (empty($order['worker_group_id']) || empty($order['children_worker_id']) || $order['worker_id'] == $order['children_worker_id']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '非群内工单或着群主没有把工单派发给群成员，请勿用这个方法结算');
        }

        $is_in_order = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;

        //计算质保金
        $quality_money = OrderSettlementService::groupOrderQualityFeeSettlement($order_id);

        $fee_model = BaseModel::getInstance('worker_order_fee');
        $fee_data = $fee_model->getOneOrFail($order_id, 'worker_total_fee_modify,insurance_fee,worker_net_receipts, cp_worker_proportion');
        $worker_proportion = $fee_data['cp_worker_proportion'];

        //群主
        $worker_model = BaseModel::getInstance('worker');
        $worker = $worker_model->getOneOrFail($order['worker_id'], 'worker_id, money, quality_money,quality_money_need');
        $worker_total_fee_modify = $fee_data['worker_total_fee_modify'] * (1 - $worker_proportion / 10000);
        $worker_money = $worker['money'] + $worker_total_fee_modify;
        $worker_last_money = number_format($worker_money, 2, '.', '');
        $worker_last_quality_money = number_format($worker['quality_money'] + $quality_money['worker_quality_fee'], 2, '.', '');
        // 技工钱包
        $worker_model->update($order['worker_id'], [
            'money'         => $worker_last_money,
            'quality_money' => $worker_last_quality_money,
        ]);
        // 技工钱包记录写入
        $add = [
            'worker_order_id'    => $order_id,
            'worker_id'          => $order['worker_id'],
            'factory_id'         => $order['factory_id'],
            'order_money'        => $worker_total_fee_modify,
            'netreceipts_money'  => $worker_total_fee_modify - $quality_money['worker_quality_fee'],
            'insurance_fee'      => $fee_data['insurance_fee'] * (1 - $worker_proportion / 10000),
            'quality_money'      => !empty($quality_money['worker_quality_fee']) ? $quality_money['worker_quality_fee'] : 0.00,
            'last_money'         => $worker_last_money,
            'last_quality_money' => $worker_last_quality_money,
            'create_time'        => NOW_TIME,
        ];
        BaseModel::getInstance('worker_repair_money_record')->insert($add);
//        if (ceil($fee_data['quality_fee'])) { //$fee_data['quality_fee'] != 0
        if (abs($quality_money['worker_quality_fee']) > 0) { //$fee_data['quality_fee'] != 0
            // worker_quality_money_record
            BaseModel::getInstance('worker_quality_money_record')->insert([
                'worker_id'          => $order['worker_id'],
                'admin_id'           => AuthService::getAuthModel()->getPrimaryValue(),
                'worker_order_id'    => $order_id,
                'type'               => self::WORKER_REPAIR_MONEY_RECORD_SETTLMENT_TYPE,
                'quality_money'      => $quality_money['worker_quality_fee'],
                'last_quality_money' => $worker_last_quality_money,
                'remark'             => '',
                'create_time'        => NOW_TIME,
            ]);
        }
        BaseModel::getInstance('worker_money_record')->insert([
            'worker_id'     => $order['worker_id'],
            'type'          => $is_in_order ? WorkerMoneyRecordService::TYPE_REPAIR_INCOME : WorkerMoneyRecordService::TYPE_REPAIR_OUT_ORDER,
            'data_id'       => $order_id,
            'money'         => $worker_total_fee_modify - $quality_money['worker_quality_fee'],
            'last_money'    => $worker_last_money,
            'create_time'   => NOW_TIME,
        ]);

        //群成员
        $children_worker = $worker_model->getOneOrFail($order['children_worker_id'], 'worker_id, money,quality_money, quality_money_need');
        $children_worker_total_fee_modify = $fee_data['worker_total_fee_modify'] * $worker_proportion / 10000;
        $children_worker_money = $children_worker['money'] + $children_worker_total_fee_modify;
        $children_worker_last_money = number_format($children_worker_money, 2, '.', '');
        $children_worker_last_quality_money = number_format($children_worker['quality_money'] + $quality_money['children_worker_quality_fee'], 2, '.', '');
        // 技工钱包
        $worker_model->update($order['children_worker_id'], [
            'money'         => $children_worker_last_money,
            'quality_money' => $children_worker_last_quality_money,
        ]);
        // 技工钱包记录写入
        $add = [
            'worker_order_id'    => $order_id,
            'worker_id'          => $order['children_worker_id'],
            'factory_id'         => $order['factory_id'],
            'order_money'        => $children_worker_total_fee_modify,
            'netreceipts_money'  => $children_worker_total_fee_modify - $quality_money['children_worker_quality_fee'],
            'insurance_fee'      => $fee_data['insurance_fee']  * $worker_proportion / 10000,
            'quality_money'      => !empty($quality_money['children_worker_quality_fee']) ? $quality_money['children_worker_quality_fee'] : 0.00,
            'last_money'         => $children_worker_last_money,
            'last_quality_money' => $children_worker_last_quality_money,
            'create_time'        => NOW_TIME,
        ];
        BaseModel::getInstance('worker_repair_money_record')->insert($add);
//        if (ceil($fee_data['quality_fee'])) { //$fee_data['quality_fee'] != 0
        if (abs($quality_money['children_worker_quality_fee']) > 0) { //$fee_data['quality_fee'] != 0
            // worker_quality_money_record
            BaseModel::getInstance('worker_quality_money_record')->insert([
                'worker_id'          => $order['children_worker_id'],
                'admin_id'           => AuthService::getAuthModel()->getPrimaryValue(),
                'worker_order_id'    => $order_id,
                'type'               => self::WORKER_REPAIR_MONEY_RECORD_SETTLMENT_TYPE,
                'quality_money'      => $quality_money['children_worker_quality_fee'],
                'last_quality_money' => $children_worker_last_quality_money,
                'remark'             => '',
                'create_time'        => NOW_TIME,
            ]);
        }
        BaseModel::getInstance('worker_money_record')->insert([
            'worker_id'     => $order['children_worker_id'],
            'type'          => $is_in_order ? WorkerMoneyRecordService::TYPE_REPAIR_INCOME : WorkerMoneyRecordService::TYPE_REPAIR_OUT_ORDER,
            'data_id'       => $order_id,
            'money'         => $children_worker_total_fee_modify - $quality_money['children_worker_quality_fee'],
            'last_money'    => $children_worker_last_money,
            'create_time'   => NOW_TIME,
        ]);
    }

    // 计算该工单技工的该工单质保金
    public function workerQualityFeeById($order_id = '', $order = [])
    {
        if (!$order['id']) {
            $order = BaseModel::getInstance('worker_order')
                ->getOneOrFail($order_id, 'worker_id,worker_order_type');
        }
        // $worker = BaseModel::getInstance('worker')->getOneOrFail($order['worker_id'], 'quality_money_need,quality_money');
        $worker = BaseModel::getInstance('worker')
            ->getOne($order['worker_id'], 'quality_money_need,quality_money');

        $model = BaseModel::getInstance('worker_order_fee');
        $fees = $model->getOneOrFail($order_id, 'worker_total_fee_modify');

        // 保外单质保金为0;保内单计算质保金：还需结质保金 = 技工共应结质保金 - 已结质保金、工单应结质保金 = 技工应结工单费用 * 0.1
        $update = ['quality_fee' => '0.00'];
        if (in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $worker_quality = $worker['quality_money_need'] - $worker['quality_money'];
            $order_quality = $fees['worker_total_fee_modify'] * 0.1;
            $quality_fee = ($worker_quality - $order_quality) > 0 ? $order_quality : $worker_quality;

            $update['quality_fee'] = number_format($quality_fee, 2, '.', '');
        }

        $update && $model->update($order_id, $update);

        return $update['quality_fee'];
    }

    public function getList()
    {

        $auth_model = AuthService::getModel();
        $opts = $this->getSearchOrderOpts();
        $where = $opts['where'];
        $join = $opts['join'];

        $is_export = I('is_export', 0, 'intval');

        $worker_order_table = $opts['index'] ? "worker_order force index({$opts['index']})" : 'worker_order';
        if (1 == $is_export) {
            $export_opts = ['where' => $where, 'join' => $join, 'alias' => 'worker_order'];
            (new ExportLogic())->adminOrder($export_opts);
        } else {
            $field = 'worker_order.id,worker_order.orno,worker_order.worker_id,worker_order.factory_id,checker_id,distributor_id,returnee_id,auditor_id,worker_order_status,worker_order_type,worker_order.service_type,worker_order.create_time,worker_order.cancel_status,origin_type,add_id,children_worker_id';
            $opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'order' => 'id DESC',
                'limit' => getPage(),
            ];

            $orders = BaseModel::getInstance($worker_order_table)->getList($opts);

            OrderService::loadAddUserInfo($orders);
//            $key_out_fees = $is_not_insurance_ids = [];
            foreach ($orders as $key => $order) {
                $orders[$key]['worker_order_status_name'] = OrderService::getStatusStr($order['worker_order_status'], $order['cancel_status']);
//                !in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST) && $is_not_insurance_ids[] = $order['id'];
            }
//            $is_not_insurance_ids = array_unique($is_not_insurance_ids);

            $order_id_map = [];
            $order_ids = [];
            $admin_ids = [];
            $worker_ids = [];
            $children_worker_ids = [];
            $factory_ids = [];
            foreach ($orders as $order) {
                $order_id_map[$order['id']] = $order;
                $order_ids[] = $order['id'];
                $worker_ids[] = $order['worker_id'];
                $children_worker_ids[] = $order['children_worker_id'];
                $factory_ids[] = $order['factory_id'];

                // 处理客服ID
                $order['checker_id'] && $admin_ids[] = $order['checker_id'];
                $order['distributor_id'] && $admin_ids[] = $order['distributor_id'];
                $order['returnee_id'] && $admin_ids[] = $order['returnee_id'];
                $order['auditor_id'] && $admin_ids[] = $order['auditor_id'];
            }

            // 处理客服列表
            if ($auth_model == AuthService::ROLE_ADMIN) {
                $admin_id_map = $admin_ids ? BaseModel::getInstance('admin')
                    ->getList([
                        'field' => 'id,nickout',
                        'where' => [
                            'id' => ['IN', $admin_ids],
                        ],
                        'index' => 'id',
                    ]) : [];
            }
            if (!$order_ids) {
                return [
                    'num'  => 0,
                    'list' => [],
                ];
            }

            // 工单统计信息
            $worker_order_id_statistics_map = BaseModel::getInstance('worker_order_statistics')
                ->getList([
                    'field' => 'worker_order_id,total_accessory_num,cost_order_num,allowance_order_num,complaint_order_num,worker_add_apply_num,total_message_num',
                    'where' => [
                        'worker_order_id' => ['IN', $order_ids],
                    ],
                    'index' => 'worker_order_id',
                ]);

            $operation_record_where = [
                'worker_order_id' => ['IN', $order_ids],
            ];
            if ($auth_model != AuthService::ROLE_ADMIN) {
                $auth_type = OrderOperationRecordService::PERMISSION_FACTORY | OrderOperationRecordService::PERMISSION_FACTORY_ADMIN;
                $operation_record_where['_string'] = " (see_auth=0 or see_auth&{$auth_type}) ";
            }

            // 工单操作记录：按降序取出所有工单操作记录id按不同的维修工单id分组
//            // 保外单费用
//            $out_fees = $is_not_insurance_ids ? BaseModel::getInstance('worker_order_out_worker_add_fee')->getList([
//                'field' => 'worker_order_id,is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee_modify,pay_time',
//                'where' => [
//                    'worker_order_id' => ['in', $is_not_insurance_ids],
//                ],
//            ]) : [];
//            foreach ($out_fees as $k => $v) {
//                $id = $v['worker_order_id'];
//                $total_fee_modify = $v['total_fee_modify'];
//                unset($v['worker_order_id'], $v['total_fee_modify']);
//                $key_out_fees[$id]['total_fee'] += $total_fee_modify;
//                $v['pay_type'] != WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO && $v['pay_type']['pay_time'] &&  $key_out_fees[$id]['pay_total_fee'] += $total_fee_modify;
//                $key_out_fees[$id]['out_fees'][] = $v;
//            }
            // 工单操作记录
            $worker_order_operation_record = BaseModel::getInstance('worker_order_operation_record')
                ->getList([
                    'field' => 'group_concat(id order by id desc) gc',
                    'where' => $operation_record_where,
                    'group' => 'worker_order_id',
                ]);

            $worker_order_operation_ids = [];  //取出各维修工单的最新十条的工单操作记录id合并成一个一维数组
            foreach ($worker_order_operation_record as $key => $val) {
                $worker_order_operation_ids_maps [$key]= array_slice(explode(',', $val['gc']), 0, 10);  //把字符串转换成数组，本身是个二维数组。
                $worker_order_operation_ids = array_merge($worker_order_operation_ids, $worker_order_operation_ids_maps[$key]);  //合并成一维数组
            }

            $worker_order_operation_where = ['id' => ['IN',$worker_order_operation_ids]];

            if (!empty($worker_order_operation_ids)) {
                //查出上面按维修工单分组的工单操作记录的最新十条内容的所有内容
                $worker_order_operation_content = BaseModel::getInstance('worker_order_operation_record')
                    ->getList([
                        'field' => 'worker_order_id,content,create_time',
                        'where' => $worker_order_operation_where,
                        'order' => 'id DESC'
                    ]);

                $worker_order_id_operation_record_map = [];
                foreach ($worker_order_operation_content as $item) {  //分配到对应维修工单id的数组里
                    $worker_order_id_operation_record_map[$item['worker_order_id']][] = $item;
                }
            } else {
                $worker_order_id_operation_record_map = [];
            }
            // 工单产品
            $worker_order_products = BaseModel::getInstance('worker_order_product')
                ->getList([
                    'field' => 'worker_order_id,cp_category_name category_name,cp_product_brand_name product_brand_name,cp_product_standard_name product_stantard_name,cp_product_mode product_mode,product_nums,yima_code',
                    'where' => [
                        'worker_order_id' => ['IN', $order_ids],
                    ],
                ]);
            $worker_order_id_product_map = [];
            foreach ($worker_order_products as $worker_order_product) {
                $worker_order_id_product_map[$worker_order_product['worker_order_id']][] = $worker_order_product;
            }

            // 工单用户信息
            $order_id_user_info_map = BaseModel::getInstance('worker_order_user_info')
                ->getList([
                    'field' => 'worker_order_id,real_name,phone,cp_area_names area_names,address',
                    'where' => [
                        'worker_order_id' => ['IN', $order_ids],
                    ],
                    'index' => 'worker_order_id',
                ]);

            // 工单技工信息
            $worker_id_map = BaseModel::getInstance('worker')->getList([
                'field' => 'worker_id,nickname,worker_telephone',
                'where' => [
                    'worker_id' => ['in', $worker_ids],
                ],
                'index' => 'worker_id',
            ]);

            // 工单子账号技工信息
            if (!empty($children_worker_ids)) {
                $children_worker_id_map = BaseModel::getInstance('worker')->getList([
                    'field' => 'worker_id,nickname,worker_telephone',
                    'where' => [
                        'worker_id' => ['in', $children_worker_ids]
                    ],
                    'index' => 'worker_id',
                ]);
            }

            // 工单厂家信息
            $factory_id_map = BaseModel::getInstance('factory')->getList([
                'field' => 'factory_id,factory_full_name,linkman,group_id',
                'where' => [
                    'factory_id' => ['IN', $factory_ids],
                ],
                'index' => 'factory_id',
            ]);

            $factory_group = $list = (new FactoryLogic())->getFactoryGroup();
            $factory_group_map = Arr::pluck($factory_group, 'name', 'id');

            // 完善信息
            foreach ($orders as $key => $order) {
                // 完善客服信息
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $orders[$key]['admin'] = [
                        'checker'     => $admin_id_map[$order['checker_id']]['nickout'] ?? '',
                        'distributor' => $admin_id_map[$order['distributor_id']]['nickout'] ?? '',
                        'returnee'    => $admin_id_map[$order['returnee_id']]['nickout'] ?? '',
                        'auditor'     => $admin_id_map[$order['auditor_id']]['nickout'] ?? '',
                    ];
                }

                // 完善工单统计信息
                $orders[$key]['statistics'] = [
                    'has_accessory_order'  => $worker_order_id_statistics_map[$order['id']]['total_accessory_num'] ?? 0,
                    'has_cost_order'       => $worker_order_id_statistics_map[$order['id']]['cost_order_num'] ?? 0,
                    'has_subsidy_order'    => $worker_order_id_statistics_map[$order['id']]['allowance_order_num'] ?? 0,
                    'has_complaint_order'  => $worker_order_id_statistics_map[$order['id']]['complaint_order_num'] ?? 0,
                    'has_worker_add_apply' => $worker_order_id_statistics_map[$order['id']]['worker_add_apply_num'] ?? 0,
                    'has_message'          => $worker_order_id_statistics_map[$order['id']]['total_message_num'] ?? 0,
                    'has_cancel'           => in_array($order['cancel_status'], [OrderService::CANCEL_TYPE_WX_USER, OrderService::CANCEL_TYPE_WX_DEALER, OrderService::CANCEL_TYPE_FACTORY, OrderService::CANCEL_TYPE_CS]) ? '1' : '0',
                ];

                // 完善工单更新内容
                $orders[$key]['operations'] = $worker_order_id_operation_record_map[$order['id']];

                // 完善产品信息
                $orders[$key]['products'] = $worker_order_id_product_map[$order['id']];

                // 完善下单用户信息
                $orders[$key]['user'] = $order_id_user_info_map[$order['id']];

                // 完善技工信息
                $orders[$key]['worker'] = $worker_id_map[$order['worker_id']];
                $orders[$key]['worker']['children_worker_nickname'] = $children_worker_id_map[$order['children_worker_id']]['nickname'] ?? null;

                // 完善下单厂家信息
                $orders[$key]['factory'] = $factory_id_map[$order['factory_id']];
                $orders[$key]['factory']['group_name'] = $factory_id_map[$order['factory_id']]['group_id'] !== null ? $factory_group_map[$factory_id_map[$order['factory_id']]['group_id']] : '';

                $orders[$key]['accessory_color_type'] = $orders[$key]['cost_color_type'] = $orders[$key]['complaint_color_type'] = $orders[$key]['message_color_type'] = $orders[$key]['cancel_color_type'] = $orders[$key]['allowance_color_type'] = $orders[$key]['add_apply_color_type'] = null;

                // 获取有配件单的工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_accessory_order'] && $has_accessory_worker_order_ids[] = $order['id'];

                // 获取有费用单的费用单工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_cost_order'] && $has_cost_order_ids[] = $order['id'];

                // 获取有投诉单的工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_complaint_order'] && $has_complaint_order_ids[] = $order['id'];

                // 获取有留言单工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_message'] && $has_message_ids[] = $order['id'];

                // 获取有取消单的工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_cancel'] && $has_cancel_ids[] = $order['id'];

                // 获取有补贴单的工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_subsidy_order'] && $has_subsidy_order_ids[] = $order['id'];

                // 获取有开点单的工单id:将当前的工单id存入数组中
                $orders[$key]['statistics']['has_worker_add_apply'] && $has_worker_add_apply_ids[] = $order['id'];
                // 完善保外单费用
//                $orders[$key]['out_fee_info'] = null;
//                in_array($order['id'], $is_not_insurance_ids)
//                &&  isset($key_out_fees[$order['id']])
//                &&  $orders[$key]['out_fee_info'] = [
//                    'total_fee' => number_format($key_out_fees[$order['id']]['total_fee'], 2, '.', ''),
//                    'pay_total_fee' => number_format($key_out_fees[$order['id']]['pay_total_fee'], 2, '.', ''),
//                    'out_fees' => $key_out_fees[$order['id']]['out_fees'] ?? null,
//                ];

            }

            // 获取配件单状态下的颜色匹配，并处理配件单颜色分配
            !empty($has_accessory_worker_order_ids) && $accessory_status_map = $this->getAccessoryColorType($has_accessory_worker_order_ids);

            // 获取费用单状态下的颜色匹配，并处理费用单颜色分配
            !empty($has_cost_order_ids) && $cost_status_map = $this->getCostColorType($has_cost_order_ids);

            // 获取投诉单状态下的颜色匹配，并处理投诉单颜色分配
            !empty($has_complaint_order_ids) && $complaint_status_map = $this->getComplaintColorType($has_complaint_order_ids);

            // 获取留言单状态下的颜色匹配，并处理留言单颜色分配
            !empty($has_message_ids) && $message_status_map = $this->getMessageColorType($has_message_ids);

            // 获取取消单状态下的颜色匹配，并处理取消单颜色分配
            !empty($has_cancel_ids) && $cancel_status_map = $this->getCancelColorType($has_cancel_ids);

            // 获取补贴单状态下的颜色匹配，并处理补贴单颜色分配
            !empty($has_subsidy_order_ids) && $allowance_status_map = $this->getAllowanceColorType($has_subsidy_order_ids);

            // 获取开点单状态下的颜色匹配，并处理开点单颜色分配
            !empty($has_worker_add_apply_ids) && $add_apply_status_map = $this->getAddApplyColorType($has_worker_add_apply_ids);

            //完善配件单、费用单、投诉单、留言单、取消单、补贴单、开点单颜色状态分配。
            foreach($orders as $key => $val) {
                $orders[$key]['accessory_color_type'] = $accessory_status_map[$val['id']]['color_type'] ?? '0';
                $orders[$key]['cost_color_type'] = $cost_status_map[$val['id']]['color_type'] ?? '0';
                $orders[$key]['complaint_color_type'] = $complaint_status_map[$val['id']]['color_type'] ?? '0';
                $orders[$key]['message_color_type'] = $message_status_map[$val['id']]['color_type'] ?? '0';
                $orders[$key]['cancel_color_type'] = $cancel_status_map[$val['id']]['color_type'] ?? '0';
                $orders[$key]['allowance_color_type'] = $allowance_status_map[$val['id']]['color_type'] ?? '0';
                $orders[$key]['add_apply_color_type'] = $add_apply_status_map[$val['id']]['color_type'] ?? '0';
            }

            $total_num = BaseModel::getInstance($worker_order_table)->getNum([
                'where' => $where,
                'join'  => $join,
            ]);

            return [
                'num'  => $total_num,
                'list' => $orders,
            ];
        }
    }

    public function getSearchOrderOpts()
    {

        $user = AuthService::getAuthModel();
        $auth_model = AuthService::getModel();
        $where = [];
        if ($auth_model == AuthService::ROLE_FACTORY) {
            $where['worker_order.factory_id'] = $user['factory_id'];
        } elseif ($auth_model == AuthService::ROLE_FACTORY_ADMIN) {
            $where['worker_order.factory_id'] = $user['factory_id'];
        }

        $join = [];

        $force_index = null;
        $where_string = [];
//        $where['_string'] = '';
        // ========================== 查询条件拼装 start ===========================
        // 单号
        if ($orno = I('orno')) {
            $where['worker_order.orno'] = $orno;
        }
        // 用户手机
        if ($user_phone = I('user_phone')) {
            $where['worker_order_user_info.phone'] = $user_phone;
            !$join['worker_order_user_info'] && $join['worker_order_user_info'] = 'INNER JOIN worker_order_user_info ON worker_order_user_info.worker_order_id=worker_order.id';
        }
        // 技工手机
        if ($worker_phone = I('worker_phone')) {
            $where['worker_order.cp_worker_phone'] = $worker_phone;
        }
        // 厂家
        if ($auth_model == AuthService::ROLE_ADMIN && $factory_ids = I('factory_ids')) {
            $where['worker_order.factory_id'] = ['IN', $factory_ids];
        }
        // 创建开始
        if ($create_time_from = I('create_time_from')) {
            $where['worker_order.create_time'][] = ['GT', $create_time_from];
        }
        if ($create_time_to = I('create_time_to')) {
            $where['worker_order.create_time'][] = ['LT', $create_time_to];
        }

        //工单状态数组
        $status = I('status');
        $status = Util::filterIdList($status);

        $show_all = I('show_all', 0);
        $service_admin_ids = 0;
        $is_admin = false;
        $has_admin_search = false;
        if ($auth_model == AuthService::ROLE_ADMIN) {
            $is_admin = true;
            if ($show_all != 1 && !in_array('10', $status) && !in_array('140', $status)) {    // TODO 厂家新单要作特殊处理
                if ($admin_ids = I('admin_ids')) {
                    $service_admin_ids = $admin_ids;
                    $service_admin_ids = Util::filterIdList($service_admin_ids);
                } else {
                    $admin_group_logic = (new AdminGroupLogic());
                    $admin_group_id = I('admin_group_id');
                    $admin_group_ids = $admin_group_id ? [$admin_group_id] : $admin_group_logic->getManageGroupIds(AuthService::getAuthModel()->getPrimaryValue());
                    $service_admin_ids = $admin_group_logic->getGroupAdmins($admin_group_ids);
                }
                $service_admin_ids && $service_admin_ids = array_unique($service_admin_ids);
            }
        }

        $cs_search_condition = [];
        $cancel_status_condition = [];
        $cancel_status_condition_string = [];
        $has_worker_order_status = false;
        $has_cancel_status = false;
        // 工单状态
        if ($status) {
            foreach($status as $key => $val) {
                switch ($val) {
                    case 10:     // 待客服接单
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 20:     // 待客服核实
                        $force_index = 'worker_order_status';
                        if ($is_admin) {
                            $has_admin_search = true;
                            $cs_search_condition[] = " worker_order.worker_order_status=". OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK . " AND checker_id IN(" . implode(',', $service_admin_ids) . ') ';
                        }
                        $has_worker_order_status = true;
                        break;
                    case 30:     // 待派发客服接单
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 40:     // 待客服派单
                        $condition = [];
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $condition[] = OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE;

                        } else {  // 厂家派单：待接单和待派单
                            $condition[] = OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE;
                            $condition[] = OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE;
                        }
                        if ($is_admin) {
                            $has_admin_search = true;
                            $cs_search_condition[] = " worker_order.worker_order_status IN (" . implode(',', $condition) . ')' . " AND distributor_id IN (" . implode(',', $service_admin_ids) . ') ';
                        }
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 50:     // 待维修商接单
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 60:     // 待维修商预约
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 64:    // 待维修商服务
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 67:    // 维修商服务中
                        // 厂家特殊处理
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE;
                        } else {
                            $cs_search_condition[] = " worker_order.worker_order_status IN (". OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE. ','.OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE . ')' ;
                        }
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 70:     // 待回访客服接单
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 80:     // 待客服回访
                        $condition = [];
                        $visit_list = [OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT, OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT];
                        // 厂家特殊处理
                        $auth_model != AuthService::ROLE_ADMIN && $visit_list[] = OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE;
                        foreach ($visit_list as $k => $v) {
                            $condition[] = $v;
                        }
                        $force_index = 'worker_order_status';
                        if ($is_admin) {
                            $has_admin_search = true;
                            $cs_search_condition[] = " worker_order.worker_order_status IN (". implode(',', $condition) . ') ' . " AND returnee_id IN(" . implode(',', $service_admin_ids) . ') ';
                        }
                        $has_worker_order_status = true;
                        break;
                    case 85:     // 回访不通过
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 90:     // 待平台财务接单
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 100:     // 待平台财务审核
                        $condition = [];
                        $audit_list = [OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT];
                        // 厂家特殊处理
                        $auth_model != AuthService::ROLE_ADMIN && $audit_list = array_merge($audit_list, [OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE, OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT]);
                        foreach ($audit_list as $k => $v) {
                            $condition[] = $v;
                        }
                        if ($is_admin) {
                            $has_admin_search = true;
                            $cs_search_condition[] = " worker_order.worker_order_status IN (" . implode(',', $condition) . ')' . " AND auditor_id IN (" . implode(',', $service_admin_ids) . ') ';
                        }
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    case 105:     // 平台财务审核不通过
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT;
                        $force_index = 'worker_order_status';
                        break;
                    case 110:     // 待客厂家财务审核
                        $condition_str = " worker_order.worker_order_status =" . OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT . ' ';
                        $force_index = 'worker_order_status';
                        if ($is_admin) {
                            $has_admin_search = true;
                            $condition_str .= " AND auditor_id IN (" . implode(',', $service_admin_ids) . ') ';
//                            $cs_search_condition[] = " worker_order.worker_order_status =" . OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT . " AND auditor_id IN (" . implode(',', $service_admin_ids) . ') ';
                        }
                        $cs_search_condition[] = $condition_str;
                        $has_worker_order_status = true;
                        break;
                    case 120:     // 已完结
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
                        $has_worker_order_status = true;
                        break;
                    case 130:     // 厂家财务审核不通过
                        $force_index = 'worker_order_status';
                        $condition_str = " worker_order.worker_order_status =" . OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT . ' ';
                        if ($is_admin) {
                            $has_admin_search = true;
                            $condition_str .= " AND auditor_id IN (" . implode(',', $service_admin_ids) . ') ';
//                            $cs_search_condition[] = " worker_order.worker_order_status =" . OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT . " AND auditor_id IN (" . implode(',', $service_admin_ids) . ') ';
                        }
                        $cs_search_condition[] = $condition_str;
                        $has_worker_order_status = true;
                        break;
                    case 140:     // 厂家自行处理
                        $force_index = 'worker_order_status';
                        $cs_search_condition[] = " worker_order.worker_order_status =". OrderService::STATUS_FACTORY_SELF_PROCESSED;
                        $has_worker_order_status = true;
                        break;
                    case 150:     // 已取消
                        $condition = [];
                        $condition[] = OrderService::CANCEL_TYPE_WX_USER;
                        $condition[] = OrderService::CANCEL_TYPE_WX_DEALER;
                        $condition[] = OrderService::CANCEL_TYPE_FACTORY;
                        $condition[] = OrderService::CANCEL_TYPE_CS;
                        $condition[] = OrderService::CANCEL_TYPE_FACTORY_ADMIN;
                        $cancel_status_condition_string[] = " worker_order.cancel_status IN (" . implode(',', $condition) . ') ';
                        $has_cancel_status = true;
                        break;
                    case 160:     // 厂家取消
                        $condition = [];
                        $condition[] = OrderService::CANCEL_TYPE_FACTORY;
                        $condition[] = OrderService::CANCEL_TYPE_FACTORY_ADMIN;
                        $cancel_status_condition_string[] = " worker_order.cancel_status IN (". implode(',', $condition) . ') ';
                        $has_cancel_status = true;
                        break;
                    case 170:     // 客服取消
                        $cancel_status_condition_string[] = " worker_order.cancel_status =". OrderService::CANCEL_TYPE_CS;
                        $has_cancel_status = true;
                        break;
                    case 180:     // 用户取消
                        $condition = [];
                        $condition[] = OrderService::CANCEL_TYPE_WX_USER;
                        $condition[] = OrderService::CANCEL_TYPE_WX_DEALER;
                        $cancel_status_condition_string[] = " worker_order.cancel_status IN (". implode(',', $condition) . ') ';
                        $has_cancel_status = true;
                        break;
                    case 190:   // 待审核易码工单/待厂家审核下单
                        $cs_search_condition[] = "worker_order.worker_order_status =". OrderService::STATUS_CREATED;
                        $force_index = 'worker_order_status';
                        $has_worker_order_status = true;
                        break;
                    default:
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态错误,请检查~');
                }
            }
        }


        if (!I('include_canceled', 0) && !$show_all && !in_array('140', $status) && !$has_cancel_status) {
            $cancel_status_condition['worker_order.cancel_status'][] = OrderService::CANCEL_TYPE_NULL;
            $cancel_status_condition['worker_order.cancel_status'][] = OrderService::CANCEL_TYPE_CS_STOP;
            $cancel_status_condition['worker_order.cancel_status'] =  implode(',', $cancel_status_condition['worker_order.cancel_status']);
            $where_string[] = "worker_order.cancel_status IN ({$cancel_status_condition['worker_order.cancel_status']})";
        }

        // 厂家服务中工单列表
        if ($factory_in_service = I('factory_in_service')) {
            $where_string[] = "(worker_order.worker_order_status IN (2,3,4,5,6,7,8,9,10,11,12,13,14,15,17))";
//            $where['_string'] .= " AND (worker_order.worker_order_status IN (2,3,4,5,6,7,8,9,10,11,12,13,14,15,17)) ";
        }

        // 技工服务中工单
        if ($admin_in_service = I('admin_in_service')) {
            $where_string[] = "(worker_order.worker_order_status IN (7,8,9))";
//            $where['_string'] .= " AND (worker_order.worker_order_status IN (7,8,9)) ";
            if ($is_admin) {
                $has_admin_search = true;
                $service_admin_ids && $where['distributor_id'] = ['IN', $service_admin_ids];
            }
        }
        // 用户名称
        if ($user_name = I('user_name')) {
            $where['worker_order_user_info.real_name'] = ['LIKE', "%{$user_name}%"];
            $join['worker_order_user_info'] = 'INNER JOIN worker_order_user_info ON worker_order_user_info.worker_order_id=worker_order.id';
        }

        // 服务类型
        if ($service_type = I('service_type')) {
            $where['worker_order.service_type'] = $service_type;
        }
        // 售后类型
        $worker_order_type = I('worker_order_type');
        if ($worker_order_type) {
            $where['worker_order.worker_order_type'] = ['IN', $worker_order_type];
        }

        // 厂家电话
        if ($factory_phone = I('factory_phone')) {
            $factory_ids = BaseModel::getInstance('factory')
                ->getFieldVal(['linkphone' => ['like', ["%{$factory_phone}%"]]], 'factory_id', true);
            $factory_ids = $factory_ids ? implode(',', $factory_ids) : 'null';
            $where_string[] = "(worker_order.factory_id IN ({$factory_ids}))";
        }
        // 回访时间
        if ($return_time_from = I('return_time_from')) {
            $where['worker_order.return_time'][] = ['GT', $return_time_from];
        }
        if ($return_time_to = I('return_time_to')) {
            $where['worker_order.return_time'][] = ['LT', $return_time_to];
        }
        // 最近更新
        if ($recent_update_days = I('recent_update_days', 0, 'intval')) {
            $has_admin_search = true;
            $is_admin && $service_admin_ids && $where[] = [
                'checker_id'     => ['IN', $service_admin_ids],
                'distributor_id' => ['IN', $service_admin_ids],
                'returnee_id'    => ['IN', $service_admin_ids],
                'auditor_id'     => ['IN', $service_admin_ids],
                '_logic'         => 'or',
            ];
            $recent_update_time = NOW_TIME - $recent_update_days * 86400;
            $where_string[] = "(SELECT create_time<={$recent_update_time} FROM worker_order_operation_record WHERE worker_order_operation_record.worker_order_id=worker_order.id ORDER BY id DESC LIMIT 1)";
        }

        // 根据上面选择客服分组与客服列表，如果未有其他条件搜索客服则在此统一搜索
        if ($is_admin && !$has_admin_search && $auth_model == AuthService::ROLE_ADMIN && $show_all != 1 && $status != 10 && $status != 140 && $service_admin_ids) {
            $where[] = [
                'checker_id'     => ['IN', $service_admin_ids],
                'distributor_id' => ['IN', $service_admin_ids],
                'returnee_id'    => ['IN', $service_admin_ids],
                'auditor_id'     => ['IN', $service_admin_ids],
                '_logic'         => 'or',
            ];
        }
        // 产品品类
        if ($product_category_ids = I('product_category_ids')) {
            $where_string[] = "worker_order.id IN (SELECT distinct worker_order_id FROM worker_order_product WHERE product_category_id IN ({$product_category_ids}))";
        }

        // 厂家组别
        $factory_group_ids = I('factory_group_ids');
        $factory_group_ids = explode(',', $factory_group_ids);
        $factory_group_ids = array_filter($factory_group_ids, function ($val) {
            return $val !== '';
        });
        if ($auth_model == AuthService::ROLE_ADMIN && $factory_group_ids) {
            $factory_ids = BaseModel::getInstance('factory')
                ->getFieldVal(['group_id' => ['IN', $factory_group_ids]], 'factory_id', true);
            $factory_ids = $factory_ids ? implode(',', $factory_ids) : 'null';
            $where_string[] = "(worker_order.factory_id IN ({$factory_ids}))";
//            $where['_string'] .= " AND (worker_order.factory_id IN ({$factory_ids})) ";
        }
        // 用户保修单
        if ($factory_wx_user_order = I('factory_wx_user_order')) {
            $where_string[] = "(worker_order.worker_order_type IN (5,6)) AND worker_order_status=0";
//            $where['_string'] .= " AND (worker_order.worker_order_type IN (5,6)) AND worker_order_status=0 ";
        } else {
            // TODO 厂家所属组，设置对应常量
            if ($auth_model != AuthService::ROLE_ADMIN) {
                if ($auth_model == AuthService::ROLE_FACTORY_ADMIN) {
                    $group_id = AuthService::getAuthModel()->tags_id;
                } else {
                    $group_id = I('group_id');
                }
                if ($group_id) {
                    $factory_admin_ids = BaseModel::getInstance('factory_admin')->getFieldVal([
                        'factory_id' => AuthService::getAuthModel()->factory_id,
                        'tags_id' => $group_id,
                    ], 'id', true);
                    $factory_id = AuthService::getAuthModel()->factory_id;

                    $factory_admin_ids = implode(',', $factory_admin_ids) ?: '-1';
                    $where_string[] = "((factory_check_order_id IN ($factory_admin_ids) AND worker_order.factory_id={$factory_id}) OR (worker_order.origin_type=2 AND add_id IN({$factory_admin_ids})))";
                }
            }
        }

        // 财务审核时间
        if ($audit_time_from = I('audit_time_from')) {
            $where['worker_order.audit_time'][] = ['GT', $audit_time_from];
            $where['worker_order.worker_order_status'] = ['IN', [OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT, OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT, OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED]];
        }
        if ($audit_time_to = I('audit_time_to')) {
            $where['worker_order.audit_time'][] = ['LT', $audit_time_to];
            $where['worker_order.worker_order_status'] = ['IN', [OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT, OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT, OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED]];
        }
        // 状态更新
        if ($status_update_days = I('status_update_days')) {
            $is_admin && $service_admin_ids && $where[] = [
                'checker_id'     => ['IN', $service_admin_ids],
                'distributor_id' => ['IN', $service_admin_ids],
                'returnee_id'    => ['IN', $service_admin_ids],
                'auditor_id'     => ['IN', $service_admin_ids],
                '_logic'         => 'or',
            ];
            $status_update_time = NOW_TIME - $status_update_days * 86400;
            $where['worker_order.last_update_time'] = ['ELT', $status_update_time];
        }
        // 未预约时间
        $appoint_time_operate = I('appoint_time_operate');
        if ($appoint_time_hours = I('appoint_time_hours')) {
            $appoint_time_hours = NOW_TIME - $appoint_time_hours * 3600;
            $where['worker_order.worker_order_status'] = 7;     // 7技工接单成功 （待技工预约上门）
            $force_index = 'worker_order_status';
            if ($appoint_time_operate == 1) {
                $appoint_time_operate = 'LT';
            } else {
                $appoint_time_operate = 'GT';
            }
            $where['worker_order.worker_receive_time'] = [$appoint_time_operate, $appoint_time_hours];
        }
        // 未签到时间
        $sign_in_time_operate = I('sign_in_time_operate');
        if ($sign_in_time_hours = I('sign_in_time_hours')) {
            $sign_in_time_hours = NOW_TIME - $sign_in_time_hours * 3600;
            $where['worker_order.worker_order_status'] = 8;     // 8预约成功 （待上门服务）
            $force_index = 'worker_order_status';
            if ($sign_in_time_operate == 1) {
                $sign_in_time_operate = 'LT';
            } else {
                $sign_in_time_operate = 'GT';
            }
            $where['worker_order.worker_receive_time'] = [$sign_in_time_operate, $sign_in_time_hours];
        }
        // 所属区域
        if (($area_level = I('area_level')) && ($area_id = I('area_id'))) {
            if ($area_level == 1) {
                $where['worker_order_user_info.province_id'] = $area_id;
            } elseif ($area_level == 2) {
                $where['worker_order_user_info.city_id'] = $area_id;
            } elseif ($area_level == 3) {
                $where['worker_order_user_info.area_id'] = $area_id;
            } else {
                $where['worker_order_user_info.street_id'] = $area_id;
            }
            $join['worker_order_user_info'] = 'INNER JOIN worker_order_user_info ON worker_order_user_info.worker_order_id=worker_order.id';
        }
        // 完结时间
        if ($factory_audit_time_from = I('factory_audit_time_from')) {
            $where['worker_order.worker_order_status'] = OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
            $where['worker_order.factory_audit_time'][] = ['GT', $factory_audit_time_from];
        }
        if ($factory_audit_time_to = I('factory_audit_time_to')) {
            $where['worker_order.worker_order_status'] = OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
            $where['worker_order.factory_audit_time'][] = ['LT', $factory_audit_time_to];
        }
        // 未返件单
        if ($has_accessory_unreturn_order = I('has_accessory_unreturn_order')) {
            $where['worker_order_statistics.accessory_unreturn_num'] = ['GT', 0];
            $join['worker_order_statistics'] = 'INNER JOIN worker_order_statistics ON worker_order_statistics.worker_order_id=worker_order.id';
        }
        // 配件单
        if ($has_accessory_order = I('has_accessory_order')) {
            $where['worker_order_statistics.total_accessory_num'] = ['GT', 0];
            $join['worker_order_statistics'] = 'INNER JOIN worker_order_statistics ON worker_order_statistics.worker_order_id=worker_order.id';
        }
        // 费用单
        if ($has_cost_order = I('has_cost_order')) {
            $where['worker_order_statistics.cost_order_num'] = ['GT', 0];
            $join['worker_order_statistics'] = 'INNER JOIN worker_order_statistics ON worker_order_statistics.worker_order_id=worker_order.id';
        }
        // 未发件工单
        if ($has_accessory_unsent_order = I('has_accessory_unsent_order')) {
            $where['worker_order_statistics.accessory_unsent_num'] = ['GT', 0];
            $join['worker_order_statistics'] = 'INNER JOIN worker_order_statistics ON worker_order_statistics.worker_order_id=worker_order.id';
        }
        // 投诉单
        if ($has_complaint_order = I('has_complaint_order')) {
            $where['worker_order_statistics.complaint_order_num'] = ['GT', 0];
            $join['worker_order_statistics'] = 'INNER JOIN worker_order_statistics ON worker_order_statistics.worker_order_id=worker_order.id';
        }
        // 留言单
        if ($has_message = I('has_message')) {
            $where['worker_order_statistics.total_message_num'] = ['GT', 0];
            $join['worker_order_statistics'] = 'INNER JOIN worker_order_statistics ON worker_order_statistics.worker_order_id=worker_order.id';
        }

        // 型号
        if ($product_mode = I('product_mode')) {
            $where_string[] = "(SELECT 1 FROM worker_order_product WHERE worker_order_id=worker_order.id and cp_product_mode like '%{$product_mode}% limit 1')";
//            $where['_string'] .= " AND worker_order.id IN(SELECT distinct worker_order_id FROM worker_order_product WHERE cp_product_mode like '%{$product_mode}%')";
        }

        // 包含已完成
        if (!I('include_completed') && !$show_all && !$has_worker_order_status) {
            $where_string[] = "(worker_order.worker_order_status!=18)";
//            $where['_string'] .= " AND (worker_order.worker_order_status!=18) ";
        }

        if ($where_string) {
            $where['_string'] = implode(' AND ', $where_string);
        }

        if ($cs_search_condition) {
            $where['_string'] && $where['_string'] .= ' AND ';
            $where['_string'] .= ' (' . implode(' OR ', $cs_search_condition) . ')';
        }

        if ($cancel_status_condition_string && $has_worker_order_status == true) {
            $where['_string'] && $where['_string'] .= ' OR ';
            $where['_string'] .= ' (' . implode(' OR ', $cancel_status_condition_string) .')';
        } elseif ($cancel_status_condition_string) {
            $where['_string'] && $where['_string'] .= ' AND ';
            $where['_string'] .= ' (' . implode(' OR ', $cancel_status_condition_string) .')';
        }

        // ========================== 查询条件拼装 end ===========================

        return [
            'where' => $where,
            'join'  => $join,
            'index' => $force_index,
        ];
    }

    public function show($order_id)
    {
        $factory = '';
        $order_where = ['id' => $order_id];
        if (AuthService::getModel() != AuthService::ROLE_ADMIN) {
            $factory = BaseModel::getInstance('factory')
                ->getOne(AuthService::getAuthModel()->factory_id);
            $order_where['factory_id'] = $factory['factory_id'];
        }

        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($order_where, 'id,orno,worker_id,factory_id,parent_id,worker_order_type,worker_order_status,service_type,cancel_status,create_time,factory_check_order_time,checker_receive_time,check_time,distribute_time,worker_receive_time,worker_first_appoint_time,worker_repair_time,return_time,audit_time,factory_audit_time,cancel_time,factory_check_order_type,factory_check_order_id,origin_type,add_id');
        $order['factory_not_settlement_fee_status'] = (string)OrderOperationRecordService::FACTORY_NOT_SETTLEMENT_WORKER_ORDER_FEE;
        $order['service_name'] = OrderService::SERVICE_TYPE[$order['service_type']];
        $order['worker_order_status_name'] = OrderService::getStatusStr($order['worker_order_status'], $order['cancel_status']);
        $order['is_insurance'] = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? '1' : '0';
        // 添加用户信息
        $order['user'] = BaseModel::getInstance('worker_order_user_info')
            ->getOne($order['id'], 'worker_order_id,real_name,phone,cp_area_names area_names,address,province_id,city_id,area_id,address,lat,lon,is_user_pay,pay_type,street_id,0 as pay_type_detail');
        // 返修单信息
        $order['classification'] = OrderService::getClassificationByOrno($order['orno']);
        $order['can_order_rework'] = $order['rework_order_id'] = '0';
        $order['parent_worker_info'] = (Object)[];
        if ($order['classification'] == OrderService::CLASSIFICATION_COMMON_ORDER_TYPE) {
            $order['can_order_rework'] = '1';
            $rework_order_id = BaseModel::getInstance('worker_order')->getFieldVal([
                'where' => ['orno' => ['LIKE', 'F%'], 'parent_id' => $order['id'], 'cancel_status' => OrderService::CANCEL_TYPE_NULL],
            ], 'id');
            $rework_order_id && $order['rework_order_id'] = $rework_order_id;

            if (!$order['rework_order_id']) {
                $order['can_order_rework'] = $this->canOrderRework($order['worker_order_status'], $order['classification'], $order['audit_time']);
            } else {
                $order['can_order_rework'] = '0';
            }
        } elseif ($order['classification'] == OrderService::CLASSIFICATION_REWORK_ORDER_TYPE) {
            $parent_order = BaseModel::getInstance('worker_order')->getOne($order['parent_id'], 'worker_id');
            $worker = BaseModel::getInstance('worker')->getOne($parent_order['worker_id'], 'worker_telephone,nickname,lat,lng');
            $worker_coop_business = BaseModel::getInstance('worker_coop_busine')->getOne(['worker_id' => $parent_order['worker_id']], 'first_distribute_areas_path');
            $first_distribute_areas = explode('-', $worker_coop_business['first_distribute_areas_path']);
            $first_distribute_areas = array_filter($first_distribute_areas);
            $first_distribute_area_ids = [];
            foreach ($first_distribute_areas as $first_distribute_area) {
                $first_distribute_area_ids = array_merge($first_distribute_area_ids, explode(',', $first_distribute_area));
            }
            $area_id_name_map = AreaService::getAreaNameMapByIds($first_distribute_area_ids);
            $first_distribute_area_names = [];
            foreach ($first_distribute_areas as $first_distribute_area) {
                $first_distribute_area_list = explode(',', $first_distribute_area);
                $first_distribute_area_names[] = "{$area_id_name_map[$first_distribute_area_list[0]]['name']}-{$area_id_name_map[$first_distribute_area_list[1]]['name']}-{$area_id_name_map[$first_distribute_area_list[2]]['name']}";
            }
            // 计算距离
            $mi = Util::distanceSimplify($order['user']['lat'], $order['user']['lon'], $worker['lat'], $worker['lng']) * 1.5;
            $li = number_format($mi / 1000, 2, '.', '');
            $order['parent_worker_info'] = [
                'worker_id' => $parent_order['worker_id'],
                'name' => $worker['nickname'],
                'worker_telephone' => $worker['worker_telephone'],
                'est_miles' => $li,
                'first_distribute_areas' => $first_distribute_area_names
            ];
        }


        // 添加产品信息
        $order['worker_order_product'] = BaseModel::getInstance('worker_order_product')
            ->getList(['worker_order_id' => $order['id']], 'id,product_brand_id,product_category_id,product_standard_id,product_id,factory_repair_fee,factory_repair_fee_modify,factory_repair_reason,worker_repair_fee,worker_repair_fee_modify,worker_repair_reason,service_fee,service_fee_modify,service_reason,cp_category_name category_name,cp_product_brand_name product_brand_name,cp_product_standard_name product_stantard_name,cp_product_mode product_mode,product_nums,fault_label_ids,user_service_request,cp_fault_name,service_request_imgs');
        $fault_label_ids = [];
        $fault_id_map = [];
        foreach ($order['worker_order_product'] as &$product) {
            $fault_label_list = array_filter(explode(',', $product['fault_label_ids']));
            $product['fault_label_list'] = [];
            foreach ($fault_label_list as $fault_label_id) {
                $fault_label_ids[] = $fault_label_id;
                $product['fault_label_list'][] = &$fault_id_map[$fault_label_id];
            }
            $product['service_request_imgs'] = json_decode($product['service_request_imgs'], true);
            $service_request_imgs = [];
            foreach ($product['service_request_imgs'] as $service_request_img) {
                $service_request_imgs[] = Util::getServerFileUrl($service_request_img['url']);
            }
            $product['service_request_imgs'] = $service_request_imgs;
        }
        if ($fault_label_ids) {
            $fault_label_ids = array_unique($fault_label_ids);
            $faults = BaseModel::getInstance('product_fault_label')->getList([
                'id' => ['IN', $fault_label_ids],
            ], 'id,label_name name');
            foreach ($faults as $fault) {
                $fault_id_map[$fault['id']] = $fault['name'];
            }
        }

        // 开点单
        $order['add_apply'] = BaseModel::getInstance('worker_add_apply')->getOne([
            'where' => [
                'worker_order_id' => $order_id,
            ],
            'field' => 'id,status',
            'order' => 'id DESC',
        ]);

        // 厂家信息
        !$factory && $factory = BaseModel::getInstance('factory')
            ->getOne($order['factory_id'], 'factory_id,factory_full_name,linkman,linkphone,money,can_read_worker_info');
        if ($order['origin_type'] == OrderService::ORIGIN_TYPE_FACTORY_ADMIN || $order['factory_check_order_type'] == 2) {
            $add_order_factory_admin = BaseModel::getInstance('factory_admin')->getOne($order['add_id'], 'nickout,tell');
            $factory_add_order_name = $add_order_factory_admin['nickout'];
        } else {
            $factory_add_order_name = $factory['linkman'];
        }
        $order_ext_info = BaseModel::getInstance('worker_order_ext_info')
            ->getOne($order['id'], 'factory_helper_id,cp_factory_helper_name,cp_factory_helper_phone,worker_base_distance,worker_base_distance_fee,worker_exceed_distance_fee,factory_base_distance,factory_base_distance_fee,factory_exceed_distance_fee,est_miles,appoint_start_time,appoint_end_time,worker_repair_out_fee_reason,accessory_out_fee_reason');
        $order['factory'] = [
            'factory_full_name'    => $factory['factory_full_name'],
            'linkman'              => $factory['linkman'],
            'linkphone'            => $factory['linkphone'],
            'factory_helper_id'  => $order_ext_info['factory_helper_id'],
            'factory_helper_name'  => $order_ext_info['cp_factory_helper_name'],
            'factory_helper_phone' => $order_ext_info['cp_factory_helper_phone'],
            'balance'              => round($factory['money'], 2),
            'add_order_name'       => $factory_add_order_name,
        ];
        // 上门费设置
        $worker_order_fee = BaseModel::getInstance('worker_order_fee')
            ->getOne(['worker_order_id' => $order['id']], 'worker_order_id,insurance_fee,homefee_mode,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,user_discount_out_fee,service_fee,service_fee_modify,coupon_reduce_money,worker_total_fee,worker_total_fee_modify,factory_total_fee,factory_total_fee_modify,worker_net_receipts');
        $order['distance_info'] = [
            'worker_base_distance'    => $order_ext_info['worker_base_distance'],
            'worker_base_distance_fee'    => $order_ext_info['worker_base_distance_fee'],
            'worker_exceed_distance_fee'    => $order_ext_info['worker_exceed_distance_fee'],
            'factory_base_distance'    => $order_ext_info['factory_base_distance'],
            'factory_base_distance_fee'    => $order_ext_info['factory_base_distance_fee'],
            'factory_exceed_distance_fee'    => $order_ext_info['factory_exceed_distance_fee'],
            'homefee_mode'    => $worker_order_fee['homefee_mode'],
            'est_miles'    => $order_ext_info['est_miles'],
        ];
        $order['accessory_out_fee'] = $worker_order_fee['accessory_out_fee'];
        $order['coupon_reduce_money'] = round($worker_order_fee['coupon_reduce_money'] / 100, 2);
        $order['coupon_id'] = BaseModel::getInstance('coupon_receive_record')->getFieldVal(['worker_order_id' => $order_id], 'coupon_id');
        $order['coupon_title'] = $order['coupon_id'] ? BaseModel::getInstance('coupon_rule')->getFieldVal($order['coupon_id'], 'title') : '';
        $order['user_discount_out_fee'] = $worker_order_fee['user_discount_out_fee'];
        $order['service_fee'] = $worker_order_fee['service_fee'];
        $order['service_fee_modify'] = $worker_order_fee['service_fee_modify'];
        $order['appoint_start_time'] = $order_ext_info['appoint_start_time'];
        $order['appoint_end_time'] = $order_ext_info['appoint_end_time'];

        if ($order['worker_id']) {
            // 添加技工信息
            $order['worker'] = BaseModel::getInstance('worker')
                ->getOne($order['worker_id'], 'worker_telephone,nickname,notes,money');
            $order['worker']['order_number'] = BaseModel::getInstance('worker_order')
                ->getNum([
                    'worker_id' => $order['worker_id'],
                    'cancel_status' => ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
                ]);
            // 如果是厂家并且不在课件技工电话列表中则情况技工手机
            if (AuthService::getModel() != AuthService::ROLE_ADMIN && !$factory['can_read_worker_info']) {
                $order['worker']['worker_telephone'] = '';
            }
            $worker_logic = new WorkerLogic();
            $effectiveness_score = $worker_logic->getWorkerEffectivenessScore($order['worker_id']);
            $user_satisfy_score = $worker_logic->getUserSatisfyScore($order['worker_id']);
            $repair_quality_score = $worker_logic->getRepairQualityScore($order['worker_id']);
            $service_specification_score = $worker_logic->workerServiceSpecificationScore($order['worker_id']);
            $order['worker']['reputation_score'] = round(($effectiveness_score + $user_satisfy_score + $repair_quality_score + $service_specification_score) / 4);

        } else {
            $order['worker'] = null;
        }

        // 添加配件单信息
        $accessory_where = ['worker_order_id' => $order['id']];
        if (AuthService::getModel() != AuthService::ROLE_ADMIN) {
            $accessory_where['accessory_status'] = ['EGT', AccessoryService::STATUS_ADMIN_CHECKED];
//            $accessory_where['cancel_status'] = AccessoryService::CANCEL_STATUS_NORMAL;
        }
        $order['accessory_apply'] = BaseModel::getInstance('worker_order_apply_accessory')
            ->getList($accessory_where, 'id,worker_order_id,worker_order_product_id,accessory_number,accessory_status,worker_transport_fee,worker_transport_fee_modify,worker_transport_fee_reason,factory_transport_fee,factory_transport_fee_modify,factory_transport_fee_reason,create_time,apply_reason,cancel_status,worker_return_pay_method');
        foreach ($order['accessory_apply'] as $key => $item) {
            $order['accessory_apply'][$key]['show_accessory_fee'] = in_array($item['accessory_status'], [AccessoryService::STATUS_WORKER_SEND_BACK, AccessoryService::STATUS_COMPLETE]) && $item['worker_return_pay_method'] == 1 ? '1' : '0';
        }
        BaseModel::getInstance('worker_order_apply_accessory_item')
            ->attachMany2List($order['accessory_apply'], 'accessory_order_id', ['id', 'name'], [], 'id', 'accessory_items');
        BaseModel::getInstance('worker_order_product')
            ->attachField2List($order['accessory_apply'], 'cp_category_name category_name,cp_product_brand_name product_brand_name,cp_product_standard_name product_standard_name,cp_product_mode product_mode', [], 'worker_order_product_id', 'product');
        $factory_receive = BaseModel::getInstance('factory')
            ->getOne($order['factory_id'], 'receive_address,receive_tell,receive_person,linkman,linkphone,factory_detail_address');
        if ($factory_receive['receive_tell'] && $factory_receive['receive_address']) {
            $order['accessory_apply_receiver']['name'] = $factory_receive['receive_person'];
            $order['accessory_apply_receiver']['phone'] = $factory_receive['receive_tell'];
            $order['accessory_apply_receiver']['address'] = $factory_receive['receive_address'];
        } else {
            $order['accessory_apply_receiver']['name'] = $factory_receive['linkman'];
            $order['accessory_apply_receiver']['phone'] = $factory_receive['linkphone'];
            $order['accessory_apply_receiver']['address'] = $factory_receive['factory_detail_address'];
        }
        // 添加费用单信息
        $cost_where = ['worker_order_id' => $order['id']];
        if (AuthService::getModel() != AuthService::ROLE_ADMIN) {
            $cost_where['status'] = ['IN', [ApplyCostService::STATUS_CS_CHECK_PASSED_AND_NEED_FACTORY_CHECK, ApplyCostService::STATUS_FACTORY_CHECK_NOT_PASSED, ApplyCostService::STATUS_FACTORY_CHECK_PASSED]];
        }
        $order['cost_apply'] = BaseModel::getInstance('worker_order_apply_cost')
            ->getList($cost_where, 'id,type,worker_order_id,apply_cost_number,status,create_time,fee,reason,worker_order_product_id');
        BaseModel::getInstance('worker_order_product')
            ->attachField2List($order['cost_apply'], 'cp_category_name category_name,cp_product_brand_name product_brand_name,cp_product_standard_name product_standard_name,cp_product_mode product_mode', [], 'worker_order_product_id', 'product');
        // 补贴单信息
        $order['apply_allowance'] = BaseModel::getInstance('worker_order_apply_allowance')
            ->getList(['worker_order_id' => $order['id'], 'status' => AllowanceService::STATUS_PASS], 'id,type,apply_fee,apply_fee_modify,modify_reason');
        // 结算备注
        $order['audit_remark'] = BaseModel::getInstance('worker_order_audit_remark')
            ->getList([
                'where' => ['worker_order_id' => $order['id']],
                'order' => 'id ASC',
                'field' => 'id,admin_id,content,create_time',
            ]);

        //结算备注记录总数
        $audit_remark_total_num = BaseModel::getInstance('worker_order_audit_remark')->getNum([
            'where' => ['worker_order_id' => $order['id']],
        ]);
        $order['audit_remark_total_num'] = $audit_remark_total_num;

        foreach ($order['audit_remark'] as $key => $item) {
            $order['audit_remark'][$key]['content'] = htmlEntityDecode($item['content']);
        }

        BaseModel::getInstance('admin')
            ->attachField2List($order['audit_remark'], 'id,nickout user_name', [], 'admin_id');
        // 工单费用详情
        $order['appoint_record'] = BaseModel::getInstance('worker_order_appoint_record')
            ->getList([
                'where' => [
                    'worker_order_id' => $order['id'],
                    'is_over'         => 1,
                ],
                'order' => 'id ASC',
                'field' => 'id,factory_appoint_fee,factory_appoint_fee_modify,factory_appoint_reason,worker_appoint_fee,worker_appoint_fee_modify,worker_appoint_reason',
            ]);
        // 保险费
        $order['worker_insurance_fee'] = $worker_order_fee['insurance_fee'];
        // 工单操作记录
        if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
            $auth_type = OrderOperationRecordService::PERMISSION_CS;
        } else {
            $auth_type = OrderOperationRecordService::PERMISSION_FACTORY;
        }
        $operation_record = BaseModel::getInstance('worker_order_operation_record')
            ->getList([
                'where' => [
                    'worker_order_id' => $order['id'],
                    '_string'         => " (see_auth=0 or see_auth&{$auth_type}) ",
                ],
                'field' => 'id,operation_type,operator_id,content,create_time,see_auth,remark',
            ]);
        foreach ($operation_record as $key => $val) {
            $content = $val['remark'];

            $content = preg_replace_callback("#src=(['\\\"])([^'\"]*)\\1#i", function($matches){
                $url = $matches[2];
                if (!preg_match('#^https?://#', $url)) {
                    $url = Util::getServerFileUrl($url);
                }
                return 'src="'.$url.'"';
            }, $content);
            $val['remark'] = $content;

            $operation_record[$key] = $val;
        }

        $order['operation_record'] = $operation_record;
        // 获取操作人名称
        foreach ($order['operation_record'] as $key => $record) {
            // 解析修改记录的remarks
            OrderOperationRecordService::setEditAppointDesc($order['operation_record'][$key]);
            $order['operation_record'][$key]['auth'] = OrderOperationRecordService::getOperationRecordSeeAuth(($record['see_auth']));
            $order['operation_record'][$key]['operator'] = OrderOperationRecordService::getUserTypeName($record['operation_type']);
        }
        OrderOperationRecordService::loadAddUserInfo($order['operation_record']);

        // 服务码
        $order['service_code'] = BaseModel::getInstance('sms_order_service_code')
            ->getOne([
                'order_id' => $order['id'],
            ], 'level_a,level_b,level_c');
        // 预返件安装单物流信息
        $order['delivery'] = [];
        if ($order['service_type'] == OrderService::TYPE_PRE_RELEASE_INSTALLATION) {
            $order['delivery'] = BaseModel::getInstance('express_tracking')->getList([
                'field' => 'express_number,express_code,state,content',
                'where' => [
                    'type' => 3,
                    'data_id' => $order['id'],
                ],
            ]);
            $express_codes = implode(',', array_unique(array_filter(array_column($order['delivery'], 'express_code'))));
            $express_names = $express_codes ? BaseModel::getInstance('express_com')->getList([
                'field' => 'name,comcode',
                'where' => [
                   'comcode' => ['in', $express_codes],
                ],
                'index' => 'comcode',
            ]) : [];
            foreach ($order['delivery'] as $k => $v) {
                $order['delivery'][$k]['express_name'] = $express_names[$v['express_code']]['name'] ?? '';
            }
        }
        // 状态进度
        $order['schedule'] = $this->getOrderStatusSchedule($order);
        $order['fee'] = [
            'factory_total_fee'         => $worker_order_fee['factory_total_fee'],
            'factory_total_fee_modify'  => $worker_order_fee['factory_total_fee_modify'],
            'worker_total_fee'          => $worker_order_fee['worker_total_fee'],
            'worker_total_fee_modify'   => $worker_order_fee['worker_total_fee_modify'],
            'worker_net_receipts'       => $worker_order_fee['worker_net_receipts'],
        ];

        // 补贴单数量
        $order['apply_allowance_num'] = BaseModel::getInstance('worker_order_apply_allowance')
            ->getNum(['worker_order_id' => $order['id']]);

        // 是否是保内单
        $is_insurance = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST);
        $order['out_fee_info'] = null;
        if (!$is_insurance) {
            $out_fees = BaseModel::getInstance('worker_order_out_worker_add_fee')->getList([
                'field' => 'is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee_modify,pay_time',
                'where' => [
                    'worker_order_id' => $order_id
                ],
            ]);
            $total = $is_apy_total = 0;
            foreach ($out_fees as $k => $v) {
                if ($v['pay_type'] != WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO && $v['pay_time']) {
                    $is_apy_total += $v['total_fee_modify'];
                    $order['user']['pay_type_detail'] = $v['pay_type'];
                }
                $total += $v['total_fee_modify'];
                unset($out_fees[$k]['total_fee_modify']);
            }
            $order['out_fee_info'] = [
                'total_worker_fee' => $worker_order_fee['worker_total_fee'],
                'total_worker_fee_modify' => $worker_order_fee['worker_total_fee_modify'],
                'total_worker_repair_fee' => $worker_order_fee['worker_repair_fee'],
                'total_worker_repair_fee_modify' => $worker_order_fee['worker_repair_fee_modify'],
                'total_worker_repair_fee_reason' => $order_ext_info['worker_repair_out_fee_reason'],
                'total_accessory_out_fee' => $worker_order_fee['accessory_out_fee'],
                'total_accessory_out_fee_modify' => $worker_order_fee['accessory_out_fee_modify'],
                'total_accessory_out_fee_reason' => $order_ext_info['accessory_out_fee_reason'],
                'need_pay_total_fee' => number_format($total, 2, '.', ''),
                'pay_total_fee' => number_format($is_apy_total, 2, '.', ''),
                'out_fees' => $out_fees,
            ];
        }

        return $order;
    }

    public function canOrderRework($order_status, $classification, $audit_time)
    {
        if ($order_status != OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT && $order_status != OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED) {
            return '0';
        }
        $audit_day = date('Ymd', $audit_time);
        $audit_end_time = strtotime($audit_day) + 31 * 86400;
        if ($classification == OrderService::CLASSIFICATION_COMMON_ORDER_TYPE && NOW_TIME < $audit_end_time) {
            return '1';
        } else {
            return '0';
        }
    }

    public function getOrderStatusSchedule($order)
    {
        $order_status = $order['worker_order_status'];
        $cancel_status = $order['cancel_status'];
        /**
         * 0创建工单,
         * 1自行处理,
         * 2外部工单经过厂家审核（待平台核实客服接单）,
         * 3平台核实客服接单（待平台核实客服核实信息），
         * 4平台核实客服核实用户信息 （待平台派单客服接单）,
         * 5平台派单客服接单 （待派发）,
         * 6已派发 （抢单池）,
         * 7技工接单成功 （待技工预约上门）,
         * 8预约成功 （待上门服务）,
         * 9已上门（待全部维修项完成维修）,
         * 10完成维修 （待平台回访客服接单）,
         * 11平台回访客服接单 （待回访）,
         * 12平台回访客服回访不通过 （已上门）,
         * 13平台回访客服已回访 （待平台财务客服接单）,
         * 14平台财务客服接单 （待平台财务客服审核）,
         * 15平台财务客服审核不通过 （重新回访客服回访）,
         * 16平台财务客服审核 （待厂家财务审核）,
         * 17厂家财务审核不通过 （平台财务重新审核）,
         * 18厂家财务审核 （已完成工单）
         */
        $schedule = [
            ['name' => '厂家下单', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '客服接单', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '客服核实工单', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '客服派发工单', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '技工接单', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '技工预约', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '技工完成服务', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '客服回访', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '财务审核', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '厂家审核', 'is_arrivals' => 0, 'time' => 0],
            ['name' => '工单完结', 'is_arrivals' => 0, 'time' => 0],
        ];
        if ($order_status >= OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE) {
            $schedule[0]['is_arrivals'] = 1;
            $schedule[0]['time'] = $order['factory_check_order_time'];
        }
        if ($order_status >= OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK) {
            $schedule[1]['is_arrivals'] = 1;
            $schedule[1]['time'] = $order['checker_receive_time'];
        }
        if ($order_status >= OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE) {
            $schedule[2]['is_arrivals'] = 1;
            $schedule[2]['time'] = $order['check_time'];
        }
        if ($order_status >= OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL) {
            $schedule[3]['is_arrivals'] = 1;
            $schedule[3]['time'] = $order['distribute_time'];
        }
        if ($order_status >= OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT) {
            $schedule[4]['is_arrivals'] = 1;
            $schedule[4]['time'] = $order['worker_receive_time'];
        }
        if ($order_status >= OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE) {
            $schedule[5]['is_arrivals'] = 1;
            $schedule[5]['time'] = $order['worker_first_appoint_time'];
        }
        if ($order_status >= OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE) {
            $schedule[6]['is_arrivals'] = 1;
            $schedule[6]['time'] = $order['worker_repair_time'];
        }
        if ($order_status >= OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE && $order_status != OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT) {
            $schedule[7]['is_arrivals'] = 1;
            $schedule[7]['time'] = $order['return_time'];
        }
        if ($order_status >= OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT && $order_status != OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT) {
            $schedule[8]['is_arrivals'] = 1;
            $schedule[8]['time'] = $order['audit_time'];
        }
        if ($order_status == OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED) {
            $schedule[9]['is_arrivals'] = 1;
            $schedule[9]['time'] = $order['factory_audit_time'];
        }
        if ($order_status >= OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED) {
            $schedule[10]['is_arrivals'] = 1;
            $schedule[10]['time'] = $order['factory_audit_time'];
        }
//        $order_status >= 2 && $schedule[0]['is_arrivals'] = 1;
//        $order_status >= 3 && $schedule[1]['is_arrivals'] = 1;
//        $order_status >= 4 && $schedule[2]['is_arrivals'] = 1;
//        $order_status >= 6 && $schedule[3]['is_arrivals'] = 1;
//        $order_status >= 7 && $schedule[4]['is_arrivals'] = 1;
//        $order_status >= 8 && $schedule[5]['is_arrivals'] = 1;
//        $order_status >= 10 && $schedule[6]['is_arrivals'] = 1;
//        $order_status >= 13 && $schedule[7]['is_arrivals'] = 1;
//        $order_status >= 16 && $schedule[8]['is_arrivals'] = 1;
//        $order_status >= 17 && $schedule[9]['is_arrivals'] = 1;
//        $order_status == 18 && $schedule[10]['is_arrivals'] = 1;

        $data = [
            'schedule'      => $schedule,
            'cancel_status' => $cancel_status,
        ];

        return $data;
    }

    public function checkFactoryOrderPermission($worker_order_id, $factory_id = null)
    {
        !$factory_id && $factory_id = AuthService::getAuthModel()->factory_id;
        $order_factory_id = BaseModel::getInstance('worker_order')
            ->getFieldVal($worker_order_id, 'factory_id');
        if ($order_factory_id != $factory_id) {
            $this->throwException(ErrorCode::SYS_NOT_POWER, '您无权限操作该工单');
        }
    }

    public function distribute2Worker($worker_order_id, $data)
    {
        $worker = BaseModel::getInstance('worker')
            ->getOne($data['worker_id'], 'worker_id,worker_telephone,nickname,base_distance,base_distance_cost,exceed_cost');
        if (!$worker['worker_telephone']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择派单技工');
        }


        $worker_order_model = BaseModel::getInstance('worker_order');
        $order = $worker_order_model->getOneOrFail($worker_order_id, 'orno,parent_id,worker_order_status,worker_id,service_type,worker_order_type,children_worker_id,worker_group_id,distributor_id');
        if ($order['worker_order_status'] == OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE) { // 首次派单
            if ($order['worker_order_status'] != OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单不是待派单状态,请确认~');
            }
        } else {    // 重新派发
            // 保外单需要判断是否已支付
            if (!in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
                $order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne($worker_order_id, 'is_user_pay');
                if ($order_user_info['is_user_pay'] == 1) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户已支付,无法重新派发');
                }
            }
            if ($order['worker_order_status'] >= OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE && $order['worker_order_status'] != OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已完成维修无法重新派发');
            }
            $original_worker_id           = $order['worker_id'];
            $original_children_worker_id  = $order['children_worker_id'];
            $original_worker_order_status = $order['worker_order_status'];
        }

        //查找技工关联的群id
        $group_id = GroupService::getGroupId($data['worker_id']);
        $original_group_id = $order['worker_group_id'];

        $worker_order_model->startTrans();
        $worker_order_model->update($worker_order_id, [
            'distribute_mode'     => $data['distribute_mode'],
            'worker_id'           => $worker['worker_id'],
            'cp_worker_phone'     => $worker['worker_telephone'],
            'worker_order_status' => OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
            'distribute_time'     => NOW_TIME,
            'worker_receive_time' => NOW_TIME,
            'last_update_time'    => NOW_TIME,
            'worker_group_id'     => $group_id ?? null,
            'children_worker_id'  => null,
        ]);
        BaseModel::getInstance('worker_order_fee')->update($worker_order_id, [
            'homefee_mode' => $data['homefee_mode'],
        ]);
        BaseModel::getInstance('worker_order_ext_info')
            ->update($worker_order_id, [
                'is_send_user_message'   => $data['is_send_user_message'],
                'user_message'           => $data['user_message'],
                'is_send_worker_message' => $data['is_send_worker_message'],
                'worker_message'         => $data['worker_message'],
                'est_miles'              => $data['est_miles'],
                'straight_miles'         => $data['straight_miles'],
                'worker_base_distance'  => $worker['base_distance'],
                'worker_base_distance_fee'     => $worker['base_distance_cost'],
                'worker_exceed_distance_fee'   => $worker['exceed_cost'],
            ]);
        //更新群内工单数量
        if (!empty($group_id) || !empty($original_group_id)) {
            event(new UpdateOrderNumberEvent([
                'worker_order_id'              => $worker_order_id,
                'operation_type'               => OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE,
                'original_worker_id'           => $original_worker_id ?? null,
                'original_children_worker_id'  => $original_children_worker_id ?? null,
                'original_worker_order_status' => $original_worker_order_status ?? null,
                'original_group_id'            => $original_group_id ?? null
            ]));
        }
        // 技工信誉记录处理
        // 删除原有技工的信誉记录
        $worker_order_reputation_model = BaseModel::getInstance('worker_order_reputation');
        if ($order['worker_id'] != $data['worker_id']) {
            $worker_order_reputation_model->remove([
                'worker_order_id' => $worker_order_id,
                'worker_id'       => $order['worker_id'],
            ]);
            if (!empty($original_group_id) && !empty($original_children_worker_id)) {
                $worker_order_reputation_model->remove([
                    'worker_order_id' => $worker_order_id,
                    'worker_id'       => $original_children_worker_id,
                ]);
            }
        }
        // 修改技工信誉记录
        $worker_order_reputation = $worker_order_reputation_model->getOne([
            'worker_order_id' => $worker_order_id,
            'worker_id'       => $data['worker_id'],
        ], 'id');
        if ($worker_order_reputation) {
            $worker_order_reputation_model->update($worker_order_reputation['id'], [
                'is_complete' => 0,
                'is_return'   => 0,
            ]);
        } else {
            $insert_data = [
                'worker_order_id' => $worker_order_id,
                'worker_id'       => $data['worker_id'],
                'addtime'         => NOW_TIME,
            ];
            if (!empty($group_id)) {
                $insert_data['cp_worker_type'] = GroupService::WORKER_TYPE_GROUP_OWNER;
            }
            $worker_order_reputation_model->insert($insert_data);
        }

        //添加工单质量回访空记录
        BaseModel::getInstance('worker_order_quality')->insert([
            'worker_order_id' => $worker_order_id,
            'worker_id'       => $data['worker_id'],
            'addtime'         => NOW_TIME,
        ]);

        // 转发该工单下的配件单
        BaseModel::getInstance('worker_order_apply_accessory')->update([
            'worker_order_id' => $worker_order_id,
//            'worker_id'    => $order['worker_id'],
        ], [
            'worker_id' => $data['worker_id'],
        ]);

        //清空  "技工 < - > 客服"  留言信息
        $worker_order_message_filter = ' ((add_type=' . OrderMessageService::ADD_TYPE_FACTORY_WORKER . ' AND receive_type=' . OrderMessageService::RECEIVE_TYPE_CS . ') OR (add_type=' . OrderMessageService::ADD_TYPE_CS . ' AND receive_type=' . OrderMessageService::RECEIVE_TYPE_FACTORY_WORKER . '))';
        BaseModel::getInstance('worker_order_message')->remove([
            'worker_order' => $worker_order_id,
            '_string'      => $worker_order_message_filter,
        ]);
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE, [
            'remark' => ($data['desc'] ? $data['desc'] . '，' : '') . "接单师傅：{$worker['nickname']}",
        ]);
        $worker_order_model->commit();

        // 工单产品信息
        $order_products = BaseModel::getInstance('worker_order_product')
            ->getList([
                'where' => [
                    'worker_order_id' => $worker_order_id,
                ],
                'field' => 'cp_product_brand_name,cp_category_name',
            ]);
        $order_product_list = [];
        foreach ($order_products as $order_product) {
            $order_product_list[] = $order_product['cp_product_brand_name'] . $order_product['cp_category_name'];
        }
        $order_product_list = implode(';', $order_product_list);

        $service_type = OrderService::SERVICE_TYPE[$order['service_type']];

        // 发送用户、技工短信
        if ($data['is_send_user_message'] || $data['is_send_worker_message']) {
            // 工单用户信息
            $order_user_info = BaseModel::getInstance('worker_order_user_info')
                ->getOne($worker_order_id, 'real_name,phone');
            if ($data['is_send_user_message']) {
                $remark = $data['user_message'] ? $data['user_message'] . '。' : '';
                sendSms($order_user_info['phone'], SMSService::TMP_ORDER_DISTRIBUTE_NOTIFY_USER, [
                    'username' => $order_user_info['real_name'],
                    'product' => $order_product_list,
                    'worker' => $worker['nickname'] . ' ' . $worker['worker_telephone'],
                    'remark' => $remark,
                ]);
//                sendSms($order_user_info['phone'], "尊敬的{$order_user_info['real_name']}:您好，您售后的{$order_product_list}，已安排师傅“{$worker['nickname']} {$worker['worker_telephone']}”跟进，师傅会尽快与您预约，请耐心等待。{$remark}");
            }
            if ($data['is_send_worker_message']) {

                $remark = $data['worker_message'] ? $data['worker_message'] . '。' : '';

                if (in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
                    $out_in_str = '';
                } else {
                    $out_in_str = '(保外)';
                }
                sendSms($worker['worker_telephone'], SMSService::TMP_ORDER_DISTRIBUTE_NOTIFY_WORKER, [
                    'workername' => $worker['nickname'],
                    'product' => $order_product_list,
                    'servicetype' => $service_type,
                    'type' => $out_in_str,
                    'remark' => $remark,
                ]);
//                sendSms($worker['worker_telephone'], "{$worker['nickname']}您好，有一张{$order_product_list}的{$service_type}新工单{$out_in_str}，请及时预约用户。{$remark}用微信也可以接单了，请在微信搜索里选择“公众号”，然后搜索“神州联保企业号”即可。新单通知更及时，随时随地接工单！");
            }
        }

        // 改派工单
        if ($order['worker_id'] != $data['worker_id'] && $order['worker_id'] != 0) {
            $admin = AuthService::getAuthModel();
            $user_address = BaseModel::getInstance('worker_order_user_info')
                ->getFieldVal($worker_order_id, 'address');
            $former_worker = BaseModel::getInstance('worker')
                ->getOne($order['worker_id'], 'worker_telephone,nickname');
            sendSms($former_worker['worker_telephone'], SMSService::TMP_ORDER_RECYCLING_NOTIFY_WORKER, [
                'orno' => $order['orno'],
                'useraddress' => $user_address,
                'product' => $order_product_list,
                'servicetype' => $service_type,
                'adminname' => $admin['user_name'],
                'adminphone' => $admin['tell_out'],
            ]);
//            sendSms($former_worker['worker_telephone'], "您的工单{$order['orno']}（{$user_address}，{$order_product_list}，{$service_type}）已被客服收回，如有疑问可联系{$admin['user_name']}：{$admin['tell_out']}");
        }

        $classification = OrderService::getClassificationByOrno($order['orno']);
        $notification_type = AppMessageService::TYPE_NEW_WORKER_ORDER_MASSAGE;
        if ($classification == OrderService::CLASSIFICATION_REWORK_ORDER_TYPE) {
            $parent_order = BaseModel::getInstance('worker_order')->getOne($order['parent_id'], 'worker_id');
            if ($parent_order['worker_id'] == $data['worker_id']) {
                $notification_type = AppMessageService::TYPE_NEW_REWORK_ORDER_MESSAGE;
            }
        }
        event(new WorkbenchEvent(['worker_order_id' => $worker_order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_DISTRIBUTOR_DISTRIBUTE')]));

        event(new OrderSendNotificationEvent([
            'data_id' => $worker_order_id,
            'type'    => $notification_type
        ]));
    }

    /**
     * 检查客服是否有操作权限操作
     *
     * @param            $worker_order_id
     * @param null|array $admin
     */
    public function checkAdminOrderPermission($worker_order_id, $admin = null)
    {
        !$admin && $admin = AuthService::getAuthModel();
        $worker_order_status = BaseModel::getInstance('worker_order')
            ->getFieldVal($worker_order_id, 'worker_order_status');
        $allow_status = AdminRoleService::ROLE_HANDLE_STATUS_MAP[$admin['role_id']];
        if (!in_array($worker_order_status, $allow_status)) {
            $this->throwException(ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION);
        }
    }

    public function checkAdminOrderOperatePermission($worker_order_status, $admin = null, $warning='')
    {
        !$admin && $admin = AuthService::getAuthModel();
        $allow_status = AdminRoleService::ROLE_HANDLE_STATUS_MAP[$admin['role_id']];
        if (!in_array($worker_order_status, $allow_status)) {
            $this->throwException(ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION, $warning);
        }
    }

    public function checkAdminOrderTransferPermission($worker_order_status, $admin = null, $warning='')
    {
        !$admin && $admin = AuthService::getAuthModel();
        $allow_status = AdminRoleService::ROLE_TRANSFER_ADMIN_STATUS_MAP[$admin['role_id']];
        if (!in_array($worker_order_status, $allow_status)) {
            $this->throwException(ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION, $warning);
        }
    }

    public function batchAddOrder($orders)
    {

        //        $origin_type = AuthService::getModel() == AuthService::ROLE_FACTORY ? OrderService::ORIGIN_TYPE_FACTORY : OrderService::ORIGIN_TYPE_FACTORY_ADMIN;
        //        $add_id = AuthService::getAuthModel()->getPrimaryValue();
        $factoryInfo = AuthService::getAuthModel();
        $product_model = BaseModel::getInstance('factory_product');

        $create_order_service = (new OrderService\CreateOrderService($factoryInfo['factory_id'], true));

        $worker_order_ids = [];
        foreach ($orders as &$data) {
            foreach ($data['products'] as $key => $product) {
                if ($product['product_category_id'] && $product['product_standard_id'] && $product['product_brand_id'] && $product['cp_product_mode']) {
                    $data['products'][$key]['product_id'] = $product_model->getFieldVal([
                        'factory_id'       => $factoryInfo['factory_id'],
                        'product_category' => $product['product_category_id'],
                        'product_guige'    => $product['product_standard_id'],
                        'product_brand'    => $product['product_brand_id'],
                        'product_xinghao'  => $product['cp_product_mode'],
                    ], 'product_id');
                    if (!$data['products'][$key]['product_id']) {
                        $data['products'][$key]['product_id'] = $product_model->insert([
                            'factory_id'       => $factoryInfo['factory_id'],
                            'product_category' => $product['product_category_id'],
                            'product_guige'    => $product['product_standard_id'],
                            'product_brand'    => $product['product_brand_id'],
                            'product_xinghao'  => $product['cp_product_mode'],
                        ]);
                    }
                } else {
                    $data['products'][$key]['product_id'] = 0;
                }
            }
            $data['is_insured'] = 1;
            $data['worker_order_status'] = OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
            $data['factory_check_order_time'] = NOW_TIME;

            $products = $data['products'];
            $user = $data['user'];
            $ext_info = $data['ext_info'];
            unset($data['products']);
            unset($data['user']);
            unset($data['ext_info']);
            $create_order_service->setOrderProducts($products);
            $create_order_service->setOrderUser($user);
            $create_order_service->setOrderExtInfo($ext_info);
            $create_order_service->setOrder($data);

            $worker_order_id = $create_order_service->create();
            $worker_order_ids[] = $worker_order_id;
        }

        return $worker_order_ids;
    }

    // 添加工单操作记录
    function addOrderOperationRecord($opeUserId, $opeNickName, $orderId, $opeRole = '', $operation = '', $ope_type = '', $desc = '', $column = '', $value = '')
    {

        $data = [];

        $data['ope_user_id'] = $opeUserId;
        $data['ope_user_name'] = $opeNickName;
        $data['order_id'] = $orderId;
        $data['ope_role'] = $opeRole;
        $data['operation'] = $operation;
        $data['desc'] = $desc;
        $data['add_time'] = time();
        $data['column_name'] = $column;
        $data['column_value'] = $value;
        $data['ope_type'] = $ope_type;

        //AAA($data);
        BaseModel::getInstance('worker_order_operation_record')->insert($data);
        //        $res = M('worker_order_operation_record')->add( $data );

        //        if($res){
        //            return true;
        //        }else{
        //            return false;
        //        }
    }

    public function loadWorkerPaidOrder(&$worker_ids)
    {
        $worker_orders = BaseModel::getInstance('worker_order')
            ->getList([
                'where' => ['worker_id' => ['IN', $worker_ids], 'is_complete' => 1],
                'field' => 'worker_id,count(*) order_num',
                'group' => 'worker_id',
                'order' => null,
            ]);
        $worker_id_order_num_map = [];
        foreach ($worker_orders as $worker_order) {
            $worker_id_order_num_map[$worker_order['worker_id']] = $worker_order['order_num'];
        }

        $worker_id_order_score_map = [];
        foreach ($worker_ids as $worker_id) {

            if (isset($worker_id_order_num_map[$worker_id])) {
                if ($worker_id_order_num_map[$worker_id] >= 100) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[0];
                } elseif ($worker_id_order_num_map[$worker_id] >= 90) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[1];
                } elseif ($worker_id_order_num_map[$worker_id] >= 80) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[2];
                } elseif ($worker_id_order_num_map[$worker_id] >= 70) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[3];
                } elseif ($worker_id_order_num_map[$worker_id] >= 60) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[4];
                } elseif ($worker_id_order_num_map[$worker_id] >= 50) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[5];
                } elseif ($worker_id_order_num_map[$worker_id] >= 40) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[6];
                } elseif ($worker_id_order_num_map[$worker_id] >= 30) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[7];
                } elseif ($worker_id_order_num_map[$worker_id] >= 20) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[8];
                } elseif ($worker_id_order_num_map[$worker_id] >= 10) {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[9];
                } else {
                    $worker_id_order_score_map[$worker_id] = C('PAID_ORDER')[10];
                }
            } else {
                $worker_id_order_score_map[$worker_id] = 0;
            }
        }

        return $worker_id_order_score_map;
    }


    public function getOneOrderInstallInPrice($category_id = 0, $stantard_id = 0)
    {
        $faults = M('product_miscellaneous')
            ->where(['product_id' => $category_id])
            ->find();

        $fault_ids = implode(',', array_filter(explode(',', $faults['product_faults'])));

        $field = 'PF.id,PF.fault_name,PF.fault_type,PFP.product_id as product_category,PFP.standard_id,PFP.factory_in_price,PFP.factory_out_price,PFP.worker_in_price,PFP.worker_out_price';

        $price_where = [
            'PF.id'           => ['in', $fault_ids],
            'PFP.standard_id' => $stantard_id,
            'PF.fault_type'   => 1,
        ];

        $get_price_data = $fault_ids ? M('product_fault')
            ->alias('PF')
            ->join('LEFT JOIN product_fault_price PFP ON PF.id = PFP.fault_id')
            ->where($price_where)
            ->field($field)
            // ->order('PF.sort ASC')
            ->order('PF.sort ASC,PFP.factory_in_price ASC')
            ->find()
            : null;

        return $get_price_data;
    }


    public function recalculateOrderFactoryFee($worker_order_id)
    {
        BaseModel::getInstance('worker_order_fee')
            ->execute("UPDATE `worker_order_fee` SET `factory_total_fee_modify`=`factory_appoint_fee_modify`+`factory_repair_fee_modify`+`service_fee_modify`+`factory_cost_fee`+`accessory_return_fee` WHERE worker_order_id={$worker_order_id}");
    }

    public function recalculateWorkerOrderFee($worker_order_id)
    {
        BaseModel::getInstance('worker_order_fee')
            ->execute("UPDATE `worker_order_fee` SET `worker_total_fee_modify`=`worker_appoint_fee_modify`+`worker_repair_fee_modify`+`worker_cost_fee`+`worker_allowance_fee_modify`+`accessory_return_fee`+`worker_allowance_fee_modify`-`insurance_fee`+`quality_fee` WHERE worker_order_id={$worker_order_id}");
    }

    public function updateOrderFee($worker_order_id, $data)
    {
        BaseModel::getInstance('worker_order_fee')
            ->update($worker_order_id, $data);
    }

    public function getOrderStatusNumMap($status_list, $extras = [])
    {
        $where = [
            'worker_order_status' => ['IN', $status_list],
            'cancel_status'       => ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
        ];
        $where = array_merge($where, $extras);
        $data = BaseModel::getInstance('worker_order')->getList([
            'where' => $where,
            'group' => 'worker_order_status',
            'field' => 'worker_order_status,count(*) num',
            'index' => 'worker_order_status',
        ]);

        return $data;
    }

    public function updateOrdersProductsServices($order_id, $data = [])
    {
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($order_id, 'id,worker_order_status,factory_id,service_type,worker_order_type');

        // 在维修商提交完成报告之后，客服确认与维修商结算之前
        if (
            $order['worker_order_status'] < OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE
            && $order['worker_order_status'] > OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT
        ) {
            $this->throwException(ErrorCode::ORDER_STATUS_CATNOT_EDIT_SERVICE);
        }


        $detail_ids = $fault_ids = [];

        $last = [];
        foreach ($data as $k => $v) {
            $detail_ids[] = $v['worker_order_product_id'];
            $fault_ids[] = $v['fault_id'];
            $last[$v['worker_order_product_id']] = $v;
        }

        // 工单产品检查
        $detail_ids = array_filter($detail_ids);
        count($detail_ids) != count($data) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少工单产品参数');
        $detail_ids = array_unique($detail_ids);
        $detail_ids_str = implode(',', $detail_ids);
        $details = $detail_ids_str ? BaseModel::getInstance(self::ORDER_PRODUCT_TABLE_NAME)
            ->getList([
                'field' => 'id,admin_edit_fault_times,cp_fault_name',
                'where' => [
                    'id'              => ['in', $detail_ids_str],
                    'worker_order_id' => $order_id,
                ],
                'index' => 'id',
            ]) : [];
        count($detail_ids) != count($details) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单产品参数错误');

        // 维修项检查
        $fault_ids = array_filter($fault_ids);
        count($fault_ids) != count($data) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少维修项参数');
        $fault_ids = array_unique($fault_ids);
        $fault_ids_str = implode(',', $fault_ids);
        $faults = $fault_ids_str ? BaseModel::getInstance(self::PRODUCT_FAULT_TABLE_NAME)
            ->getList([
                'field' => 'id,fault_name',
                'where' => ['id' => ['in', $fault_ids_str]],
                'index' => 'id',
            ]) : [];
        count($fault_ids) != count($faults) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '维修项参数错误');

        // 修改次数限制
        foreach ($details as $v) {
                $v['admin_edit_fault_times'] >= OrderService::ORDER_PRODUCT_ADMIN_EDIT_FAULT_TIMES
            && $this->throwException(ErrorCode::ORDER_DETAIL_EDIT_FAULT_DY_TWO);
        }

        M()->startTrans();
        foreach ($last as $k => $v) {
            BaseModel::getInstance(self::ORDER_PRODUCT_TABLE_NAME)
                ->update($v['worker_order_product_id'], [
                    'fault_id' => $v['fault_id'],
                    'cp_fault_name' => $faults[$v['fault_id']]['fault_name'],
                    'admin_edit_fault_times'       => $details[$v['worker_order_product_id']]['admin_edit_fault_times'] + 1,
                ]);
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_MODIFY_SERVICE_FAULT, [
                'worker_order_product_id' => $v['worker_order_product_id'],
                'worker_order_product_ids' => $detail_ids_str, // 维修项
                'content_replace'         => [
                    'fault_name' => $faults[$v['fault_id']]['fault_name'],
                    'remark'     => $v['remark'] ?? '',
                ],
            ]);
        }
        // 计算工单产品表的价钱数据，并保存
        // OrderSettlementService::settlement(
        //     $order_id,
        //     OrderOperationRecordService::CS_MODIFY_SERVICE_FAULT,
        //     [
        //         'worker_order_product_ids' => $detail_ids_str,
        //         'worker_order'             => $order,
        //     ]
        // );
//        $is_in_order = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;
        // 保外单不进行资金修改及与统计
//        $is_in_order && OrderSettlementService::autoSettlement();
        OrderSettlementService::autoSettlement();
        M()->commit();
    }

    //获取配件单状态对应的颜色color_type
    protected function getAccessoryColorType($has_accessory_worker_order_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_accessory_worker_order_ids)) {
            return [];
        }

        $where = [
            'worker_order_id' => ['IN', $has_accessory_worker_order_ids],
        ];

        if ($auth_model != AuthService::ROLE_ADMIN) {
            $where['accessory_status'] = ['EGT', AccessoryService::STATUS_ADMIN_CHECKED];
        }

        //获取配件单
        $accessory_records = BaseModel::getInstance('worker_order_apply_accessory')->getList([
            'field' => 'group_concat(id order by id desc) ac',
            'where' => $where,
            'group' => 'worker_order_id'
        ]);

        //获取到最新的配件单的id
        $new_accessory_record_ids = [];
        foreach ($accessory_records as $key => $val) {
            $accessory_records_id [$key]= array_slice(explode(',', $val['ac']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_accessory_record_ids = array_merge($new_accessory_record_ids, $accessory_records_id[$key]);  //合并成一维数组
        }

        //查询到最新配件单id对应的配件单状态。
        $accessory_status_records = $new_accessory_record_ids ? BaseModel::getInstance('worker_order_apply_accessory')->getList([
            'field' =>  'worker_order_id,is_giveup_return,cancel_status,accessory_status',
            'where' =>  ['id'   =>  ['IN', $new_accessory_record_ids]],
        ]) : [];
        //配件单对应的颜色映射  color_type 0无标识 1 红色 2 绿色
        $color_type_map [AuthService::ROLE_ADMIN] = [
            AccessoryService::STATUS_WORKER_APPLY_ACCESSORY => '1',
            AccessoryService::STATUS_ADMIN_FORBIDDEN        => '2',
            AccessoryService::STATUS_ADMIN_CHECKED          => '2',
            AccessoryService::STATUS_FACTORY_CHECKED        => '2',
            AccessoryService::STATUS_FACTORY_SENT           => '2',
            AccessoryService::STATUS_WORKER_TAKE            => '3',
            AccessoryService::STATUS_WORKER_SEND_BACK       => '4',
            AccessoryService::STATUS_FACTORY_FORBIDDEN      => '2',
            AccessoryService::STATUS_COMPLETE               => '2',
        ];

        $color_type_map [AuthService::ROLE_FACTORY_ADMIN] = $color_type_map [AuthService::ROLE_FACTORY] = [
            AccessoryService::STATUS_WORKER_APPLY_ACCESSORY => '0',
            AccessoryService::STATUS_ADMIN_FORBIDDEN        => '0',
            AccessoryService::STATUS_ADMIN_CHECKED          => '1',
            AccessoryService::STATUS_FACTORY_CHECKED        => '1',
            AccessoryService::STATUS_FACTORY_SENT           => '2',
            AccessoryService::STATUS_WORKER_TAKE            => '3',
            AccessoryService::STATUS_WORKER_SEND_BACK       => '4',
            AccessoryService::STATUS_FACTORY_FORBIDDEN      => '2',
            AccessoryService::STATUS_COMPLETE               => '2',
        ];

        // 根据配件单状态判断返回的颜色状态字段 color_type 0无标识 1 红色配 2 绿色配 3红色返 4绿色返
        foreach ($accessory_status_records as $key => $val) {
            //技工已签收（不需要返件，放弃配件返还）
            if ($val['accessory_status'] == AccessoryService::STATUS_WORKER_TAKE && $val['is_giveup_return'] != 0) {
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $accessory_status_records[$key]['color_type'] = '2';
                } else {
                    $accessory_status_records[$key]['color_type'] = '2';
                }
            } elseif ($val['cancel_status'] == AccessoryService::CANCEL_STATUS_ADMIN_STOP || $val['cancel_status'] == AccessoryService::CANCEL_STATUS_FACTORY_STOP) {
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $accessory_status_records[$key]['color_type'] = '2';
                } else {
                    $accessory_status_records[$key]['color_type'] = '2';
                }
            } else{
                $accessory_status_records[$key]['color_type'] = $color_type_map[$auth_model][$val['accessory_status']];
            }
        }

        $accessory_status_map = [];  //获取对应的维修工单
        foreach ($accessory_status_records as $item) {  //分配到对应维修工单id的数组里
            $accessory_status_map[$item['worker_order_id']]['color_type'] = $item['color_type'];
        }

        return $accessory_status_map;
    }

    //获取费用单状态对应的颜色color_type
    protected function getCostColorType($has_cost_order_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_cost_order_ids)) {
            return [];
        }

        //获取费用单
        $cost_records = BaseModel::getInstance('worker_order_apply_cost')->getList([
            'field' => 'group_concat(id order by id desc) cc',
            'where' => [
                'worker_order_id' => ['IN', $has_cost_order_ids],
            ],
            'group' => 'worker_order_id'
        ]);

        //获取到最新的费用单的id
        $new_cost_record_ids = [];
        foreach ($cost_records as $key => $val) {
            $cost_records_id [$key]= array_slice(explode(',', $val['cc']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_cost_record_ids = array_merge($new_cost_record_ids, $cost_records_id[$key]);  //合并成一维数组
        }

        //查询到最新费用单id对应的费用单状态。
        $cost_status_records = BaseModel::getInstance('worker_order_apply_cost')->getList([
            'field' =>  'worker_order_id,status',
            'where' =>  ['id'   =>  ['IN', $new_cost_record_ids]],
        ]);
        //费用单单状态对应的颜色映射 cost_type 0无标识 1 红色 2 绿色
        $cost_type_map [AuthService::ROLE_ADMIN] = [
            CostService::STATUS_APPLY             => '1',
            CostService::STATUS_ADMIN_PASS        => '2',
            CostService::STATUS_ADMIN_FORBIDDEN   => '2',
            CostService::STATUS_FACTORY_FORBIDDEN => '2',
            CostService::STATUS_FACTORY_PASS      => '2',
        ];

        $cost_type_map [AuthService::ROLE_FACTORY] = $cost_type_map [AuthService::ROLE_FACTORY_ADMIN] = [
            CostService::STATUS_APPLY             => '0',
            CostService::STATUS_ADMIN_PASS        => '1',
            CostService::STATUS_ADMIN_FORBIDDEN   => '0',
            CostService::STATUS_FACTORY_FORBIDDEN => '2',
            CostService::STATUS_FACTORY_PASS      => '2',
        ];

        // 根据费用单状态判断返回的颜色状态字段
        foreach ($cost_status_records as $key => $val) {
            $cost_status_records[$key]['color_type'] = $cost_type_map[$auth_model][$val['status']];
        }

        $cost_status_map = [];  //获取对应的维修工单
        foreach ($cost_status_records as $item) {  //分配到对应维修工单id的数组里
            $cost_status_map[$item['worker_order_id']]['color_type'] = $item['color_type'];
        }

        return $cost_status_map;
    }

    //投诉单
    public function getComplaintColorType ($has_complaint_order_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_complaint_order_ids)) {
            return [];
        }

        //获取投诉单
        $complaint_records = BaseModel::getInstance('worker_order_complaint')->getList([
            'field' => 'group_concat(id order by id desc) cr,group_concat(complaint_create_type order by complaint_create_type desc) type,worker_order_id',
            'where' => [
                'worker_order_id' => ['IN', $has_complaint_order_ids],
            ],
            'group' => 'worker_order_id'
        ]);

        //获取如果有厂家发起投诉的给当前维修工单下一个有的标识  0没有 1有
        foreach($complaint_records as $key => $val) {
            if (in_array('2',explode(',',$complaint_records[$key]['type'])) || in_array('3',explode(',',$complaint_records[$key]['type']))) {
                $complaint_records [$key]['has_factory_complaint_create'] = '1';
            }else {
                $complaint_records [$key]['has_factory_complaint_create'] = '0';
            }
        }

        //获取到最新的投诉单的id
        $new_complaint_record_ids = [];
        foreach ($complaint_records as $key => $val) {
            $complaint_records_id [$key]= array_slice(explode(',', $val['cr']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_complaint_record_ids = array_merge($new_complaint_record_ids, $complaint_records_id[$key]);  //合并成一维数组
        }

        //查询到最投诉单id对应的投诉单状态。
        $complaint_status_records = BaseModel::getInstance('worker_order_complaint')->getList([
            'field' =>  'worker_order_id,reply_time,verify_time',
            'where' =>  ['id'   =>  ['IN', $new_complaint_record_ids]],
        ]);

        //把维修工单对应的投诉单下放入厂家发起投诉的标识：0没有 ，1有
        foreach ($complaint_records as $key => $val) {
            foreach ($complaint_status_records as $k => $v) {
              if($complaint_records[$key]['worker_order_id'] == $complaint_status_records[$k]['worker_order_id']) {
                  $complaint_status_records [$k]['has_factory_complaint_create'] = $complaint_records[$key]['has_factory_complaint_create'];
              }
            }
        }

        // 根据投诉单状态判断返回的颜色状态字段 color_type 0无标识  1 红色 2 绿色
        foreach ($complaint_status_records as $key => $val) {

            //未回复
            if ($val['reply_time'] == 0) {
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $complaint_status_records[$key]['color_type'] = '1';
                } else {  //针对厂家发起的投诉
                    if ($complaint_status_records[$key]['has_factory_complaint_create'] == '1') {  //has_factory_complaint_create标识：0没有，1有
                        $complaint_status_records[$key]['color_type'] = '1';
                    }else{
                        $complaint_status_records[$key]['color_type'] = '0';
                    }
                }
            }

            //已回复未核实
            if ($val['reply_time'] > 0 && $val['verify_time'] == 0) {
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $complaint_status_records[$key]['color_type'] = '1';
                } else {  //针对厂家发起的投诉
                    if ($complaint_status_records[$key]['has_factory_complaint_create'] == '1') {  //has_factory_complaint_create标识：0没有，1有
                        $complaint_status_records[$key]['color_type'] = '2';
                    }else{
                        $complaint_status_records[$key]['color_type'] = '0';
                    }
                }
            }
            //已核实
            if ($val['reply_time'] > 0 && $val['verify_time'] > 0) {
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $complaint_status_records[$key]['color_type'] = '2';
                } else {  //针对厂家发起的投诉
                    if ($complaint_status_records[$key]['has_factory_complaint_create'] == '1') {  //has_factory_complaint_create标识：0没有，1有
                        $complaint_status_records[$key]['color_type'] = '2';
                    }else{
                        $complaint_status_records[$key]['color_type'] = '0';
                    }
                }
            }
        }

        $complaint_status_map = [];  //获取对应的维修工单
        foreach ($complaint_status_records as $item) {  //分配到对应维修工单id的数组里
            $complaint_status_map[$item['worker_order_id']]['color_type'] = $item['color_type'];
        }

        return $complaint_status_map;
    }

    //留言单颜色配对
    public function getMessageColorType ($has_message_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_message_ids)) {
            return [];
        }

        //查最新的留言
        $message_records = BaseModel::getInstance('worker_order_message')->getList([
            'field' => 'group_concat(id order by id desc) mc',
            'where' => [
                'worker_order_id' => ['IN', $has_message_ids],
            ],
            'group' => 'worker_order_id'
        ]);

        //查最旧的留言
        $message_records_old = BaseModel::getInstance('worker_order_message')->getList([
            'field' => 'group_concat(id order by id asc) omc',
            'where' => [
                'worker_order_id' => ['IN', $has_message_ids],
            ],
            'group' => 'worker_order_id'
        ]);

        //获取到最新的留言单的id
        $new_message_record_ids = [];
        foreach ($message_records as $key => $val) {
            $message_records_id [$key]= array_slice(explode(',', $val['mc']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_message_record_ids = array_merge($new_message_record_ids, $message_records_id[$key]);  //合并成一维数组
        }

        //获取到最旧的留言单id
        $old_message_record_ids = [];
        foreach ($message_records_old as $key => $val) {
            $old_message_records_id [$key]= array_slice(explode(',', $val['omc']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $old_message_record_ids = array_merge($old_message_record_ids, $old_message_records_id[$key]);  //合并成一维数组
        }

        if (empty($old_message_record_ids) || empty($new_message_record_ids)) {
            return [];
        }

        //查询到最新留言单id对应的留言单状态。
        $message_status_records = BaseModel::getInstance('worker_order_message')->getList([
            'field' =>  'worker_order_id,add_type,receive_type',
            'where' =>  ['id'   =>  ['IN', $new_message_record_ids]],
        ]);

        //查询到最旧留言单id对应的留言单状态。
        $old_message_status_records = BaseModel::getInstance('worker_order_message')->getList([
            'field' =>  'worker_order_id,add_type,receive_type',
            'where' =>  ['id'   =>  ['IN', $old_message_record_ids]],
        ]);

        // 根据留言单状态判断返回的颜色状态字段 color_type 0标识 1 红色 2 绿色
        foreach ($message_status_records as $key => $val) {
            foreach ($old_message_status_records as $k => $old) {
                if ($message_status_records[$key]['worker_order_id'] == $old_message_status_records[$k]['worker_order_id']) {  //寻找新旧留言单对应维修工单相等时去判断
                    //厂家对客服发起留言未回复
                    if (($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_FACTORY || $message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_FACTORY_ADMIN) && $message_status_records[$key]['receive_type'] == OrderMessageService::RECEIVE_TYPE_CS
                        && $message_status_records[$key]['add_type'] == $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] == $old_message_status_records[$k]['receive_type']) {
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '1';
                        } else {
                            $message_status_records[$key]['color_type'] = '2';
                        }
                    } elseif (($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_FACTORY || $message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_FACTORY_ADMIN) && $message_status_records[$key]['receive_type'] == OrderMessageService::RECEIVE_TYPE_CS
                       && $message_status_records[$key]['add_type'] != $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] != $old_message_status_records[$k]['receive_type']) {  ///厂家对客服发起留言已回复
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '2';
                        } else {
                            $message_status_records[$key]['color_type'] = '2';
                        }
                    }
                    //技工对客服发起留言未回复
                    elseif ($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_FACTORY_WORKER && $message_status_records[$key]['receive_type'] == OrderMessageService::RECEIVE_TYPE_CS
                        && $message_status_records[$key]['add_type'] == $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] == $old_message_status_records[$k]['receive_type']) {
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '1';
                        } else {
                            $message_status_records[$key]['color_type'] = '0';
                        }
                    } elseif ($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_FACTORY_WORKER && $val['receive_type'] == OrderMessageService::RECEIVE_TYPE_CS
                        && $message_status_records[$key]['add_type'] != $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] != $old_message_status_records[$k]['receive_type']) {  //技工对客服发起留言已回复
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '2';
                        } else {
                            $message_status_records[$key]['color_type'] = '0';
                        }
                    }
                    //客服发起留言厂家未回复
                    elseif ($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_CS && ($message_status_records[$key]['receive_type'] == OrderMessageService::ADD_TYPE_FACTORY || $message_status_records[$key]['receive_type'] == OrderMessageService::ADD_TYPE_FACTORY_ADMIN)
                        && $message_status_records[$key]['add_type'] == $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] == $old_message_status_records[$k]['receive_type']) {
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '2';
                        } else {
                            $message_status_records[$key]['color_type'] = '1';
                        }
                    } elseif ($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_CS && ($message_status_records[$key]['receive_type'] == OrderMessageService::ADD_TYPE_FACTORY || $message_status_records[$key]['receive_type'] == OrderMessageService::ADD_TYPE_FACTORY_ADMIN)
                        && $message_status_records[$key]['add_type'] != $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] != $old_message_status_records[$k]['receive_type']) {  //客服发起留言厂家已回复
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '2';
                        } else {
                            $message_status_records[$key]['color_type'] = '2';
                        }
                    }
                    //客服发起留言技工未回复
                    elseif ($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_CS && $message_status_records[$key]['receive_type'] == OrderMessageService::ADD_TYPE_FACTORY_WORKER
                        && $message_status_records[$key]['add_type'] == $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] == $old_message_status_records[$k]['receive_type']) {
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '1';
                        } else {
                            $message_status_records[$key]['color_type'] = '0';
                        }
                    } elseif ($message_status_records[$key]['add_type'] == OrderMessageService::ADD_TYPE_CS && $message_status_records[$key]['receive_type'] == OrderMessageService::ADD_TYPE_FACTORY_WORKER
                        && $message_status_records[$key]['add_type'] != $old_message_status_records[$k]['add_type'] && $message_status_records[$key]['receive_type'] != $old_message_status_records[$k]['receive_type']) {  //客服发起留言技工已回复
                        if ($auth_model == AuthService::ROLE_ADMIN) {
                            $message_status_records[$key]['color_type'] = '2';
                        } else {
                            $message_status_records[$key]['color_type'] = '0';
                        }
                    }
                }
            }
        }
        $message_status_map = [];  //获取对应的维修工单
        foreach ($message_status_records as $item) {  //分配到对应维修工单id的数组里
            $message_status_map[$item['worker_order_id']]['color_type'] = $item['color_type'];
        }
        return $message_status_map;
    }

    //取消单状态的颜色分配
    public function getCancelColorType($has_cancel_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_cancel_ids)) {
            return [];
        }

        //获取取消单
        $cancel_records = BaseModel::getInstance('worker_order')->getList([
            'field' => 'group_concat(id order by id desc) cancel',
            'where' => [
                'id' => ['IN', $has_cancel_ids],
            ],
            'group' => 'id'
        ]);

        //获取到最新的取消单的id
        $new_cancel_record_ids = [];
        foreach ($cancel_records as $key => $val) {
            $cancel_records_id [$key]= array_slice(explode(',', $val['cancel']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_cancel_record_ids = array_merge($new_cancel_record_ids, $cancel_records_id[$key]);  //合并成一维数组
        }

        //查询到最新取消单id对应的取消单状态。
        $cancel_status_records = BaseModel::getInstance('worker_order')->getList([
            'field' =>  'id,cancel_status',
            'where' =>  ['id'   =>  ['IN', $new_cancel_record_ids]],
        ]);

        foreach ($cancel_status_records as $key => $val) {  // color_type颜色：1红色  2 绿色
            if ($val['cancel_status'] == 1 || $val['cancel_status'] == 2 || $val['cancel_status'] == 3 || $val['cancel_status'] == 4) {
                if ($auth_model == AuthService::ROLE_ADMIN) {
                    $cancel_status_records[$key]['color_type'] = '2';
                } else {
                    $cancel_status_records[$key]['color_type'] = '2';
                }
            }
        }

        $cancel_status_map = [];  //获取对应的维修工单
        foreach ($cancel_status_records as $item) {  //分配到对应维修工单id的数组里
            $cancel_status_map[$item['id']]['color_type'] = $item['color_type'];
        }

        return $cancel_status_map;
    }

    //补贴单状态的颜色分配color_type
    protected function getAllowanceColorType($has_subsidy_order_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_subsidy_order_ids)) {
            return [];
        }

        //获取补贴单
        $allowance_records = BaseModel::getInstance('worker_order_apply_allowance')->getList([
            'field' => 'group_concat(id order by id desc) allowance',
            'where' => [
                'worker_order_id' => ['IN', $has_subsidy_order_ids],
            ],
            'group' => 'worker_order_id'
        ]);

        //获取到最新的补贴单的id
        $new_allowance_records_ids = [];
        foreach ($allowance_records as $key => $val) {
            $allowance_records_id [$key]= array_slice(explode(',', $val['allowance']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_allowance_records_ids = array_merge($new_allowance_records_ids, $allowance_records_id[$key]);  //合并成一维数组
        }

        //查询到最新补贴单id对应的费用单状态。
        $allowance_status_records = BaseModel::getInstance('worker_order_apply_allowance')->getList([
            'field' =>  'worker_order_id,status',
            'where' =>  ['id'   =>  ['IN', $new_allowance_records_ids]],
        ]);

        //补贴单状态对应的颜色映射 cost_type 0无标识 1 红色 2 绿色
        $allowance_type_map [AuthService::ROLE_ADMIN] = [
            AllowanceService::STATUS_UNCHECKED  => '1',
            AllowanceService::STATUS_PASS       => '2',
            AllowanceService::STATUS_NOT_PASS   => '2',
        ];

        $allowance_type_map [AuthService::ROLE_FACTORY] = $allowance_type_map [AuthService::ROLE_FACTORY_ADMIN] = [
            AllowanceService::STATUS_UNCHECKED  => '0',
            AllowanceService::STATUS_PASS       => '0',
            AllowanceService::STATUS_NOT_PASS   => '0',
        ];

        // 根据补贴单状态判断返回的颜色状态字段
        foreach ($allowance_status_records as $key => $val) {
            $allowance_status_records[$key]['color_type'] = $allowance_type_map[$auth_model][$val['status']];
        }

        $allowance_status_map = [];  //获取对应的维修工单
        foreach ($allowance_status_records as $item) {  //分配到对应维修工单id的数组里
            $allowance_status_map[$item['worker_order_id']]['color_type'] = $item['color_type'];
        }

        return $allowance_status_map;
    }

    //开点单状态的颜色分配color_type
    protected function getAddApplyColorType($has_worker_add_apply_ids)
    {
        $auth_model = AuthService::getModel();
        if (empty($has_worker_add_apply_ids)) {
            return [];
        }

        //获取开点单
        $add_apply_records = BaseModel::getInstance('worker_add_apply')->getList([
            'field' => 'group_concat(id order by id desc) apply',
            'where' => [
                'worker_order_id' => ['IN', $has_worker_add_apply_ids],
            ],
            'group' => 'worker_order_id'
        ]);

        //获取到最新的开点单的id
        $new_add_apply_records_ids = [];
        foreach ($add_apply_records as $key => $val) {
            $add_apply_records_id [$key]= array_slice(explode(',', $val['apply']), 0, 1);  //把字符串转换成数组，本身是个二维数组。
            $new_add_apply_records_ids = array_merge($new_add_apply_records_ids, $add_apply_records_id[$key]);  //合并成一维数组
        }

        //查询到最新开点单id对应的开点单状态。
        $add_apply_status_records = BaseModel::getInstance('worker_add_apply')->getList([
            'field' =>  'worker_order_id,status',
            'where' =>  ['id'   =>  ['IN', $new_add_apply_records_ids]],
        ]);

        //开点单状态对应的颜色映射 cost_type 0无标识 1 红色 2 绿色
        $add_apply_type_map [AuthService::ROLE_ADMIN] = [
            WorkerAddApplyService::STATUS_CANCELED      => '2',
            WorkerAddApplyService::STATUS_NEED_PROCESS  => '1',
            WorkerAddApplyService::STATUS_PROCESSING    => '1',
            WorkerAddApplyService::STATUS_FOLLOW_UP     => '1',
            WorkerAddApplyService::STATUS_CAN_NOT_ADDED => '2',
            WorkerAddApplyService::STATUS_HAD_ADDED     => '2',
        ];

        $add_apply_type_map [AuthService::ROLE_FACTORY] = $add_apply_type_map [AuthService::ROLE_FACTORY_ADMIN] = [
            WorkerAddApplyService::STATUS_CANCELED      => '0',
            WorkerAddApplyService::STATUS_NEED_PROCESS  => '0',
            WorkerAddApplyService::STATUS_PROCESSING    => '0',
            WorkerAddApplyService::STATUS_FOLLOW_UP     => '0',
            WorkerAddApplyService::STATUS_CAN_NOT_ADDED => '0',
            WorkerAddApplyService::STATUS_HAD_ADDED     => '0',
        ];

        // 根据开点单状态判断返回的颜色状态字段
        foreach ($add_apply_status_records as $key => $val) {
            $add_apply_status_records[$key]['color_type'] = $add_apply_type_map[$auth_model][$val['status']];
        }

        $add_apply_status_map = [];  //获取对应的维修工单
        foreach ($add_apply_status_records as $item) {  //分配到对应维修工单id的数组里
            $add_apply_status_map[$item['worker_order_id']]['color_type'] = $item['color_type'];
        }

        return $add_apply_status_map;
    }
}
