<?php
/**
* @User zjz
* @Date 2016/12/16
* TODO 该接口还需要优化代码
*/
namespace Common\Common\Logic;
 
use Api\Common\ErrorCode;
use Library\Common\Util;
use Common\Common\Model\BaseModel;
use Common\Common\Logic\Sms\SmsServerLogic;
use Common\Common\Service\AuthService;

class SmsCodeLogic extends \Common\Common\Logic\BaseLogic
{
	
	private $sms_name = '神州联保';
	private $save_model = 'phone_code';
    private $code_type = [
        1  => 'wxUserRegisterPhoneCode', // 微信用户认证手机号码 获取code
        2  => 'wxUserPhoneLoginPhoneCode', // 微信用户手机号码登陆 获取code (先占坑，未有这样的需求)
        3  => 'workerPhoneLoginPhoneCode', // 企业号技工手机号码登陆 获取code (先占坑，未有这样的需求)
        11 => 'workerEditPayPasswordCode', // 技工忘记提现密码，申请更改提现密码 获取code
        20 => 'orderaddAppoint',            // 订单添加预约记录
        21 => 'orderUpdateAppoint',         // 订单更新预约记录
        22 => 'factoryForgetPassword',       // 忘记密码 fzy
        30 => 'createFactoryExcdeApply',    // 厂家申请记录，发送短信通知审核人员
        99 => 'workerQiYeDisabled',         // 企业号技工账号禁用
    ];
    
    public function phoneSmsForTypeRule($phone, $data = [])
    {
    	$type = $data['type'];
    	if (!$phone) {
            $this->throwException(ErrorCode::PHONE_NOT_EMPTY);
        } elseif (!Util::isPhone($phone)) {
    		$this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
    	} elseif ($this->getCodeNextMin($phone)) {
            $this->throwException(ErrorCode::GET_PLEACE_NOT_XY_60);
        } elseif (!$function = $this->code_type[$type]) {
    		$this->throwException(ErrorCode::DATA_IS_WRONG);
    	}
    	return $this->$function($phone, $data);
    }
    
    protected function getCodeNextMin($phone)
    {
        $data = BaseModel::getInstance('phone_code')->getOne([
                'phone_number' => $phone,
                'create_time' => ['GT', (NOW_TIME - 60)],
            ]);
        return $data;
    }

    protected function factoryForgetPassword($phone, $data = [])
    {
        $type = 22;
        $model = BaseModel::getInstance($this->save_model);
        // $is_expired = $data['is_expired'] ? $data['is_expired'] : 2*3600;
        $code_data = [    // 手机号验证码
            'verification_code' => (string)mt_rand(100000, 999999),
            'phone_number'      => $phone,
            'create_time'       => (string)NOW_TIME,
            'type'              => $type,
            'is_expired'        => 180, // 验证码过期时间 单位： s
        ];
        $queue_data = [
            'phone_number' => $phone,
            'content'      => '验证码'.$code_data['verification_code'].'，您正在重置你密码。',
            'create_time'  => $code_data['create_time'],
            'type'         => $type,
            'state'        => 2, // 短信发送状态，1-未处理 2-处理中 3-成功 4-失败
        ];
        $model->startTrans();

        $is_expired_where = [
            'phone_number' => $phone,
            'type' => $type,
            '_string' => 'verified_time IS NULL',
            'is_expired' => ['neq', 0],
        ];
        if (empty($phone)) {
            $this->throwException(ErrorCode::YOU_NOT_SAME_PHONE);
        } elseif (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::PHONE_GS_IS_WRONG, '手机号码格式错误');
        } if (false === $model->update($is_expired_where, ['is_expired' => 0])) {
        $this->throwException($model->getError() ? $model->getError() : ErrorCode::SYS_DB_ERROR);
    } elseif (!$id = $model->insert($code_data)) {
        $this->throwException($model->getError() ? $model->getError() : ErrorCode::SYS_DB_ERROR);
    }

        // 加入队列
        if (!$data['is_return']) {
            $add_data = [
                'table_id' => $id,
                'phone'    => $queue_data['phone_number'],
                'content'  => $queue_data['content'],
                'type'     => $queue_data['type'],
            ];
            $add_datas[] = $add_data;
            (new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
        }

        $model->commit();
        return $code_data;
    }

