<?php
/**
 * File: WorkerExtractedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:35
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\SystemMessageEvent;
use Common\Common\Service\AppMessageService;

class SystemMessageSendNotification implements ListenerInterface
{
    /**
     * @param SystemMessageEvent $event
     * 系统消息事件
     * $event->data['data_id'] 对应worker_announcement表id
     */
    public function handle(EventAbstract $event)
    {
        try {
            $announcement = BaseModel::getInstance('worker_announcement')->getOne($event->data['data_id']);
            if ($announcement['type'] == '1') {
                $type = AppMessageService::TYPE_BUSINESS_MESSAGE;
            } elseif ($announcement['type'] == '2') {
                $type = AppMessageService::TYPE_ORDERS_MASSAGE;
            } elseif ($announcement['type'] == '3') {
                $type = AppMessageService::TYPE_ACTIVITY_MESSAGE;
            } else {
                return false;
            }
            if ($announcement['push_type'] == '0') {
                $send_type = ['ios', 'android'];
            } elseif ($announcement['push_type'] == '1') {
                $send_type = ['android'];
            } elseif ($announcement['push_type'] == '2') {
                $send_type = ['ios'];
            } else {
                return false;
            }
            //极光推送
            workerNotificationJPush('', $type, $event->data['data_id'], $event->data['title'], '系统消息', $event->data['data_id'], $send_type, 'all');
        } catch (\Exception $e) {

        }

    }

}
