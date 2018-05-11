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
use Common\Common\Repositories\Events\GroupNewsEvent;
use Common\Common\Service\GroupService;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;

class GroupNewsSendNotification implements ListenerInterface
{
    /*
     * @param GroupNewsEvent $event
     *
     *  群相关推送
     *  $event->data['data_id'] 群记录id
     *  $event->data['type']    类型对应GroupService中 GROUP_RECORD_TYPE_* 群记录类型
     */
    public function handle(EventAbstract $event)
    {
        try {
            $record_info = BaseModel::getInstance('worker_group_record')->getOne($event->data['data_id']);
            $group_info = BaseModel::getInstance('worker_group')->getOne($record_info['worker_group_id']);
            if ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_CREATE_GROUP_PASS) {
                $title = "您创建的网点群“{$group_info['group_name']}”审核通过啦！";
                $content = "群号：{$group_info['group_no']}，群成员输入群号即可加入网点群接单。";
                $worker_id = $record_info['operated_worker_id'];
                $group_id  = $record_info['worker_group_id'];
            } elseif ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_CREATE_GROUP_NO_PASS) {
                $title = "您创建的网点群“{$group_info['group_name']}”审核不通过";
                $content = $group_info['audit_remark'];
                $worker_id = $record_info['operated_worker_id'];
            } elseif ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_APPLY_JOIN_GROUP) {
                $nickname = BaseModel::getInstance('worker')->getFieldVal($record_info['record_operator_id'], 'nickname');
                $title = $nickname."申请加入网点群";
                $content = '请及时审核';
                $worker_id = GroupService::getOwnerId($record_info['worker_group_id']);
            } elseif ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_ALLOW_JOIN_GROUP) {
                $worker_id = $record_info['operated_worker_id'];
                $title = "恭喜您成功加入网点群“{$group_info['group_name']}”";
                $content = '请联系管理员派单给您';
                $group_id  = $record_info['worker_group_id'];
            } elseif ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_NOT_ALLOW_JOIN_GROUP) {
                $relation_info = BaseModel::getInstance('worker_group_relation')->getOne([
                    'where' => [
                        'worker_group_id' => $record_info['worker_group_id'],
                        'worker_id'       => $record_info['worker_id']
                    ]
                ]);
                $worker_id = $record_info['operated_worker_id'];
                $title = "您加入网点群“{$group_info['group_name']}”的申请被拒";
                $content = $relation_info['audit_remark'] . ' 您可继续接受客服派的单';
            } elseif ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_REMOVE_FROM_GROUP) {
                $worker_id = $record_info['operated_worker_id'];
                $title = "您已被管理员从网点群“{$group_info['group_name']}”移除";
                $content = '之后您可继续接收客服派的工单';
            } elseif ($event->data['type'] == GroupService::GROUP_RECORD_TYPE_SYSTEM_AUTO_AUDIT) {
                $worker_id = $record_info['operated_worker_id'];
                $title = "您加入网点群“{$group_info['group_name']}”的申请被拒";
                $content = '群管理员7天未审核，系统自动审核不通过，您可继续接收客服派的工单。';
            } else {
                return false;
            }
            $worker = BaseModel::getInstance('worker')->getOne([
                'where' => [
                    'worker_id' => $worker_id
                ],
                'field' => 'jpush_alias, worker_telephone'
            ]);
            //极光推送
            workerNotificationJPush($worker['jpush_alias'], $event->data['type'], $event->data['data_id'], $title, $content, $group_id ?? null);

            //企业号推送
            $text = $title."\n".$content;
            if (!$text) {
                return false;
            }
            sendQyWechatNotification($worker['worker_telephone'], $text, C('SEND_NEWS_MESSAGE_APPLICATION_ID'));
        } catch (\Exception $e) {

        }
    }

}
