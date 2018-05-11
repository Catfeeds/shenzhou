<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/09/30
 * PM 14:34
 */
namespace Script\Model;

use Script\Model\BaseModel;
use QiuQiuX\IndexedArray\IndexedArray;

class WorkerOrderModel extends BaseModel
{
	const WORKER_ORDER_TYPE = [

	];

    public function deleteNewWorkerOrderByIds($ids = '')
    {
        if (!$ids) {
            return false;
        }
        $this->remove(['id' => $ids]);
        BaseModel::getInstance('worker_order_ext_info')->remove(['worker_order_id' => $ids]);
        BaseModel::getInstance('worker_order_user_info')->remove(['worker_order_id' => $ids]);
        BaseModel::getInstance('worker_order_product')->remove(['worker_order_id' => $ids]);
    }

    // 旧数据 的多个转态 转换 成 OrderStatus
    public function getListSetOrderStatus($where = [], $limit = 0 ,$order = 'order_id ASC')
    {
    	// $concat = 'concat(IF(`is_need_factory_confirm`,0,1),",",`is_check`,",",`is_distribute`,",",`is_receive`,",",`is_appoint`,",",`is_repair`,",",`is_return`,",",`is_platform_check`,",",`is_factory_check`) as old_status';
    	$concat = 'is_need_factory_confirm,is_check,is_distribute,is_receive,is_appoint,is_repair,is_return,is_platform_check,is_factory_check,is_complete';
    	$field = 'order_id,'.$concat;
    	$opt = [
    			'field' => '*,substring_index(area_full, ",", 3) as area_full,substring_index(area_desc, "-", 3) as area_desc',
	    		'where' => $where,
	    		'limit' => $limit,
	    		'order' => $order,
	    	];

    	$list = $this->getList($opt);

 		$appoint = $revisit = $record = $iswxorder = $first_sign = $worker = $factory = [];
 		foreach ($list as $k => $v) {

 			if (in_array($v['is_repair'], [0, 2]) && $v['is_appoint'] == 1) {
 				$appoint[] = $v['order_id'];
 			}

 			if (in_array($v['is_return'], [0, 2]) && in_array($v['is_repair'], [0, 2]) && $v['is_appoint'] == 1) {
 				$revisit[] = $v['order_id'];
 			}

 			// if ($v['is_fact_cancel'] == 1 || $v['is_cancel'] == 1) {
 				$record[] = $v['order_id'];	
 			// }

 			if ($v['order_origin'] == 'FC') {
 				$iswxorder[] = $v['order_id'];
 			}

 			if ($v['is_appoint'] == 1 || $v['is_repair'] == 1) {
 				$first_sign[] = $v['order_id'];
 			}

            $worker[$v['worker_id']] = $v['worker_id'];
            $factory[$v['factory_id']] = $v['factory_id'];
 			$order_ids[] = $v['order_id'];

 		}

 		$appoint = implode(',', $appoint);
 		$revisit = implode(',', $revisit);
 		$record = implode(',', $record);
 		$iswxorder = implode(',', $iswxorder);
        $first_sign = implode(',', $first_sign);
        $worker = implode(',', array_filter($worker));
 		$factory = implode(',', $factory);

 		$appoints = $revisits = $records = $iswxorders = $first_signs = $workers = $factorys = [];
 		if ($appoint) {
 			$appoints = $this->setOtherAtModel('worker_order_appoint')->getList([
 					'field' => 'worker_order_id',
	 				'where' => [
	 					'worker_order_id' => ['in', $appoint],
	 					'is_over' => 1,
	 				],
	 				'group' => 'worker_order_id',
	 				'index' => 'worker_order_id',
	 			]);
 		}
 		if ($revisit) {
 			$revisits = $this->setOtherAtModel('worker_order_revisit')->getList([
 					'field' => 'worker_order_id',
	 				'where' => [
	 					'worker_order_id' => ['in', $revisit],
	 				],
	 				'group' => 'worker_order_id',
	 				'index' => 'worker_order_id',
	 			]);
 		}
 		if ($record) {
 			$cw_where = [
 				'field' => 'order_id,ope_role,ope_type,ope_user_id,add_time,desc',
	    		'where' => [
	    			'order_id' => ['in', $record],
	    			// SO,FY,SZ,FI,FL 取消状态   SF,SH,SL,SA 工单接单的平台客服
	    			'ope_type' => ['in', 'SO,FY,SZ,FI,FL,SF,SH,SL,SA,FK,AB'],
	    		],
	    		'order' => 'add_time DESC',
	    	];
 			foreach ($this->setOtherAtModel('worker_order_operation_record')->getList($cw_where) as $k => $v) {
 				$records[$v['order_id']][] = $v;
 			}
 		}
 		
 		if ($iswxorder) {
 			$iswxorders = $this->setOtherAtModel('wx_user_order')->getList([
	 				'field' => 'order_id,wx_user_id',
	 				'where' => [
	 					'order_id' => ['in', $iswxorder],
	 				],
	 				'index' => 'order_id',
	 			]);
 		}

 		if ($first_sign) {
 			$fi_where = [
 				'field' => 'order_id,ope_role,ope_type,ope_user_id,add_time,desc',
	    		'where' => [
	    			'order_id' => ['in', $first_sign],
	    			// SO,FY,SZ,FI,FL 取消状态   SF,SH,SL,SA 工单接单的平台客服
	    			'ope_type' => ['in', 'WG,WH,WD,WI,WJ,FI,FL'],
	    		],
	    		'order' => 'add_time ASC',
	    	];
	    	foreach ($this->setOtherAtModel('worker_order_operation_record')->getList($fi_where) as $k => $v) {
 				$first_signs[$v['order_id']][] = $v;
 			}
 		}

        if ($worker) {
            $workers = $this->setOtherAtModel('worker')->getList([
                    'field' => 'worker_id,base_distance,base_distance_cost,exceed_cost',
                    'where' => [
                        'worker_id' => ['in', $worker],
                    ],
                    'index' => 'worker_id',
                ]);
        }

        if ($factory) {
            $factorys = $this->setOtherAtModel('factory')->getList([
                    'field' => 'factory_id,base_distance,base_distance_cost,exceed_cost',
                    'where' => [
                        'factory_id' => ['in', $factory],
                    ],
                    'index' => 'factory_id',
                ]);
        }

 		$last_records = $filk_records = $wx_user_arr = [];
 		if ($order_ids) {
 			$filk_where = [
 				'field' => 'order_id,max(add_time) as add_time,sum(if(ope_type="FI",1,0)) as fi_nums',
	    		'where' => [
	    			'order_id' => ['in', $order_ids],
	    			'ope_type' => ['in', 'FI,FL,FK,FH'],
	    		],
	    		'group' => 'order_id',
 			];
 			foreach ($this->setOtherAtModel('worker_order_operation_record')->getList($filk_where) as $k => $v) {
 				$filk_records[$v['order_id']][] = $v;
 			}

 			$last_where = [
 				'field' => 'order_id,max(add_time) as add_time',
	    		'where' => [
	    			'order_id' => ['in', $order_ids],
	    		],
	    		'group' => 'order_id',
 			];
 			foreach ($this->setOtherAtModel('worker_order_operation_record')->getList($last_where) as $k => $v) {
 				$last_records[$v['order_id']][] = $v;
 			}

 			$wx_where = [
 				'field' => 'wx_user_id,order_id',
 				'where' => [
	    			'order_id' => ['in', $order_ids],
	    		],
	    		'index' => 'order_id'
 			];
 			$wx_user_arr = $this->setOtherAtModel('wx_user_order')->getList($wx_where);
 		}
 		
    	$sql = [];
    	foreach ($list as $k => $v) {
    		$result = $this->ruleWorkerDataToMysql($v, $appoints[$v['order_id']], $revisits[$v['order_id']], $records[$v['order_id']], $iswxorders[$v['order_id']], $first_signs[$v['order_id']], $filk_records[$v['order_id']], $last_records[$v['order_id']], $wx_user_arr[$v['order_id']]['wx_user_id'], $workers[$v['worker_id']], $factorys[$v['factory_id']]);
            $sql['worker_order'][] = $result['worker_order'];
            $sql['worker_order_ext_info'][] = $result['worker_order_ext_info'];
            $sql['worker_order_user_info'][] = $result['worker_order_user_info'];
    	}
    	$sql['worker_order_product'] = $this->ruleWokerProductDataToMysql($order_ids);
    	
    	return $sql;
    }

