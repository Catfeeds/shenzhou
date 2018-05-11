<?php
/**
* 
*/
namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Controller\BaseController;
use Api\Logic\OrderLogic;
use Api\Model\BaseModel;
use Common\Common\Service\OrderService;
use Illuminate\Support\Arr;
use Library\Common\Util;
use Library\Crypt\AuthCode;
use Common\Common\Service\AuthService;
use Api\Model\FactoryModel;

class OrderController extends BaseController
{

    public function getList()
    {
        try {
            $user_id = $this->requireAuth();

            $status = I('status', 0);
            $where = [
                'wx_user_id' => $user_id,
            ];
            if ($status == 1) {
                $where['cancel_status'] = ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]];
                $where['worker_order_status'] = ['IN', [OrderService::STATUS_CREATED, OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE, OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK, OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE, OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE, OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL, OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT, OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE, OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE, OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT]];
            } elseif ($status == 2) {
                $where['cancel_status'] = ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]];
                $where['worker_order_status'] = ['IN', [OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE, OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT, OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT, OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT, OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED]];
            }
            $join = [
                'INNER JOIN worker_order_user_info ON worker_order_user_info.worker_order_id=worker_order.id',
            ];
            $orders = BaseModel::getInstance('worker_order')->getList([
                'where' => $where,
                'field' => 'id,worker_order_type,worker_order_status,cancel_status,worker_id,distributor_id,service_type',
                'join' => $join,
                'order' => 'id DESC',
                'limit' => getPage(),
            ]);
            BaseModel::getInstance('worker')->attachField2List($orders, 'worker_id,worker_telephone', [], 'worker_id');
            BaseModel::getInstance('admin')->attachField2List($orders, 'id,tell_out', [], 'distributor_id');
            $worker_order_ids = $orders ? Arr::pluck($orders, 'id') : '-1';
            $worker_order_id_pay_map = BaseModel::getInstance('worker_order_user_info')->getFieldVal([
                'where' => ['worker_order_id' => ['IN', $worker_order_ids]],
            ], 'worker_order_id,is_user_pay');

            $worker_order_appoints = BaseModel::getInstance('worker_order_appoint_record')->getList([
                'where' => [
                    'worker_order_id' => ['IN', $worker_order_ids],
                ],
                'field' => 'worker_order_id,group_concat(appoint_time order by id desc) appoint_times',
                'group' => 'worker_order_id',
                'index' => 'worker_order_id'
            ]);

            $order_logic = new OrderLogic();
            foreach ($orders as $key => $order) {
                $orders[$key]['is_user_pay'] = $worker_order_id_pay_map[$order['id']];
                $orders[$key]['show_status'] = OrderService::isCanceledOrder($order['cancel_status']) ? '0' : $order_logic->getOrderShowStatus($order['worker_order_status'], $orders[$key]['is_user_pay']);
                $orders[$key]['is_insurance'] = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? '1' : '0';
                $appoint_times = $worker_order_appoints[$order['id']]['appoint_times'];
                $appoint_times = explode(',', $appoint_times);
                $orders[$key]['worker_appoint_time'] = $appoint_times[0] ?? '';
                $orders[$key]['is_user_add'] = $order_logic->isOrderAddByUser($order['worker_order_type']);
            }
            BaseModel::getInstance('worker_order_product')->attachMany2List($orders, 'worker_order_id', 'id,product_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode');
            BaseModel::getInstance('worker_order_ext_info')->attachField2List($orders, 'appoint_start_time,appoint_end_time', [], 'id', 'user_appoint');
            BaseModel::getInstance('worker_order_fee')->attachField2List($orders, 'user_discount_out_fee,accessory_out_fee accessory_fee,worker_repair_fee_modify user_repair_fee', [], 'id', 'fee');



            $num = BaseModel::getInstance('worker_order')->getNum([
                'where' => $where,
                'join' => $join,
            ]);

            $this->paginate($orders, $num);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function detail()
    {
        try {
            $id = I('get.id');

            $order_logic = new OrderLogic();
            $order = $order_logic->getOrderDetail($id);
            $show_status = $order_logic->getOrderShowStatus($order['worker_order_status'], $order['is_user_pay']);
            $order['show_status'] = OrderService::isCanceledOrder($order['cancel_status']) ? '0' : $show_status;
            $order['show_status_detail'] = $order_logic->getOrderShowStatusDetail($order['id'], $order['show_status'])[$order['show_status']];


            $this->response($order);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function orderStatus()
    {
        try {
            $id = I('get.id');
            $order_logic = new OrderLogic();
            $order = $order_logic->getOrderDetail($id);
            $show_status = $order_logic->getOrderShowStatus($order['worker_order_status'], $order['is_user_pay']);
            $order['show_status'] = OrderService::isCanceledOrder($order['cancel_status']) ? '0' : $show_status;
            $status = (new OrderLogic())->getOrderShowStatusDetail($order['id'], $show_status);
            Util::sortByField($status, 'time', 1);
            $this->responseList(array_values($status));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

//	public function orders()
//	{
//		$select = [];
//		$model = D('WorkerOrder');
//		switch (I('get.member_type')) {
//			case 'wxuser':
//				list($list, $count) = $model->listWxUserPaginate(I('get.member_id', 0), I('get.status', 1), $model->getFieldByKey());
//				break;
//
//			default:
//				$where = [
//					'WO.is_delete' => 0,
//					'WO.is_fact_cancel' => 0,
//					'WO.is_cancel' => 0,
//				];
//				switch (I('get.status', 1)) {
//					case '2':
//						$where['WO.is_return'] = '1';
//						break;
//
//					case '3':
//						$where['WO.is_return'] = '0';
//						break;
//				}
//				list($list, $count) = $model->paginate([
//						'alias' => 'WO',
//						'where' => $where,
//						'field' => $model->getFieldByKey('list'),
//					]);
//				break;
//		}
//
//		if ($list) {
//			$detail_model = D('WorkerOrderDetail');
//
//			$d_field = $detail_model->getFieldByKey('orders_product_info');
//			$d_field = $d_field ? $d_field .= ',WOD.worker_order_id' : '*';
//
//			$order_ids = arrFieldForStr($list, 'order_id');
//			$dlist = $detail_model->getOrdersProductByOrderIds($order_ids, 'worker_order_id', $d_field);
//
//			// $codes = arrFieldForStr($dlist, 'code');
//			// $clist = $detail_model->getExcelDatasByCodes($codes, 'code,zhibao_time,active_time', true);
//
//			$worker_id = arrFieldForStr($list, 'worker_id');
//
//			$worder_list = $worker_id?
//				BaseModel::getInstance('worker')->getList([
//					'worker_id' => ['in', $worker_id],
//					'index' => 'worker_id',
//				]):
//				[];
//
//			// 订单最后一个FK操作记录
//			$fk_list = BaseModel::getInstance('worker_order_operation_record')->getList([
//					'where' => [
//						'order_id' => ['in', $order_ids],
//						'ope_type' => 'FK',
//					],
//					'index' => 'order_id',
//					'order' => 'id ASC',
//					// 'group' => 'id',
//				]);
//
//			$fl_list = BaseModel::getInstance('worker_order_operation_record')->getList([
//					'where' => [
//						'order_id' => ['in', $order_ids],
//						'ope_type' => 'FL',
//					],
//					'index' => 'order_id',
//					'order' => 'id ASC',
//					// 'group' => 'id',
//				]);
//
//			// 每个订单最后一条技工预约记录
//			$woa_list = BaseModel::getInstance('worker_order_appoint')->getList([
//					'where' => [
//						'worker_order_id' => ['in', $order_ids],
//					],
//					'index' => 'worker_order_id',
//					'order' => 'id ASC',
//					// 'group' => 'id',
//				]);
//
//			$od_data = $detail_model->getList([
//					'where' => [
//						'worker_order_id' => ['in', $order_ids],
//					],
//					'field' => 'worker_order_id,fault_id,servicefault',
//					'order' => 'order_detail_id DESC ',
//					'index' => 'worker_order_id',
//				]);
//
//			foreach ($list as $k => $v) {
//				$p_data = $dlist[$v['order_id']];
//
//                if ($p_data['product_thumb']) {
//                    $p_data['product_thumb'] = Util::getServerFileUrl($p_data['product_thumb']);
//                } else {
//                    $product_thumb = BaseModel::getInstance('cm_list_item')
//                        ->getFieldVal($p_data['product_cate_id'], 'item_thumb');
//                    $p_data['product_thumb'] = Util::getServerFileUrl($product_thumb);
//                }
//
//				// $c_data = $clist[$p_data['code']];
//
//				if ($woa_list[$v['order_id']]) {
//					$v['appoint_time_str'] = date('Y-m-d H:i', $woa_list[$v['order_id']]['appoint_time']);
//				} elseif (!$v['add_member_appoint_stime'] || !$v['add_member_appoint_etime']) {
//					$v['appoint_time_str'] = '客户未预约';
//				} else {
//					$v['appoint_time_str'] = date('Y-m-d H:i', $v['add_member_appoint_stime']).'~'.date('H:i', $v['add_member_appoint_etime']);
//				}
//
//				// TODO 需要重新查数据的逻辑再比较
//				// if (!$p_data['code'] || get_limit_date($c_data['active_time'], $c_data['zhibao_time']) >=  $v['datetime'] || !$c_data['zhibao_time']) {
//				if (isInWarrantPeriod($v['in_out_type'])) {
//					$v['is_in'] = '1';
//					$v['is_out'] = '0';
//				} else {
//					$v['is_in'] = '0';
//					$v['is_out'] = '1';
//				}
//				$v['order_type'] = $od_data[$v['order_id']]['servicefault'] || !in_array($v['servicetype'], [106,110]) ? '1' : '2';
//
//				$v['is_self_handle'] = '0';
//				$v['handle_time'] = '0';
//				// 是否是自行处理
//				if ($v['is_need_factory_confirm'] == 1 && $fk_list[$v['order_id']]['add_time']) {
//					$v['is_self_handle'] = '1';
//					$v['handle_time'] = (string)$fk_list[$v['order_id']]['add_time'];
//				} elseif ($v['add_member_id']) {
//				// } elseif ($woa_list[$v['order_id']]) {
//					// $v['handle_time'] = $woa_list[$v['order_id']]['addtime'];
//					$v['is_self_handle'] = '2';
//					$v['handle_time'] = (string)$fl_list[$v['order_id']]['add_time'];
//				}
//
//				$v['worker_name'] = '';
//				if ($worder_list[$v['worker_id']]) {
//					$v['worker_name'] = $worder_list[$v['worker_id']]['nickname'];
//				}
//
//				unset(
//					$p_data['worker_order_id'],
//					$v['add_member_appoint_stime'],
//					$v['add_member_appoint_etime']
//				);
//				$v['product_info'] = $p_data;
//				$list[$k] = $v;
//			}
//		}
//		$this->paginate($list, $count);
//	}

//	public function orderDetail()
//	{
//		$id = I('get.id', 0);
//		$model = D('WorkerOrder');
//		try {
//			$data = $model->detailByIdOrFail($id);
//			$this->response($data);
//		} catch (\Exception $e) {
//			$this->getExceptionError($e);
//		}
//	}

	public function wxUserCreateWorkerOrder()
	{
		$wx_user_id = $this->requireAuth();
		$post = I('post.', []);
		if ($post['form']==1) {
            try {
                $worker_order_id = D('Order', 'Logic')->createWorkerOrder($post);
                $this->response(['order_id' => $worker_order_id]);
            } catch (\Exception $e) {
                $this->getExceptionError($e);
            }
        } else {
            if (!$post['product_code']) {
                $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
            }
            try {
                D('FactoryProduct')->checkMyProductCreateOrderByCode($post['product_code']);
                $logic = D('Order', 'Logic');
                $create_data = $logic->getWorkerOrderByMyCodeOrFail($post['product_code']);
                $worker_order_id = $logic->createWorkerOrderByWX($create_data, $post);
                $this->response([
                    'order_id' => $worker_order_id,
                ]);
            } catch (\Exception $e) {
                $this->getExceptionError($e);
            }
        }
	}

	public function checkCodeWxUserCreateOrder()
	{
		$code = I('get.product_code', '');
		if (!$code) {
			$this->fail(ErrorCode::DATA_IS_WRONG);
		}
		try {
			$wx_user_id = $this->requireAuth();
			// $md5 = D('WorkerOrderDetail')->codeToMd5Code($code);
			// $key = substr($md5, 0, 1);
			// $excel_info = BaseModel::getInstance('factory_excel_datas_'.$key)->getOneOrFail(['md5code' => $md5]);
			$excel_info = (new \Api\Model\YimaModel())->getYimaInfoByCode($code);
			$data = D('FactoryProduct')->checkMyProductCreateOrderByCode($code, true);

			if (!$excel_info['product_id']) {
				$excel_info['product_id'] = D('FactoryProductQrcode')->getInfoByCode($code)['product_id'];
			}
			$produ_data = D('FactoryProduct')->getOneOrFail($excel_info['product_id']);
			$produ_data['excel_info'] = $excel_info;
			$error = '';
			if (!$produ_data['excel_info']['active_time']) {
				$this->throwException(ErrorCode::PRODUCT_DETAIL_NOT_ACTIVE_TIME);
			} elseif (!$produ_data['factory_id'] || !$produ_data['excel_info']['code']) {
				$this->throwException(ErrorCode::DATA_WRONG);
			} elseif (AuthService::getModel() !=  'wxuser' || !in_array(AuthService::getAuthModel()->user_type + 1, explode(',', $excel_info['active_json']['is_order_type']))) {
				$error = '';
				switch (AuthService::getAuthModel()->user_type) {
					case 0:
						$error = '该产品暂不支持消费者直接申请售后，请联系您的产品卖家';
						break;

					case 1:
						$error = '该产品暂不支持经销商申请售后，如有疑问，可联系厂家，联系电话：'.(new FactoryModel())->getWorkerNeedPhone($excel_info['factory_id']);
						break;

					default: 
						$error = '用户类型不在质保策略允许报装/修的范围内';	
						break;
				}
	            // $this->throwException(ErrorCode::SYS_NOT_POWER, '用户类型不在质保策略允许报装/修的范围内');
	        }

			$this->response([
					'error' => $error,
					'order_id' => $data['order_id'],
				]);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function userCancel()
	{
		$wx_user_id = $this->requireAuth();
		try {
			$id = I('get.id');
			D('Order', 'Logic')->cancelOrderForFyOrFail($id, I('put.desc', ''));
			$this->okNull();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function actionLogs()
	{
		$id = I('get.id', 0);
		$type = I('get.type');

		// if ($type && !in_array($type, [1,2,3,4])) {
		// 	$this->paginate();	
		// }

		$where = [
			'order_id' => $id,
		];

		$change_text = false;

		switch ($type) {
			case '1':
				$where['ope_type'] = ['like', 'S%'];
				break;

			case '2':
				$where['ope_type'] = ['like', 'F%'];
				break;

			case '3':
				$where['ope_type'] = ['like', 'W%'];
				break;

			case '4':
				$where['ope_type'] = ['like', 'A%'];
				break;

			case '5':
				$ope_role = 'SO,FY,FL,FK,WA,WE,FH,WJ,SL';
				$where['ope_type'] = ['in', $ope_role];
				break;
		}

		$model = BaseModel::getInstance('worker_order_operation_record');

		$count = $model->getNum($where);
		$opt = [
			'where' => $where,
			'field' => 'id,order_id,add_time,ope_user_id,ope_user_name,ope_role,ope_type,operation,desc,super_login,is_curent_worker',
			// 'limit' => getPage(),
			'order' => 'add_time DESC',
		];
		$list = $count?
				$model->getList($opt):
				[];
		
		switch ($type) {
			case '5':
				$list = D('OrderLogs', 'Logic')->changeTextForWxUserOrderLogs($list);
				break;
		}

		$this->paginate($list, $count);
	}

}