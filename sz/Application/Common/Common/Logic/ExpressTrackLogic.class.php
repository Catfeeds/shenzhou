<?php
/**
* @User zjz
* @Date 2017/04/21
* 快递100 付费版 接口
*/
namespace Common\Common\Logic;

use Common\Common\ErrorCode;
use Library\Common\Util;
use Common\Common\Model\BaseModel;
use Common\Common\Logic\Sms\SmsServerLogic;
use Common\Common\Service\AuthService;
use Qiye\Repositories\Events\ExpressTrackWsoEvent;

class ExpressTrackLogic extends \Common\Common\Logic\BaseLogic
{
	// 快递100 提供的配置
	const CONFIG_KEY 		= 'wVXlZZVl1983';
	const CONFIG_CUSTOMER 	= '11310083EE095BABC24AC220AA406274';

	const URL_POLL 				= 'http://www.kuaidi100.com/poll'; // 订阅
	const URL_POLL_QUERY_DO 	= 'http://poll.kuaidi100.com/poll/query.do'; // 物流实时查询
	const URL_AUTONUMBER_AUTO 	= 'http://www.kuaidi100.com/autonumber/auto'; // 

	// 配件单：发件SO，返件：SB。预安装单：发件WSO
	public $express_types = ['SO', 'SB', 'WSO'];
	// key => $category  $category > 0
	public $express_categories = [
		1 => ['SO', 'SB'],	// 配件单
		2 => ['WSO'],		// 厂家新建的预发件安装单
	];
	// 订阅的回调url
	public $express_call_back_url = [
		'SO' 	=> '/workerqy.php/express/callback',
		'SB' 	=> '/workerqy.php/express/callback',
		'WSO' 	=> '/admin.php/express/callback/WSO',
	];

	// 配件单：发件SO，返件：SB  	$category = 1;
    // 预安装单：发件WSO			$category = 2;

	// 添加快递单订阅，并保存记录表，如果已经存在，则更新记录，否则添加新记录  zjz
	public function setExpressTrack($com_code, $number, $acor_id, $type, $data_type)
	{
		if(empty($com_code) || empty($number) || !$acor_id || empty($type) || !isset($this->express_call_back_url[$type])){
			$this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
		}
		
		$exp_call_back = Util::getServerUrl().__ROOT__.$this->express_call_back_url[$type]; // 回调地址

		$category = 0;
		$count = count($this->express_categories);
		$while_i = 1;
		do {
			$express_category = $this->express_categories[$while_i];
			if (in_array($type, $express_category)) {
				$category = $while_i;
			}
			$while_i++;
		} while ($category === 0 && $while_i <= $count);

		if ($category === 0) {
			$this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
		}

		// callbackurl 请参考 callback.php 实现，key经常会变，请与快递100联系获取最新key
		// $post_data["param"] = '{"company":"'.$com_code.'", "number":"'.$number.'","from":"", "to":"", "key":"'.self::CONFIG_KEY.'", "parameters":{"callbackurl":"'.$exp_call_back.'"}}';
		$post_data_param_arr = [
			'company' => $com_code,
			'number' => $number,
			'from' => '',
			'to' => '',
			'key' => self::CONFIG_KEY,
			'parameters' => [
				'callbackurl' => $exp_call_back
			]
		];
		$post_data = [
			'schema' => 'json',
			'param'	 => json_encode($post_data_param_arr),
		];

		//默认UTF-8 编码格式
		// foreach ($post_data as $k => $v) {
		// 	$post_data[$k] = urlencode($v);
		// }
	
		$post_data_str = http_build_query($post_data);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL, self::URL_POLL);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		$result = curl_exec($ch);		//返回提交结果，格式与指定的格式一致（result=true代表成功）
		$result = json_decode($result, true);
		
		//当前物流信息
		$expres_info  = $this->queryOrder($com_code, $number);
		
		if ($expres_info['returnCode'] && $expres_info['message']) {
			// $this->throwException(-400, $expres_info['message']);
		}
		
		$data_track = [
			'number' 	=> $number,
			'comcode' 	=> $com_code,
			'state' 	=> $expres_info['state'] >= -1 ? $expres_info['state'] : -1,
			'content' 	=> json_encode($expres_info['data'] ? $expres_info['data'] : []),
			'is_book'	=> $result['returnCode']=='200' ? 1 : 0,
		];
		
		$cond = [
			'express_number' => $number,
			'express_code' 	 => $com_code,
			'data_id' 		 => $acor_id,
			'type' 		     => $data_type,
		];
		