    public function deleteOrderIdOfWorkerOrderDetail()
    {
    	// $model = $this->setOtherAtModel('worker_order_detail');
    	$data = $this->query('select group_concat(order_detail_id) as order_detail_ids from worker_order_detail a left join worker_order b on a.worker_order_id = b.order_id where b.order_id is null group by b.order_id');
    	$ids = reset($data)['order_detail_ids'];
    	$ids && $this->execute("delete from worker_order_detail where order_detail_id in ({$ids})");
    }

    public function ruleWokerProductDataToMysql($order_ids = '')
    {
    	$model = $this->setOtherAtModel('worker_order_detail');
    	// $model->getList([
    	// 	'alias' => 'wod',
    	// 	'join'  => 'left join worker_order wo on wod.worker_order_id = wo.order_id left join factory_product fp on wod.product_id = fp.product_id',
    	// 	'where' => [
    	// 		'_string' => ' fp.factory_id != wo.factory_id ',
    	// 	],
    	// ]);
    	$list = $order_ids ? $model->getList([
    			'where' => [
    				'worker_order_id' => ['in', $order_ids]
    			],
    		]) : [];

    	$data = [];
    	foreach ($list as $k => $v) {
    		$data[] = [
    			'id' 								=> $v['order_detail_id'],
    			'product_brand_id'					=> $v['servicebrand'],
    			'product_category_id'				=> $v['servicepro'],
    			'product_standard_id'				=> $v['stantard'],
    			'worker_order_id' 					=> $v['worker_order_id'],
    			'product_id' 						=> $v['product_id'],
    			'fault_id' 							=> $v['fault_id'],
    			'cp_fault_name' 					=> $v['fault_desc'],
    			'admin_edit_fault_times'			=> $v['cs_change_fault_num'],
    			'frozen_money'						=> $v['frozen_money'],
    			'factory_repair_fee'				=> $v['fact_cost'],
    			'factory_repair_fee_modify'			=> $v['fact_cost_modify'],
    			'factory_repair_reason'				=> $v['fact_modify_remark'],
    			'worker_repair_fee'					=> $v['work_cost'],
    			'worker_repair_fee_modify'			=> $v['work_cost_modify'],
    			'worker_repair_reason'				=> $v['work_modify_remark'],
    			'service_fee'						=> $v['service_fee'],
    			'service_fee_modify'				=> $v['service_fee_modify'],
    			'service_modify_reason'				=> $v['service_fee_reason'],
    			'cp_category_name'					=> $v['servicepro_desc'],
    			'cp_product_brand_name'				=> $v['servicebrand_desc'],
    			'cp_product_standard_name'			=> $v['stantard_desc'],
    			'fault_label_ids'					=> $v['servicefault'],
    			// 'servicefault_desc'				=> $v['servicefault_desc'],
    			'product_nums'						=> $v['nums'],
    			'cp_product_mode'					=> $v['model'],
    			'yima_code'							=> $v['code'],
    			'user_service_request'				=> $v['description'],
    			'is_complete'						=> $v['is_complete'],
    			'worker_report_imgs'				=> $v['report_imgs'],
    			'worker_report_remark'				=> $v['report_desc'],
    			'is_reset'							=> $v['is_return'],
    			'wrong_lock_status'					=> $v['wrong_lock_status'],
    		];
    	}
        return $data;
    }

