<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/4/11
 * Time: 10:16
 */

namespace Qiye\Controller;

use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderUserService;
use Common\Common\Service\PayPlatformRecordService;
use Common\Common\Service\PayService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use function GuzzleHttp\Psr7\build_query;
use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Qiye\Logic\PayLogic;
use Qiye\Logic\QiYeWechatLogic;
use Qiye\Model\BaseModel;
use Stoneworld\Wechat\AccessToken;
use EasyWeChat\Payment;

class PayController extends BaseController
{

    /**
     * 思路:
     * 1.获取add_fee记录,两条
     * 2.根据add_fee数量,数量=1,修改user_info的pay_type,pay_type和add_fee的pay_type关联
     */
    public function appPay()
    {
        try {
            $this->requireAuth();
            $worker_order_id = I('get.id', 0, 'intval');

            //检查参数
            if (empty($worker_order_id) || $worker_order_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $trade = (new PayLogic())->pay($worker_order_id);
            $desc = $trade['text'];
            $out_trade_no = $trade['out_order_no'];
            $amount = $trade['amount'];

            $notify_url = C('WORKER_PAY.SERVER_URL') . '/pay_result/app_notify';
            $wxpay_config = C('easyWeChatApp');

            //发起支付
            $app = new Application($wxpay_config);

            $attributes = [
                'trade_type'   => Order::APP,
                'body'         => $desc,
                'detail'       => $desc,
                'out_trade_no' => $out_trade_no,
                'total_fee'    => round($amount * 100), // 单位：分
                'notify_url'   => $notify_url,    // 支付结果通知网址，如果不设置则会使用配置里的默认地址
            ];

            $payment = $app->payment;
            $payment_order = new Order($attributes);
            $result = $payment->prepare($payment_order);
            if (
                'SUCCESS' != $result->return_code ||
                'SUCCESS' != $result->result_code
            ) {
                $this->throwException(ErrorCode::SYS_ABOUT_DB_CONFIG_ERROR, '获取支付配置失败：' . $result->return_msg);
            }

            $prepay_id = $result->prepay_id;

            $data = [
                'total_amount' => $amount,
                'config'       => $payment->configForAppPayment($prepay_id),
            ];

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function qyJsPay()
    {
        try {
            $this->requireAuth();
            $worker_order_id = I('get.id', 0, 'intval');

            //检查参数
            if (empty($worker_order_id) || $worker_order_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $trade = (new PayLogic())->pay($worker_order_id);
            $desc = $trade['text'];
            $out_trade_no = $trade['out_order_no'];
            $amount = $trade['amount'];

            $notify_url = C('WORKER_PAY.SERVER_URL') . '/pay_result/qy_js_notify';
            $wxpay_config = C('easyWeChatQy');

            $qiye_logic = new QiYeWechatLogic();
            $token_obj = new AccessToken($wxpay_config['app_id'], $wxpay_config['secret']);
            $token = $token_obj->getToken();
            $openid = $qiye_logic->convert2Openid(AuthService::getAuthModel()->worker_telephone, $token);

            //发起支付
            $app = new Application($wxpay_config);

            $attributes = [
                'trade_type'   => Order::JSAPI,
                'body'         => $desc,
                'detail'       => $desc,
                'out_trade_no' => $out_trade_no,
                'total_fee'    => round($amount * 100), // 单位：分
                'notify_url'   => $notify_url,    // 支付结果通知网址，如果不设置则会使用配置里的默认地址
                'openid'       => $openid,
            ];

            $payment = $app->payment;
            $payment_order = new Order($attributes);
            $result = $payment->prepare($payment_order);
            if (
                'SUCCESS' != $result->return_code ||
                'SUCCESS' != $result->result_code
            ) {
                $this->throwException(ErrorCode::SYS_ABOUT_DB_CONFIG_ERROR, '获取支付配置失败：' . $result->return_msg);
            }

            $prepay_id = $result->prepay_id;

            //不使用插件获取支付参数是因为插件仅支持微信公众号支付,企业号不支持
            $config = [
                'appId'     => $wxpay_config['app_id'],
                'nonceStr'  => uniqid(),
                'timeStamp' => (string)time(),
                'package'   => "prepay_id={$prepay_id}",
                'signType'  => 'MD5',
            ];

            $config['sign'] = Payment\generate_sign($config, $wxpay_config['payment']['key']);
            $config['debug'] = false;
            $config['jsApiList'] = ['getBrandWCPayRequest'];

            $data = [
                'total_amount' => $amount,
                'config'       => $config,
            ];

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function payResultAppNotify()
    {
        try {
            (new PayLogic())->notify(C('easyWeChatApp'));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function payResultQyNotify()
    {
        try {
            (new PayLogic())->notify(C('easyWeChatQy'));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
