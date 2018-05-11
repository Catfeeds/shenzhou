<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/25
 * Time: 12:22
 */

namespace Common\Common\Service;

use Admin\Logic\AllowanceLogic;
use Admin\Logic\ApplyCostLogic;
use Admin\Logic\ProductLogic;
use Admin\Logic\WorkerAddApplyLogic;
use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\ApplyCostService;
use Common\Common\Service\WorkerOrderAppointRecordService;
use Common\Common\Service\AllowanceService;

class OrderSettlementService
{	
    const WORKER_TABLE_NAME                         = 'worker';
    const ORDER_TABLE_NAME                          = 'worker_order';
	const ORDER_PRODUCT_TABLE_NAME 					= 'worker_order_product';
	const ORDER_USER_INFO_TABLE_NAME                = 'worker_order_user_info';
	const FACTORY_PRODUCT_FAULT_PRICE_TABLE_NAME 	= 'factory_product_fault_price';
	const PRODUCT_FAULT_PRICE_TABLE_NAME 			= 'product_fault_price';
	const PRODUCT_FAULT_TABLE_NAME 					= 'product_fault';
	const CATEGORY_SERVICE_COST_TABLE_NAME 			= 'factory_category_service_cost';
    const ORDER_APPLY_ACCESSORY_TABLE_NAME          = 'worker_order_apply_accessory';
    const ORDER_APPLY_COST_TABLE_NAME               = 'worker_order_apply_cost';
    const ORDER_APPOINT_RECORD_TABLE_NAME           = 'worker_order_appoint_record';
    const ORDER_APPLY_ALLOWANCE_TABLE_NAME          = 'worker_order_apply_allowance';
    const ORDER_FEE_TABLE_NAME                      = 'worker_order_fee';

    const CS_MODIFY_SERVICE_FAULT = 'csModifyServiceFault'; // 客服修改维修项 计算
    const WORKER_SELECT_FAULT = 'csModifyServiceFault'; // 工单产品选择维修项 计算
	const WORKER_SUBMIT_PRODUCT_REPORT = 'orderIsRepair'; // 上传服务报告 技工完成维修 （上传完成服务报告，还有配简单和服务单，所以未统计总费用）
    const CS_EDID_ORDER_APPOINT_NUMS = 'csEditOrderAppointNums'; // 客服修改上门次数

    const WORKER_SUBMIT_WARRANTY_BILL = 'warrantyBillSettlement'; // 保外单结算
    const WORKER_UPDATE_WARRANTY_BILL = 'warrantyBillSettlement'; // 保外单修改费用
    const WORKER_ADD_OUT_ORDER_FEE = 'warrantyBillSettlement'; // 保外单费用加收

//    const CS_CONFIRM_USER_PAID          = 'orderFeeStatisticsUpdateFee'; // 保外单确认支付
//    const WORKER_ORDER_USER_PAY_SUCCESS = 'orderFeeStatisticsUpdateFee'; // 保外单确认支付
    // const CS_SETTLEMENT_FOR_WORKER = 'csSettlementForWorker'; // 确认工单已完成并提交财务审核 (回访成功),
	const ORDER_ACTION_SETTLEMENT_CONFIG = [
		OrderOperationRecordService::CS_MODIFY_SERVICE_FAULT      => self::CS_MODIFY_SERVICE_FAULT,
        OrderOperationRecordService::WORKER_SELECT_FAULT          => self::WORKER_SELECT_FAULT,
        OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT => self::WORKER_SUBMIT_PRODUCT_REPORT,
        OrderOperationRecordService::CS_EDID_ORDER_APPOINT_NUMS   => self::CS_EDID_ORDER_APPOINT_NUMS,
        OrderOperationRecordService::WORKER_SUBMIT_WARRANTY_BILL  => self::WORKER_SUBMIT_WARRANTY_BILL,
        OrderOperationRecordService::WORKER_UPDATE_WARRANTY_BILL  => self::WORKER_UPDATE_WARRANTY_BILL,
        OrderOperationRecordService::WORKER_ADD_OUT_ORDER_FEE     => self::WORKER_ADD_OUT_ORDER_FEE,

//        OrderOperationRecordService::CS_CONFIRM_USER_PAID  => self::CS_CONFIRM_USER_PAID,
//        OrderOperationRecordService::WORKER_ORDER_USER_PAY_SUCCESS  => self::WORKER_ORDER_USER_PAY_SUCCESS,
        OrderOperationRecordService::SYSTEM_DELETE_PRODUCT_AUTH_FINISH_REPAIR => self::WORKER_SUBMIT_PRODUCT_REPORT,
	];

    //  一次接口访问多次操作记录中，只计算一次的操作
    const ACTION_IS_END_DATA = [
        OrderOperationRecordService::CS_MODIFY_SERVICE_FAULT,
        OrderOperationRecordService::WORKER_SELECT_FAULT,
        OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT,
    ];