		$model = BaseModel::getInstance('express_tracking');
		$info = $model->getOne($cond);

		// 一旦签到 则不再更新 更新 最后更新时间
		if ($info['state'] != 3) {
			$data_track['last_update_time'] = NOW_TIME;
		}
		
		if($info){
			// $data_track['last_uptime'] 	= NOW_TIME;
			$model->update($info['id'], $data_track);
		}else{
			$data_track['data_id'] 		= $acor_id;
			$data_track['type'] 		= $data_type;
			//$data_track['category'] 	= $category;
			$data_track['create_time'] 		= NOW_TIME;
			// $data_track['last_uptime'] 	= NOW_TIME;

			$res = $model->insert($data_track);
			$data_track['id'] = $res;
		}

		// if ($type == 'WSO' && $expres_info['state'] == 3 && $data_track['id'] && $info['is_book'] == 0 && $data_track['is_book'] == 1) {
		if ($info['state'] != $data_track['state'] && $data_track['state'] == 3) {
			$acceOrder = BaseModel::getInstance('worker_order_apply_accessory')->getOne($info['data_id']);
			if (in_array($type, $this->express_categories[1])) {
				$msg_data = [
		        	'href' => 2,
		        	'msg' =>'【配件签收】工单号：'.$acceOrder['worker_order_orno'].'配件已签收，请注意',
	        	];
	        	try {
					(new \Api\Logic\OrderLogic())->orderSendMessage($acceOrder['worker_order_id'], $msg_data);
				} catch (\Exception $e) {

				}
			} elseif ($type == 'WSO') {
				$order = BaseModel::getInstance('worker_order')->getOne($info['acor_id']);
				$msg_data = [
		        	'href' => 2,
		        	'msg' =>'【已签收】预发件工单:'.$order['orno'].'，客户已签收',
	        	];

				$send_mess = [
					'state' 	=> 3,
					'order_id'  => $order['order_id'],
				];
				try {
					(new \Api\Logic\OrderLogic())->orderSendMessage($order['order_id'], $msg_data, $order);
					if ($order['worker_id']) {
						event(new ExpressTrackWsoEvent($send_mess));
					}
				} catch (\Exception $e) {

				}
			}
		}

