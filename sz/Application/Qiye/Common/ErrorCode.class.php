<?php

namespace Qiye\Common;


class ErrorCode extends \Common\Common\ErrorCode
{

    /******************** 用户登录 ***********************/

    const WORKER_LOGIN_DATA_NOT_EXIST         = -1000001;
    const WORKER_LOGIN_DATA_AUTH_FAIL         = -1000002;
    const WORKER_LOGIN_DATA_STOP              = -1000003;
    const WORKER_LOGIN_DATA_NOT_PASS          = -1000004;
    const WORKER_LOGIN_DATA_WAIT_PASS         = -1000005;
    const WORKER_VERIFY_CODE_PHONE_WRONG      = -1000006;
    const WORKER_VERIFY_CODE_REGISTERED       = -1000007;
    const WORKER_VERIFY_CODE_UNREGISTERED     = -1000008;
    const WORKER_VERIFY_CODE_MULTI_REQUEST    = -1000009;
    const WORKER_VERIFY_NOT_PASS              = -1000010;
    const WORKER_FORGET_NEW_CONFIRM_NOT_MATCH = -1000011;
    const WORKER_PASSWORD_NOT_SAFE            = -1000012;
    const WORKER_FILE_UPLOAD_FAIL             = -1000013;
    /******************** 用户登录END ***********************/
    // ==============================技工钱包模块 start====================================
    const CREDIT_CARD_NOT_EMPTY           = -1000014;                 // 银行卡号不能为空
    const CREDIT_CARD_GS_IS_WRONG         = -1000015;                 // 银行卡号格式出错
    const BANK_INFO_NOT_EMPTY             = -1000016;                 // 请选择开户行
    const BANK_CITY_NOT_EMPTY             = -1000017;                 // 开户行所在城市不能为空
    const BANK_CITY_IS_WRONG              = -1000018;                 // 请检查开户行所在城市
    const OTHER_BANK_NAME_NOT_EMPTY       = -1000019;                 // 开户银行名称不能为空
    const OLD_PASSWORD_NOT_EMPTY          = -1000020;                 // 旧密码不能为空
    const PAY_PASSWORD_NOT_EMPTY          = -1000021;                 // 提现密码不能为空
    const WORKER_NOT_SET_PAY_PASSWORD     = -1000022;                 // 技工未设置提现密码
    const PAY_PASSWORD_TODAY_ERROR_IS_SEX = -1000023;                 // 提现密码错误，今天已达上限，请明天再试
    const OLD_PASSWORD_IS_WRONG           = -1000024;                 // 旧密码错误
    const PAY_PASSWORD_IS_WRONG           = -1000025;                 // 提现密码错误
    const PAY_PASSWORD_IS_AGINS           = -1000026;                 // 新密码与旧密码不能重复，请重新设置
    const YOU_HAD_PAY_PASSWORD            = -1000027;                 // 你已设置了提现密码
    const YOU_NOT_HAD_PAY_PASSWORD        = -1000028;                 // 你尚未设置提现密码
    const MONEY_NOT_XU_ZERO               = -1000029;                 // 金额必须大于零
    const NOT_SUFFICIENT_FUNDS            = -1000030;                 // 余额不足 // MONEY_IS_NOT_FULL
    const BANK_CARD_INFO_IS_EMPTY         = -1000031;                 // 银行卡信息未完善，请完善银行卡信息
    const PAY_PASSWORD_GS_IS_WORNG        = -1000032;                 // 提现密码格式错误
    const LOGIN_PHONE_NEQ_EDIT_PHONE      = -1000033;                 // 登陆账号与修改账号不一致
    const WORKER_VERIFY_CODE_IS_WRONG     = -1000034;                 // 验证码错误
    const CODE_IS_WRONG_OR_ED             = -1000035;                  // 验证码输入有误或已过期，请重新输入
    const ERROR_NOT_APPLY_AGEN            = -1000036;                 // 请勿重复申请
    const ORDER_IS_INSURANCE_NOT_USER_PAY     = -1000037;                  // 改工单是保内单不支持支付
    const PAY_TYPE_IS_NOT_WX_PAY              = -1000038;                  // 非微信支付
    const ORDER_OUT_WARRANTY_PAY_TYPE_NOT_FIT = -1000039;                  // 保外单支付方式不一致

    // ==============================技工钱包模块 end====================================
    const NOW_AUTH_IS_NOT_ADMIN                 = -2000001;                 // 当前登陆用户不是技工
    const ORDER_IS_NOT_GROUP                    = -2000002;                 // 当前订单不是群组订单
    const WORKERID_IS_NOT_ORDERID_GROUP         = -2000003;                 // 该技工不是订单的接单群主
    const ORDER_STATUS_IS_NOT_OPERATION         = -2000004;                 // 当前订单状态不允许该操作