    public static $not_operation_type;

    public static function autoSettlement()
    {
        $auto_sort = $end = [];
        $action_log = OrderOperationRecordService::getAutoActionLog();

        foreach ($action_log as $sort => $v) {
            $operation_type     = $v['operation_type'];
            $worker_order_id    = $v['worker_order_id'];
//            $sort               = $sort;
            $end_key            = $operation_type.'_'.$worker_order_id;
            if (in_array($operation_type, self::ACTION_IS_END_DATA) && isset($end[$end_key])) {
                $pre_sort = $end[$end_key];
                unset($auto_sort[$pre_sort]);
            }
            $auto_sort[$sort] = $v;
            $end[$end_key] = $sort;
        }

        //ksort($auto_sort);

        foreach ($auto_sort as $v) {
            if (isset(self::ORDER_ACTION_SETTLEMENT_CONFIG[$v['operation_type']])) {
                self::settlement($v['worker_order_id'], $v['operation_type'], $v['extras']);
            }
        }
        // 运算成功后 清空 static::$autoactionlog,防止多次调用autoSettlement时重复计算
        OrderOperationRecordService::deleteAutoActionLog();
    }

    // 结算
    protected static function settlement($order_id, $type, $extras = [])
    {

        $cp = self::ORDER_ACTION_SETTLEMENT_CONFIG[$type];
        self::$not_operation_type = $type;
        self::$cp($order_id, $extras);
    }

    // 工单 技工完成维修
    protected static function orderIsRepair($order_id, $extras = [])
    {
        $model = BaseModel::getInstance(self::ORDER_TABLE_NAME);
        $product_model = BaseModel::getInstance(self::ORDER_PRODUCT_TABLE_NAME);
        $order = $model ->getOneOrFail($order_id);

        //  报外单结算计算方法
        // 是否是保内单: true 保内单; false 保外单;
        if (!isInWarrantPeriod($order['worker_order_type'])) {
            return;
        }
        if ($order['worker_order_status'] != OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE) {
            return;
        }
        $appoints = [];
        self::doorFeeSettlement($order_id, $appoints);

        // 统计工单完结的维修项金额   平台的服务费
        $products = BaseModel::getInstance(self::ORDER_PRODUCT_TABLE_NAME)->getList([
                'field' => 'worker_order_id,SUM(factory_repair_fee) as factory_repair_fee,SUM(factory_repair_fee_modify) as factory_repair_fee_modify,SUM(worker_repair_fee) as worker_repair_fee,SUM(worker_repair_fee_modify) as worker_repair_fee_modify,SUM(service_fee) as service_fee,SUM(service_fee_modify) as service_fee_modify',
                'where' => [
                    'worker_order_id' => $order_id,
                ],
                'group' => 'worker_order_id',
            ]);

        // 工单申请费用
        $apply_cost = BaseModel::getInstance(self::ORDER_APPLY_COST_TABLE_NAME)->getList([
                'field' => 'worker_order_id,SUM(fee) as worker_cost_fee,SUM(fee) as factory_cost_fee',
                'where' => [
                    'worker_order_id'   => $order_id,
                    'status'            => ApplyCostService::STATUS_FACTORY_CHECK_PASSED,
                ],
                'group' => 'worker_order_id',
            ]);

        $update = array_merge(
            (array)$products[0],
            (array)$apply_cost[0],
            (array)$appoints
        );
        $fee_data = self::orderFeeStatisticsUpdateFee($order_id, $update);

//        FactoryMoneyFrozenRecordService::process($order_id, FactoryMoneyFrozenRecordService::TYPE_WORKER_ORDER_PRODUCT_FINSH_REPAIR, $fee_data['factory_total_fee_modify']);
        // 上门费
//        $appoints = BaseModel::getInstance(self::ORDER_APPOINT_RECORD_TABLE_NAME)->getList([
//                'field' => 'worker_order_id,SUM(factory_appoint_fee) as factory_appoint_fee,SUM(factory_appoint_fee_modify) as factory_appoint_fee_modify,SUM(worker_appoint_fee) as worker_appoint_fee,SUM(worker_appoint_fee_modify) as worker_appoint_fee_modify',
//                'where' => [
//                    'worker_order_id'   => $order_id,
//                    'is_over'           => WorkerOrderAppointRecordService::IS_OVER_YES, // 不知道状态对不对
//                ],
//                'group' => 'worker_order_id',
//            ]);

        // 返件费用
//        $accessories = BaseModel::getInstance(self::ORDER_APPLY_ACCESSORY_TABLE_NAME)->getList([
//                'field' => 'worker_order_id,SUM(worker_transport_fee) as accessory_return_fee',
//                'where' => [
//                    'worker_order_id'   => $order_id,
//                    'accessory_status'  => AccessoryService::STATUS_COMPLETE,
//                    // 'is_giveup_return'  => AccessoryService::RETURN_ACCESSORY_PASS,
//                    'is_giveup_return'  => ['in', implode(',', AccessoryService::FACTORY_NEED_PAY_RETURN_STATUS)],
//                    'cancel_status'     => AccessoryService::CANCEL_STATUS_NORMAL,
//                    'worker_return_pay_method'  => AccessoryService::PAY_METHOD_NOW_PAY,
//                ],
//                'group' => 'worker_order_id',
//            ]);

        // 补贴单
//        $allowances = BaseModel::getInstance(self::ORDER_APPLY_ALLOWANCE_TABLE_NAME)->getList([
//                'field' => 'worker_order_id,SUM(apply_fee) as worker_allowance_fee,SUM(apply_fee) as worker_allowance_fee_modify',
//                'where' => [
//                    'worker_order_id'   => $order_id,
//                    'status'            => AllowanceService::STATUS_PASS,
//                ],
//                'group' => 'worker_order_id',
//            ]);

//        $update = array_merge(
//            (array)$products[0],
//            (array)$appoints[0],
//            (array)$accessories[0],
//            (array)$apply_cost[0],
//            (array)$allowances[0]
//        );
//        self::orderFeeStatisticsUpdateFee($order_id, $update);
    }