		return $data_track;
	}

	// 物流订阅成功之后，快递100 回调处理 zjz
	public function ruleExpressCallBack($type = '', $data = [])
	{
		$number   = $data['lastResult']['nu'];
		$comCode  = $data['lastResult']['com'];
		$state    = $data['lastResult']['state'];
		$content  = $data['lastResult']['data'];
		
		!in_array($type, $this->express_types) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
			
		$model = BaseModel::getInstance('express_track');
		$fao_model = BaseModel::getInstance('factory_acce_order');
		$order_model = BaseModel::getInstance('worker_order');

		$cond = [
			'number'  => $number,
			'comCode' => $comCode,
			'type'    => $type,
		];
		$opt = [
			'where' => $cond,
			'order' => 'id DESC',
		];
		$oldInfo = $model->getOne($opt);
		
		if(count($oldInfo) > 0){
			//如果配件单判断到技工已经收货，更新配件单信息
			if($type == 'SO' && $state == '3' ){
				$accOrInfo = $fao_model->getOne(['id' => $oldInfo['acor_id']]);
				$dataAc = array();
				$dataAc['id']   			=  $accOrInfo['id'];
				$dataAc['is_worker_get']    =  1;
				//先返后发
				if($accOrInfo['exe_type']=='B'){
					$dataAc['is_complete']   =  1;
					$log_data = [
						'id' => $oldInfo['acor_id'],
						'ope_user_id' => 0,
						'ope_user_name' => '系统',
						'operation' => '技工已签收快件，配件单已完结',
						'desc' => '本操作记录由物流公司反馈完成',
					];
					D('FactoryAcceOrder', 'Logic')->AcceOrderRecord('expressCallBackSO', $log_data);
					// ARE(
					// 	0,
					// 	'系统',
					// 	$oldInfo['acor_id'],
					// 	'system',
					// 	'技工已签收快件，配件单已完结',
					// 	'AA',
					// 	''
					// );
				}
				try {
					$fao_model->update($dataAc['id'], $dataAc);
					$msg_data = [
			        	'href' => 2,
			        	'msg' =>'【配件签收】工单号：'.$accOrInfo['worker_order_orno'].'配件已签收，请注意',
		        	];	
					(new \Api\Logic\OrderLogic())->orderSendMessage($accOrInfo['worker_order_id'], $msg_data);
				} catch (\Exception $e) {
					
				}
			} elseif($type == 'SB' && $state == '3'){
				//如果配件单判断到技工已返件（厂家签收），更新配件单信息
				$accOrInfo = $fao_model->getOne(['id' => $oldInfo['acor_id']]);
				$dataAc = array();
				$dataAc['id']   			   =  $accOrInfo['id'];
				$dataAc['is_fact_checkback']   =  1;
				//先发后返
				if($accOrInfo['exe_type']=='A'){
					$dataAc['is_complete']   =  1;
					$log_data = [
						'id' => $oldInfo['acor_id'],
						'ope_user_id' => 0,
						'ope_user_name' => '系统',
						'operation' => '厂家已确认返件，配件单已完结',
						'desc' => '本操作记录由物流公司反馈完成',
					];
					D('FactoryAcceOrder', 'Logic')->AcceOrderRecord('expressCallBackSB', $log_data);
					// ARE(
					// 	0,
					// 	'系统',
					// 	$oldInfo['acor_id'],
					// 	'system',
					// 	'厂家已确认返件，配件单已完结',
					// 	'AB',
					// 	'本操作记录由物流公司反馈完成'
					// );
				}
				try {
					$fao_model->update($dataAc['id'], $dataAc);
					$msg_data = [
			        	'href' => 2,
			        	'msg' =>'【配件签收】工单号：'.$accOrInfo['worker_order_orno'].'配件已签收，请注意',
		        	];	
					(new \Api\Logic\OrderLogic())->orderSendMessage($accOrInfo['worker_order_id'], $msg_data);
				} catch (\Exception $e) {
					
				}
			} elseif ($type == 'WSO' && $state == '3') {
				$order = $order_model->getOne($oldInfo['acor_id']);

				$msg_data = [
		        	'href' => 2,
		        	'msg' =>'【已签收】预发件工单:'.$order['orno'].'，客户已签收',
	        	];

				$send_mess = [
					'state' 	=> 3,
					'order_id'  => $order['order_id'],
				];
				try {
					(new \Api\Logic\OrderLogic())->orderSendMessage($order['order_id'], $msg_data, $order);
					if ($order['worker_id']) {
						event(new ExpressTrackWsoEvent($send_mess));
					}
				} catch (\Exception $e) {
					
				}
			}

			$dataExp = array();
			$dataExp['id'] 			= 	$oldInfo['id'];
			$dataExp['state'] 		= 	$state;
			$dataExp['content'] 	= 	json_encode($content);
			$dataExp['last_uptime'] = 	NOW_TIME;
			$dataExp['is_book'] 	= 	1;
				!$model->update($dataExp['id'], $dataExp)
			&&  $this->throwException(ErrorCode::SYS_DB_ERROR);
		} else {
			$this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
		}
	}

	// 查询快递单 zjz
	public function queryOrder($com_code, $number){

		$post_data_param_arr = [
			'com' => $com_code,
			'num' => $number,
		];
		$post_data = [
			'customer' 	=> self::CONFIG_CUSTOMER,
			'param' 	=> json_encode($post_data_param_arr),
		];
		// 签名
		$sign = $post_data['param'].self::CONFIG_KEY.$post_data['customer'];
		$post_data['sign'] = strtoupper(md5($sign));

		//默认UTF-8编码格式
		// foreach ($post_data as $k => $v) {
		// 	$post_data[$k]= urlencode($v);
		// }
		$post_data_str = http_build_query($post_data);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL, self::URL_POLL_QUERY_DO);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		$data = str_replace('\&quot;', '"', $result );
		$data = json_decode($data, true);
		return $data;
	}

	//通过单号获取可能的快递公司  zjz
	public function autoComCode($number = ''){
		$post_data = [
			'num' => $number,
			'key' => self::CONFIG_KEY,
		];

		//默认UTF-8编码格式
		foreach ($post_data as $k => $v){
			$post_data[$k] = urlencode($v);
		}
		$post_data_str = http_build_query($post_data);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL, self::URL_AUTONUMBER_AUTO);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);

		$data = str_replace('\&quot;', '"', $result );
		$data = json_decode($data, true);
		// $com_code_arr = arrFieldForStr($data, 'comName');
		foreach($data as $key => $val){
			$data[$key]['number']  = $number;
			$data[$key]['comCode'] = $val['comCode'];
		}
		return $data;
	}

}
