<?php
/**
 * Created by Sublime Text 3.
 * User: zjz
 * Date: 2017/11/18
 * Time: 21:44
 */

namespace Common\Common\Service;

use Carbon\Carbon;
use Common\Common\ResourcePool\RedisPool;
use Flc\Dysms\Client;
use Flc\Dysms\Request\SendSms;
use Yunpian\Sdk\YunpianClient;

class SMSService
{
    const TMP_WX_USER_REGISTER_PHONE_CODE 		= 1; 		// 微信用户认证手机号码 获取code	 					zjz
    const TMP_WORKER_PHONE_REGISTER_PHONE_CODE  = 4;		// APP技工手机号码注册 获取code

    const TMP_WORKER_EDIT_PAY_PASSWORD_CODE		= 11;		// 技工忘记提现密码，申请更改提现密码 获取code				zjz
    const TMP_FACTORY_FORGET_PASSWORD			= 22;		// 厂家/技工忘记密码/										fzy
    const TMP_CREATE_FACTORY_EXCDE_APPLY		= 30;		// 厂家申请记录，发送短信通知审核人员
    const TMP_WORKER_QIYE_DISABLED				= 99;		// 企业号技工账号禁用
    const TMP_ORDER_DISTRIBUTE_NOTIFY_USER		= 110;		// 派单用户短信

    const TMP_ORDER_DISTRIBUTE_NOTIFY_WORKER	= 111;		// 派单技工短信
    const TMP_ORDER_RECYCLING_NOTIFY_WORKER	    = 112;		// 工单回收通知

    const TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WORKER      = 113;      // 技工结算(无质保金)短信                      zjz
    const TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WORKER_QUA  = 114;      // 技工结算(有质保金)短信                      zjz
    const TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WXUSER      = 115;      // 微信用户工单完结通知(无优惠)                 zjz
    const TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WXUSER_FAV  = 116;      // 微信用户工单完结通知(有优惠)                 zjz
    const TMP_ORDER_ACCESSORY_PROMPT_WORKER_SEND_BACK   = 117; //配件单需要返还的旧件在工单上传完成服务3天后，还未上传返件报告
    const TMP_WORKER_APPOINT_SUCCESS_SEND               = 118; //技工上传预约成功

    const TMP_WORKER_UPDATE_APPOINT_TIME_SEND           = 119; //技工修改预约时间推送
    const TMP_WX_USER_REGISTER_PRODUCT           = 120; // 微信用户成功登记产品信息
    const ORDER_MODIFY_FEE_SEND_WORKER           = 121; // 客服修改费用，通知技工

    const TMP_DIRECTIONAL_SEND_COUPON           = 200;     //定向发送优惠券短信
    const TMP_WX_USER_REGISTER_PRODUCT_WITH_DRAW_URL    = 202;     // 微信用户成功登记产品信息附带抽奖链接

    const TMP_ALL_FACTORY_TO_SEND                       = 1001; //系统升级，推送给全部厂家

    const TMP_ALL_WORKER_FOR_ANDROID_TO_SEND            = 1002; //系统升级，推送给最近一个月内有接单的安卓端技工

    const TMP_ALL_WORKER_FOR_IOS_TO_SEND                = 1003; //系统升级，推送给最近一个月内有接单的ios端技工

    const TMP_WORKER_CHECK_PASS   = 2000; //技工审核通过
    const TMP_WORKER_CHECK_FORBIDDEN   = 2001; // 技工审核不通过

    const TMP_AUTO_RECEIVE_WORKER_ORDER_NOBODY_RECEIVE = 3000; //自动接单-没有符合条件的工单客服接单
    const TMP_AUTO_RECEIVE_AUDITOR_NOBODY_RECEIVE = 3001; //自动接单-没有符合条件的财务客服接单

