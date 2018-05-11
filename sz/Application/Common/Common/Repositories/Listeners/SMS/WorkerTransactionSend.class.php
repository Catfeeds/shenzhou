<?php
/**
* 
*/
namespace Common\Common\Repositories\Listeners\SMS;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\WorkerTransactionEvent;
use Common\Common\Service\SMSService;
use Common\Common\Service\OrderService;

class WorkerTransactionSend implements ListenerInterface
{

	/**
     * @param WorkerTransactionEvent $event
     * 交易消息事件 （工单由神州财务提交给厂家财务审核 触发 发送短信）
     */
	public function handle(EventAbstract $event)
	{
		$worker_order   = $event->db_worker_order;
		$order_product  = $event->db_worker_order_product;
		$worker         = $event->db_worker_info;
		$order_fee 		= $event->db_order_fee;
		$user_info 		= $event->db_order_user_info;
        $repair_money_info = $event->db_repair_money_record;
		$admin_info		= $event->getDbAdmin();


		$worker_sn = SMSService::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WORKER;
		$wxuser_sn = SMSService::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WXUSER_FAV;

		$productfullname = $order_product['cp_product_brand_name'].$order_product['cp_category_name'];
		$worker_params = [
			'name' 				=> $worker['nickname'],	// 技工名称
			'productfullname' 	=> $productfullname,  // 联想液晶电视（品牌+产品类别）上门维修（工单服务类型）
			'inorout'			=> in_array($worker_order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? '保内' : '保外',  // 保内外单
			'repairfee'			=> $repair_money_info['netreceipts_money'],  // 维修费
			'money'				=> $worker['money'],  // 技工余额
		];


		// user_info
		$contactfullinfo = $admin_info['user_name'].'：'.$admin_info['tell_out'];
		$wxuser_params = [
			'name' 				=> $user_info['real_name'], // 用户名称
			'productfullname' 	=> $productfullname, // 联想液晶电视（品牌+产品类别）
			'reorin'			=> OrderService::SERVICE_TYPE_SHORT_NAME_FOR_APP[$worker_order['service_type']], // （维修/安装）
			'contactfullinfo'	=> $contactfullinfo, // 客服名称:客服电话
		];

		// 工单质保金只可能： 等于 或者 大于 0
		if ($repair_money_info['quality_money'] > 0) {
			$worker_params['qualityfee'] = $repair_money_info['quality_money'];
			$worker_sn = SMSService::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WORKER_QUA;
		}
		// 注：如果该工单不需要缴纳质保金，则不需要显示质保金的段落;
		// $sms_worker_content = "赵小赵（师傅名称）您好，您服务的联想液晶电视（品牌+产品类别）上门维修（工单服务类型）工单（保外（工单为保外才标识）），已经审核结算完成，95.00元维修金已经转入您账户，5.00元质保金已经转入您的质保金账户。现在您的可提现余额为123.00元。您可微信搜索“神州联保企业号”，关注后进入“我的钱包”即可提现";
		$worker['worker_telephone'] && sendSms($worker['worker_telephone'], $worker_sn, $worker_params);
		// $sms_wx_user_content = "尊敬的赵小姐:（用户名称）您好，您的联想液晶电视（品牌+产品类别）已经完成XX服务（维修/安装），如对我们服务有任何意见和建议，请联系客服123：020-8208208820。（工单的当前工单客服对外昵称：客服的工作座机）为感谢您使用我们的服务，特赠您30元代金券，豪华电陶炉用券后只需99元，关注“神州聚惠”微信验证您的手机号即可领取，数量有限先到先得！";
		$user_info['phone'] && sendSms($user_info['phone'], $wxuser_sn, $wxuser_params);
	}


}
