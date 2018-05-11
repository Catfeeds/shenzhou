<?php
/**
* @User zjz
* @Date 2016/12/12
*/
namespace Api\Model;

use Api\Model\BaseModel;
use Api\Common\ErrorCode;
use Library\Common\Util;

class WorkerOrderModel extends BaseModel
{	

	public function getFieldByKey($key = 'list')
	{
		$fields = [
			// is_in,is_out,appoint_time_str
			'list' => 'WO.order_id,WO.order_type as in_out_type,WO.orno,WO.datetime,WO.is_need_factory_confirm,WO.add_member_id,WO.is_distribute,WO.distribute_time,WO.is_receive,WO.receive_time,WO.is_appoint,WO.appoint_time,WO.is_repair,WO.repair_time,WO.is_return,WO.return_time,WO.is_platform_check,WO.platform_check_time,WO.is_factory_check,WO.factory_check_time,WO.is_complete,WO.is_worker_pay,WO.is_fact_cancel,WO.is_cancel,WO.worker_id,WO.worker_phone,WO.add_member_appoint_stime,WO.add_member_appoint_etime,WO.last_uptime',

			// is_in,is_out,appoint_time_str
			'detai_by_id' => 'WO.order_id,WO.order_type as in_out_type,WO.orno,WO.full_name,WO.tell,WO.area,WO.address,WO.area_desc,WO.homefee_model,WO.hf_fact,WO.hf_fact_modify,WO.hf_fact_reason,WO.hf_work,WO.hf_work_modify,WO.hf_work_reason,WO.is_show_tell,WO.datetime,WO.is_receive,WO.receive_time,WO.is_appoint,WO.appoint_time,WO.is_repair,WO.repair_time,WO.is_return,WO.return_time,WO.is_platform_check,WO.platform_check_time,WO.is_factory_check,WO.factory_check_time,WO.is_complete,WO.is_worker_pay,WO.is_fact_cancel,WO.is_cancel,WO.close_reason,WO.close_remark,WO.worker_id,WO.worker_phone,WO.add_member_appoint_stime,WO.add_member_appoint_etime,A.tell_out as cs_phone',

			'listWorkerPaginateById' => 'WO.order_id,WO.order_type as in_out_type,WO.orno,WO.full_name,WO.tell,WO.area,WO.area_full,WO.area_desc,WO.address,WO.area_desc_search,WO.lat,WO.lng,WO.is_receive,WO.receive_time,WO.extend_appoint_time,WO.is_appoint,WO.appoint_time,WO.is_repair,WO.repair_time,WO.last_uptime,WO.datetime',
		];
		return $fields[$key] ? $fields[$key] : '';
	}

	public function listWxUserPaginate($user_id = 0, $status = 1, $field = '*')
	{
		$opt = $where = [];
		$where = [
			'WO.is_delete' => 0,
		];
		$user_id && ($where['WUO.wx_user_id'] = $user_id);
		switch ($status) {
			case '2':
				$where['WO.is_return'] = '1';
				$where['WO.is_fact_cancel'] = '0';
				$where['WO.is_cancel'] = '0';
				break;

			case '3':
				$where['WO.is_return'] = '0';
				$where['WO.is_fact_cancel'] = '0';
				$where['WO.is_cancel'] = '0';
				break;
		}
		$opt = [
			'alias' => 'WO',
			'join'  => 'LEFT JOIN worker_order_user_info WUO ON WUO.worker_order_id = WO.order_id',
			'where' => $where,
			'order' => 'datetime DESC',
		];
		return $this->paginate($opt, $field);
	}

	/**
	 * @User zjz
	 */
	public function paginate($opt = [], $field = '*')
	{
		$list = [];
		// !isset($opt['where']) && $opt['where'] = ['is_delete' => 0];
		$count = $this->getNum($opt);
		if ($count) {
			$opt['limit'] = getPage();
			$opt['field'] = $field;
			$list = $this->getList($opt);
		}
		return [$list, $count];
	}