    public function ruleWorkerDataToMysql($data = [], $appoint =[], $revisit = [], $record = [], $is_wx_order = [], $first_sign = [], $filk_record = [], $last_record = [], $wx_user_id = 0, $worker = [], $factory = [])
    {
    	$auth_ids = $this->getAccessAuthId($data['order_id'], $record);

    	$worker_order = [
	    		'id' 							=> $data['order_id'],
	    		'worker_id' 					=> $data['worker_id'],
	    		'factory_id' 					=> $data['factory_id'],
	    		'orno' 							=> $data['orno'],
	    		'factory_check_order_time'		=> 0,
	    		'checker_id' 					=> $auth_ids['checker_id'],
	    		'checker_receive_time' 			=> $auth_ids['checker_receive_time'],
	    		'check_time'					=> $data['check_time'],
	    		'distributor_id' 				=> $auth_ids['distributor_id'],
	    		'distributor_receive_time' 		=> $auth_ids['distributor_receive_time'],
	    		'distribute_time'				=> $data['distribute_time'],
	    		'worker_receive_time'			=> $data['receive_time'],
	    		'extend_appoint_time' 			=> $data['extend_appoint_time'],
	    		'worker_first_appoint_time'		=> $data['appoint_time'],
	    		'worker_first_sign_time'		=> 0, // WG,WH,WD,WI,WJ 会触发签到成功的操作
	    		'worker_repair_time'			=> $data['repair_time'],
	    		'returnee_id' 					=> $auth_ids['returnee_id'],
	    		'returnee_receive_time' 		=> $auth_ids['returnee_receive_time'],
	    		'return_time'					=> $data['return_time'],
	    		'auditor_id' 					=> $auth_ids['auditor_id'],
	    		'auditor_receive_time' 			=> $auth_ids['auditor_receive_time'],
	    		'audit_time'					=> $data['platform_check_time'],
	    		'factory_audit_time'			=> $data['factory_check_time'],
	    		'factory_audit_remark'			=> $data['fact_check_notes'],
	    		'worker_order_type' 			=> $data['order_type'],
	    		'cancel_status' 				=> 0,
	    		'cancel_time' 					=> 0,
	    		'cancel_type' 					=> $data['giveup_reason'],
	    		'cancel_remark' 				=> '',
	    		'origin_type' 					=> 0,
	    		'add_id' 						=> 0,
	    		'service_type' 					=> $data['servicetype'],
	    		'is_worker_pay'					=> $data['is_worker_pay'],
	    		'distribute_mode'				=> $data['send_model'],
	    		'cp_worker_phone'				=> $data['worker_phone'],
	    		'create_remark'					=> $data['add_remark'],
	    		'last_update_time'				=> $data['last_uptime'],
	    		'important_level'				=> $data['import_sign'],
	    		'create_time' 					=> $data['datetime'],
    		];    	

    	$worker_order['worker_order_status'] = $this->ruleOrderStatus($data, $worker_order, $appoint, $revisit);
    	// list($worker_order['cancel_status'], $worker_order['cancel_time']) = ;
    	$this->ruleOrderCancelStatus($data, $worker_order, $record);
    	$first_sign && $this->ruleWorkerFirstSignTime($data, $worker_order, $first_sign);
    	$this->ruleOriginType($data, $worker_order, $is_wx_order);
    	$this->ruleLastUptime($data, $worker_order, $last_record);
    	$this->ruleFactoryCheckOrderTime($data, $worker_order, $filk_record);

    	$fi_nums = reset($filk_record)['fi_nums'];
    	$worker_order_ext_info = [
    		'worker_order_id' 				=> $data['order_id'],
    		'factory_helper_id' 			=> 0,
    		'cp_factory_helper_name'		=> $data['technology_name'],
    		'cp_factory_helper_phone' 	    => $data['technology_tell'],
    		'appoint_start_time' 			=> $data['add_member_appoint_stime'] ?? 0,
    		'appoint_end_time' 				=> $data['add_member_appoint_etime'] ?? 0,
    		'is_send_user_message' 			=> $data['is_send_user_mess'],
    		'user_message' 					=> $data['user_message'],
    		'is_send_worker_message' 		=> $data['is_send_worker_mess'],
    		'worker_message' 				=> $data['worker_message'],
    		'est_miles' 					=> $data['est_miles'],
    		'straight_miles' 				=> $data['straight_miles'],
            'worker_base_distance'          => $worker['base_distance'] ?? 0,
            'worker_base_distance_fee'      => $worker['base_distance_cost'] ?? 0,
            'worker_exceed_distance_fee'    => $worker['exceed_cost'] ?? 0,
            'factory_base_distance'         => $factory['base_distance'] ?? 0,
            'factory_base_distance_fee'     => $factory['base_distance_cost'] ?? 0,
            'factory_exceed_distance_fee'   => $factory['exceed_cost'] ?? 0,
    		'service_evaluate' 				=> $data['service_evaluate'],
    		'reset_nums'					=> $fi_nums ?? 0,
    		'is_worker_show_factory'		=> $data['is_show_tell'],
    	];
    	
    	$area_full_arr = explode(',', $data['area_full']);
    	$worker_order_user_info = [
    		'worker_order_id' 				=> $data['order_id'],
    		'wx_user_id' 					=> $wx_user_id ? $wx_user_id : 0,
    		'real_name' 					=> $data['full_name'],
    		'phone' 						=> $data['tell'],
    		'province_id' 					=> $area_full_arr[0] ? $area_full_arr[0] : 0,
    		'city_id' 						=> $area_full_arr[1] ? $area_full_arr[1] : 0,
    		'area_id' 						=> $area_full_arr[2] ? $area_full_arr[2] : 0,
    		'cp_area_names'					=> $data['area_desc'],
    		'address'						=> $data['address'],
    		'lat'							=> $data['lat'],
    		'lon'							=> $data['lng'],
    	];

        $sql = [
            'worker_order' => $worker_order,
            'worker_order_ext_info' => $worker_order_ext_info,
            'worker_order_user_info' => $worker_order_user_info,
        ];
    	
    	return $sql;
    }