    const TEMPLATE_MAP = [
        self::TMP_WX_USER_REGISTER_PHONE_CODE => '2128970',    // 验证码${verify},您正在绑定您的手机号码。
        self::TMP_WORKER_PHONE_REGISTER_PHONE_CODE => '2136920',              // 【神州联保】验证码#verify#,您正在绑定您的手机号码成为神州联保的服务商。
        self::TMP_WORKER_EDIT_PAY_PASSWORD_CODE => '2136934',              // 【神州联保】验证码#verify#,您正在重置您的提现密码。
        self::TMP_FACTORY_FORGET_PASSWORD => '2128988',                // 验证码${verify}，您正在重置密码。
        self::TMP_CREATE_FACTORY_EXCDE_APPLY => '2135868',      // {$fdata['factory_full_name']}申请了{$excel['nums']}个二维码，标签类型为：{$str}，请及时处理。
        self::TMP_ORDER_DISTRIBUTE_NOTIFY_USER => '2107912',        // 尊敬的#username#:您好，您售后的#product#，已安排师傅“#worker#”跟进，师傅会尽快与您预约，请耐心等待。#remark#
        self::TMP_ORDER_DISTRIBUTE_NOTIFY_WORKER => '2108362',           // ${workername}您好，有一张${product}的${servicetype}新工单${type}，请及时预约用户。${remark}
        self::TMP_ORDER_RECYCLING_NOTIFY_WORKER => '2108394',           // 您的工单${orno}（${useraddress}，${product}，${servicetype}）已被客服收回，如有疑问可联系${adminname}：${adminphone}

        self::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WORKER => '2134150',   // ${name}您好，你服务的${productfullname}工单(保${inorout})，已经审核结算完成，${repairfee}元维修金已经转入您账户。现在您的可提现余额为${money}元。您可微信搜索“神州联保企业号”，关注后进入“我的钱包”即可提现"
        self::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WORKER_QUA => '2128838', // ${name}您好，你服务的${productfullname}工单(${inorout})，已经审核结算完成，${repairfee}元维修金已经转入您账户，${qualityfee}元质保金已经转入您的质保金账户。现在您的可提现余额为${money}元。
        self::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WXUSER => '2128986',
        self::TMP_ORDER_PLATFORM_AUDITED_NOTIFY_WXUSER_FAV => '2161312',
        self::TMP_ORDER_ACCESSORY_PROMPT_WORKER_SEND_BACK => '2108490', // ${workername}您好，您有配件还未返还，请及时返还才能结算费用哦，配件名称：${accessoryname}，工单信息：${orno}，${detailaddress}，${brand}${category}，${servicetype}
        self::TMP_WORKER_APPOINT_SUCCESS_SEND => '2137240', //尊敬的#user#您好，您售后的#product#，师傅与您约的上门时间为：#time#，烦请到时安排时间接待。如对我们服务有任何意见和建议，请联系客服#user_name#：#tell_out#。神州联保祝您生活愉快！
        self::TMP_WORKER_UPDATE_APPOINT_TIME_SEND => '2137246', //尊敬的#user#您好，您售后的#product#，师傅将上门时间修改为：#time#，烦请到时安排时间接待。如对我们服务有任何意见和建议，请联系客服#user_name#：#tell_out#。神州联保祝您生活愉快！
        self::TMP_WX_USER_REGISTER_PRODUCT => '2161210',    // 您已成功登记了#pro_str#的质保信息，质保期为#stime# 至 #etime#。感谢使用我们的服务，送您30元代金券，豪华电陶炉用券后只需99元，关注“神州聚惠”微信验证您的手机号即可领取，数量有限先到先得！
        self::ORDER_MODIFY_FEE_SEND_WORKER => '',   // 内容待定

        self::TMP_ALL_FACTORY_TO_SEND => 'SMS_119920072',
        self::TMP_ALL_WORKER_FOR_ANDROID_TO_SEND => 'SMS_120790041',
        self::TMP_ALL_WORKER_FOR_IOS_TO_SEND => 'SMS_120376859',

        self::TMP_WORKER_CHECK_PASS => '2137694', // 尊敬的师傅#worker_name#您好，你在神州联保注册的账号，已经通过审核.
        self::TMP_WORKER_CHECK_FORBIDDEN => '2137702', // 由于资料不符，未能通过审核，请再次登录系统完善资料

        self::TMP_DIRECTIONAL_SEND_COUPON => '2161110',  //定向发送优惠券短信
        self::TMP_WX_USER_REGISTER_PRODUCT_WITH_DRAW_URL => '2161112',  //微信用户成功登记产品信息附带抽奖链接

        self::TMP_AUTO_RECEIVE_WORKER_ORDER_NOBODY_RECEIVE => '2184880',
        self::TMP_AUTO_RECEIVE_AUDITOR_NOBODY_RECEIVE => '2184880',

    ];

