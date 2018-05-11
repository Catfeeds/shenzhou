<?php
/**
* @User zjz
* @Date 2016/12/5
* @mess 订单
*/
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Repositories\Events\OrderCancelEvent;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Library\Common\BaiDuLbsApi;
use Api\Model\FactoryModel;
use Library\Common\Util;

class OrderLogic extends BaseLogic
{
	
	/**
	 * @User zjz
	 *  根据用户我的产品库id查询产品信息，用户信息，购买信息
	 */	
	public function getWorkerOrderByMyPidOrFail($pid)
	{
		$detail = BaseModel::getInstance('wx_user_product')->getOneOrFail($pid);
		$produ_data = D('FactoryProduct')->getOneOrFail($detail['wx_product_id']);
		$produ_data['excel_info'] = D('FactoryExcel')->getExcelDataByMyPidOrFail($detail['md5code']);
		$produ_data['user_info'] = BaseModel::getInstance('wx_user')->getOneOrFail($detail['wx_user_id']);
		return $produ_data;
	}

	/**
	 * @User zjz
	 *  根据产品code查询产品信息，用户信息，购买信息
	 */	
	public function getWorkerOrderByMyCodeOrFail($code = '')
	{
		// $md5 = D('WorkerOrderDetail')->codeToMd5Code($code);
		// $excel_info = D('FactoryExcel')->getExcelDataByMyPidOrFail($md5);
		$excel_info = (new \Api\Model\YimaModel())->getYimaInfoByCode($code);

		if (!$excel_info['product_id']) {
			$excel_info['product_id'] = D('FactoryProductQrcode')->getInfoByCode($code)['product_id'];
		}

		$produ_data = D('FactoryProduct')->getOneOrFail($excel_info['product_id']);
		// $produ_data['user_info'] = BaseModel::getInstance('wx_user')->getOneOrFail(AuthService::getAuthModel()->id);
		$produ_data['user_info'] = AuthService::getAuthModel()->data;

		// if ($produ_data['user_info']['user_type'] == '1') {
		// 	$this->throwException(ErrorCode::DEALER_NOT_CREATE_ORDER);
		// } else
		if (!$produ_data['user_info']['openid'] || !$produ_data['user_info']['telephone']) {
			$this->throwException(ErrorCode::YOU_NOT_SAME_PHONE);
		} elseif (!D('WeChatUser', 'Logic')->isSubscribe($produ_data['user_info']['openid'])) {
			$this->throwException(ErrorCode::OPENID_NOT_SUBSCRIBE_WECHAT);
		}

		$produ_data['excel_info'] = $excel_info;
		return $produ_data;
	}