    protected function workerEditPayPasswordCode($phone, $data = [])
    {

            AuthService::getModel() != 'worker'
        &&  $this->throwException(ErrorCode::NOT_WORKER);
        $phone = AuthService::getAuthModel()->worker_telephone;
        $type = 11;

        $model = BaseModel::getInstance($this->save_model);
        // $is_expired = $data['is_expired'] ? $data['is_expired'] : 2*3600;
        $code_data = [    // 手机号验证码
            'verification_code' => (string)mt_rand(100000, 999999),
            'phone_number'      => $phone,
            'create_time'       => (string)NOW_TIME,
            'type'              => $type, 
            'is_expired'        => 180, // 验证码过期时间 单位： s
        ];
        $queue_data = [
            'phone_number' => $phone,
            'content'      => '验证码'.$code_data['verification_code'].'，您正在重置你的提现密码。',
            'create_time'  => $code_data['create_time'],
            'type'         => $type, 
            'state'        => 2, // 短信发送状态，1-未处理 2-处理中 3-成功 4-失败
        ];
        $model->startTrans();
        
        $is_expired_where = [
            'phone_number' => $phone,
            'type' => $type, 
            '_string' => 'verified_time IS NULL',
            'is_expired' => ['neq', 0],
        ];
        if (strlen(AuthService::getAuthModel()->pay_password) != 32) {
            $this->throwException(ErrorCode::YOU_NOT_HAD_PAY_PASSWORD);
        } elseif (empty($phone)) {
            $this->throwException(ErrorCode::YOU_NOT_SAME_PHONE);
        } elseif (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::PHONE_GS_IS_WRONG, '手机号码格式错误');
        } if (false === $model->update($is_expired_where, ['is_expired' => 0])) {
            $this->throwException($model->getError() ? $model->getError() : ErrorCode::SYS_DB_ERROR);
        } elseif (!$id = $model->insert($code_data)) {
            $this->throwException($model->getError() ? $model->getError() : ErrorCode::SYS_DB_ERROR);
        }

        // 加入队列
        if (!$data['is_return']) {
            $add_data = [
                'table_id' => $id,
                'phone'    => $queue_data['phone_number'],
                'content'  => $queue_data['content'],
                'type'     => $queue_data['type'],
            ];
            $add_datas[] = $add_data;
            (new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
        }
        //
        $model->commit();
        return $code_data;
    }

    protected function wxUserRegisterPhoneCode($phone, $data)
    {
    	$model = BaseModel::getInstance($this->save_model);
        // $is_expired = $data['is_expired'] ? $data['is_expired'] : 2*3600;
        $code_data = [    // 手机号验证码
            'verification_code' => (string)mt_rand(100000, 999999),
            'phone_number'      => $phone,
            'create_time'       => (string)NOW_TIME,
            'type'              => 1, 
            'is_expired'        => 180, // 验证码过期时间 单位： s
        ];
        $queue_data = [
            'phone_number' => $phone,
            'content'      => '验证码'.$code_data['verification_code'].'，您正在绑定你的手机号码。',
            'create_time'  => $code_data['create_time'],
            'type'         => 1, 
            'state'        => 2, // 短信发送状态，1-未处理 2-处理中 3-成功 4-失败
        ];
        $model->startTrans();
        $phone_user = BaseModel::getInstance('wx_user')->getOne(['telephone' => $phone], 'id');
        if ($phone_user['id']) {
            $this->throwException(ErrorCode::HAD_SAME_PHONE);
        } elseif (!$id = $model->insert($code_data)) {
            $this->throwException($model->getError() ? $model->getError() : ErrorCode::SYS_DB_ERROR);
        }

        // 加入队列
        if (!$data['is_return']) {
            $add_data = [
                'table_id' => $id,
                'phone'    => $queue_data['phone_number'],
                'content'  => $queue_data['content'],
                'type'     => $queue_data['type'],
            ];
            $add_datas[] = $add_data;
            (new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
        }
        //
        $model->commit();
        return $code_data;
    }



}