    protected  static function csEditOrderAppointNums($order_id, $extras = [])
    {
        $model = BaseModel::getInstance(self::ORDER_TABLE_NAME);
        $order = $model ->getOneOrFail($order_id);

        //  报外单结算计算方法
        // 是否是保内单: true 保内单; false 保外单;
        if (!isInWarrantPeriod($order['worker_order_type'])) {
            return;
        }
        $appoints = [];
        self::doorFeeSettlement($order_id, $appoints);
        self::orderFeeStatisticsUpdateFee($order_id, $appoints);
    }

	// 修改服务项的时候重新计算技工厂家维修费及厂家改产品类别的服务费
    protected static function csModifyServiceFault($order_id, $extras = [])
    {
        $order = $extras['worker_order'];
        if (
                !$order['id']
            ||  !$order['factory_id']
            || 	!$order['service_type']
            || 	!$order['worker_order_type']
        ) {
            $order = BaseModel::getInstance(self::ORDER_TABLE_NAME)->getOneOrFail($order_id, 'id,factory_id,service_type,worker_order_type');
        }
        // 保内单，保外单
        $order_is_in = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;

        // 保外单使用 worker_order_fee: accessory_out_fee,user_discount_out_fee
        if (!$order_is_in) {
            return;
        }

        $extras = array_filter($extras);
        $model = BaseModel::getInstance(self::ORDER_PRODUCT_TABLE_NAME);
        $where = [
            'worker_order_id' => $order_id
        ];

        isset($extras['worker_order_product_ids']) && $where['id'] = ['in', $extras['worker_order_product_ids']];
        $list = $model->getList($where, 'id,fault_id,product_id,product_category_id,product_standard_id,product_nums,admin_edit_fault_times');

        // 维修项的平台服务价格
        $cate_ids = arrFieldForStr($list, 'product_category_id');
        $cate_cost_list = $cate_ids ? BaseModel::getInstance(self::CATEGORY_SERVICE_COST_TABLE_NAME)->getList([
            'where' => [
                'factory_id' => $order['factory_id'],
                'cat_id' => ['in', $cate_ids],
            ],
            'order' => 'id asc',
            'index' => 'cat_id',
        ]) : [];

        $factory = BaseModel::getInstance('factory')->getOne($order['factory_id'], 'service_charge,default_frozen');
        $factory_sevice_fee = $factory['service_charge'];

        $logic = new \Qiye\Logic\OrderLogic();
        $jia_frozen = 0;
        foreach ($list as $k => $v) {
            if (isset($cate_cost_list[$v['product_category_id']])) {
                $sevice_fee = $cate_cost_list[$v['product_category_id']]['cost'];
            } else {
                $sevice_fee = $factory_sevice_fee;
            }
            $sevice_fee = number_format($sevice_fee * $v['product_nums'], 2, '.', '');
            // 维修项厂家和技工价格
            $price = $logic->getFaultFeeList([
                'factory_id' => $order['factory_id'],
                'category_id' => $v['product_category_id'],
                'standard_id' => $v['product_standard_id'],
                'fault_type' => FaultTypeService::getFaultType($order['service_type']),
                'fault_id' => $v['fault_id']
            ], true);

            $worker_price 	= number_format($price['worker_in_price'],  2, '.', '') * $v['product_nums'];
            $factory_price 	= number_format($price['factory_in_price'], 2, '.', '') * $v['product_nums'];
            $jia_frozen += $factory_price;

            // 修改服务项是发生在回访前，所以modify同步更新是没问题的
            $update = [
                'factory_repair_fee'		=> $factory_price,
                'factory_repair_fee_modify'	=> $factory_price,
                'worker_repair_fee'			=> $worker_price,
                'worker_repair_fee_modify'	=> $worker_price,
                'service_fee'				=> $sevice_fee,
                'service_fee_modify'		=> $sevice_fee,
            ];

            $model->update($v['id'], $update);
        }
        // 统计工单完结的维修项金额   平台的服务费
        $products = $model->getList([
            'field' => 'worker_order_id,SUM(factory_repair_fee) as factory_repair_fee,SUM(factory_repair_fee_modify) as factory_repair_fee_modify,SUM(worker_repair_fee) as worker_repair_fee,SUM(worker_repair_fee_modify) as worker_repair_fee_modify,SUM(service_fee) as service_fee,SUM(service_fee_modify) as service_fee_modify',
            'where' => [
                'worker_order_id' => $order_id,
            ],
            'group' => 'worker_order_id',
        ]);
        $update = (array)$products[0];
        $fee_data = self::orderFeeStatisticsUpdateFee($order_id, $update);
        // 冻结金
//        if ($order_is_in) {
//            $total_frozen = 0;
//            $not_where = $where;
//            $not_where['id'] = ['not in', $extras['worker_order_product_ids']];
//            $not_in = $model->getList($not_where, 'id,product_id,product_category_id,product_standard_id,product_nums,fault_id,factory_repair_fee_modify');
//            foreach ($not_in as $v) {
//                if ($v['fault_id']) {
//                    $total_frozen += $v['factory_repair_fee_modify'];
//                } else {
//                    $total_frozen += FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($order['service_type'], $order['factory_id'], $v['product_category_id'], $v['product_standard_id'], $factory['default_frozen']) * $v['product_nums'];
//                }
//            }
//            $total_frozen += $jia_frozen;
//
//            switch (self::$not_operation_type) {
//                case OrderOperationRecordService::CS_MODIFY_SERVICE_FAULT:
//                    FactoryMoneyFrozenRecordService::process($order_id, FactoryMoneyFrozenRecordService::TYPE_CS_MODIFY_PRODUCT_FAULT, $total_frozen);
//                    break;
//
//                case OrderOperationRecordService::WORKER_SELECT_FAULT:
//                    FactoryMoneyFrozenRecordService::process($order_id, FactoryMoneyFrozenRecordService::TYPE_WORKER_UPLOAD_PRODUCT_FAULT, $total_frozen);
//                    break;
//            }
//        }
    }

