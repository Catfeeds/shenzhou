<?php
/**
 * File: CostOrderCheckSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 15:01
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\CostOrderCheckEvent;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\GroupService;
use Library\Common\Util;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;

class CostOrderCheckSendNotification implements ListenerInterface
{

    /**
     * @param CostOrderCheckEvent $event
     * 费用单推送
     *  $event->data['type']类型对应AppMessageService中 费用消息TYPE_* 类型
     */
    public function handle(EventAbstract $event)
    {
        try {
            $cost = BaseModel::getInstance('worker_order_apply_cost')->getOne($event->data['data_id']);
            $order_info = BaseModel::getInstance('worker_order')->getOne($cost['worker_order_id']);
            $worker = BaseModel::getInstance('worker')->getOne($order_info['worker_id'], 'worker_telephone, jpush_alias');

            $date = date('m月d日', $cost['create_time']);
            $cost_type = [
                '',
                '远程上门费用',
                '购买配件费用',
                '旧机拆机合和打包费用',
                '旧机返厂运费',
                '其他费用'
            ];

            if ($event->data['type'] == AppMessageService::TYPE_WAIT_CHECK_MASSAGE) {
                $title = '费用单待厂家审核';
                $content = "您在{$date}申请{$cost['fee']}元{$cost_type[$cost['type']]},客服已审核通过,正在等待厂家审核";
            } elseif ($event->data['type'] == AppMessageService::TYPE_CHECK_PASS_MASSAGE) {
                $title = '费用单审核通过';
                $content = "您在{$date}申请{$cost['fee']}元{$cost_type[$cost['type']]},厂家已审核通过,申请的{$cost['fee']}元将在完成工单后,计入维修费用一起结算";
            } elseif ($event->data['type'] == AppMessageService::TYPE_CHECK_NOT_PASS_MASSAGE) {
                if ($cost['status'] == '1') {
                    $operator = '客服';
                    $reason = $cost['admin_check_remark'];
                } else {
                    $operator = '厂家';
                    $reason = $cost['factory_check_remark'];
                }
                $title = '费用单'.$operator.'审核不通过';
                $content = "您在{$date}申请{$cost['fee']}元{$cost_type[$cost['type']]},{$title}，原因为:{$reason}";
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
            $news->url = C('qiyewechat_host') . C('qy_base_path') . C('application_url.cost_order_base_url'). $event->data['data_id'];
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
