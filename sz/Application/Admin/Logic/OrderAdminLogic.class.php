<?php
/**
 * User: zjz
 * Date: 2017/11/02
 * Time: 10:31
 */

namespace Admin\Logic;

use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkbenchEvent;
use Common\Common\Repositories\Events\ReturneeNotPayForWorkerEvent;
use Common\Common\Repositories\Events\ReturneePayForWorkerEvent;
use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\Repositories\Events\WorkerTransactionEvent;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;
use Common\Common\Service\OrderUserService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use Library\Common\Util;
use Admin\Common\ErrorCode;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\WorkerOrderAppointRecordService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Admin\Model\WorkerOrderAppointRecordModel;
use Admin\Model\WorkerOrderApplyAccessoryModel;
use Admin\Model\WorkerOrderApplyAllowanceModel;
use Admin\Model\WorkerOrderApplyCostModel;
use Admin\Logic\OrderLogic;

class OrderAdminLogic extends BaseLogic
{
	const ORDER_TABLE_NAME = 'worker_order';
	const ORDER_FEE_TABLE_NAME = 'worker_order_fee';
	const ORDER_APPOINT_RECORD_TABLE_NAME = 'worker_order_appoint_record';
	const FACTORY_FROZEN_TABLE_NAME = 'factory_money_frozen';

	// (与维修商结算)平台财务客服审核财务通过
	public function auditedOrder($order_id = 0, $remark = '')
	{
		$model = BaseModel::getInstance('worker_order');
		// worker_id,worker_first_appoint_time,worker_receive_time,worker_first_sign_time 技工该工单信誉记录处理 所需要的字段不可或缺
        $order = $model->getOneOrFail($order_id, 'orno,worker_id,factory_id,worker_first_appoint_time,worker_receive_time,worker_first_sign_time,worker_order_status,is_worker_pay,worker_order_type,audit_time,worker_group_id,children_worker_id,auditor_id');

        	!in_array($order['worker_order_status'], OrderService::CAN_AUDITOR_AUDITED_WORKER_ORDER_STATUS_ARRAY)
		&&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_AUDITED_ORDER);

		// 检查分枝流程是否完结（配件已返还，补贴单完结,费用单完结）
		$this->checkoutAccessoryAllowanceOrFail($order_id);

        $is_in_order = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;
		M()->startTrans();

        $update = [
            'worker_order_status' 	=> $is_in_order ? OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT : OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
            'is_worker_pay'			=> 1,
            'audit_time' 			=> NOW_TIME,
            'last_update_time'		=> NOW_TIME,
        ];
        !$is_in_order && $update['factory_audit_time'] = NOW_TIME;

		// 操作记录 CS_AUDITED_WORKER_ORDER
		$extras = ['remark' => $remark];

        /**
         * 1, 工单费用结算至技工钱包 并写入 技工收入记录
         * 2, if (!$is_in_order) {...} elseif ($is_in_order && !$order['is_worker_pay']) {...}
         * 3, 保外单：工单直接进入玩完结状态 （回访已验证 worker_order_user_info.is_user_pay）,额外添加一条厂家及客服可见的厂家自动审核通过操作记录
         * 4, 保外单：厂家自动审核通过不需要发送消息
         * 5, 保外单：触发工单信誉结算
         * 6, 保内单：发送后台消息
         * 7, 保外单：不发送后台消息
         * 8, !$order['audit_time'] 判断是否是第一次审核 有数据则不是第一次审核
         */
        //!$order['is_worker_pay'] && (new OrderLogic())->workerOrderSettlementForWorkerById($order_id);
        if (!$order['is_worker_pay']) {
            if (!empty($order['worker_group_id']) && !empty($order['children_worker_id']) && $order['worker_id'] != $order['children_worker_id']) {
                //群内工单且已派发给群成员的
                (new OrderLogic())->groupWorkerOrderSettlementForWorkerById($order_id);
            } else {
                (new OrderLogic())->workerOrderSettlementForWorkerById($order_id);
            }
        }
        if (!$is_in_order) {
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_AUDITED_WORKER_ORDER, $extras);
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::SYSTEM_ORDER_OUT_SYSTEM_AUTO_AUDITOR_SUCCESS);
            // worker_order_reputation 添加技工信誉记录
            !$order['audit_time'] && (new \Admin\Logic\OrderLogic)->workerOrderCompleteReputation($order_id, $order);
            // 自动通过
            $system_msg = '工单号：'.$order['orno'].'，服务已完成。';
            SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $order['factory_id'], $system_msg, $order_id, SystemMessageService::MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS_AND_FACTORY_AOTU_PASS);