	/*
	 * 保外单结算
	 */
	public static function warrantyBillSettlement($order_id, $extras=[])
    {
        $extras['before_update_worker_repair_fee'] = $extras['before_update_worker_repair_fee'] ?? number_format($extras['before_update_worker_repair_fee'], 2, '.', '');
        $extras['worker_order_product_id'] && BaseModel::getInstance('worker_order_product')->update([
            'worker_order_id' => $order_id,
            'id' => $extras['worker_order_product_id'],
        ], [
            'worker_repair_fee'        => ['exp', 'worker_repair_fee-'.$extras['before_update_worker_repair_fee'].'+'.$extras['worker_repair_fee']],
            'worker_repair_fee_modify' => ['exp', 'worker_repair_fee_modify-'.$extras['before_update_worker_repair_fee'].'+'.$extras['worker_repair_fee']],
        ]);

        // TODO 报外单流程结算需要更改 OrderSettlementService::autoSettlement();     
        $service_fee = ($extras['all_worker_repair_fee'] + $extras['all_accessory_out_fee']) * C('NOTINRUANCE_SERVICE_FEE_PERENT');
        $fee_data = [
            'worker_repair_fee'        => $extras['all_worker_repair_fee'],
            'worker_repair_fee_modify' => $extras['all_worker_repair_fee'],
            'accessory_out_fee'        => $extras['all_accessory_out_fee'],
            'accessory_out_fee_modify' => $extras['all_accessory_out_fee'],
            'service_fee'              => $service_fee,
            'service_fee_modify'       => $service_fee
        ];

        self::orderFeeStatisticsUpdateFee($order_id, $fee_data);
    }

