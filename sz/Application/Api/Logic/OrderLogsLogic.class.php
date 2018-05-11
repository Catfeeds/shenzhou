<?php
/**
* 
*/
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Library\Common\Util;

class OrderLogsLogic extends BaseLogic
{
	
	public function changeTextForWxUserOrderLogs($list, $filed = 'ope_type')
	{
		$order_id = reset($list)['order_id'];
		$order = BaseModel::getInstance('worker_order')->getOne(['order_id' => $order_id], '*,order_type as in_out_type');	
		$detail = BaseModel::getInstance('worker_order_detail')->getOne(['worker_order_id' => $order['order_id']]);
		$order_type = $detail['servicefault'] || !in_array($order['servicetype'], [106,110]) ? '1' : '2';
		$worker = BaseModel::getInstance('worker')->getOne(['worker_id' => $order['worker_id']]);
		$appoins = BaseModel::getInstance('worker_order_appoint')->getList([
				'where' => ['worker_order_id' => $order_id],
				'order' => 'addtime ASC',
			]);
		$new = [];
		foreach ($appoins as $k => $v) {
			$new[] = [
				'id' => 0,
				'order_id' => $v['worker_order_id'],
				'add_time' => $v['addtime'],
				'ope_user_id' => 0,
				'ope_user_name' => '',
				'ope_role' => 'worker',
				'ope_type' => 'WA',
				'operation' => '维修商，'.$worker['nickname'].'，电话：'.$worker['worker_telephone'].' 与您联系，预约上门时间为：'.date('Y-m-d H:i', $v['appoint_time']),
				'desc' => '',
				'super_login' => 0,
				'is_curent_worker' => 0,
				'order_type' => $order_type,

			];
		}
		
		// $new[0]['ope_type'] = 
		$order_type_arr = [
			'1' => '修',
			'2' => '装',
			'3' => '维修',
			'4' => '安装',
		];
		
		foreach ($list as $k => $v) {
			switch ($v[$filed]) {
				case 'FH':
					if (!$v['ope_user_id'] || $v['ope_user_name'] === '微信用户') {
						$v['operation'] = '报'.$order_type_arr[$order_type].'单号：'.$order['orno'].'，厂家将尽快处理您的订单。';
						$v['desc'] = '';
						$v['order_type'] = $order_type;
						$new[] = $v;
					}
					break;

				case 'FK':
					$v['operation'] = '厂家正安排处理您的工单';
					$v['desc'] = '';
					$v['order_type'] = $order_type;
					$new[] = $v;
					break;

				case 'FL':
					$v['operation'] = '工作人员会在15分钟内联系您。';
					$v['desc'] = '';
					$v['order_type'] = $order_type;
					$new[] = $v;
					break;

				case 'WA':
					if (count(explode('抢单成功', $v['operation'])) < 2) {
						$time = str_replace('预约客户成功,预约时间为:', '', $v['operation']);
						$v['operation'] = '维修商，'.$worker['nickname'].'，电话：'.$worker['worker_telephone'].' 与您联系，预约上门时间为：'.$time;
						$v['desc'] = '';
						$v['order_type'] = $order_type;
						$new[] = $v;
					}
					break;

				// case 'SH':
				// 	if ($order['is_appoint']) {
				// 		$v['operation'] = '维修商，'.$worker['nickname'].'，电话：'.$order['worker_phone'].' 与您联系，预约上门时间为：'.date('Y-m-d H:i', $order['appoint_time']);
				// 		$v['desc'] = '';
				// 		$new[] = $v;	
				// 	}
				// 	break;	

				case 'SL':
					if (count(explode('未完成', $v['operation'])) > 1) {
						$v['operation'] = '工单重置为待服务，请等待师傅联系';
						$v['desc'] = '';
						$v['order_type'] = $order_type;
						$v[$filed] = 'NSL';
						$new[] = $v;
					} else {
						$v['operation'] = '报'.$order_type_arr[$order_type].'单已完成，感谢您选择神州联保服务。';
						$v['desc'] = '';
						$v['order_type'] = $order_type;
						$new[] = $v;
					}
					break;

				case 'WJ':
					$wj_str = '师傅已完成'.$order_type_arr[$order_type+2];
					if (isInWarrantPeriod($order['in_out_type'])) {
						$v['operation'] = $wj_str;
					} else {
						$v['operation'] = $wj_str.'，维修费请与师傅面议后支付给师傅';
					}
					// 师傅已完成维修，维修费请与师傅面议后支付给师傅 (保外)
					$v['desc'] = '';
					$v['order_type'] = $order_type;
					$new[] = $v;
					break;	

				case 'SO':
					$v['operation'] = '客服已取消订单，感谢您选择神州联保服务。如有疑问，可直接联系客服。';
					$v['desc'] = '';
					$v['order_type'] = $order_type;
					$new[] = $v;
					break;

				case 'FY':
					if (!$v['ope_user_id'] || $v['ope_user_name'] === '微信用户') {
						$v['operation'] = '您已取消订单，感谢您选择神州联保服务。';
					} else {	
						$v['operation'] = '厂家已取消订单，感谢您选择神州联保服务。如有疑问，可直接联系客服。';
					}
					$v['desc'] = '';
					$v['order_type'] = $order_type;
					$new[] = $v;
					break;					
			}

		}

		// if ($order['is_return']) {
		// 	$new[] = [
		// 		'id' => 0,
		// 		'order_id' => $order['order_id'],
		// 		'add_time' => $order['return_time'],
		// 		'ope_user_id' => 0,
		// 		'ope_user_name' => '',
		// 		'ope_role' => 'worker',
		// 		'ope_type' => 'WA',
		// 		'operation' => '维修商，'.$worker['nickname'].'，电话：'.$worker['worker_telephone'].' 已与您取得联系，预约上门时间为：'.date('Y-m-d H:i', $v['appoint_time']),
		// 		'desc' => '',
		// 		'super_login' => 0,
		// 		'is_curent_worker' => 0,

		// 	];
		// }
		// die;
		Util::sortByField($new, 'add_time');
		$has_we = 0;
		foreach ($new as $k => $v) {
			if (!$has_we && $v[$filed] == 'WA') {
				$new[$k][$filed] = 'WE';
				$has_we = 1;
			}
		}


		$list = [];
		$i = 1;
		foreach ($new as $k => $v) {
			$list[count($new)-$i] = $v;
			++$i;
		}
		ksort($list);
		return $list;
	}

}