    //  检查数据时需要判断时，可以忽略掉必须要未审核的数据
    const CHECK_PHONE_CODE_NOT_MUST_VERIFIED = [
    	self::TMP_WX_USER_REGISTER_PHONE_CODE,
    	self::TMP_WORKER_EDIT_PAY_PASSWORD_CODE,
    ];

    // 需要在阿里大鱼发送的短信列表
    const ALIYUN_SMS_LIST = [
//        self::TMP_WX_USER_REGISTER_PHONE_CODE,
//        self::TMP_FACTORY_FORGET_PASSWORD,
          self::TMP_ALL_FACTORY_TO_SEND,
          self::TMP_ALL_WORKER_FOR_ANDROID_TO_SEND,
          self::TMP_ALL_WORKER_FOR_IOS_TO_SEND
    ];

    // 营销短信列表
    const MARKETING_SMS_LIST = [
        self::TMP_DIRECTIONAL_SEND_COUPON,
        self::TMP_WX_USER_REGISTER_PRODUCT_WITH_DRAW_URL

    ];

    const VERIFY_SMS_LIST = [
        self::TMP_WORKER_PHONE_REGISTER_PHONE_CODE,
        self::TMP_WORKER_EDIT_PAY_PASSWORD_CODE,
        self::TMP_WX_USER_REGISTER_PHONE_CODE,
        self::TMP_FACTORY_FORGET_PASSWORD,
    ];

    public static function sendSms($phone, $template_sn, $template_params, $client_ip)
    {

        if (in_array($template_sn, self::VERIFY_SMS_LIST) && self::checkSmsFrequency($phone, $client_ip)) {
            return ;
        }
        if (in_array($template_sn, self::ALIYUN_SMS_LIST)) {
            $config = C('ALIDAYU');
            $client  = new Client($config);
            $sendSms = new SendSms();
            $sendSms->setPhoneNumbers($phone);
            $sendSms->setSignName(C('ALIDAYU.sign'));
            $sendSms->setTemplateCode(self::TEMPLATE_MAP[$template_sn]);
            $sendSms->setTemplateParam($template_params);

            $result = $client->execute($sendSms);
            if ($result->Code != 'OK') {
                throw new \Exception("短信发送失败:{$result->Code}-{$result->Message}");
            }
        } else {
            $config = C('YUNPIAN.apikey');
            if (in_array($template_sn, self::MARKETING_SMS_LIST)) {
                $config = C('YUNPIAN.marketing_apikey');
            }
            $clnt = YunpianClient::create($config);

            $params = [];
            foreach ($template_params as $key => $template_param) {
                $params[] = '#' . urlencode($key) . '#' . '=' . urlencode($template_param);
            }
            $tpl_value = implode('&', $params);

            $param = [
                YunpianClient::MOBILE => $phone,
                YunpianClient::TPL_ID => self::TEMPLATE_MAP[$template_sn],
                YunpianClient::TPL_VALUE => $tpl_value,
            ];
            $r = $clnt->sms()->tpl_single_send($param);
        }
    }

    /**
     * 检查短信发送频率
     * @param $phone
     * @param $client_ip
     * @return bool
     */
    protected static function checkSmsFrequency($phone, $client_ip)
    {
        $phone_key = 'send_sms_check_phone_' . $phone;
        $ip_key = 'send_sms_check_ip_' . $client_ip;
        $sms_phone_times = intval(RedisPool::getInstance()->get($phone_key));
        $sms_ip_times = intval(RedisPool::getInstance()->get($ip_key));

        if ($sms_phone_times >= 3 || $sms_ip_times >= 3) {
            $is_check = true;
        } else {
            $is_check = false;
        }

        if (!$is_check) {
            $end_of_day = Carbon::now()->endOfDay()->timestamp - time();
            RedisPool::getInstance()->setex($phone_key, $end_of_day, ++$sms_phone_times);
            RedisPool::getInstance()->setex($ip_key, $end_of_day, ++$sms_ip_times);
        }

        return $is_check;
    }

}