    /*
     * 上门费用结算
     */
    public static function doorFeeSettlement($order_id, &$worker_order_fee)
    {
        $order_model = BaseModel::getInstance(self::ORDER_TABLE_NAME);
        $appoint_model = BaseModel::getInstance(self::ORDER_APPOINT_RECORD_TABLE_NAME);

        //工单信息
        $order_info = $order_model->getOne([
            'alias' => 'wo',
            'where' => [
                'wo.id' => $order_id
            ],
            'join'  => 'left join worker_order_fee as wof on wof.worker_order_id=wo.id
                        left join worker_order_ext_info as woe on woe.worker_order_id=wo.id',
            'field' => 'wo.*, wof.homefee_mode, woe.est_miles, woe.worker_base_distance, woe.worker_base_distance_fee, woe.worker_exceed_distance_fee, woe.factory_base_distance, woe.factory_base_distance_fee, woe.factory_exceed_distance_fee'
        ]);

        // 是否是保内单: true 保内单; false 保外单;
        if (!isInWarrantPeriod($order_info['worker_order_type'])) {
            return;
        }

        //计费模式
        $homefee_mode = $order_info['homefee_mode'];

        //上门距离
        $est_miles    = $order_info['est_miles'];

        //总上门次数
        $appoint_count = $appoint_model->getNum([
            'where' => [
                'worker_order_id' => $order_id,
                'is_over' => 1
            ],
            'order' => 'create_time'
        ]);
        //工单预约信息
        $appoint_info = $appoint_model->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'is_over' => 1
            ],
            'order' => 'create_time'
        ]);

        //厂家基本信息、技工基本信息
        //厂家基本里程 ， 基本里程费，超程单价
        $base_distance 		 = $order_info['factory_base_distance'];
        $base_distance_cost  = $order_info['factory_base_distance_fee'];
        $exceed_cost 		 = $order_info['factory_exceed_distance_fee'];
        //技工基本里程 ， 基本里程费，超程单价
        $base_distance2 	 = $order_info['worker_base_distance'];
        $base_distance_cost2 = $order_info['worker_base_distance_fee'];
        $exceed_cost2 		 = $order_info['worker_exceed_distance_fee'];

        $factory_appoint_fee = 0.00;
        $worker_appoint_fee  = 0.00;
        foreach ($appoint_info as $k => $v) {
            $now = $k + 1;
            $data = array();
            $data['id'] 			= $v['id'];
            $data['factory_appoint_fee']   = 0.00;
            $data['factory_appoint_fee_modify'] = 0.00;
            $data['worker_appoint_fee']         = 0.00;
            $data['worker_appoint_fee_modify']  = 0.00;

            //上门次数为 0；厂家费用为：0，技工费用为：0
            if ($appoint_count == 0) {
                $data['factory_appoint_fee'] = 0.00;
                $data['worker_appoint_fee']  = 0.00;
            }
            //里程在基本里程内（厂家）
            if($est_miles <= $base_distance){
                //第一次免基本里程费 并 第一次上门
                if ($homefee_mode == 1 && $now==1) {
                    $data['factory_appoint_fee']  =  0.00;
                } elseif ($homefee_mode == 2 && $now == 2) {
                    $data['factory_appoint_fee']  =  0.00;
                } else {
                    $data['factory_appoint_fee']  =  $base_distance_cost;
                }
            } else {
                //超程公里数
                $out_miles   	  = $est_miles - $base_distance;
                //超程费
                $out_money   	  = $out_miles * $exceed_cost;
                //基本里程费
                $inner_money 	  = $base_distance_cost;

                if ($homefee_mode == 1 && $now == 1) {
                    $data['factory_appoint_fee']   =  $out_money;
                } elseif ($homefee_mode == 2 && $now == 2) {
                    $data['factory_appoint_fee']   =  $out_money;
                } else {
                    $data['factory_appoint_fee']  =   $inner_money + $out_money;
                }
            }

            //里程在基本里程内（技工）
            if ($est_miles <= $base_distance2) {
                //第一次免基本里程费 并 第一次上门
                if ($homefee_mode == 1 && $now == 1) {
                    $data['worker_appoint_fee']  =  0.00;
                } elseif ($homefee_mode == 2 && $now == 2) {
                    $data['worker_appoint_fee']  =  0.00;
                } else {
                    $data['worker_appoint_fee']  =  $base_distance_cost2;
                }
            } else {
                //超程公里数
                $out_miles   	  = $est_miles - $base_distance2;
                //超程费
                $out_money   	  = $out_miles * $exceed_cost2;
                //基本里程费
                $inner_money 	  = $base_distance_cost2;
                if ($homefee_mode == 1 && $now == 1) {			//第一次免基本
                    $data['worker_appoint_fee']   =  $out_money;
                } elseif ($homefee_mode == 2 && $now == 2) {	//第二次 基本为？元
                    $data['worker_appoint_fee']   =  $out_money;
                } else {									//超过2次以上的上门
                    $data['worker_appoint_fee']  =   $inner_money + $out_money;
                }
            }

            $data['factory_appoint_fee_modify'] = $data['factory_appoint_fee'];
            $data['worker_appoint_fee_modify']  = $data['worker_appoint_fee'];

            $homefee_desc = $homefee_mode==1 ? '首次上门免基本里程费':'第二次上门免基本里程费';
            $hf_fact_remark = '第('.$now.')次上门：'.$est_miles.'(公里), 费用为：'.$data['factory_appoint_fee'].'(元), <br>计费模式为：'.$homefee_desc.';<br> 基本里程：'.$base_distance.'(公里);<br> 基本里程费：'.$base_distance_cost.'(元);<br> 超程单价：'.$exceed_cost.'(元/公里)';
            $hf_work_remark = '第('.$now.')次上门：'.$est_miles.'(公里), 费用为：'.$data['worker_appoint_fee'].'(元), <br>计费模式为：'.$homefee_desc.';<br> 技工基本里程：'.$base_distance2.'(公里);<br> 技工基本里程费：'.$base_distance_cost2.'(元);<br> 技工超程单价：'.$exceed_cost2.'(元/公里)';
            $data['factory_appoint_remark'] = $hf_fact_remark;
            $data['worker_appoint_remark']  = $hf_work_remark;

            $res = $appoint_model->update([
                'id' => $v['id']
            ], $data);

            $factory_appoint_fee = $factory_appoint_fee + $data['factory_appoint_fee'];
            $worker_appoint_fee  = $worker_appoint_fee + $data['worker_appoint_fee'];
        }
        $worker_order_fee['factory_appoint_fee']        = $factory_appoint_fee;
        $worker_order_fee['factory_appoint_fee_modify'] = $factory_appoint_fee;
        $worker_order_fee['worker_appoint_fee']         = $worker_appoint_fee;
        $worker_order_fee['worker_appoint_fee_modify']  = $worker_appoint_fee;
        // BaseModel::getInstance('worker_order_fee')->update([
        //     'worker_order_id' => $order_id
        // ], $worker_order_fee);