    /**
     * @User allen.zhang
     *  微信用户的创建工单
     */
    public function createWorkerOrder($post = [])
    {
        $area_id_arr = array_unique(array_filter(explode(',', $post['area_ids'])));
        if (!$post['category_id']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品分类');
        } elseif (!$post['standard_id']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品规格');
        } elseif (!$area_id_arr) {
            $this->throwException(ErrorCode::AREA_IDS_NOT_EMPTY);
        } elseif (empty($post['area_desc'])) {
            $this->throwException(ErrorCode::AREA_DESC_NOT_EMPTY);
        } elseif (!$post['name'] || !$post['tell']) {
            $this->throwException(ErrorCode::NAME_TELL_NOT_EMPTY);
        } elseif (!$post['appoint_stime'] || !$post['appoint_etime']) {
            $this->throwException(ErrorCode::APPOINT_S_E_TIME_NOT_EMPTY);
        } elseif ($post['appoint_stime'] >= $post['appoint_etime'] || $post['appoint_stime'] < NOW_TIME) {
            $this->throwException(ErrorCode::APPOINT_TIME_WRONG);
        } elseif (count(htmlEntityDecodeAndJsonDecode($post['images'])) > 3) {
            $this->throwException(ErrorCode::IMAGES_NOT_DY_3);
        }

        $factory_id = C('WORKER_OUT_ORDER_FACTORY_ID');
        $factory_helper = BaseModel::getInstance('factory_helper')->getOne([
            'where' => ['factory_id' => $factory_id],
            'field' => 'id,name,telephone',
            'order' => 'is_default DESC'
        ]);

        switch ($post['type']) {
            case '1':
                $service_type = OrderService::TYPE_WORKER_REPAIR;
                break;

            case '2':
                $service_type = OrderService::TYPE_WORKER_INSTALLATION;
                break;

            default:
                $this->throwException(ErrorCode::CREATE_ORDER_NOT_SERVICETYPE);
                break;
        }

        $worker_order_type = OrderService::ORDER_TYPE_WEIXIN_OUT_INSURANCE;

        $areas = explode(',', $post['area_ids']);
        $create_order_service = new OrderService\CreateOrderService($factory_id);
        $create_order_service->setOrderUser([
            'province_id' => $areas[0],
            'city_id' => $areas[1],
            'area_id' => $areas[2],
            'wx_user_id' => AuthService::getModel() == AuthService::ROLE_WX_USER ? AuthService::getAuthModel()->getPrimaryValue() : 0,
            'real_name' => $post['name'],
            'phone' => $post['tell'],
            'address' => $post['area_desc']
        ]);
        $create_order_service->setOrderExtInfo([
            'factory_helper_id' => $factory_helper['id'],
            'cp_factory_helper_name' => $factory_helper['name'],
            'cp_factory_helper_phone' => $factory_helper['telephone'],
            'appoint_start_time' => $post['appoint_stime'],
            'appoint_end_time' => $post['appoint_etime']
        ]);
        $create_order_service->setOrderProducts([
            [
                //'product_brand_id' => $data['product_brand'],
                'product_category_id' => $post['category_id'], //$data['product_category'],
                'product_standard_id' => $post['standard_id'], //$data['product_guige'],
                //'product_id' => $data['product_id'],
                'fault_label_ids' => $post['fault_label_ids'],
                'product_nums' => 1,
//                'cp_product_mode' => $data['product_xinghao'],
//                'yima_code' => $data['excel_info']['code'],
                'user_service_request' => $post['desc'],
                'service_request_imgs' => htmlEntityDecode($post['images'])
            ]
        ]);
        $create_order_service->setOrder([
            'factory_id' => $factory_id,
            'worker_order_type' => $worker_order_type,
            'worker_order_status' => OrderService::STATUS_CREATED,
            'cancel_status' => OrderService::CANCEL_TYPE_NULL,
            'origin_type' => AuthService::getModel() == AuthService::ROLE_WX_USER ? OrderService::ORIGIN_TYPE_WX_USER : OrderService::ORIGIN_TYPE_WX_DEALER,
            'service_type' => $service_type,
            'create_time' => NOW_TIME,
            'last_update_time' => NOW_TIME,
            'is_insured' => $worker_order_type == OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE ? 1 : 0
        ]);

        M()->startTrans();
        $worker_order_id = $create_order_service->create(); //print_r($worker_order_id);exit;

        // 自动下单
        BaseModel::getInstance('worker_order')->update($worker_order_id, [
            'worker_order_status' => OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE,
            'factory_check_order_type' => 1,
            'factory_check_order_id' => $factory_id,
            'factory_check_order_time' => NOW_TIME,
            'last_update_time' => NOW_TIME,
        ]);
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::SYSTEM_WX_OUT_ORDER_AUTO_ADD, [
            'operator_id' => 0,
            'is_system_create' => 1,
        ]);
        M()->commit();