//        } elseif (!$order['audit_time']) {
        } else {
            // 冻结金 进行中 转换 待结算
            BaseModel::getInstance(self::FACTORY_FROZEN_TABLE_NAME)->update([
                'worker_order_id' => $order_id
            ], [
                'type' => FactoryMoneyFrozenRecordService::FROZEN_TYPE_WAITING_SETTLEMENT,
            ]);

            // 冻结金变动
            $order_frozen = BaseModel::getInstance(self::ORDER_FEE_TABLE_NAME)->getFieldVal($order_id, 'factory_total_fee_modify');
            FactoryMoneyFrozenRecordService::process($order_id, FactoryMoneyFrozenRecordService::TYPE_ADMIN_ORDER_CONFORM_AUDITOR, $order_frozen);

            OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_AUDITED_WORKER_ORDER, $extras);
            $system_msg = "工单号 {$order['orno']}，已提交费用审核";
            SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $order['factory_id'], $system_msg, $order_id, SystemMessageService::MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS);
		}
        $event_data = [
            'data_id' => $order_id,
            'type' => AppMessageService::TYPE_BALANCE_MASSAGE,
            'worker_id' => $order['worker_id']
        ];
        !$order['audit_time'] && event(new WorkerTransactionEvent($event_data));
        if (!$order['audit_time'] && !empty($order['worker_group_id']) && !empty($order['children_worker_id']) && $order['worker_id'] != $order['children_worker_id']) {
            $event_data = [
                'data_id' => $order_id,
                'type' => AppMessageService::TYPE_BALANCE_MASSAGE,
                'worker_id' => $order['children_worker_id']
            ];
            event(new WorkerTransactionEvent($event_data));
        }

        $model->update($order_id, $update);

        M()->commit();

        event(new WorkbenchEvent(['worker_order_id' => $order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_AUDIT')]));
	}

	// (与维修商结算)平台财务客服审核财务不通过(退回工单)
	public function notAuditedOrder($order_id = 0, $remark = '')
	{
		$remark_content = $remark;  //获取备注内容
		$model = BaseModel::getInstance('worker_order');
		$order = $model->getOneOrFail($order_id, 'orno,returnee_id,worker_order_status,is_worker_pay,worker_order_type');

        	!in_array($order['worker_order_status'], OrderService::CAN_AUDITOR_AUDITED_WORKER_ORDER_STATUS_ARRAY)
		&&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_AUDITED_ORDER);

        if (!in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '保外单不予退回给回访客服！');
        }

		$order['is_worker_pay'] && $this->throwException(ErrorCode::IS_WORKER_PAY_NOT_RETURN_AGEN);

		// 检查分枝流程是否完结（配件已返还，补贴单完结,费用单）审核不通过不做分支判断
		// $this->checkoutAccessoryAllowanceOrFail($order_id);
		M()->startTrans();
		$update = [
	        	'worker_order_status' 	=> OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
	        	'auditor_id'			=> 0,
	        	'auditor_receive_time'	=> 0,
	        	'audit_time' 			=> NOW_TIME,
	        	'last_update_time'		=> NOW_TIME,
	        ];
		$model->update($order_id, $update);

		// 操作记录 CS_NOT_AUDITED_WORKER_ORDER
		$extras = ['remark' => $remark];
		OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_NOT_AUDITED_WORKER_ORDER, $extras);
		// 给回访客服 【财务退回】 工单号 *****，财务退回
        $system_msg = "工单号 {$order['orno']}，财务退回。{$remark_content}";
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order['returnee_id'], $system_msg, $order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_AUDITOR_CHARGE_BACK);
		M()->commit();

        event(new WorkbenchEvent(['worker_order_id' => $order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_RETURN')]));
	}

	// 回访的时候确保费用申请单全部完结。
	// 平台财务审核的时候确保费用申请单、补贴单完结，配件单是技工返件及之后或已取消
	public function checkoutAccessoryAllowanceOrFail($order_id = 0)
	{
			(new WorkerOrderApplyAccessoryModel())->getNotCompleteNumsByOid($order_id)
		&&  $this->throwException(ErrorCode::HAS_APPLY_ACCESSORY_NOT_COMPLETE);
			(new WorkerOrderApplyAllowanceModel())->getNotCompleteNumsByOid($order_id)
		&&  $this->throwException(ErrorCode::HAS_APPLY_ALLOWANCE_NOT_COMPLETE);
			(new WorkerOrderApplyCostModel())->getNotCompleteNumsByOid($order_id)
		&&  $this->throwException(ErrorCode::HAS_APPLY_COST_NOT_COMPLETE);
		return true;
	}

	// 确认与维修商结算（已回访）
	public function payForWorkerById($order_id = 0, $remark = '', $nums = 0)
	{
		$model = BaseModel::getInstance('worker_order');
		$order = $model->getOneOrFail($order_id, 'worker_order_status,worker_id,worker_order_type,worker_group_id,children_worker_id,returnee_id');
        $order_user = BaseModel::getInstance('worker_order_user_info')->getOneOrFail($order_id, 'is_user_pay');
        $is_in_order = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;

            !in_array($order['worker_order_status'], OrderService::CAN_RETURNEE_RETURN_WORKER_ORDER_STATUS_ARRAY)
        &&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_SETTLEMENT_FOR_WORKER);
			(new WorkerOrderApplyCostModel())->getNotCompleteNumsByOid($order_id)
		&&  $this->throwException(ErrorCode::HAS_APPLY_COST_NOT_COMPLETE); // 未完结的费用申请单

		$update = [];
		M()->startTrans();
		// 是否需要更改上门次数，保外单不允许更改上门次数
		$appoint_model  = new WorkerOrderAppointRecordModel();
		if ($is_in_order && $nums > 0 && $appoint_model->getOverNumsByOid($order_id) != $nums) {
			$this->updateWorkerOrderAppointOverNums($order_id, $nums);
		}

		$update = [
            'worker_order_status' 	=> OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
            'return_time'			=> NOW_TIME,
            'last_update_time'		=> NOW_TIME,
        ];
		$model->update($order_id, $update);
		// 操作记录 CS_SETTLEMENT_FOR_WORKER
		$extras = ['remark' => $remark];
		OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_SETTLEMENT_FOR_WORKER, $extras);
		// 默认回访记录数据 TODO 后续版本需要注释，可以手动添加回访记录
		$this->defaultWorkerOrderRevisitRecord($order_id, $order);

		// 保外单的工单用户未支付不允许回访成功
        if (!$is_in_order && $order_user['is_user_pay'] != OrderUserService::IS_USER_PAY_SUCCESS) {
            $this->throwException(ErrorCode::OUT_ORDER_USER_IS_NOT_PAY);
        }

		// 发送企业号信息, 第一次确认与维修商结算，需要发送企业号消息通知，如果之后被财务退回，再次确认与维修商结算的，则不发送(接收worker)
		if ($order['worker_order_status'] == OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT) {
            event(new OrderSendNotificationEvent([
                'data_id' => $order_id,
                'type'    => AppMessageService::TYPE_VISIT_PASS_MASSAGE
            ]));
		}
		M()->commit();

        event(new WorkbenchEvent(['worker_order_id' => $order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_FINISH')]));

	}

	public function notPayForWorkerById($order_id = 0, $remark = '')
	{
		$remark_content = $remark;  //获取备注内容
		$model = BaseModel::getInstance('worker_order');

		$order = $model->getOneOrFail($order_id, 'worker_order_status,worker_order_type,distributor_id,returnee_id,orno');

		    !in_array($order['worker_order_status'], OrderService::CAN_RETURNEE_RETURN_WORKER_ORDER_STATUS_ARRAY)
		&&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_SETTLEMENT_FOR_WORKER);

        $is_in_order = isInWarrantPeriod($order['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;
        /**
         * 保外单流程优化：准许审核不通过
         */
//        if (!$is_in_order && $order_user['is_user_pay'] == OrderUserService::IS_USER_PAY_SUCCESS) {
//            $this->throwException(ErrorCode::OUT_ORDER_USER_IS_PAY);
//        }

		$update = [];
		M()->startTrans();
        // 保外单 删除 没有支付的费用
        if (!$is_in_order) {

            $last_upload_time =BaseModel::getInstance('worker_order_operation_record')->getFieldVal([
                'order' => 'create_time desc',
                'where' => [
                    'operation_type' => OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT,
                    'worker_order_id' => $order_id,
                ],
            ], 'create_time');

            if ($last_upload_time <= 1525446900) {
                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '旧数据不予回访不通过！');
            }

            $out_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
            $out_fees = $out_model->getList([
                'where' => [
                    'worker_order_id' => $order_id,
                ],
                'field' => 'id,pay_type,worker_order_product_id,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee,total_fee_modify,pay_time',
            ]);

            $total_out_fee_data = $out_fee_data = $delete_id = [];
            $had_pay_log = false;
            foreach ($out_fees as $k => $v) {
                !isset($out_fee_data[$v['worker_order_product_id']]) && $out_fee_data[$v['worker_order_product_id']] = [
                    'worker_repair_fee'         => 0.00,
                    'worker_repair_fee_modify'  => 0.00,
                ];

                if ($v['pay_type'] == WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO || !$v['pay_time']) {
                    $delete_id[] = $v['id'];
                } else {
                    $had_pay_log = true;
                    $total_out_fee_data['worker_repair_fee']        += $v['worker_repair_fee'];
                    $total_out_fee_data['worker_repair_fee_modify'] += $v['worker_repair_fee_modify'];
                    $total_out_fee_data['accessory_out_fee']        += $v['accessory_out_fee'];
                    $total_out_fee_data['accessory_out_fee_modify'] += $v['accessory_out_fee_modify'];

                    $out_fee_data[$v['worker_order_product_id']]['worker_repair_fee']           += $v['worker_repair_fee'];
                    $out_fee_data[$v['worker_order_product_id']]['worker_repair_fee_modify']    += $v['worker_repair_fee_modify'];
                }
            }

            $ids = implode(',', $delete_id);

            if ($ids) { // 减去加收费用，重新计算总费用
                $out_model->remove(['id' => ['in', $ids]]);
                foreach ($out_fee_data as $k => $v) {
                    BaseModel::getInstance('worker_order_product')->update([
                        'id' => $k,
                        'worker_order_id' => $order_id,
                    ], [
                        'worker_repair_fee'         => $v['worker_repair_fee'],
                        'worker_repair_fee_modify'  => $v['worker_repair_fee_modify'],
                    ]);
                }

                BaseModel::getInstance('worker_order_user_info')->update($order_id, [
                    'is_user_pay' => $had_pay_log ? OrderUserService::IS_USER_PAY_SUCCESS : OrderUserService::IS_USER_PAY_DEFAULT,
                ]);


                $total_out_fee_data = array_filter($total_out_fee_data) + [
                        'worker_repair_fee' => 0.00,
                        'worker_repair_fee_modify' => 0.00,
                        'accessory_out_fee' => 0.00,
                        'accessory_out_fee_modify' => 0.00,
                    ];
                $total_out_fee_data['service_fee'] = ($total_out_fee_data['worker_repair_fee'] + $total_out_fee_data['accessory_fee']) * C('NOTINRUANCE_SERVICE_FEE_PERENT');
                $total_out_fee_data['service_fee_modify'] = ($total_out_fee_data['worker_repair_fee_modify'] + $total_out_fee_data['accessory_fee_modify']) * C('NOTINRUANCE_SERVICE_FEE_PERENT');
                // 计算worker_order_fee表

                OrderSettlementService::orderFeeStatisticsUpdateFee($order_id, $total_out_fee_data);
            }
        }

        $update = [
            'worker_order_status'   => OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
            'returnee_id'           => 0,
            'returnee_receive_time' => 0,
            'last_update_time'      => NOW_TIME,
            'return_time'           => NOW_TIME,
        ];

		$model->update($order_id, $update);

		BaseModel::getInstance('worker_order_product')->update([
			'worker_order_id' => $order_id,
		], [
			'is_complete' => 0,
		]);

		// 操作记录 CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED
		$extras = [
            'remark' => $remark
        ];
		OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED, $extras);
//        SystemMessageService::USER_TYPE_ADMIN, $order['returnee_id'], $system_msg, $order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_AUDITOR_CHARGE_BACK
		//群内工单修改数量
        event(new UpdateOrderNumberEvent([
            'worker_order_id'              => $order_id,
            'operation_type'               => OrderOperationRecordService::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED
        ]));
		// 发送企业号信息, 第一次确认与维修商结算，需要发送企业号消息通知，如果之后被财务退回，再次确认与维修商结算的，则不发送 (接收worker)
		if ($order['worker_order_status'] == OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT) {
            event(new OrderSendNotificationEvent([
                'data_id' => $order_id,
                'type'    => AppMessageService::TYPE_VISIT_NOT_PASS
            ]));
		}
        // 待客服回访 【回访不通过】 工单号 *****，回访不通过
        $system_msg = "工单号 {$order['orno']}，回访不通过。{$remark_content}";
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order['returnee_id'], $system_msg, $order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_RETURNEE_RETURN_BACK);
		M()->commit();

		//工作台
        event(new WorkbenchEvent(['worker_order_id' => $order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_RETURN')]));
	}

	// 确认一维修商结算后，默认回访记录数据
	public function defaultWorkerOrderRevisitRecord($order_id = 0, $order = [])
	{
		$add = [
			'worker_order_id' 		=> $order_id,
			'admin_id' 				=> AuthService::getAuthModel()->getPrimaryValue(),
			'is_visit_ontime'		=> 1,
			'is_user_satisfy'		=> 1,
			'repair_quality_score'	=> 10,
			'create_time'			=> NOW_TIME,
		];
        BaseModel::getInstance('worker_order_ext_info')->update($order_id, ['service_evaluate' => 'A']);
		BaseModel::getInstance('worker_order_revisit_record')->insert($add);
		BaseModel::getInstance('worker_order_reputation')->update([
				'worker_order_id' => $order_id,
				'worker_id'		  => $order['worker_id'],
			],
			[
				// 客户回访码
				'sercode' 			=> 'A',
				'sercode_fraction'	=> 10,
				// 回访客服回访码
				'revcode'		   	=> 'A',
				'revcode_fraction' 	=> 10,
                // 补充分数
                'quality_standard'  => '',
                'quality_standard_fraction' => 30,
                'repair_nums_fraction'  => 10,
			]);
		if (!empty($order['worker_group_id']) && $order['worker_id'] != $order['children_worker_id']) {
            BaseModel::getInstance('worker_order_reputation')->update([
                'worker_order_id' => $order_id,
                'worker_id'		  => $order['children_worker_id'],
            ],
                [
                    // 客户回访码
                    'sercode' 			=> 'A',
                    'sercode_fraction'	=> 10,
                    // 回访客服回访码
                    'revcode'		   	=> 'A',
                    'revcode_fraction' 	=> 10,
                    // 补充分数
                    'quality_standard'  => '',
                    'quality_standard_fraction' => 30,
                    'repair_nums_fraction'  => 10,
                ]);
        }
	}

	// 更改成功上门次数并重新计算上门费
	protected function updateWorkerOrderAppointOverNums($order_id, $repair_num = 0)
	{
		// $update['cs_appoint_nums']
		// $this->throwException(-100, '暂不支持修改上门次数');
		$order 	   = BaseModel::getInstance(self::ORDER_TABLE_NAME)->getOneOrFail($order_id);
		$order_fee = BaseModel::getInstance(self::ORDER_FEE_TABLE_NAME)->getOneOrFail(['worker_order_id' => $order_id]);
		if ($order_fee['cs_appoint_nums'] > 0) {
			$this->throwException(ErrorCode::APPOINT_NUMS_NOT_EDIT_AGAIN);
		}

		$appoints = BaseModel::getInstance(self::ORDER_APPOINT_RECORD_TABLE_NAME)
			->field('id')
			->order('create_time ASC')
		    ->where([
		    	'worker_order_id' => $order_id,
				'is_over' => WorkerOrderAppointRecordService::IS_OVER_YES,
		    	])
			->select();

		$data_fee = null;
		$appoint_num = count($appoints);
		if ($appoint_num != $repair_num) {
			$data_fee['cs_appoint_nums'] = $repair_num;
			$extras = [
				'content_replace' => [
					'appoint_num' => $appoint_num,
					'repair_num' => $repair_num,
				]
			];
			OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_EDID_ORDER_APPOINT_NUMS, $extras);
		}

		// 添加或者减少预约记录
		if ($appoint_num < $repair_num) {
			$cx_num = $repair_num - $appoint_num;
			$adds = [];
			do {
				$adds[] = array(
					'worker_order_id' => $order_id,
					'appoint_status' => WorkerOrderAppointRecordService::STATUS_CS_EDIT_NUMS_RECORD,
					'appoint_remark' => '系统操作添加预约记录',
					'create_time' => NOW_TIME,
					'is_sign_in' => WorkerOrderAppointRecordService::SIGN_IN_SUCCESS,
					'sign_in_time' => NOW_TIME,
					'is_over' => WorkerOrderAppointRecordService::IS_OVER_YES,
					'over_time' => NOW_TIME,
				);
				--$cx_num;
			} while ($cx_num > 0);

			BaseModel::getInstance(self::ORDER_APPOINT_RECORD_TABLE_NAME)->insertAll($adds);

		} elseif ($appoint_num > $repair_num) {
			$search_list = array_values($appoints);
			$cx_num = $appoint_num - $repair_num;
			$delete = [];
	        do {
	        	$del_data = end($search_list);
	            $delete[$del_data['id']] = $del_data['id'];
	            unset($search_list[count($search_list)-1]);
	            --$cx_num;
	        } while ($cx_num > 0);

	        BaseModel::getInstance(self::ORDER_APPOINT_RECORD_TABLE_NAME)->remove([
	        		'id' => ['in', implode(',', $delete)],
	        	]);
		}

		if ($data_fee) {
			BaseModel::getInstance(self::ORDER_FEE_TABLE_NAME)->update($order_fee['worker_order_id'], $data_fee);
			OrderSettlementService::autoSettlement();
		}
	}

}