    // 最后更新时间
    public function ruleLastUptime($data = [], &$news = [], $record = [])
    {
    	$time = reset($record)['add_time'];
    	$news['last_update_time'] = $time ? $time : $news['last_update_time'];
    }

    // 工单厂家下单、重新下单、自行处理时间
    public function ruleFactoryCheckOrderTime($data = [], &$news = [], $record = [])
    {
        $data = reset($record);
    	$time = $data['add_time'] ?? 0;
        $type = $data['ope_role'] == 'factory_admin' ? 2 : 1;
        $id   = $data['ope_user_id'];
        $news['factory_check_order_time']   = $time;
        $news['factory_check_order_type']   = $type;
    	$news['factory_check_order_id']     = $id ?? $news['factory_id'];
    }

    // WG,WH,WD,WI,WJ 会触发签到成功的操作
    public function ruleWorkerFirstSignTime($data = [], &$news = [], $record = [])
    {
    	$result = [];
    	$first_time = 0;
    	foreach ($record as $k => $v) {
    		if ($k = 0) {
    			$first_time = $v['add_time'];
    		}
    		if (in_array($v['ope_type'], ['FI', 'FL']) && $record[$k + 1]) {
    			$first_time = $record[$k + 1]['add_time'];
    		}
    	}
    	$news['worker_first_sign_time'] = $first_time;
    }

