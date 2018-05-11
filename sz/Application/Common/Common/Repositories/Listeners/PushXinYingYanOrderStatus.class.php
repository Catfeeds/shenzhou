<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/14
 * Time: 11:25
 */

namespace Common\Common\Repositories\Listeners;

use Admin\Logic\XinYingYanLogic;
use Common\Common\Logic\CryptLogic;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\XinYingYngService;
use GuzzleHttp\Pool;
use JPush\Http;
use Library\Common\Util;
use Think\Cache\Driver\Redis;

class PushXinYingYanOrderStatus implements ListenerInterface
{
    // 工单发生变化 发送订单状态通知新迎燕
    public function handle(EventAbstract $event)
    {
        $order_ids = (string)$event->order_ids;
        $orders = $order_ids ? BaseModel::getInstance('worker_order')->getList([
            'field' => 'id,worker_order_status,cancel_status,orno',
            'where' => [
                'id' => ['in', $order_ids],
            ],
            'index' => 'id',
        ]) : [];

        $order_orno_index = [];
        foreach ($orders as $k => $v) {
            $order_orno_index[$v['orno']] = $v['id'];
        }

//        $ext_info = $order_ids ? BaseModel::getInstance('worker_order_ext_info')->getList([
//            'field' => 'worker_order_id,out_trade_number,out_platform',
//            'where' => [
//                'worker_order_id' => ['in', $order_ids],
//            ],
//            'index' => 'worker_order_id',
//        ]) : [];

        $acce_list = $order_ids ? BaseModel::getInstance('worker_order_apply_accessory')->getList([
            'field' => 'worker_order_id,accessory_status,cancel_status,is_giveup_return,last_update_time',
            'where' => [
                'worker_order_id' => ['in', $order_ids],
//                'worker_order_id' => 583565,
            ],
            'order' => 'last_update_time asc,id asc',
            'index' => 'worker_order_id',
        ]) : [];

        $data = [];
        $logic = new XinYingYanLogic();
        foreach ($orders as $k => $order) {
            $acces = $acce_list[$order['id']];
            $data_status = [];
            $order_status = $logic->returnCodeOrderStatus($order['worker_order_status'], $order['cancel_status']);
            $acc_order_status = $logic->returnTagAccessoryStatus($acces['accessory_status'], $acces['cancel_status'], $acces['is_giveup_return']);
            if ($order_status !== false) {
                $data_status['status'] = $order_status;
            }

            if ($acc_order_status !== false) {
                $data_status['tag'] = $acc_order_status;
            }

            if (!empty($data_status)) {
                $data_status['order_sn'] = $order['orno'];
                $data[$order['orno']] = $data_status;
            }

        }
        if (!$data) {
            return;
        }

        $cry = new CryptLogic();
        $push_data = $cry->xinyingyanEncrypt(array_values($data));
        $push_data['order_platform_code'] = XinYingYngService::OBJECT_PLATFORM_CODE;
        $url = C('XINYINGYAN_PUSH_WORKER_ORDER_STATUS_URL');
        $dejson_result = json_decode(curlPostHttps($url, $push_data), true);
        $result = $cry->xinyingyanDecrypt($dejson_result['data'], $dejson_result['des_key']);

        $redis_result = json_decode(RedisPool::getInstance()->get((C('REDIS_KEY.XINYINGYAN_PUSH_WORKER_ORDER_STATUS'))), true);

        foreach ($result as $k => $v) {
            unset($data[$v['order_sn']]);
            $id = $order_orno_index[$v['order_sn']];
            if (!$id) {
                continue;
            }
            if ($v['result']) {
                unset($redis_result[$id]);
            } else {
                $redis_data = $redis_result[$id] ? $redis_result[$id] : [
                    'n' => 0,
                    't' => NOW_TIME,
                ];
                ++$redis_data['n'];
                $redis_data['t'] = $logic->setPushRuleTime($redis_data['n']);
                if ($redis_data['t'] === false) {
                    unset($redis_result[$id]);
                } else {
                    $redis_result[$id] = $redis_data;
                }
            }
        }

        foreach ($data as $k => $v) {
            $id = $order_orno_index[$k];
            if (!$id) {
                continue;
            }
            $redis_data = $redis_result[$id] ? $redis_result[$id] : [
                'n' => 0,
                't' => NOW_TIME,
            ];
            ++$redis_data['n'];
            $redis_data['t'] = $logic->setPushRuleTime($redis_data['n']);
            if ($redis_data['t'] === false) {
                unset($redis_result[$id]);
            } else {
                $redis_result[$id] = $redis_data;
            }
        }

        RedisPool::getInstance()->set(C('REDIS_KEY.XINYINGYAN_PUSH_WORKER_ORDER_STATUS'), json_encode($redis_result, JSON_UNESCAPED_UNICODE));

    }

}
