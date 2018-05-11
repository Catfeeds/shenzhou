<?php
/**
 * File: VerifyCodeLogic.class.php
 * User: sakura
 * Date: 2017/11/1
 */

namespace Qiye\Logic;

use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\SMSService;
use Common\Common\Service\WorkerService;
use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Qiye\Logic\BaseLogic;
use Qiye\Model\BaseModel;
use Library\Crypt\AuthCode;
use Common\Common\Service\AuthService;

class VerifyCodeLogic extends BaseLogic
{

    public function forgetPayPassword()
    {
        $phone = $this->getParam('phone');

        empty($phone) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入手机号码');

            AuthService::getAuthModel()->worker_telephone != $phone
        &&  $this->throwException(ErrorCode::LOGIN_PHONE_NEQ_EDIT_PHONE);

        //检查手机格式
        !Util::isPhone($phone) && $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);

        $ip = get_client_ip();
        $ip2long = ip2long($ip);

        $phone_limit_key = $phone.'-'.'-forget-pay-password';
        $phone_times = (int)S($phone_limit_key);
        $phone_times = $phone_times > 0 ? $phone_times : 0;

        $ip_limit_key = $ip2long.'-forget-pay-password';
        $ip_times = (int)S($ip_limit_key);
        $ip_times = $ip_times > 0 ? $ip_times : 0;

        $max_times = 3;
        if ((!empty($phone_times) && $phone_times >= $max_times) || (!empty($ip_times) && $ip_times >= $max_times)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_MULTI_REQUEST);
        }

        $verify_code = (string)mt_rand(111111, 999999);

        $key = $phone . '-forget-pay-password';
        S($key, $verify_code, ['expire' => 15 * 60]);

        //$str = $verify_code . '，您正在修改您的提现密码，请于15分钟内正确输入验证，如非本人操作，请忽略本短信。';

        //记录本次请求,用来限制次数
        $day_end = strtotime(date('Ymd'))+86399;
        S($phone_limit_key, $phone_times+1, ['expire' => $day_end-NOW_TIME]);
        S($ip_limit_key, $ip_times+1, ['expire' => $day_end-NOW_TIME]);

        sendSms($phone, SMSService::TMP_WORKER_EDIT_PAY_PASSWORD_CODE, [
            'verify' => $verify_code
        ]);

//        return [
//            'verify_code' => $verify_code
//        ];

    }

    public function checkForgetPayPassword($phone, $verify_code, $set_empty = false)
    {
        empty($phone) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号为空');
        empty($verify_code) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码为空');

            AuthService::getAuthModel()->worker_telephone != $phone
        &&  $this->throwException(ErrorCode::LOGIN_PHONE_NEQ_EDIT_PHONE);

        $key = $phone . '-forget-pay-password';
        $chk_verify_code = S($key);
        
        if ($verify_code != $chk_verify_code) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_IS_WRONG);
        }

        $set_empty && S($key, null);
    }

    public function register()
    {
        $phone = $this->getParam('phone');

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入手机号码');
        }

        //检查手机格式
        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);
        }

        //查找手机是否已注册
        $worker_db = BaseModel::getInstance('worker');
        $where = ['worker_telephone' => $phone];
        $user_data = $worker_db->getOne($where);
        if (!empty($user_data)) {
            $is_qianzai = $user_data['is_qianzai']; //潜在用户默认 可以继续注册流程
            if (WorkerService::IDENTIFY_POTENTIAL != $is_qianzai) {
                $this->throwException(ErrorCode::WORKER_VERIFY_CODE_REGISTERED);
            }
        }

        $code = $this->getParam('code');
        $code_id = $this->getParam('code_id');
        if (!isset($code) || empty($code)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请把版本升级到最新，填入图形验证码才可注册');
        }
        if (strtolower(S($code_id)) != strtolower($code)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '图形验证码错误，请重新确认');
        }

        $ip = get_client_ip();
        $ip2long = ip2long($ip);

        $phone_limit_key = $phone.'-'.'-user-register';
        $phone_times = (int)S($phone_limit_key);
        $phone_times = $phone_times > 0 ? $phone_times : 0;

        $ip_limit_key = $ip2long.'-user-register';
        $ip_times = (int)S($ip_limit_key);
        $ip_times = $ip_times > 0 ? $ip_times : 0;

        $max_times = 3;
        if ((!empty($phone_times) && $phone_times >= $max_times) || (!empty($ip_times) && $ip_times >= $max_times)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_MULTI_REQUEST);
        }

        $verify_code = (string)mt_rand(111111, 999999);
        //$verify_code = 111111;

        $key = $phone . '-register';
        S($key, $verify_code, ['expire' => 15 * 60]);

        //$str = $verify_code . '，您正在注册神州联保，请于15分钟内正确输入验证，如非本人操作，请忽略本短信。';

        //记录本次请求,用来限制次数
        $day_end = strtotime(date('Ymd'))+86399;
        S($phone_limit_key, $phone_times+1, ['expire' => $day_end-NOW_TIME]);
        S($ip_limit_key, $ip_times+1, ['expire' => $day_end-NOW_TIME]);

        sendSms($phone, SMSService::TMP_WORKER_PHONE_REGISTER_PHONE_CODE, [
            'verify' => $verify_code
        ]);

