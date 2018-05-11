<?php
/**
 * File: PayPlatformController.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/12
 */

namespace Admin\Controller;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\PayPlatformRecordService;
use Common\Common\Service\PayService;

class PayPlatformController extends BaseController
{

    public function index()
    {
        try {

            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $model = BaseModel::getInstance('pay_platform_record');

            $admin_info = AuthService::getAuthModel();
            $role = AuthService::getModel();
            $admin_id = AuthService::getAuthModel()->getPrimaryValue();

            $factory_id = 0;
            if (AuthService::ROLE_FACTORY == $role) {
                $factory_id = $admin_id;
            } else {
                $factory_id = $admin_info['factory_id'];
            }

            $factory_admin_model = BaseModel::getInstance('factory_admin');
            $factory_admin_ids = $factory_admin_model->getFieldVal(['factory_id' => $factory_id], 'id', true);
            $factory_admin_ids_str = empty($factory_admin_ids) ? '-1' : implode(',', $factory_admin_ids);

            $where = [
                'pay_type' => PayPlatformRecordService::PAY_TYPE_FACTORY_MONEY_RECHARGE,
                '_string'  => "(user_type=" . PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN . " and user_id in ({$factory_admin_ids_str})) or (user_type=" . PayPlatformRecordService::USER_TYPE_FACTORY . " and user_id={$factory_id})",
            ];

            $cnt = $model->getNum($where);

            $opts = [
                'field' => 'id,platform_type,money,create_time,pay_time,pay_type,pay_ment,user_type,user_id,status',
                'where' => $where,
                'order' => 'id desc',
                'limit' => $this->page(),
            ];

            $list = $model->getList($opts);

            $factory_ids = [];
            $factory_admin_ids = [];

            foreach ($list as $val) {
                $user_type = $val['user_type'];
                $user_id = $val['user_id'];

                if (PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN == $user_type) {
                    $factory_admin_ids[] = $user_id;
                } elseif (PayPlatformRecordService::USER_TYPE_FACTORY == $user_type) {
                    $factory_ids[] = $user_id;
                }
            }

            $factories = $this->getFactories($factory_ids);
            $factory_admins = $this->getFactoryAdmins($factory_admin_ids);

            $pay_class = [];
            foreach ($list as $key => $val) {
                $class = $pay_class[$val['platform_type']];
                $user_type = $val['user_type'];
                $user_id = $val['user_id'];
                $pay_ment = $val['pay_ment'];

                $user_name = '';
                if (PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN == $user_type) {
                    $user_name = empty($factory_admins) ? '' : $factory_admins[$user_id]['nickout'];
                } elseif (PayPlatformRecordService::USER_TYPE_FACTORY == $user_type) {
                    $user_name = empty($factories) ? '' : $factories[$user_id]['linkman'];
                }

                $val['user_name'] = $user_name;
                $val['payment_str'] = self::getPaymentStr($pay_ment);

                if (!$class && PayService::PLATFORM_TYPE[$val['platform_type']]) {
                    $pay_class[$val['platform_type']] = $class = PayService::initDiy($val['platform_type']);
                }
                if ($class) {
                    $val['status'] = $class->getOrderStatus($val['status']);
                    $sys_pay_ment = $class->getSystemPayMent($pay_ment);
                    $val['payment_str'] = PayService::PAYMENT_NAME_KEY_VALUE[$sys_pay_ment] ?? '';
                }

                unset($val['platform_type']);
                $list[$key] = $val;
            }

            $this->paginate($list, $cnt);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    protected function getFactories($factory_ids)
    {
        if (empty($factory_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory');

        $list = $model->getList(['factory_id' => ['in', $factory_ids]]);

        $data = [];
        foreach ($list as $val) {
            $factory_id = $val['factory_id'];

            $data[$factory_id] = $val;
        }

        return $data;
    }

    protected function getFactoryAdmins($factory_admin_ids)
    {
        if (empty($factory_admin_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory_admin');

        $list = $model->getList(['id' => ['in', $factory_admin_ids]]);

        $data = [];
        foreach ($list as $val) {
            $id = $val['id'];

            $data[$id] = $val;
        }

        return $data;
    }

    protected static function getPaymentStr($pay_type)
    {
        switch ($pay_type) {
            case FactoryRechargeController::ALIPAY_PC_DIRECT:
                return '支付宝支付';
            case FactoryRechargeController::WX_PUB_QR:
                return '微信支付';
            case FactoryRechargeController::UPACP_PC:
                return '银联支付';
        }

        return '';
    }

    public function info()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $query_id = I('id');

            if ($query_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $model = BaseModel::getInstance('pay_platform_record');

            $field = 'id,money,status';
            $info = $model->getOneOrFail($query_id, $field);

            $this->response($info);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}