        return $worker_order_id;
    }

	/**
	 * @User zjz
	 *  微信用户的创建工单
	 */
	public function createWorkerOrderByWX($data = [], $post = [])
	{
		$area_id_arr = array_unique(array_filter(explode(',', $post['area_ids'])));
		if (!$data['excel_info']['active_time']) {
			$this->throwException(ErrorCode::PRODUCT_DETAIL_NOT_ACTIVE_TIME);
		} elseif (!$data['user_info']['id'] || !$data['factory_id']|| !$data['excel_info']['code']) {
			$this->throwException(ErrorCode::DATA_WRONG);
		} elseif (!$area_id_arr) {
			$this->throwException(ErrorCode::AREA_IDS_NOT_EMPTY);
		} elseif (empty($post['area_desc'])) {
			$this->throwException(ErrorCode::AREA_DESC_NOT_EMPTY);
		} elseif (!$post['name'] || !$post['tell']) {
			$this->throwException(ErrorCode::NAME_TELL_NOT_EMPTY);
		} elseif (!$post['appoint_stime'] || !$post['appoint_etime']) {
			$this->throwException(ErrorCode::APPOINT_S_E_TIME_NOT_EMPTY);
		} elseif ($post['appoint_stime'] >= $post['appoint_etime'] || $post['appoint_stime'] < NOW_TIME) {
			$this->throwException(ErrorCode::APPOINT_TIME_WRONG);
		} elseif (count(htmlEntityDecodeAndJsonDecode($post['images'])) > 3) {
			$this->throwException(ErrorCode::IMAGES_NOT_DY_3);
		} elseif ($data['excel_info']['is_disable'] > 0) {
			$this->throwException(ErrorCode::SYS_NOT_POWER, '该产品已被厂家停用，如有疑问，可联系厂家，联系方式为：'.(new FactoryModel())->getWorkerNeedPhone($data['excel_info']['factory_id']));
		} elseif (AuthService::getModel() !=  'wxuser' || !in_array(AuthService::getAuthModel()->user_type + 1, explode(',', $data['excel_info']['active_json']['is_order_type']))) {
			$error = '';
			switch (AuthService::getAuthModel()->user_type) {
				case 0:
					$error = '该产品暂不支持消费者直接申请售后，请联系您的产品卖家';
					break;

				case 1:
					$error = '该产品暂不支持经销商申请售后，如有疑问，可联系厂家，联系电话：'.(new FactoryModel())->getWorkerNeedPhone($data['excel_info']['factory_id']);
					break;

				default: 
					$error = '用户类型不在质保策略允许报装/修的范围内';	
					break;
			}
            $this->throwException(ErrorCode::SYS_NOT_POWER, $error);
        }

		$factory_helper = BaseModel::getInstance('factory_helper')->getOne([
		    'where' => ['factory_id' => $data['factory_id']],
            'order'=> 'is_default DESC',
            'field' => 'id,name,telephone',
        ]);
//		$area_data = BaseModel::getInstance('cm_list_item')->getList([
//				'list_item_id' => ['in', $area_id_arr],
//				'list_id' => 13,
//			], 'item_desc');

//		$fault_label_ids = implode(',', array_unique(array_filter(explode(',', $post['fault_label_ids']))));
		switch ($post['type']) {
			case '1':
				$service_type = OrderService::TYPE_WORKER_REPAIR;
//				$s_w = ['id' => ['in', $fault_label_ids]];
				// if ($data['product_category']) {
				// 	$s_w['product_id'] = $data['product_category'];
				// }
//				$fault_labels = $fault_label_ids ? BaseModel::getInstance('product_fault_label')->getList($s_w) : [];
//                !$fault_labels &&  $this->throwException(ErrorCode::FAULT_LABEL_IS_WRONG);
				break;

			case '2':
				$service_type = OrderService::TYPE_WORKER_INSTALLATION;
				break;
			
			default:
				$this->throwException(ErrorCode::CREATE_ORDER_NOT_SERVICETYPE);
				break;
		}


		
		$factory_id = $data['factory_id'];
		$worker_order_type =  OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE;
		
		if (get_limit_date($data['excel_info']['active_time'], $data['excel_info']['zhibao_time'] + $data['excel_info']['active_json']['active_reward_moth']) < NOW_TIME && C('outFactoryId')) { // 保外
			$worker_order_type =  OrderService::ORDER_TYPE_WX_USER_OUT_INSURANCE;
		}
		$areas = explode(',', $post['area_ids']);
        $create_order_service = new OrderService\CreateOrderService($factory_id);
        $create_order_service->setOrderUser([
            'province_id' => $areas[0],
            'city_id' => $areas[1],
            'area_id' => $areas[2],
            'wx_user_id' => AuthService::getModel() == AuthService::ROLE_WX_USER ? AuthService::getAuthModel()->getPrimaryValue() : 0,
            'real_name' => $post['name'],
            'phone' => $post['tell'],
            'address' => $post['area_desc'],
        ]);
        $create_order_service->setOrderExtInfo([
            'factory_helper_id' => $factory_helper['id'],
            'cp_factory_helper_name' => $factory_helper['name'],
            'cp_factory_helper_phone' => $factory_helper['telephone'],
            'appoint_start_time' => $post['appoint_stime'],
            'appoint_end_time' => $post['appoint_etime'],
        ]);
        $create_order_service->setOrderProducts([
            [
                'product_brand_id' => $data['product_brand'],
                'product_category_id' => $data['product_category'],
                'product_standard_id' => $data['product_guige'],
                'product_id' => $data['product_id'],
                'fault_label_ids' => $post['fault_label_ids'],
                'product_nums' => 1,
                'cp_product_mode' => $data['product_xinghao'],
                'yima_code' => $data['excel_info']['code'],
                'user_service_request' => $post['desc'],
                'service_request_imgs' => htmlEntityDecode($post['images']),
            ]
        ]);
        $create_order_service->setOrder([
            'factory_id' => $factory_id,
            'worker_order_type' => $worker_order_type,
            'worker_order_status' => OrderService::STATUS_CREATED,
            'cancel_status' => OrderService::CANCEL_TYPE_NULL,
            'origin_type' => AuthService::getModel() == AuthService::ROLE_WX_USER ? OrderService::ORIGIN_TYPE_WX_USER : OrderService::ORIGIN_TYPE_WX_DEALER,
            'service_type' => $service_type,
            'create_time' => NOW_TIME,
            'last_update_time' => NOW_TIME,
            'is_insured' => $worker_order_type == OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE ? 1 : 0,
        ]);
        M()->startTrans();
        $worker_order_id = $create_order_service->create();
        M()->commit();

//        $order_data = [
//            'factory_id' => $factory_id,
//            'worker_order_type' => $worker_order_type,
//            'worker_order_status' => OrderService::STATUS_CREATED,
//            'cancel_status' => OrderService::CANCEL_TYPE_NULL,
//            'origin_type' => AuthService::getModel() == AuthService::ROLE_WX_USER ? OrderService::ORIGIN_TYPE_WX_USER : OrderService::ORIGIN_TYPE_WX_DEALER,
//            'service_type' => $service_type,
//            'create_time' => NOW_TIME,
//            'last_update_time' => NOW_TIME,
//            'is_insured' => $worker_order_type == OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE ? 1 : 0,
//        ];
//
//        M()->startTrans();
//        $worker_order_id = (new \Admin\Logic\OrderLogic())->add($factory_id, $order_data);
//        M()->commit();

        return $worker_order_id;

//
//		$model = D('WorkerOrder');
//		$add = [
//			'order_type' => $order_type,
//			'factory_id' => $data['factory_id'],
//			'full_name' => $post['name'],
//			'tell' => $post['tell'],
//			'add_member_appoint_stime' => $post['appoint_stime'],
//			'add_member_appoint_etime' => $post['appoint_etime'],
//			'area' => end($area_id_arr),
//			'area_full' => $post['area_ids'],
//			'area_desc' => arrFieldForStr($area_data, 'item_desc', '-'),
//			'address' => $post['area_desc'],
//			'servicetype' => $servicetype,
//			'order_origin' => 'FC',
//			'datetime' => NOW_TIME,
//			'orno' => $model->genOrNo(),
//			'is_need_factory_confirm' => 1,
//			'is_insurance_cost' => 1.00,  // 保险费用
//			//   防止默认值为null 导致语句出错
//			'add_member_id' => 0,
//		];
//
//		if ($help_person['name']) {
//			$add['technology_name'] = $help_person['name'];
//		}
//
//		if ($help_person['telephone']) {
//			$add['technology_tell'] = $help_person['telephone'];
//		}
//
//		$model->startTrans();
//		$order_id = $model->insert($add);
//		$add['order_id'] = $order_id;
//
//		// $data['product_category'],
//		$servicepro_desc = BaseModel::getInstance('cm_list_item')->getOne($data['product_category'])['item_desc'];
//
//		// $post['fault_id']
//		// $fault_desc = BaseModel::getInstance('product_fault')->getOne($post['fault_id'])['fault_name'];
//
//		// $data['product_brand']
//		$servicebrand_desc = BaseModel::getInstance('factory_product_brand')->getOne($data['product_brand'])['product_brand'];
//
//		// $data['product_guige'],
//		$stantard_desc = BaseModel::getInstance('product_standard')->getOne($data['product_guige'])['standard_name'];
//
//		$wod = [
//			'worker_order_id' => $order_id,
//			'servicepro' => $data['product_category'],
//			'servicepro_desc' => $servicepro_desc ? $servicepro_desc : '',
//			// 'fault_id' => $post['fault_id'],
//			// 'fault_desc' => $fault_desc ? $fault_desc : '',
//			'servicefault' => $fault_label_ids,
//			'servicefault_desc' => $fault_labels ? arrFieldForStr($fault_labels, 'label_name') : '',
//			'servicebrand' => $data['product_brand'],
//			'servicebrand_desc' => $servicebrand_desc ? $servicebrand_desc : '',
//			'stantard' => $data['product_guige'],
//			'stantard_desc' => $stantard_desc ? $stantard_desc : '',
//			'product_id' => $data['product_id'],
//			'description' => $post['desc'],
//			'model' => $data['product_xinghao'],
//			'nums' => 1,
//			'code' => $data['excel_info']['code'],
//			'buy_date' => $data['excel_info']['active_time'],
//			'report_imgs' => htmlEntityDecode($post['images']),
//		];
//
//		$wod_id = BaseModel::getInstance('worker_order_detail')->insert($wod);
//		$wod['id'] = $wod_id;
//
//		$wuo = [
//			'wx_user_id' => $data['user_info']['id'],
//			'order_id'   => $order_id,
//		];
//		$wuo_id = BaseModel::getInstance('wx_user_order')->insert($wuo);
//		$wuo['id'] = $wuo_id;
//
//		$cmdata = BaseModel::getInstance('cm_list_item')->getOne([
//                'where' => [
//                    'list_item_id' => $servicetype
//                ],
//            ]);
//
//		$this->wxUserOrderRecord('wxAddWorkerOrder', [
//				'factory_id' => $data['factory_id'],
//				'order_id' => $order_id,
//				'servicebrand_desc' => $servicebrand_desc,
//				'stantard_desc' => $stantard_desc,
//				'servicepro_desc' => $servicepro_desc,
//				'product_xinghao' => $data['product_xinghao'],
//				'servicetype_desc' => $cmdata['item_desc'] ? '<br />工单类型 :'.$cmdata['item_desc'] : '',
//			]);
//
//		$model->commit();
//		return [$add, $wod, $wuo];
	}

	/**
	 * @User zjz
	 * 用户取消工单
	 */
	public function cancelOrderOrFail($order_id = 0, $desc = '')
	{
//		if (in_array(AuthService::getModel(), ['wxuser', 'factory', 'factory_admin'])) {
			$this->cancelOrderForFyOrFail($order_id, $desc);
//		}
	}

	/**
	 * @User zjz
	 * 厂家/微信用户取消工单
	 */
	public function cancelOrderForFyOrFail($order_id = 0, $desc = '')
	{
		$wx_user_id = AuthService::getAuthModel()->getPrimaryValue();
		if (!$wx_user_id) {
			$this->throwException(ErrorCode::SYS_USER_VERIFY_FAIL);
		}

		$worker_order_model = BaseModel::getInstance('worker_order');
		$order = $worker_order_model->getOneOrFail($order_id, 'id,cancel_status,worker_order_status,factory_id,worker_id');
		$order_user = BaseModel::getInstance('worker_order_user_info')->getOne($order_id, 'wx_user_id');
		if ($order_user['wx_user_id'] != $wx_user_id) {
		    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '您无权限操作');
        }

        if ($order['worker_order_status'] == OrderService::STATUS_FACTORY_SELF_PROCESSED) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已自行处理工单无法取消');
        }
		if ($order['worker_order_status'] >= OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE) {
		    $this->throwException(ErrorCode::CANCEL_WRONG_IS_RECEIVE, '技工已预约无法取消工单');
        }
        if ($order['cancel_status'] != 0) {
		    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消,无需重复取消');
        }

        $worker_order_model->startTrans();
        OrderSettlementService::unfreezeOrderMoneyAndOtherOrder($order_id, $order['factory_id']);
        BaseModel::getInstance('worker_order')->update($order_id, [
            'cancel_status' => OrderService::CANCEL_TYPE_WX_USER,
            'canceler_id' => $wx_user_id,
            'cancel_time' => NOW_TIME,
        ]);


		//清空该工单的信誉记录
		$del_cond = [
			'worker_id' => $order['worker_id'],
			'worker_order_id' => $order_id,
			'is_return' => 0,
		];
        BaseModel::getInstance('worker_order_reputation')->remove($del_cond);

        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WX_USER_CANCEL_ORDER);


