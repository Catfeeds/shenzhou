<?php
/**
 * File: WorkerExtractedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:35
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\OtherTransactionEvent;
use Common\Common\Service\AppMessageService;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;
use Common\Common\Model\BaseModel;

class OtherTransactionSendNotification implements ListenerInterface
{
    /**
     * @param OtherTransactionEvent $event
     * 交易消息事件
     */
    public function handle(EventAbstract $event)
    {
        try {
            //其他消息
            if ($event->data['type'] == AppMessageService::TYPE_MONEY_ADJUST_SET) {
                $worker_money_adjust_record = BaseModel::getInstance('worker_money_adjust_record')->getOne([
                    'alias' => 'wm',
                    'where' => [
                        'wm.id' => $event->data['data_id']
                    ],
                    'join'  => 'left join worker_order as wo on wo.id=wm.worker_order_id',
                    'field' => 'wm.*, wo.orno'
                ]);
                $worker = BaseModel::getInstance('worker')->getOne($worker_money_adjust_record['worker_id']);
                $title = "钱包余额调整";
                $content = "调整工单号:{$worker_money_adjust_record['orno']}<br>".
                           "调整原因:{$worker_money_adjust_record['adjust_remark']}<br>".
                           "调整金额:￥{$worker_money_adjust_record['adjust_money']}<br>".
                           "您钱包当前可提现金额为{$worker_money_adjust_record['worker_last_money']}元";
            } elseif ($event->data['type'] == AppMessageService::TYPE_QUALITY_MONEY_SET) {
                $worker_quality_money_record = BaseModel::getInstance('worker_quality_money_record')->getOne($event->data['data_id']);
                $worker = BaseModel::getInstance('worker')->getOne($worker_quality_money_record['worker_id']);
                $title = "质保金调整";
                $content = "调整原因:{$worker_quality_money_record['remark']}<br>".
                    "调整金额:￥{$worker_quality_money_record['quality_money']}<br>".
                    "您目前已缴纳质保金总额为￥{$worker_quality_money_record['last_quality_money']}元";
            }


            //消息记录
            $id = AppMessageService::create($worker['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
            if (!empty($id)) {
                //极光推送
                $jpush_content = str_replace('<br>', ' ', $content);
                workerNotificationJPush($worker['jpush_alias'], $event->data['type'], $id, $title, $jpush_content, $event->data['data_id']);
            }

            //企业号推送
            $text = $title."\n".$content;
            if (!$text) {
                return false;
            } else {
                $text = str_replace('<br>', "\n", $text);
            }
            sendQyWechatNotification($worker['worker_telephone'], $text, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WALLET'));
        } catch (\Exception $e) {

        }

    }

}
