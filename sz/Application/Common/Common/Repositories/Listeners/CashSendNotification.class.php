<?php
/**
 * File: WorkerExtractedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:35
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\CashEvent;
use Common\Common\Service\AppMessageService;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;
use Common\Common\Model\BaseModel;

class CashSendNotification implements ListenerInterface
{
    /**
     * @param CashEvent $event
     * 交易消息事件
     */
    public function handle(EventAbstract $event)
    {
        try {
            //提现消息
            $worker_withdrawcash_record = BaseModel::getInstance('worker_withdrawcash_record')->getOne([
                'id' => $event->data['data_id']
            ]);
            $worker = BaseModel::getInstance('worker')->getOne([
                'worker_id' => $worker_withdrawcash_record['worker_id']
            ]);

            $date = date('m月d日H:i', $worker_withdrawcash_record['create_time']);
            $card_number = substr($worker_withdrawcash_record['card_number'], -4);
            $last_money = BaseModel::getInstance('worker_money_adjust_record')->getFieldVal([
                'where' => [
                    'worker_id' => $worker_withdrawcash_record['worker_id']
                ],
                'order' => 'create_time desc'
            ], 'worker_last_money');// todo
            if ($event->data['type'] == AppMessageService::TYPE_CASHING) {
                $title = '提现中';
                $content = "您在{$date}的申请提现的{$worker_withdrawcash_record['out_money']}已受理，根据各银行汇款时效，将于2-48小时内汇入您账号所绑定的银行卡中";
            } elseif ($event->data['type'] == AppMessageService::TYPE_CASH_SUCCESS) {
                $title = '提现成功';
                $content = "您在{$date}的申请提现的{$worker_withdrawcash_record['out_money']}已汇入您尾号****{$card_number}的银行帐户，敬请留意";
            } elseif ($event->data['type'] == AppMessageService::TYPE_CASH_FAIL) {
                $title = '提现失败';
                $last_money = $last_money + $worker_withdrawcash_record['out_money'];
                $content = "您在{$date}的申请提现的{$worker_withdrawcash_record['out_money']}未转账成功，原因为:{$worker_withdrawcash_record['fail_reason']};本次提现的金额已返还至您的钱包，钱包当前可提现金额为{$last_money}元";
            } else {
                return false;
            }

            //消息记录
            $id = AppMessageService::create($worker_withdrawcash_record['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
            if (!empty($id)) {
                //极光推送
                workerNotificationJPush($worker['jpush_alias'], $event->data['type'], $id, $title, $content, $event->data['data_id']);
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
