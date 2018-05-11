<?php
/**
 * File: FactoryMoneyRecordService.class.php
 * User: zjz
 * Date: 2017/12/04
 */

namespace Common\Common\Service;

use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Common\Common\Service\PayService;
use Common\Common\Service\PayPlatformRecordService;
use Common\Common\Service\AuthService;
use Common\Common\Service\SystemMessageService;

class FactoryMoneyRecordService
{
    const FACTORY_MONEY_CHANGE_TABLE_NAME   = 'factory_money_change_record';
    const FACTORY_TABLE_NAME                = 'factory';
    const ORDER_TABLE_NAME                  = 'worker_order';

	// 变动类型：1 （厂家）银联在线支付；2 （厂家）支付宝支付；3 （厂家）微信支付； 4 （系统）工单结算资金变动；5 （客服）手动调整; 6 （客服）工单费用调整；7 （客服）其他;
    const CHANGE_TYPE_UNIONPAY 		= 1;
    const CHANGE_TYPE_ALIPAY 		= 2;
    const CHANGE_TYPE_WECHATPAY 	= 3;
    const CHANGE_TYPE_WORKER_ORDER 	= 4;
    const CHANGE_TYPE_ADJUST        = 5;
    const CHANGE_TYPE_WORKER_ORDER_ADJUST = 6;
    const CHANGE_TYPE_OTHER 	    = 7;

    const CHANGE_TYPE_ALL = [
        self::CHANGE_TYPE_UNIONPAY,
        self::CHANGE_TYPE_ALIPAY,
        self::CHANGE_TYPE_WECHATPAY,
        self::CHANGE_TYPE_WORKER_ORDER,
        self::CHANGE_TYPE_ADJUST,
        self::CHANGE_TYPE_WORKER_ORDER_ADJUST,
        self::CHANGE_TYPE_OTHER,
    ];

    // 属于充值类型的变动
    const CHANGE_TYPE_FOR_RECHARGE_ARR = [
		self::CHANGE_TYPE_UNIONPAY,
        self::CHANGE_TYPE_ALIPAY,
        self::CHANGE_TYPE_WECHATPAY,
        self::CHANGE_TYPE_ADJUST,
        self::CHANGE_TYPE_WORKER_ORDER_ADJUST,
        self::CHANGE_TYPE_OTHER,
    ];

    // 状态
    const STATUS_VALUE_CREATE   = '0';      // 未支付
    const STATUS_VALUE_SUCCESS  = '1';      // 支付成功
    const STATUS_VALUE_FAIL     = '2';      // 支付失败
    // 处理后的支付平台结果的支付状态对应的操作状态
    const PAYSERVICE_STATUS_VALUE = [
        PayService::PAY_STATUS_NOT_PAY  => self::STATUS_VALUE_CREATE,
        PayService::PAY_STATUS_SUCCESS  => self::STATUS_VALUE_SUCCESS,
        PayService::PAY_STATUS_FAIL     => self::STATUS_VALUE_FAIL,
        PayService::PAY_STATUS_OTHER    => self::STATUS_VALUE_CREATE,
    ];

    const USER_TYPE_ADMIN           = '1';  // 1 平台客服
    const USER_TYPE_FACTORY         = '2';  // 2 厂家客服
    const USER_TYPE_FACTORY_ADMIN   = '3';  // 3 厂家子账号
    const USER_TYPE_WORKER          = '4';  // 4 技工
    const USER_TYPE_WEUSER          = '5';  // 5 微信用户(普通用户)
    const USER_TYPE_WEDEALER        = '6';  // 6 微信用户(经销商)
    const USER_TYPE_USER_CHANGE_TYPE = [
        self::USER_TYPE_ADMIN => [
            self::CHANGE_TYPE_ADJUST,
            self::CHANGE_TYPE_WORKER_ORDER_ADJUST,
            self::CHANGE_TYPE_OTHER,
        ],
    ];
    const AUTH_MODEL_USER_USER_TYPE = [
        AuthService::ROLE_WX_USER       => self::USER_TYPE_WEUSER,
        AuthService::ROLE_WORKER        => self::USER_TYPE_WORKER,
        AuthService::ROLE_FACTORY       => self::USER_TYPE_FACTORY,
        AuthService::ROLE_FACTORY_ADMIN => self::USER_TYPE_FACTORY_ADMIN,
        AuthService::ROLE_ADMIN         => self::USER_TYPE_ADMIN,
    ];