//        return [
//            'verify_code' => $verify_code
//        ];
    }


    public function checkRegister($phone, $verify_code)
    {
        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号为空');
        }
        if (empty($verify_code)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码为空');
        }

        $key = $phone . '-register';
        $chk_verify_code = S($key);

        if ($verify_code != $chk_verify_code) {
            $this->throwException(ErrorCode::WORKER_VERIFY_NOT_PASS, '验证码错误，请重新输入');
        }
    }

    public function checkForget($phone, $verify_code)
    {
        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号为空');
        }
        if (empty($verify_code)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码为空');
        }

        $key = $phone . '-forget';
        $chk_verify_code = S($key);
        if ($verify_code != $chk_verify_code) {
            $this->throwException(ErrorCode::WORKER_VERIFY_NOT_PASS, '验证码错误，请重新输入');
        }
    }

    public function delRegisterVerifyCode($phone)
    {
        $key = $phone . '-register';
        S($key, null);
    }

    public function delForgetVerifyCode($phone)
    {
        $key = $phone . '-forget';
        S($key, null);
    }

    public function forget()
    {
        $phone = $this->getParam('phone');

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入手机号码');
        }

        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);
        }

        //查找手机是否已注册
        $worker_db = BaseModel::getInstance('worker');
        $where = ['worker_telephone' => $phone];
        $user_data = $worker_db->getOne($where);
        if (empty($user_data)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_UNREGISTERED);
        }

        $ip = get_client_ip();
        $ip2long = ip2long($ip);

        $phone_limit_key = $phone.'-'.'-user-forget';
        $phone_times = (int)S($phone_limit_key);
        $phone_times = $phone_times > 0 ? $phone_times : 0;

        $ip_limit_key = $ip2long.'-user-forget';
        $ip_times = (int)S($ip_limit_key);
        $ip_times = $ip_times > 0 ? $ip_times : 0;

        $max_times = 3;
        if ((!empty($phone_times) && $phone_times >= $max_times) || (!empty($ip_times) && $ip_times >= $max_times)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_MULTI_REQUEST);
        }

        $verify_code = (string)mt_rand(111111, 999999);
        //$verify_code = 111111;

        $key = $phone . '-forget';
        S($key, $verify_code, ['expire' => 15 * 60]);

        //记录本次请求,用来限制次数
        $day_end = strtotime(date('Ymd'))+86399;
        S($phone_limit_key, $phone_times+1, ['expire' => $day_end-NOW_TIME]);
        S($ip_limit_key, $ip_times+1, ['expire' => $day_end-NOW_TIME]);

        //$str = $verify_code . '，您正在修改您的密码，请于15分钟内正确输入验证，如非本人操作，请忽略本短信。';

        sendSms($phone, SMSService::TMP_FACTORY_FORGET_PASSWORD, [
            'verify' => $verify_code
        ]);

//        return [
//            'verify_code' => $verify_code
//        ];
    }

}