    public static $customMessage
        = [
            self::WORKER_LOGIN_DATA_NOT_EXIST => '手机号码不存在',
            self::WORKER_LOGIN_DATA_AUTH_FAIL => '手机号码或密码错误',
            self::WORKER_LOGIN_DATA_STOP      => '您的帐户已被停用，如有疑问请联系客服专员',
            self::WORKER_LOGIN_DATA_NOT_PASS  => '您的帐户审核未通过，请重新完善帐户信息后再次提交',
            self::WORKER_LOGIN_DATA_WAIT_PASS => '您的帐户正在审核中，如有疑问请联系客服',

            self::WORKER_VERIFY_CODE_PHONE_WRONG   => '手机号码填写错误，请重新输入',
            self::WORKER_VERIFY_CODE_REGISTERED    => '该手机号码已经注册神州联保',
            self::WORKER_VERIFY_CODE_UNREGISTERED  => '该手机号码还未在神州联保注册',
            self::WORKER_VERIFY_CODE_MULTI_REQUEST => '您的验证码接收次数已超过3次，请明天再试',

            self::WORKER_VERIFY_NOT_PASS              => '手机号码或验证码错误',
            self::WORKER_FORGET_NEW_CONFIRM_NOT_MATCH => '您两次输入的密码不一致，请重新核对输入',
            self::WORKER_PASSWORD_NOT_SAFE            => '为了您的账号安全，密码的长度建议至少包含6个字符',

            self::WORKER_FILE_UPLOAD_FAIL             => '上传文件失败',
            // ==============================技工钱包模块 start====================================
            self::CREDIT_CARD_NOT_EMPTY               => '银行卡号不能为空',
            self::CREDIT_CARD_GS_IS_WRONG             => '银行卡号格式出错',
            self::BANK_INFO_NOT_EMPTY                 => '请选择开户行',
            self::BANK_CITY_NOT_EMPTY                 => '开户行所在城市不能为空',
            self::BANK_CITY_IS_WRONG                  => '请检查开户行所在城市',
            self::OTHER_BANK_NAME_NOT_EMPTY           => '开户银行名称不能为空',
            self::OLD_PASSWORD_NOT_EMPTY              => '旧密码不能为空',
            self::PAY_PASSWORD_NOT_EMPTY              => '提现密码不能为空',
            self::WORKER_NOT_SET_PAY_PASSWORD         => '技工未设置提现密码',
            self::PAY_PASSWORD_TODAY_ERROR_IS_SEX     => '提现密码错误，今天已达上限，请明天再试',
            self::OLD_PASSWORD_IS_WRONG               => '旧密码错误',
            self::PAY_PASSWORD_IS_WRONG               => '提现密码错误',
            self::PAY_PASSWORD_IS_AGINS               => '新密码与旧密码不能重复，请重新设置',
            self::YOU_HAD_PAY_PASSWORD                => '你已设置了提现密码',
            self::MONEY_NOT_XU_ZERO                   => '金额必须大于零',
            self::NOT_SUFFICIENT_FUNDS                => '余额不足',
            self::BANK_CARD_INFO_IS_EMPTY             => '银行卡信息未完善，请完善银行卡信息',
            self::PAY_PASSWORD_GS_IS_WORNG            => '提现密码格式错误',
            self::YOU_NOT_HAD_PAY_PASSWORD            => '你尚未设置提现密码',
            self::LOGIN_PHONE_NEQ_EDIT_PHONE          => '登陆账号与修改账号不一致',
            self::WORKER_VERIFY_CODE_IS_WRONG         => '验证码错误',
            self::CODE_IS_WRONG_OR_ED                 => '验证码输入有误或已过期，请重新输入',
            self::ERROR_NOT_APPLY_AGEN                => '请勿重复申请',
            self::ORDER_IS_INSURANCE_NOT_USER_PAY     => '改工单是保内单不支持支付',
            self::PAY_TYPE_IS_NOT_WX_PAY              => '非微信支付',
            self::ORDER_OUT_WARRANTY_PAY_TYPE_NOT_FIT => '请联系客服处理',

            // ==============================技工钱包模块 end====================================
            self::NOW_AUTH_IS_NOT_ADMIN             => '当前登陆用户不是技工',
            self::ORDER_IS_NOT_GROUP                => '当前订单不是群组订单',
            self::WORKERID_IS_NOT_ORDERID_GROUP     => '该技工不是订单的接单群主',
            self::ORDER_STATUS_IS_NOT_OPERATION     => '当前订单状态不允许该操作',
        ];

}

