<?php
/**
 * File: AccessoryCheckSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 10:24
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use \Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\AccessoryCheckEvent;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\GroupService;
use Library\Common\Util;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;

class AccessoryCheckSendNotification implements ListenerInterface
{
    /*
     * @param AccessoryCheckEvent $event
     *
     *  配件单推送
     *  $event->data['type']类型对应AppMessageService中 配件消息TYPE_* 类型
     */
    public function handle(EventAbstract $event)
    {
        try {
            $accessory = BaseModel::getInstance('worker_order_apply_accessory')->getOne($event->data['data_id']);
            $order_info = BaseModel::getInstance('worker_order')->getOne($accessory['worker_order_id']);
            $item = BaseModel::getInstance('worker_order_apply_accessory_item')->getOne([
                'accessory_order_id' => $event->data['data_id']
            ]);
            $worker = BaseModel::getInstance('worker')->getOne($order_info['worker_id'], 'worker_telephone, jpush_alias');

            if (!$worker) {
                return;
            }

            $date = date('m月d日', $accessory['create_time']);
            $factory_estimate_time = date('m月d日H:i', $accessory['factory_estimate_time']);

            if ($event->data['type'] == AppMessageService::TYPE_WAIT_FACTORY_CHECK) {
                $title = '配件单待厂家审核';
                $content = "您在{$date}申请的配件:{$item['name']},客服已审核通过,正在等待厂家审核";
                //$type = AppMessageService::TYPE_ACCESSORY_OTHER_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_WAIT_ACCESSORY_MASSAGE) {
                $title = '配件单厂家审核通过';
                $content = "您在{$date}申请的配件:{$item['name']},厂家已审核通过，配件将在{$factory_estimate_time}之前发出";
                //$type = AppMessageService::TYPE_WAIT_ACCESSORY_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_ACCESSORY_CHECK_NOT_PASS) {
                if ($accessory['accessory_status'] == '2') {
                    $operator = '客服';
                    $reason = $accessory['admin_check_remark'];
                } else {
                    $operator = '厂家';
                    $reason = $accessory['factory_check_remark'];
                }
                $title = '配件单'.$operator.'审核不通过';
                $content = "您在{$date}申请的配件:{$item['name']},审核未通过,原因为:{$reason}";
                //$type = AppMessageService::TYPE_ACCESSORY_OTHER_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_FACTORY_DELAY_SEND) {
                $title = '厂家延时发件';
                $content = "您在{$date}申请的配件:{$item['name']},厂家将发货时间修改为:{$factory_estimate_time},请调整上门服务时间";
                //$type = AppMessageService::TYPE_ACCESSORY_OTHER_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_SEND_ACCESSORY_MASSAGE) {
                $express_number = BaseModel::getInstance('express_tracking')->getFieldVal([
                    'data_id' => $event->data['data_id'],
                    'type'    => 1
                ], 'express_number');
                $title = '厂家已发件';
                $content = "您在{$date}申请的配件:{$item['name']},厂家已发出，快递单号为:{$express_number},请注意查收";
                if ($accessory['is_giveup_return'] > 0) {
                    $content .= '。注：旧件不需返还';
                }
                //$type = AppMessageService::TYPE_SEND_ACCESSORY_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_RETURN_ACCESSORY_MASSAGE) {
                $title = '旧配件待返还';
                $content = "您在{$date}申请的配件:{$item['name']},已被签收,请及时将旧配件打包返厂,以免影响工单的结算";
                //$type = AppMessageService::TYPE_RETURN_ACCESSORY_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_FACTORY_ABANDON_RETURN) {
                $title = '厂家放弃旧配件返还';
                $content = "您在{$date}申请的配件:{$item['name']},厂家已放弃旧配件返还,本配件单的配件无需返厂";
                //$type = AppMessageService::TYPE_ACCESSORY_OTHER_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_ACCESSORY_END) {
                $title = '配件单终止';
                if ($accessory['cancel_status'] == '1') {
                    $operator = '客服';
                } elseif ($accessory['cancel_status'] == '2') {
                    $operator = '厂家';
                } else {
                    return false;
                }
                $content = "您在{$date}申请的配件:{$item['name']},{$operator}已进行终止操作，本配件单已完结";
                //$type = AppMessageService::TYPE_ACCESSORY_OTHER_MASSAGE;
            } elseif ($event->data['type'] == AppMessageService::TYPE_CS_SEND_ACCESSORY_MASSAGE) {
                $order_product = BaseModel::getInstance('worker_order_product')->order('id asc')->getOne($order_info['id']);
                $express_number = $event->data['express_number'] ?? BaseModel::getInstance('express_tracking')->getFieldVal([
                    'data_id' => $event->data['data_id'],
                    'type'    => 1
                ], 'express_number');

                $title = '厂家已发件';
                $content = "{$order_product['cp_product_brand_name']}{$order_product['cp_category_name']}：{$item['name']}，厂家已发出，快递单号为：{$express_number}，请注意查收";

                if ($accessory['is_giveup_return'] > 0) {
                    $content .= '。注：旧件不需返还';
                }
                $content .= "。（工单号：{$order_info['orno']}）";
            } else {
                return false;
            }
            $content .= "(工单号:{$order_info['orno']})";
            //消息记录
            $id = AppMessageService::create($order_info['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
            if (!empty($id)) {
                //极光推送
                workerNotificationJPush($worker['jpush_alias'], $event->data['type'], $id, $title, $content, $event->data['data_id']);
            }

            //企业号推送
            $news = new NewsItem();
            $news->title = $title;
            $news->description  = str_replace('<br>', "\n", $content);
            $news->url = C('qiyewechat_host'). C('qy_base_path'). C('application_url.accessory_base_url') . $accessory['worker_order_id'].'/'.$event->data['data_id'];
            $message = Message::make('news')->item($news);
            sendQyWechatNotification($worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER'));

            //检查是否群内工单
            if (!empty($order_info['worker_group_id'])) {
                $owner_worker_id = GroupService::getOwnerId($order_info['worker_group_id']);
                if ($owner_worker_id != $order_info['worker_id']) {
                    $owner_worker = BaseModel::getInstance('worker')->getOne($owner_worker_id, 'worker_id, worker_telephone, jpush_alias');
                }
            }
            if (!empty($owner_worker)) {
                //消息记录
                $id = AppMessageService::create($owner_worker['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
                if (!empty($id)) {
                    //极光推送
                    workerNotificationJPush($owner_worker['jpush_alias'], $event->data['type'], $id, $title, $content, $event->data['data_id']);
                }
                //企业号推送
                sendQyWechatNotification($owner_worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER'));
            }
        } catch (\Exception $e) {

        }
    }

}