	// (old)下单来源：F:厂家，FC：厂家外部客户，C：C端客户，FCD：厂家外部客户自行处理
	// (new)下单来源：1厂家，2厂家子账号，3厂家外部客户，4普通用户，5经销商
	// 厂家的测试账号的手机号码 18818461566,18922363292,13602418838,18888888881,13450788338,13926112540
    public function ruleOriginType($data = [], &$new = [], $is_wx_order = [])
    {
    	$value = 0;
    	$add_id = 0;
    	switch (strtolower($data['order_origin'])) {
    		case 'f': // 厂家下单
    			$value = 1;
    			$add_id = $data['factory_id'];
    			if ($data['add_member_id'] && $data['add_member_id'] != $data['factory_id']) {
    				$value = 2;
    				$add_id = $data['add_member_id'];
    			}
    			break;

    		case 'fd': // 导单
    			$value = 1;
    			$add_id = $data['factory_id'];
    			if ($data['add_member_id'] && $data['add_member_id'] != $data['factory_id']) {
    				$value = 2;
    				$add_id = $data['add_member_id'];
    			}
    			break;

    		case 'fc':
    			$value = 3;
    			if ($is_wx_order) {
    				$value = 4;
    				$add_id = $is_wx_order['wx_user_id'];
    			}
    			break;
    	}
    	$new['origin_type'] = $value;
    	$new['add_id'] = $add_id;
    	// return $value;
    }