    public static function getAuthChangeType()
    {
        $type = self::AUTH_MODEL_USER_USER_TYPE[AuthService::getModel()];
        return (array)self::USER_TYPE_USER_CHANGE_TYPE[$type];
    }

    // 平台调整
    public static function platformCreate($fid, $amount, $change_type, $orno = '', $extends = [])
    {
        if (!in_array($change_type, self::getAuthChangeType())) {
            throw new \Exception("", ErrorCode::SYS_NOT_POWER);
        } 

        if (self::CHANGE_TYPE_WORKER_ORDER_ADJUST == $change_type && !BaseModel::getInstance(self::ORDER_TABLE_NAME)->getOne(['orno' => $orno], 'orno')) {
            throw new \Exception("工单号不存在", ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        
        $f_model = BaseModel::getInstance(self::FACTORY_TABLE_NAME);
        $factory = $f_model->getOneOrFail($fid);

        $model = BaseModel::getInstance(self::FACTORY_MONEY_CHANGE_TABLE_NAME);
        
        $last_money = $factory['money'] + $amount;
        
        $data_id = $model->insert([
            'factory_id'    => $fid,
            'operator_id'   => AuthService::getAuthModel()->getPrimaryValue(),
            'operator_type' => self::USER_TYPE_ADMIN,
            'operation_remark'  => $extends['remark'],
            'change_type'       => $change_type,
            'out_trade_number'  => $orno,
            'money'             => $factory['money'],
            'change_money'      => $amount,
            'last_money'        => $last_money,
            'status'            => self::STATUS_VALUE_SUCCESS,
            'create_time'       => NOW_TIME,
        ]);

        $f_model->update($fid, ['money' => $last_money]);

        $msg_content = "你的维修金已成功充值 {$amount} 元";
        SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $fid, $msg_content, $data_id, SystemMessageService::MSG_TYPE_FACTORY_RECHARGE_SUCCESS);
        return $data_id;
    }

    // 易联支付
    public static function yilianCreate($fid, $change_type, $pay_status)
    {
        $f_model = BaseModel::getInstance(self::FACTORY_TABLE_NAME);
        $factory = $f_model->getOneOrFail($fid);

        $paylog = PayPlatformRecordService::$paylog;
        $model = BaseModel::getInstance(self::FACTORY_MONEY_CHANGE_TABLE_NAME);
        $data = $model->getOne(['out_trade_number' => $paylog['out_order_no']]);
        if ($data) {
            return $data['id'];
        }
        $amount  = $paylog['money'];
        $last_money = $factory['money'] + $amount;
        
        $data_id = $model->insert([
            'factory_id'    => $fid,
            'operator_id'   => $paylog['user_id'],
            'operator_type' => $paylog['user_type'],
            'operation_remark'  => $paylog['remark'],
            'change_type'       => $change_type,
            'money'             => $factory['money'],
            'change_money'      => $amount,
            'last_money'        => $last_money,
            'out_trade_number'  => $paylog['out_order_no'],
            'status'            => self::PAYSERVICE_STATUS_VALUE[$pay_status],
            'create_time'       => NOW_TIME,
        ]);

        $f_model->update($fid, ['money' => $last_money]);

        $msg_content = "你的维修金已成功充值 {$amount} 元";
        SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $fid, $msg_content, $data_id, SystemMessageService::MSG_TYPE_FACTORY_RECHARGE_SUCCESS);
        return $data_id;
    }

}