//		$msg_data = [
//			'href' => 2,
//			'type' => 'B10',
//			'msg' => '【工单消息】工单'.$data['orno'].'已经被厂家取消，请注意',
//		];
//		$ore_data = [
//			'factory_id' => $data['factory_id'],
//			'order_id' => $data['order_id'],
//			'desc' => $desc,
//		];

//        $ore_data['ope_user_name'] = '微信用户';
//        $msg_data['msg'] = '【工单消息】工单'.$data['orno'].'已经被微信用户，请注意';

//		// 推送消息给客服端
//		$this->orderSendMessage($data['order_id'], $msg_data, $data);
//		// 记录操作记录
//		$this->wxUserOrderRecord('cancelWorkerOrderForFy', $ore_data);

		// 触发取消工单事件
        event(new OrderCancelEvent(['order_id' => $order_id]));

        $worker_order_model->commit();
	}

	/**
	 * @User zjz
	 * 添加消息到轮询表 (客服后台)
	 *	href
	 *	0 配件单  ：/acceOrder/acceOrder_operate/212
	 *	1 费用单  ：/costOrder/costOrder_operate/114
	 *	2 工单  ：/order/edit_order/406
	 *	3 工单投诉  ：/order/opeComplaint/406
	 *	4 工单留言 ：/order/opeMessage/77
	 */
	public function orderSendMessage($order_id = 0, $data = [], $order_info =[])
	{
		$data['type']  = $data['type'] === null ? '' : $data['type'];
		if (!$order_info['order_id']) {
			$order_info = D('WorkerOrder')->getOneOrFail($order_id);
		}

		//找出工单的所有受理客服
		$admins = BaseModel::getInstance('worker_order_access')->getList(['link_order_id' => $order_info['order_id']]);
		$admin_ids = arrFieldForStr($admins, 'admin_id');
		if (!$admin_ids) {
			return true;
		}

		$model = BaseModel::getInstance('worker_order_msg');
		$continue_arr = [];
		if($data['type'] == 'B8'){			//留言消息类型
			$where = [
				'type' => $data['type'],
				'no' => $order_info['orno'], 
				'admin_id' => ['in', $admin_ids],
			];
			$continue_arr = $model->getOne([
					'where' => $where,
					'index' => 'admin_id',
				]);
			
		}

		$adds = [];
		$href_arr = [
			'/acceOrder/acceOrder_operate/'.$order_info['id'],
			'/costOrder/costOrder_operate/'.$order_info['id'],
			'/order/edit_order/'.$order_info['order_id'],
			'/order/opeComplaint/'.$order_info['order_id'],
			'/order/opeMessage/'.$order_info['order_id'],
		];
		foreach($admins as $k =>$v)
		{
			if ($continue_arr[$v['admin_id']]) {
				continue;
			}
			$adds[]  = [
				'admin_id' => $v['admin_id'],
				'msg' => $data['msg'],
				'href' => $href_arr[$data['href']],
				'add_time' => NOW_TIME,
				'no' => $order_info['orno'],
				'type' => $data['type'],
			];
		}	

		$count = count($adds);		
		if ($count) {
				false === $model->insertAll($adds)
			&&  $this->throwException(ErrorCode::SYS_DB_ERROR);
		}
	}

	/**
	 * @User zjz
	 * 添加消息到轮询表 (厂家后台)
	 */
	public function orderSendFactoryMessage()
	{
		
	}

	/**
	 * @User zjz
	 *  微信用户的操作记录方法
	 */
	public function wxUserOrderRecord($type, $record_data = [])
	{
		$model = BaseModel::getInstance('worker_order_operation_record');
		$add = [];
		switch ($type) {
			case 'wxAddWorkerOrder':
				$servicebrand_desc = $record_data['servicebrand_desc'];
				$stantard_desc = $record_data['stantard_desc'];
				$servicepro_desc = $record_data['servicepro_desc'];
				$product_xinghao = $record_data['product_xinghao'];
				$product_str = $servicebrand_desc.' '.$stantard_desc.' '.$product_xinghao.' '.$servicepro_desc;
				$add = [
					'ope_user_id' => $record_data['factory_id'],
					'ope_user_name' => '微信用户',
					'order_id' => $record_data['order_id'],
					'ope_role' => 'factory',
					'operation' => '创建工单，提交厂家审核.产品信息为：'.'<br>'.$product_str.$record_data['servicetype_desc'],
					'ope_type'  => 'FH',
					'add_time'  => NOW_TIME,
				];
				break;
			
			case 'cancelWorkerOrderForFy':
				$add = [
					'ope_user_id' => $record_data['factory_id'],
					'ope_user_name' => $record_data['ope_user_name'],
					'order_id' => $record_data['order_id'],
					'desc'    => $record_data['desc'],
					'ope_role' => 'factory',
					'operation' => '取消工单',
					'ope_type'  => 'FY',
					'add_time'  => NOW_TIME,
				];
				break;

			default:
				return 0;
				break;
		}
		$id = $model->insert($add);
		$add['id'] = $id;
			false === $id
		&&  $this->throwException(ErrorCode::SYS_DB_ERROR);
		return $add; 
	}

    public function getOrderDetail($worker_order_id)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, 'id,orno,worker_order_type,factory_id,worker_id,distributor_id,worker_order_status,cancel_status,worker_id,distributor_id,service_type,0 is_user_pay,create_time');
        $order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne([
            'where' => ['worker_order_id' => $order['id']],
            'field' => 'is_user_pay,pay_type payment'
        ]);
        $order = array_merge($order, $order_user_info);
        $order['worker_order_product'] = BaseModel::getInstance('worker_order_product')->getList([
            'where' => [
                'worker_order_id' => $order['id'],
            ],
            'join' => [
                'LEFT JOIN factory_product ON factory_product.product_id=worker_order_product.product_id'
            ],
            'field' => 'id,product_category_id,product_standard_id,worker_order_product.product_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,user_service_request,fault_label_ids,service_request_imgs,product_thumb,cp_fault_name',
            'order' => 'id ASC',
        ]);
        $fault_label_ids = [];
        $fault_id_map = [];
        foreach ($order['worker_order_product'] as &$product) {
            $fault_label_list = array_filter(explode(',', $product['fault_label_ids']));
            $product['fault_label_list'] = [];
            foreach ($fault_label_list as $fault_label_id) {
                $fault_label_ids[] = $fault_label_id;
                $product['fault_label_list'][] = &$fault_id_map[$fault_label_id];
            }
            $product['product_thumb'] = $product['product_thumb'] ?: BaseModel::getInstance('product_category')
                    ->getFieldVal($product['product_category_id'], 'thumb');
            $product['product_thumb'] = Util::getServerFileUrl($product['product_thumb']);
            $images = [];
            $service_request_imgs = json_decode($product['service_request_imgs'], true);
            foreach ($service_request_imgs as $item) {
                $images[] = Util::getServerFileUrl($item['url']);
            }
            $product['service_request_imgs'] = $images;
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

        $order['user_appoint'] = BaseModel::getInstance('worker_order_ext_info')->getOne($worker_order_id, 'appoint_start_time,appoint_end_time');
        $order['worker_appoint_time'] = BaseModel::getInstance('worker_order_appoint_record')->getFieldVal([
            'where' => [
                'worker_order_id' => $worker_order_id,
            ],
            'order' => 'id DESC'
        ], 'appoint_time');


        $order['is_insurance'] = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? '1' : '0';
        $order['is_user_add'] = $this->isOrderAddByUser($order['worker_order_type']);

        $order['fee'] = BaseModel::getInstance('worker_order_fee')->getOne($worker_order_id, 'user_discount_out_fee,accessory_out_fee accessory_fee,worker_repair_fee_modify user_repair_fee,coupon_reduce_money');
        $order['fee']['coupon_reduce_money'] = round($order['fee']['coupon_reduce_money'] / 100, 2);
        $order['user'] = BaseModel::getInstance('worker_order_user_info')->getOne($worker_order_id, 'real_name,phone,cp_area_names,address');
        $order['worker'] = BaseModel::getInstance('worker')->getOne(['worker_id' => $order['worker_id']], 'worker_id,worker_telephone,nickname');
        $order['admin'] = BaseModel::getInstance('admin')->getOne($order['distributor_id'], 'id,tell_out');
        $order['factory'] = BaseModel::getInstance('factory')->getOne($order['factory_id'], 'factory_id,factory_full_name');

        return $order;
	}

    public function getOrderShowStatus($order_status, $is_user_pay)
    {
        switch ($order_status) {
            case OrderService::STATUS_CREATED:
            case OrderService::STATUS_FACTORY_SELF_PROCESSED:
                return '1';     // 已提交
            case OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE:
            case OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK:
                return '2';      // 工单处理中
            case OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE:
            case OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL:
            case OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE:
                return '3';     // 已核实
            case OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT:
                return '4';     // 已派单
            case OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE:
            case OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE:
            case OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE:
                return '5';     // 已预约
            case OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE:
            case OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT:
                if ($is_user_pay == 0) {
                    return '6';     // 服务完成
                } else {
                    return '7';     // 已支付
                }
            case OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE:
            case OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT:
            case OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT:
            case OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT:
            case OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT:
            case OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED:
                return '8';      // 已完结
        }
	}

    public function getOrderShowStatusDetail($worker_order_id, $show_status)
    {
        $order = BaseModel::getInstance('worker_order')->getOne($worker_order_id, 'worker_id,orno,cancel_status,cancel_time,create_time,factory_check_order_time,check_time,worker_receive_time,worker_repair_time,return_time');

        $status_detail = [];
        switch ($show_status) {
            case 8:
                $status_detail[8] = [
                    'status' => '8',
                    'time' => $order['return_time'],
                    'description' => "工单已完结，感谢您选择神州联保服务",
                ];
            case 7:
                $pay_time = BaseModel::getInstance('worker_order_user_info')->getFieldVal($worker_order_id, 'pay_time');
                $status_detail[7] = [
                    'status' => '7',
                    'time' => $pay_time,
                    'description' => "您已完成支付",
                ];
            case 6:
                $status_detail[6] = [
                    'status' => '6',
                    'time' => $order['worker_repair_time'],
                    'description' => $order['is_insurance'] ? "师傅已完成服务" : "师傅已完成服务，费用请与师傅面议，如有疑问，请联系客服：400-830-9995",
                ];
            case 5:
                $appointed_at = BaseModel::getInstance('worker_order_operation_record')->getFieldVal([
                    'where' => [
                        'worker_order_id' => $worker_order_id,
                        'operation_type' => OrderOperationRecordService::WORKER_APPOINT_SUCCESS,
                    ],
                    'order' => 'id DESC',
                ], 'create_time');
                $appointed_time = BaseModel::getInstance('worker_order_appoint_record')->getFieldVal([
                    'where' => [
                        'worker_order_id' => $worker_order_id,
                    ],
                    'order' => 'id DESC',
                ], 'appoint_time');
                $appointed_time = date('m月d日 H:i', $appointed_time);
                $status_detail[5] = [
                    'status' => '5',
                    'time' => $appointed_at,
                    'description' => "师傅已与您联系，预约上门时间为：{$appointed_time}",
                ];
            case 4:
                $worker = BaseModel::getInstance('worker')->getOne($order['worker_id'], 'nickname,worker_telephone');
                $status_detail[4] = [
                    'status' => '4',
                    'time' => $order['worker_receive_time'],
                    'description' => "维修商：{$worker['nickname']} {$worker['worker_telephone']}；师傅将会尽快与您联系",
                ];
            case 3:
                $status_detail[3] = [
                    'status' => '3',
                    'time' => $order['check_time'],
                    'description' => "客服已确认工单信息",
                ];
            case 2:
                $status_detail[2] = [
                    'status' => '2',
                    'time' => $order['factory_check_order_time'],
                    'description' => '工作人员将会尽快与您联系',
                ];
            case 1:
                $status_detail[1] = [
                    'status' => '1',
                    'time' => $order['factory_check_order_time'] ?: $order['create_time'],
                    'description' => $order['worker_order_status'] == 0 ? "工单号：{$order['orno']}，厂家将尽快处理您的订单。" : '厂家将尽快处理您的工单',
                ];
                break;
        }
        if (OrderService::isCanceledOrder($order['cancel_status'])) {
            $status_detail['0'] = [
                'status' => '0',
                'time' => $order['cancel_time'],
                'description' => "工单已取消，感谢您选择神州联保服务",
            ];
        }


        return $status_detail;
	}

    public function isOrderAddByUser($worker_order_type)
    {
        if (in_array($worker_order_type, [OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE, OrderService::ORDER_TYPE_WX_USER_OUT_INSURANCE, OrderService::ORDER_TYPE_WEIXIN_OUT_INSURANCE])) {
            return '1';
        }
        return '0';
	}

}
