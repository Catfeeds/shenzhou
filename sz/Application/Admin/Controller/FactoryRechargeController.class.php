<?php
/**
 * File: FactoryRechargeController.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/10
 */

namespace Admin\Controller;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryMoneyChangeRecordService;
use Common\Common\Service\PayPlatformRecordService;
use Common\Common\Service\PayService;
use Common\Common\Service\SystemMessageService;
use Pingpp\Charge;
use Pingpp\Pingpp;
use Pingpp\Util\Util;

class FactoryRechargeController extends BaseController
{

    const ALIPAY_PC_DIRECT = 1;
    const WX_PUB_QR        = 2;
    const UPACP_PC         = 3;

    const STATUS_UNPAID       = 0;
    const STATUS_PAID_SUCCESS = 1;

    const CHANNEL_TYPE
        = [
            'alipay_pc_direct' => self::ALIPAY_PC_DIRECT,
            'wx_pub_qr'        => self::WX_PUB_QR,
            'upacp_pc'         => self::UPACP_PC,
        ];

    public function request()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_FACTORY]);

            $amount = I('amount', 0);
            $pay_type = I('pay_type', 0, 'intval');
            $return_url = I('return_url');

            $valid_pay_type = [1, 2];
            if ($amount <= 0 || !in_array($pay_type, $valid_pay_type)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $admin_id = AuthService::getAuthModel()->getPrimaryValue();
            $user_type = 0;
            $role = AuthService::getModel();

            if (AuthService::ROLE_FACTORY_ADMIN == $role) {
                $user_type = PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN;
            } else {
                $user_type = PayPlatformRecordService::USER_TYPE_FACTORY;
            }

            Pingpp::setApiKey(C('PINGPP.APP_KEY'));
            Pingpp::setPrivateKeyPath(C('PINGPP.APP_RSA_PRI_KEY_PATH'));

            $channel = '';
            $extra = [];
            $pingpp_return_url = C('PINGPP.SYNC_URL');
            $payment = 0;
            if (1 == $pay_type) {
                //银联
                $channel = 'upacp_pc';
                $extra = [
                    'result_url' => $pingpp_return_url,
                ];
                $payment = self::UPACP_PC;
            } elseif (2 == $pay_type) {
                //支付宝
                $channel = 'alipay_pc_direct';
                $extra = [
                    'success_url' => $pingpp_return_url,
                ];
                $payment = self::ALIPAY_PC_DIRECT;
            }

            $ping_amount = bcmul($amount, 100, 2);
            $out_trade_no = $this->generateOutTradeNo();

            $ch = Charge::create(
                [
                    'order_no'  => $out_trade_no,
                    'app'       => ['id' => C('PINGPP.APP_ID')],
                    'channel'   => $channel,
                    'amount'    => $ping_amount,
                    'client_ip' => get_client_ip(),
                    'currency'  => 'cny',
                    'subject'   => '神州联保售后系统充值',
                    'body'      => '神州联保售后系统充值',
                    'extra'     => $extra,
                ]
            );

            M()->startTrans();

            $record_model = BaseModel::getInstance('pay_platform_record');
            $insert_data = [
                'platform_type' => PayService::PLATFORM_TYPE_PINGPP_VALUE,
                'out_order_no'  => $out_trade_no,
                'money'         => $amount,
                'pay_type'      => PayPlatformRecordService::PAY_TYPE_FACTORY_MONEY_RECHARGE,
                'data_id'       => 0,
                'user_id'       => $admin_id,
                'user_type'     => $user_type,
                'create_time'   => NOW_TIME,
                'syn_url'       => $return_url,
                'status'        => self::STATUS_UNPAID,
                'pay_ment'      => $payment,
            ];
            $record_model->insert($insert_data);
            M()->commit();

            $this->response([
                'charge_data' => $ch,
            ]);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function qrcode()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_FACTORY]);

            $amount = I('amount', 0);
            $pay_type = I('pay_type', 0, 'intval');

            $valid_pay_type = [1];
            if ($amount <= 0 || !in_array($pay_type, $valid_pay_type)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $admin_id = AuthService::getAuthModel()->getPrimaryValue();
            $user_type = 0;
            $role = AuthService::getModel();
            $admin_info = AuthService::getAuthModel();

            $factory_id = 0;
            if (AuthService::ROLE_FACTORY_ADMIN == $role) {
                $user_type = PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN;
                $factory_id = $admin_info['factory_id'];
            } else {
                $user_type = PayPlatformRecordService::USER_TYPE_FACTORY;
                $factory_id = $admin_id;
            }

            Pingpp::setApiKey(C('PINGPP.APP_KEY'));
            Pingpp::setPrivateKeyPath(C('PINGPP.APP_RSA_PRI_KEY_PATH'));

            $channel = '';
            $extra = [];
            if (1 == $pay_type) {
                $channel = 'wx_pub_qr';
                $product_id = 'Factory_' . $factory_id;
                $extra = [
                    'product_id' => $product_id,
                ];
            }

            $ping_amount = bcmul($amount, 100, 2);
            $out_trade_no = $this->generateOutTradeNo();

            $ch = Charge::create(
                [
                    'order_no'  => $out_trade_no,
                    'app'       => ['id' => C('PINGPP.APP_ID')],
                    'channel'   => $channel,
                    'amount'    => $ping_amount,
                    'client_ip' => get_client_ip(),
                    'currency'  => 'cny',
                    'subject'   => '神州联保售后系统充值',
                    'body'      => '神州联保售后系统充值',
                    'extra'     => $extra,
                ]
            );

            $qr_code = '';
            $payment = 0;
            if (1 == $pay_type) {
                $charge = json_decode($ch, true);
                $qr_code = $charge['credential']['wx_pub_qr'];
                $payment = self::WX_PUB_QR;
            }

            M()->startTrans();
            $record_model = BaseModel::getInstance('pay_platform_record');
            $insert_data = [
                'platform_type' => PayService::PLATFORM_TYPE_PINGPP_VALUE,
                'out_order_no'  => $out_trade_no,
                'money'         => $amount,
                'pay_type'      => PayPlatformRecordService::PAY_TYPE_FACTORY_MONEY_RECHARGE,
                'data_id'       => 0,
                'user_id'       => $admin_id,
                'user_type'     => $user_type,
                'create_time'   => NOW_TIME,
                'pay_ment'      => $payment,
            ];
            $query_id = $record_model->insert($insert_data);
            M()->commit();

            vendor('phpqrcode.qrlib');
            ob_start();
            \QRcode::png($qr_code, false, 'H', 50, 2);
            $buffer = ob_get_clean();
            ob_end_clean();
            $url = 'data:image/png;base64, ' . base64_encode($buffer);
            $this->response([
                'qr_data'  => $url,
                'query_id' => (string)$query_id,
            ]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function generateOutTradeNo()
    {
        $out_trade_no = [];
        $max_len = 5;
        for ($i = 0; $i < $max_len; $i++) {
            $out_trade_no[] = 'SZLB' . date('Ymd') . sprintf('%06d', mt_rand(0, 999999));
        }

        $model = BaseModel::getInstance('pay_platform_record');
        $exist = $model->getFieldVal(['out_order_no' => ['in', $out_trade_no], 'out_order_no'], true);
        $exist = empty($exist) ? [] : $exist;

        $diff = array_diff($out_trade_no, $exist);

        if (!empty($diff)) {
            return array_pop($diff);
        } else {
            return $this->generateOutTradeNo();
        }
    }

    protected function verify_signature($raw_data, $signature, $pub_key_path)
    {
        $pub_key_contents = file_get_contents($pub_key_path);

        // php 5.4.8 以上，第四个参数可用常量 OPENSSL_ALGO_SHA256
        return openssl_verify($raw_data, base64_decode($signature), $pub_key_contents, OPENSSL_ALGO_SHA256);
    }

    public function callback()
    {
        try {
            $raw_data = file_get_contents('php://input');

            $headers = Util::getRequestHeaders();
            $signature = isset($headers['X-Pingplusplus-Signature']) ? $headers['X-Pingplusplus-Signature'] : null;

            $pub_key_path = C('PINGPP.PINGPP_RSA_PUB_KEY_PATH');
            $result = $this->verify_signature($raw_data, $signature, $pub_key_path);
            if ($result === 1) {
                // 验证通过
            } elseif ($result === 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'verification failed');
            } else {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'verification error');
            }

            $event = json_decode($raw_data, true);
            if ($event['type'] == 'charge.succeeded') {
                $charge = $event['data']['object'];
                $channel_type = $charge['channel'];
                $livemode = $charge['livemode'];
                $amount = $charge['amount'];

                if (!$livemode) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '不是live模式');
                }

                $order_no = $charge['order_no'];
                $transaction_no = $charge['transaction_no'];

                $record_model = BaseModel::getInstance('pay_platform_record');
                $where = ['out_order_no' => $order_no];
                $field = 'data_id,id,money,user_id,user_type';
                $record = $record_model->getOneOrFail($where, $field);
                $data_id = $record['data_id'];
                $record_id = $record['id'];
                $change_money = $record['money'];
                $user_type = $record['user_type'];
                $user_id = $record['user_id'];

                $verify_money = bcmul($change_money, 100, 2);
                if ($verify_money != $amount) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '金额不一致');
                }

                $valid_user_type = [FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN, FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY];
                if (!in_array($user_type, $valid_user_type)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户类型错误');
                }
                if (!array_key_exists($channel_type, self::CHANNEL_TYPE)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '非法支付类型');
                }

                $operator_type = 0;
                $factory_id = 0;
                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $user_type) {
                    $operator_type = FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN;
                    $factory_admin_model = BaseModel::getInstance('factory_admin');
                    $factory_admin_info = $factory_admin_model->getOneOrFail($user_id);
                    $factory_id = $factory_admin_info['factory_id'];
                } else {
                    $operator_type = FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY;
                    $factory_id = $user_id;
                }

                if ($data_id > 0) {
                    http_response_code(200); // PHP 5.4 or greater
                    $this->okNull();
                }

                $factory_model = BaseModel::getInstance('factory');
                $factory_info = $factory_model->getOneOrFail($factory_id);
                $money = $factory_info['money'];

                $last_money = bcadd($money, $change_money, 2);

                M()->startTrans();

                $change_type = 0;
                if ('upacp_pc' == $channel_type) {
                    $change_type = FactoryMoneyChangeRecordService::CHANGE_TYPE_FACTORY_UNIONPAY;
                } elseif ('alipay_pc_direct' == $channel_type) {
                    $change_type = FactoryMoneyChangeRecordService::CHANGE_TYPE_FACTORY_ALIPAY;
                } elseif ('wx_pub_qr' == $channel_type) {
                    $change_type = FactoryMoneyChangeRecordService::CHANGE_TYPE_FACTORY_WXPAY;
                }

                $change_model = BaseModel::getInstance('factory_money_change_record');
                $insert_data = [
                    'factory_id'       => $factory_id,
                    'operator_id'      => $user_id,
                    'operator_type'    => $operator_type,
                    'change_type'      => $change_type,
                    'change_money'     => $change_money,
                    'create_time'      => NOW_TIME,
                    'money'            => $money,
                    'last_money'       => $last_money,
                    'out_trade_number' => $transaction_no,
                    'status'           => FactoryMoneyChangeRecordService::STATUS_SUCCESS,
                ];
                $change_id = $change_model->insert($insert_data);

                $record_model->update($record_id, [
                    'data_id'  => $change_id,
                    'pay_time' => NOW_TIME,
                    'pay_ment' => self::CHANNEL_TYPE[$channel_type],
                    'status'   => self::STATUS_PAID_SUCCESS,
                ]);

                $change_record_model = BaseModel::getInstance('factory_money_change_record');
                $change_record_model->update($change_id, [
                    'money'            => $money,
                    'last_money'       => $last_money,
                    'out_trade_number' => $transaction_no,
                    'status'           => FactoryMoneyChangeRecordService::STATUS_SUCCESS,
                ]);

                $factory_model->update($factory_id, [
                    'money' => $last_money,
                ]);

                $content = "您的维修金已成功充值{$change_money}元";
                SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $factory_id, $content, $change_id, SystemMessageService::MSG_TYPE_FACTORY_RECHARGE_SUCCESS);

                M()->commit();

                http_response_code(200); // PHP 5.4 or greater
                $this->okNull();
            }
        } catch (\Exception $e) {
            http_response_code(400);
            $this->getExceptionError($e);
        }

    }

    public function sync()
    {
        try {
            $orderId = I('orderId');
            $respMsg = I('respMsg');
            $out_trade_no = I('out_trade_no');
            $result = I('result');

            if (strlen($orderId) <= 0 && strlen($out_trade_no) <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            if (strlen($orderId) > 0) {
                $out_trade_no = $orderId;
            }

            $pay_record = BaseModel::getInstance('pay_platform_record');
            $record = $pay_record->getOneOrFail(['out_order_no' => $out_trade_no]);
            $syn_url = $record['syn_url'];

            header('Location:' . $syn_url);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

}