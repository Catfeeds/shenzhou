<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/7
 * Time: 09:59
 */

namespace Admin\Controller;


use Admin\Common\ErrorCode;
use Admin\Logic\BaseLogic;
use Admin\Logic\XinYingYanLogic;
use Admin\Model\BaseModel;
use Common\Common\Logic\CryptLogic;
use Common\Common\Repositories\Events\PushXinYingYanOrderStatusChangeEvent;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\XinYingYngService;
use Library\Common\Util;
use Library\Crypt\AuthCode;
use Library\Crypt\Des;
use Library\Crypt\Rsa;

class XinYingYanController extends BaseController
{

    public function createExpressInstallationOrders()
    {
        $post = I('post.');
        $des_key = I('post.des_key', '');
        $sale_order_data = I('post.data', '');

        try {
            $this->platFormRequireAuth(XinYingYngService::PLATFORM_CODE);
            $data = XinYingYngService::$data;

            // 解密
            $logic = new CryptLogic();
            $data = $logic->xinyingyanDecrypt($sale_order_data, $des_key);

            if (!isset(XinYingYngService::WORKEKR_ORDER_TYPE_ARR_VALUE[$data['type']])) {
                $this->XYYFail(XinYingYngService::RETURN_CODE_PRODUCT_NOT_INSTALLATION, '不存在该类型工单');
            }

            (new XinYingYanLogic())->platformOrderDataCheckAndCreate(XinYingYngService::$factory, $data, $result);

            $return = [];
            $error_default = [
                'result' => XinYingYngService::RETURN_RESULT_CREATE_ORDER_FAIL,
                'error_code' => '',
                'error_message' => '',
                'order_sn' => null,
                'status' => null,
                'tag' => null,
            ];
            foreach ($result as $k => $v) {
                $v['platform_order_sn'] = $k;
                $return[] = $v+$error_default;
            }
//            $this->response($return);
            $des_data = $logic->xinyingyanEncrypt($return);
            $this->XYYSuccess($des_data);
        } catch (\Exception $e) {
//            var_dump($e->getMessage(), $e->getCode());
            $this->XYYGetExceptionError($e);
        }
    }

    public function loginAndSkip()
    {
        try {
            $this->platFormRequireAuth(XinYingYngService::PLATFORM_CODE);
            $data = XinYingYngService::$data;
            $factory = XinYingYngService::$factory;
            if (!in_array(XinYingYngService::$data['type'], XinYingYngService::SKIP_TYPE_ARR)) {
                $this->XYYFail(XinYingYngService::RETURN_CODE_OTHER_ERROR, '参数错误');
            }
            switch ($data['type']) {
                case XinYingYngService::SKIP_TYPE_WORKER_ORDER_DETAIL:
//                    $orno = I('get.order_sn', '');
                    $orno = $data['order_sn'];

                    $order = BaseModel::getInstance('worker_order')->getOne(['orno' => $orno], 'id');
//                    $order['id'] = 584519;
                    if (!$order) {
                        $this->XYYFail(XinYingYngService::RETURN_CODE_OTHER_ERROR, '订单不存在 ');
                    }

                    $url = str_replace(':id', $order['id'], C('FACTORY_DOMIAN_NAME').C('FACTORY_WORKER_ORDER_ROUTING'));
                    $s = 36 * 3600;
                    $token_data = [
                        'user_id'     => $factory['factory_id'],
                        'type'        => AuthService::ROLE_FACTORY,
                        'expire_time' => NOW_TIME + $s,
                    ];
                    $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);

                    $is_money_not_enouth = '0';
                    $is_money_not_enouth_msg = '';
                    $factory_total_frozen = D('FactoryMoney')->factory_total_frozen($factory['factory_id']);
                    if ($factory['money'] - $factory_total_frozen < 1000) {
                        $is_money_not_enouth = '1';
                        $is_money_not_enouth_msg = '您好! 您账户的可下单余额已经不足1000元 ，请及时充值，以免影响正常下单^_^';
                    }

                    $is_date_to = '0';
                    $is_date_to_msg = '';
                    //检查账号是否到期   提前一个月提醒
                    $toDate = date('Y-m-d', $factory['dateto']);
                    //获取到期前的一个月时间
                    $preMonth = strtotime("$toDate -1 month");

                    if (NOW_TIME >= $preMonth) {
                        $is_date_to = '1';
                        $is_date_to_msg = '提醒：您的账号将于' . date('Y-m-d', $factory['dateto']) . '到期，到期后将无法使用系统下单，请及时联系神州联保业务员^_^';
                    }

                    $login_data = [
                        'result' => [
                            'factory_id'        => $factory['factory_id'],
                            'excel_in_order'    => $factory['excel_in_order'],
                            'role'              => AuthService::ROLE_FACTORY,
                            'factory_full_name'  => $factory['factory_full_name'],
                            'factory_type'      => $factory['factory_type'],
                            'code'              => $factory['code'],
                            'username'          => $factory['linkman'],
                            'factory_admin_id'  => 0,
                            'linkphone'         => $factory['linkphone'],
                            'logo'              => Util::getServerFileUrl($factory['factory_logo']),
                            'tags_id'           => '0',
                            'factory_admin_role' => 0,
                            'is_dateto'         => $is_date_to,
                            'is_dateto_msg'     => $is_date_to_msg,
                            'is_money_not_enouth' => $is_money_not_enouth,
                            'is_money_not_enouth_msg' => $is_money_not_enouth_msg,
                            'factory_short_name' => $factory['factory_short_name'],
                            'access'            => BaseModel::getInstance('factory_adnode')->getFieldVal([], 'name', true),
                        ],
                        'token' => $token,
                    ];
                    $string = 'Location: '.$url.'?a='.json_encode($login_data);
                    header($string);
                    break;

                default:
                    $this->XYYFail(XinYingYngService::RETURN_CODE_OTHER_ERROR, '参数错误');
                    break;
            }

        } catch (\Exception $e) {
            $this->XYYGetExceptionError($e);
        }
    }

    public function pushWorderOrderStatus()
    {
        try {
            $redis = json_decode(RedisPool::getInstance()->get((C('REDIS_KEY.XINYINGYAN_PUSH_WORKER_ORDER_STATUS'))), true);
            $ids_arr = [];

            foreach ($redis as $k => $v) {
                if ($v['t'] <= NOW_TIME) {
                    $ids_arr[] = $k;
                }
            }
            $str_ids = implode(',', $ids_arr);
            $str_ids && event(new PushXinYingYanOrderStatusChangeEvent($str_ids));
        } catch (\Exception $e) {
            $this->XYYGetExceptionError($e);
        }
    }

}