    // 确保$record（工单操作记录数据）根据时间倒叙排列 （工单取消状态 0正常，1C端用户取消，2C端经销商取消，3厂家取消，4客服取消，5 客服终止工单（可结算））
    public function ruleOrderCancelStatus($data = [], &$news = [], $record = [])
    {
    	$time = $status = $canceler_id = 0;
    	$remark = '';
    	// $is = [];
    	$arr = $last = $times = $remarks =  $canceler_ids = $result = [];
    	// SO 客服取消，FY厂家取消。FI重新下单, FL确认下单，SZ客服终止，FZ厂家终止（无数据）
    	if ($data['is_fact_cancel'] == 1 && $data['is_cancel'] == 0) {
    		$arr = ['SO', 'FY', 'FI', 'FL'];
    	} elseif ($data['is_fact_cancel'] == 0 && $data['is_cancel'] == 1) {
    		$arr = ['SZ', 'FI', 'FL'];
    	} elseif ($data['is_fact_cancel'] == 1 && $data['is_cancel'] == 1) {
    		$arr = ['SO', 'FY', 'SZ', 'FI', 'FL'];
    	}

        $check_cs  = $check_cs_type = $check_cs_time = 0;
    	foreach ($record as $k => $v) {
            if ($v['ope_type'] == 'AB' && !$check_cs) {
                $check_cs_time = $v['add_time'];
            } elseif ($v['ope_type'] == 'FK' && !$check_cs) {
                $check_cs_time = $v['add_time'];
            } elseif ($v['ope_type'] == 'SA' && !$check_cs) {
                $check_cs       = $v['ope_user_id'];
                $check_cs_type  = 4;
                $check_cs_time  = $v['add_time'];
            }

    		if (!in_array($v['ope_role'], ['admin', 'factory']) || !in_array($v['ope_type'], $arr)) {
    			continue;
    		}
    		$last[] = strtolower($v['ope_type']);
    		$times[] = $v['add_time'];
    		$remarks[] = $v['desc'];
            $canceler_ids[] = $v['ope_user_id'];
    	}
        
    	switch (reset($last)) {
    		case 'so':
    			$status = 4;
    			$time = reset($times);
    			$remark = reset($remarks);
                $canceler_id = reset($canceler_ids);
    			break;
    		case 'fy':
    			$status = 3;
    			$time = reset($times);
    			$remark = reset($remarks);
                $canceler_id = reset($canceler_ids);
    			break;
    		case 'sz':
    			if (in_array($last[1], ['fi', 'fl'])) { // 
    				$status = 5;
    				$time = reset($times);
    				$remark = reset($remarks);
                    $canceler_id = reset($canceler_ids);
    			} elseif ($last[1] == 'so') {
    				$status = 4;
    				$time = $times[1];
    				$remark = $remarks[1];
                    $canceler_id = $canceler_ids[1];
    			} elseif ($last[1] == 'fy') {
    				$status = 3;
    				$remark = $remarks[1];
                    $canceler_id = $canceler_ids[1];
    			}
    			break;
    	}    		

        if (!$last && ($data['is_cancel'] == 1 || $data['is_fact_cancel'] == 1)) {
            if ($check_cs) {
                $canceler_id =  $check_cs;
                $status =       $check_cs_type;
                $time =         $check_cs_time;
            } else {
                $canceler_id = $data['factory_id'];
                $status = 3;
                $time = $check_cs_time ?? reset($record)['add_time'] + 1;
            }

        }

    	$news['cancel_status'] = $status;
        $news['canceler_id'] = $canceler_id ?? 0;
		$news['cancel_time'] = $time ?? 0;
		$news['cancel_remark'] = $remark;
        
    	// return [$status, $time];
    }