	/**
	 * @User zjz
	 * 订单详情
	 */
	public function detailByIdOrFail($id, $field = '')
	{
		!$field && ($field = $this->getFieldByKey('detai_by_id'));
		$data = $this->getOneOrFail([
			'alias' => 'WO',
			'join'  => 'LEFT JOIN worker_order_access WOA ON WO.order_id = WOA.link_order_id LEFT JOIN admin A ON WOA.admin_id = A.id',
			'where' => [
				'WO.order_id' => $id,
				// 'WO.is_detele' => 0,
			], 
			'field' => $field.',WO.add_member_id,WO.is_need_factory_confirm,WO.is_distribute,WO.distribute_time,WO.last_uptime,WO.servicetype',
		]);

		// 每个订单最后一条技工预约记录
		$woa_detail = BaseModel::getInstance('worker_order_appoint')->getOne([
				'where' => [
					'worker_order_id' => $id,
				],
				'order' => 'id DESC',
			]);

		if ($woa_detail) {
			$data['appoint_time_str'] = date('Y-m-d H:i', $woa_detail['appoint_time']);
		} elseif (!$data['add_member_appoint_stime'] || !$data['add_member_appoint_etime']) {
			$data['appoint_time_str'] = '客户未预约';
		} else {
			$data['appoint_time_str'] = date('Y-m-d H:i', $data['add_member_appoint_stime']).'~'.date('H:i', $data['add_member_appoint_etime']);
		}
		
		$model = D('WorkerOrderDetail');
		$d_field = $model->getFieldByKey('orders_product_info');
		$d_field = $d_field ? $d_field .= ',WOD.worker_order_id,FP.product_thumb,FP.is_delete' : '*';
		$products = $model->getOrdersProductByOrderIds($id, 'product_id', $d_field);

		$factory_ids = arrFieldForStr($products, 'factory_id');
		$factorys = BaseModel::getInstance('factory')->getList([
				'where' => ['factory_id' => ['in', $factory_ids]],
				'field' => 'factory_id,factory_full_name,factory_short_name',
				'index' => 'factory_id',
			]);


		$detail_list = $model->getList([
				'alias' => 'WOD',
				'join'  => 'LEFT JOIN product_fault PFT ON WOD.fault_id = PFT.id',
				'where' => [
					'WOD.worker_order_id' => $id,
				],
				'field' => 'WOD.order_detail_id,WOD.product_id,WOD.fault_id,PFT.fault_name,PFT.fault_desc,WOD.servicefault,WOD.servicefault_desc,WOD.description,WOD.code,WOD.buy_date,WOD.is_complete,WOD.report_imgs',
			]);
		$detail_list_arr = [];
		foreach ($detail_list as $k => $v) {
			$pid = $v['product_id'];
			unset($v['product_id']);
			 $v['report_imgs'] = json_decode($v['report_imgs'], true);
            foreach ($v['report_imgs'] as &$report_img) {
                $report_img['name'] = $report_img['url'];
                $report_img['url_full'] = Util::getServerFileUrl($report_img['url']);
			 }
			$detail_list_arr[$pid][] = $v;
		}

		// $codes = arrFieldForStr($detail_list, 'code');

		if (isInWarrantPeriod($data['in_out_type'])) {
			$data['is_in'] = '1';
			$data['is_out'] = '0';
		} else {
			$data['is_in'] = '0';
			$data['is_out'] = '1';
		}

		// if ($codes) {
		// 	$c_list = $model->getExcelDatasByCodes($codes);
		// 	$is_data = reset($c_list);
		// 	if (!$is_data['zhibao_time'] || get_limit_date($is_data['active_time'], $is_data['zhibao_time']) >=  $data['datetime']) {
		// 		$data['is_in'] = '1';
		// 		$data['is_out'] = '0';
		// 	} else {
		// 		$data['is_in'] = '0';
		// 		$data['is_out'] = '1';
		// 	}
		// } else {
		// 	$data['is_in'] = '1';
		// 	$data['is_out'] = '0';
		// }
		
		$data['order_type'] = '2';
		if (reset($detail_list)['servicefault'] || !in_array($data['servicetype'], [106,110])) {
			$data['order_type'] = '1';
		}

		$worker = BaseModel::getInstance('worker')->getOne(['worker_id' => $data['worker_id']]);
		$data['worker_name'] = $worker['nickname'];

		$fk_list = BaseModel::getInstance('worker_order_operation_record')->getOne([
			'where' => [
				'order_id' => $data['order_id'],
				'ope_type' => 'FK',
			],
			'order' => 'add_time DESC',
		]);
		$fl_list = BaseModel::getInstance('worker_order_operation_record')->getOne([
			'where' => [
				'order_id' => $data['order_id'],
				'ope_type' => 'FL',
			],
			'order' => 'add_time DESC',
		]);

		$data['is_self_handle'] = '0';
		$data['handle_time'] = '0';
		$data['cancel_time'] = '0';
		
		if ($data['is_need_factory_confirm'] == 1 && $fk_list) {
			$data['is_self_handle'] = '1';
			$data['handle_time'] = $fk_list['add_time'];
		} elseif ($data['add_member_id'] && $fl_list) {
		// } elseif ($woa_detail) {
		// 	$data['handle_time'] = $woa_detail['addtime'];
			$data['is_self_handle'] = '2';
			$data['handle_time'] = $fl_list['add_time'];
		}

		$data['is_appoint_time'] = '0';
		if ($data['is_appoint']) {
			$data['is_appoint_time'] = $woa_detail['addtime'];
		}

		if ($data['is_fact_cancel'] == 1) {
			$cancel_recode = BaseModel::getInstance('worker_order_operation_record')->getOne([
				'where' => [
					'order_id' => $data['order_id'],
					'ope_type' => ['in', 'FY,SO'],
				],
				'order' => 'add_time DESC',
			]);
			$data['cancel_time'] = $cancel_recode['add_time'] ? $cancel_recode['add_time'] : $data['last_uptime'];
		}

		foreach ($products as $k => $v) {
            if ($v['product_thumb']) {
                $v['product_thumb'] = Util::getServerFileUrl($v['product_thumb']);
            } else {
                $product_thumb = BaseModel::getInstance('cm_list_item')
                    ->getFieldVal($v['product_cate_id'], 'item_thumb');
                $v['product_thumb'] = Util::getServerFileUrl($product_thumb);
            }
//			$v['product_thumb'] = $v['product_thumb'] ? Util::getServerFileUrl($v['product_thumb']) : '';
			$v = array_merge($v, $factorys[$v['factory_id']]);
			$v['order_faults_detail'] = $detail_list_arr[$k];
			unset($v['code']);
			$products[$k] = $v;
		}

		unset(
			$data['add_member_appoint_stime'],
			$data['add_member_appoint_etime']
		);
		$data['products'] = array_values($products);
		return $data;
	}

	//获取工单号，随机生成
	public function genOrNo(){
	    
	    $type       = 'A';
	    
	    list($t1, $t2) = explode(' ', microtime());
	    $microtime  = (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
	    
	    $microStr   = substr($microtime,10,3);
	    
	    $timeStr = date('ymdHis',time());
	    
	    $orno = $type.$timeStr.$microStr;
	    
	    $condition = array();
	    $condition['orno'] = array('eq',$orno);
	    
	    $count = $this->where($condition)->find();
	    
	    if(count($count)>0){
	        
	        return $this->genOrNo();
	    }else{
	        
	        return $orno;
	    }
	}

}