//        self::orderFeeStatisticsUpdateFee($order_id, $worker_order_fee);
    }





    public static function unfreezeOrderMoneyAndOtherOrder($worker_order_id, $factory_id)
    {
        (new ApplyCostLogic())->cancelOrderApplyCost($worker_order_id);
        (new AllowanceLogic())->cancelOrderApplyAllowance($worker_order_id);
        (new WorkerAddApplyLogic())->cancelUncompletedApply($worker_order_id);

        $order_frozen = BaseModel::getInstance('factory_money_frozen')->getOne([
            'where' => ['worker_order_id' => $worker_order_id],
            'field' => 'frozen_money',
        ]);
        if ($order_frozen) {
            // 方法里面更新厂家总冻结金
            FactoryMoneyFrozenRecordService::process($worker_order_id, FactoryMoneyFrozenRecordService::TYPE_ORDER_CANCEL);
//            BaseModel::getInstance('factory')->setNumDec($factory_id, 'frozen_money', $order_frozen['frozen_money']);
        }
    }

    /*
     * 更新并更新统计表数据
     * $order 订单信息
     * $order_fee 订单费用信息，并将旧数据更新成$order_fee
     */
    public static function orderFeeStatisticsUpdateFee($order_id, $order_fee = [], $order = [])
    {
        $order_field    = [
            'id',
            'worker_id',
            'worker_order_type',
            'is_worker_pay'

        ];
        $fee_update_field = [
            'factory_appoint_fee',
            'factory_repair_fee',
//            'accessory_return_fee',
            'worker_accessory_return_fee',
            'worker_accessory_return_fee_modify',
            'factory_accessory_return_fee',
            'factory_accessory_return_fee_modify',
            'factory_cost_fee',
            'service_fee',
            'service_fee_modify',
            'factory_appoint_fee_modify',
            'factory_repair_fee_modify',
            'worker_appoint_fee',
            'worker_repair_fee',
            'worker_cost_fee',
            'worker_allowance_fee',
            'worker_appoint_fee_modify',
            'worker_repair_fee_modify',
            'worker_allowance_fee_modify',
            'accessory_out_fee',
            'accessory_out_fee_modify',
        ];

        $order_data_field   = array_keys($order);
        $fee_data_feild     = array_keys($order_fee);

        $order_model    = BaseModel::getInstance(self::ORDER_TABLE_NAME);
        $fee_model      = BaseModel::getInstance(self::ORDER_FEE_TABLE_NAME);

        // 检查参数 $order 数据满不满足 操作
        $order_diff = array_diff($order_field, array_intersect($order_data_field, $order_field));
        $order_diff && $order = $order_model->getOneOrFail($order_id, implode(',', $order_field));

        // 获取 worker_order_fee 数据，并检查传过来的参数，赛选掉不能修改的参数
        $data = $fee_model->getOneOrFail($order_id);
        $data['quality_fee']  = '0.00';
        $check_field = array_intersect($fee_data_feild, $fee_update_field);
        foreach ($check_field as $v) {
                isset($order_fee[$v]) && $data[$v] != $order_fee[$v]
            &&  $data[$v] = number_format($order_fee[$v], 2, '.', '');
        }

        // $order_fee 在 $data 中能保存的数据，保存的数据是统计过的资金数据
        $user_info = BaseModel::getInstance('worker_order_user_info')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => 'pay_type'
        ]);
        //保内单计算
        if (in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            //总金额 = 维修金 + 上门费 + 配件单邮费（返件） + 费用单 + 服务费  // + 地区价差
            $data['factory_total_fee']            = $data['factory_appoint_fee']
                + $data['factory_repair_fee']
                + $data['factory_accessory_return_fee']
                + $data['factory_cost_fee']
                + $data['service_fee'];
            $data['factory_total_fee_modify']     = $data['factory_appoint_fee_modify']
                + $data['factory_repair_fee_modify']
                + $data['factory_accessory_return_fee_modify']
                + $data['factory_cost_fee']
                + $data['service_fee_modify'];

            //保内单：总金额 = 维修金 + 上门费 + 配件单邮费（返件） + 费用单 + 补贴单（内部费用）- 订单保险费
            $data['worker_total_fee']             = $data['worker_appoint_fee']
                + $data['worker_repair_fee']
                + $data['worker_accessory_return_fee']
                + $data['worker_cost_fee']
                + $data['worker_allowance_fee']
                - $data['insurance_fee'];
            $data['worker_total_fee_modify']      = $data['worker_appoint_fee_modify']
                + $data['worker_repair_fee_modify']
                + $data['worker_accessory_return_fee_modify']
                + $data['worker_cost_fee']
                + $data['worker_allowance_fee_modify']
                - $data['insurance_fee'];
            $data['worker_net_receipts']          = $data['worker_total_fee_modify'];
        } else {
//                假设：1≤配件费+维修费≤30.00，则平台服务费=0
//                假设：30＜配件费+维修费≤300.00，则平台服务费=（配件费+维修费）*10%
//                假设：配件费+维修费＞300.00，则平台服务费=300.00*10%=30.00
            $data['worker_total_fee'] = $data['worker_repair_fee'] + $data['accessory_out_fee'];
            $data['service_fee'] = $data['worker_total_fee'] * C('NOTINRUANCE_SERVICE_FEE_PERENT');
            if ($data['worker_total_fee'] <= C('NOTINRUANCE_MIN_FEE')) {
                $data['service_fee'] = 0.00;
            } elseif ($data['worker_total_fee'] >= C('NOTINRUANCE_MAX_FEE')) {
                $data['service_fee'] = C('NOTINRUANCE_MAX_FEE') * C('NOTINRUANCE_SERVICE_FEE_PERENT');
            }

            $data['worker_total_fee_modify'] = $data['worker_repair_fee_modify'] + $data['accessory_out_fee_modify'];
            $data['service_fee_modify'] = $data['worker_total_fee_modify'] * C('NOTINRUANCE_SERVICE_FEE_PERENT');
            if ($data['worker_total_fee_modify'] <= C('NOTINRUANCE_MIN_FEE')) {
                $data['service_fee_modify'] = 0.00;
            } elseif ($data['worker_total_fee_modify'] >= C('NOTINRUANCE_MAX_FEE')) {
                $data['service_fee_modify'] = C('NOTINRUANCE_MAX_FEE') * C('NOTINRUANCE_SERVICE_FEE_PERENT');
            }

            $data['worker_total_fee'] = $data['worker_total_fee'] - $data['service_fee'] - $data['insurance_fee'];
            $data['worker_total_fee_modify'] = $data['worker_total_fee_modify'] - $data['service_fee_modify'] - $data['insurance_fee'];

            $pay_type_detail = BaseModel::getInstance('worker_order_out_worker_add_fee')->order('create_time asc,id asc')->getFieldVal(['worker_order_id' => $order_id], 'pay_type');

            // 保外单：总金额 = 维修金 + 上门费 + 配件单邮费（返件） +  费用单 + 补贴单（内部费用）- 订单保险费 - 服务费
            if (in_array($pay_type_detail, WorkerOrderOutWorkerAddFeeService::PAY_TYPE_PLATFORM_GET_MONEY_LIST)) {

                // 应收 = 改后总费用 - 改后总费用的服务费 - 保险费 - （改后总费用 - 改前总费用）
                // 应收 = 改前总费用 + 改后总费用的服务费 - 保险费
                // 应收 = 总维修费 + 总配件费 + （总维修费 + 总配件费) * 改后总费用的服务费 - 保险费
                $data['worker_net_receipts'] = $data['worker_repair_fee'] + $data['accessory_out_fee'] -  $data['service_fee_modify'] - $data['insurance_fee'];

            } elseif (in_array($pay_type_detail, WorkerOrderOutWorkerAddFeeService::PAY_TYPE_CASH_PAY_LIST)) {
//                (线上+现金)*系数=平台服务费
//                线上 + 现金 - 平台服务费 - 现金 = 应收
//                线上 - 平台服务费 = 应收
//                $pay_total_fee_modify = BaseModel::getInstance('worker_order_out_worker_add_fee')->where([
//                    'pay_type' => ['in', implode(',', WorkerOrderOutWorkerAddFeeService::PAY_TYPE_PLATFORM_GET_MONEY_LIST)],
//                    'worker_order_id' => $order_id
//                ])->sum('total_fee_modify');

                // 实收
                $data['worker_net_receipts'] = 0 - $data['service_fee_modify'] - $data['insurance_fee'];
            }
        }

        $fee_model->update($order_id, $data);
        return $data;
    }

    /**
     * 计算更新质保金后返回worker_order_fee表数据
     * @param $order_id
     * @return array
     */
    public static function statisticsUpdateQualityFeeAndGet($order_id)
    {
        $fee_model = BaseModel::getInstance(self::ORDER_FEE_TABLE_NAME);
        $fee_data = $fee_model->getOneOrFail($order_id);

        $order = BaseModel::getInstance(self::ORDER_TABLE_NAME)->getOneOrFail($order_id, 'worker_id,is_worker_pay');
        $worker = BaseModel::getInstance(self::WORKER_TABLE_NAME)->getOneOrFail($order['worker_id'], 'quality_money_need,quality_money');
        $user_info = BaseModel::getInstance(self::ORDER_USER_INFO_TABLE_NAME)->getOneOrFail($order_id, 'pay_type');

        $update = [
            'quality_fee' => '0.00',
            'worker_net_receipts' => $fee_data['worker_total_fee_modify'],
        ];
        // 保外单线下支付质保金为0; 保外单微信支付、保内单计算质保金：还需结质保金 = 技工共应结质保金 - 已结质保金; 工单应结质保金 = 技工应结工单费用 * 0.1
        $worker_quality = $worker['quality_money_need'] - $worker['quality_money'];
        if ($worker_quality > 0 && $user_info['pay_type'] != OrderUserService::PAY_TYPE_CASH && $order['is_worker_pay'] == OrderService::IS_WORKER_PAY_NOT_PAY) {
            // 计算工单应得质保金
            $order_quality  = $fee_data['worker_total_fee_modify'] * 0.1;
            $quality_fee    = ($worker_quality - $order_quality) > 0 ? $order_quality : $worker_quality;
            $update['quality_fee'] = number_format($quality_fee, 2, '.', '');

            $update['worker_net_receipts'] -= $update['quality_fee'];
        }
        if ($update['quality_fee'] != $fee_data['quality_fee']) {
            $fee_model->update($order_id, $update);
            $fee_data['quality_fee'] = $update['quality_fee'];
            $fee_data['worker_net_receipts'] = $update['worker_net_receipts'];
        }

        return $fee_data;
    }

    /*
     * 群内工单质保金结算
     */
    public static function groupOrderQualityFeeSettlement($order_id)
    {
        $order_info = BaseModel::getInstance('worker_order')->getOne($order_id, 'worker_id, children_worker_id, worker_group_id');
        if (!empty($order_info['worker_group_id']) && !empty($order_info['children_worker_id']) && $order_info['worker_id'] != $order_info['children_worker_id']) {
            $pay_type = BaseModel::getInstance('worker_order_user_info')->getFieldVal([
                'worker_order_id' => $order_id
            ], 'pay_type');
            if ($pay_type == OrderUserService::PAY_TYPE_CASH) {
                return false;
            }
            $fee_model = BaseModel::getInstance('worker_order_fee');
            $fee_info = $fee_model->getOne($order_id, 'worker_total_fee_modify, cp_worker_proportion');
            $worker_info = BaseModel::getInstance('worker')->getOne($order_info['worker_id'], 'quality_money_need, quality_money');
            $children_worker_info = BaseModel::getInstance('worker')->getOne($order_info['children_worker_id'], 'quality_money_need, quality_money');
            $worker_quality = $worker_info['quality_money_need'] - $worker_info['quality_money'];
            if ($worker_quality > 0) {
                $order_quality = $fee_info['worker_total_fee_modify'] * (1 - $fee_info['cp_worker_proportion'] / 10000) * 0.1;
                $worker_quality_fee = ($worker_quality - $order_quality) > 0 ? $order_quality : $worker_quality;
            }
            $children_worker_quality = $children_worker_info['quality_money_need'] - $children_worker_info['quality_money'];
            if ($children_worker_quality > 0) {
                $order_quality = $fee_info['worker_total_fee_modify'] * $fee_info['cp_worker_proportion'] / 10000 * 0.1;
                $children_worker_quality_fee = ($children_worker_quality - $order_quality) > 0 ? $order_quality : $children_worker_quality;
            }
            $return_data = [
                'worker_quality_fee'          => number_format($worker_quality_fee ?? 0, 2, '.', ''),
                'children_worker_quality_fee' => number_format($children_worker_quality_fee ?? 0, 2, '.', '')
            ];
            $fee_update['quality_fee'] = $return_data['worker_quality_fee'] + $return_data['children_worker_quality_fee'];
            $fee_update['worker_net_receipts'] = $fee_info['worker_total_fee_modify'] - $fee_update['quality_fee'];
            //更新fee表数据
            $fee_model->update([
                'worker_order_id' => $order_id
            ], $fee_update);
            return $return_data;
        }
    }

}