    // 工单状态
    public function ruleOrderStatus($data = [], $news = [], $appoint = [], $revisit = [])
    {
    	$status = 0;
    	if ($data['is_complete'] == 1 || $data['is_factory_check'] == 1) {
    		$status = 18;
    	} elseif ($data['is_factory_check'] == 2 && $data['is_platform_check'] == 0) {
    		$status = 17;
    	} elseif ($data['is_platform_check'] == 1) {
    		$status = 16;
    	// } elseif ($data['']) { 
    	// 	$status = 15;
    	} elseif ($news['auditor_id'] && $data['is_return'] == 1) {
    		$status = 14;
    	} elseif ($data['is_return'] == 1) {
    		$status = 13;
    	} elseif ($data['is_appoint'] == 1 && $data['is_repair'] == 0 && $revisit) {
    	// } elseif ($data['is_appoint'] == 1 && $data['is_repair'] == 0 && $this->setOtherAtModel('worker_order_revisit')->getOne(['worker_order_id' => $data['order_id']])) {
    		$status = 12;
    	} elseif ($news['returnee_id'] && $data['is_repair'] ==  1) {
    		$status = 11;
    	} elseif ($data['is_repair'] ==  1) {
    		$status = 10;
    	} elseif ($data['is_appoint'] == 1 && $appoint) {
    	// } elseif ($data['is_appoint'] == 1 && $this->setOtherAtModel('worker_order_appoint')->getOne(['worker_order_id' => $data['order_id'], 'is_over' => 1])) {
    		$status = 9;
    	} elseif ($data['is_appoint'] == 1) {
    		$status = 8;
    	} elseif ($data['is_receive'] == 1) {
    		$status = 7;
    	} elseif ($data['is_distribute'] == 1) {
    		$status = 6;
    	} elseif ($news['distributor_id'] && $data['is_check'] == 1) {
    		$status = 5;
    	} elseif ($data['is_check'] == 1) {
    		$status = 4;
    	} elseif ($news['checker_id'] && $data['is_need_factory_confirm'] == 0) {
    		$status = 3;
    	} elseif ($data['is_need_factory_confirm'] == 0) {
    		$status = 2;
    	} elseif ($data['is_need_factory_confirm'] == 1 && $data['is_fact_cancel'] == 1) {
    		$status = 1;
    	} else {
    		$status = 0;
    	}

    	return $status;
    }

    // 获取指定工单的平台各种接单客服 （根据操作记录识别）
    public function getAccessAuthId($order_id = 0, $record = [])
    {
    	$return = [
    		'checker_id' => 0,
    		'distributor_id' => 0,
    		'returnee_id' => 0,
    		'auditor_id' => 0,
    	];
    	
    	$result = [];
    	foreach ($record as $k => $v) {
    		if ($v['ope_role'] != 'admin') {
    			continue;
    		}
    		$result[strtolower($v['ope_type'])][] = [
    				$v['add_time'] => [
    					'ope_user_id' => $v['ope_user_id'],
    					'add_time' => $v['add_time'],
    				],
    		];
    	}
        
    	$sf = reset(reset($result['sf'])); // 客服财务审核
    	$sh = reset(reset($result['sh'])); // 派单客服
    	$sl = reset(reset($result['sl'])); // 回访客服
    	$sa = reset(reset($result['sa'])); // 核实客服
    	// 财务客服
    	$return['auditor_id'] = $sf['ope_user_id'] ? $sf['ope_user_id'] : 0;
    	$return['auditor_receive_time'] = $sf['add_time'] ? $sf['add_time'] : 0;
    	// 回访客服
    	$return['returnee_id'] = $sl['ope_user_id'] ? $sl['ope_user_id'] : 0;
    	$return['returnee_receive_time'] = $sl['add_time'] ? $sl['add_time'] : 0;
    	// 派单客服
    	$return['distributor_id'] = $sh['ope_user_id'] ? $sh['ope_user_id'] : 0;
    	$return['distributor_receive_time'] = $sh['add_time'] ? $sh['add_time'] : 0;
    	// 核实客服
    	$return['checker_id'] = $sa['ope_user_id'] ? $sa['ope_user_id'] : 0;
    	$return['checker_receive_time'] = $sa['add_time'] ? $sa['add_time'] : 0;

        // var_dump($return);
        // $a = BaseModel::getInstance('worker_order')->update($order_id, $return);

    	return $return;
    }

}
