<?php
/**
 * File: WorkerExtractedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:35
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\WorkerTransactionEvent;
use Common\Common\Service\AppMessageService;
use Library\Common\Util;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;
use Common\Common\Model\BaseModel;

class WorkerTransactionSendNotification implements ListenerInterface
{
    /**
     * @param WorkerTransactionEvent $event
     * 交易消息事件 （工单由神州财务提交给厂家财务审核 触发 发送app与企业号消息）
     */
    public function handle(EventAbstract $event)
    {
        try {
            //结算消息
            // $order_info = BaseModel::getInstance('worker_order')->getOne($event->data['data_id']);
            // $worker = BaseModel::getInstance('worker')->getOne($order_info['worker_id'], 'worker_telephone, jpush_alias');
            // $order_fee_info = BaseModel::getInstance('worker_order_fee')->getOne($event->data['data_id']);

            $order_info     = $event->db_worker_order;
            $worker         = $event->db_worker_info;
            $service_type_name   = $event->getServiceTypeName($order_info['service_type']);

            //$order_fee_info = $event->db_order_fee;
            //$fee = $order_fee_info['worker_net_receipts'] + $order_fee_info['quality_fee'];

            $repair_money_info = $event->db_repair_money_record;
            $fee = $repair_money_info['order_money'];

            if ($repair_money_info['quality_money'] == '0.00') {
                $remark = "本次结算的费用已存入您的钱包";
            } else {
//                $remark = "由于您质保金尚未缴足,本次结算{$order_fee_info['worker_net_receipts']}元存入您的钱包,{$order_fee_info['quality_fee']}元存入您的质保金帐户";
                $remark = "由于您质保金尚未缴足,本次结算{$repair_money_info['netreceipts_money']}元存入您的钱包,{$repair_money_info['quality_fee']}元存入您的质保金帐户";
            }
            $title = '工单已结算';
            $content = "工单号:{$order_info['orno']}({$service_type_name})<br>".
                "结算费用:￥{$fee}<br>".
                "{$remark}<br>".
                "点击查看详情";

            //消息记录
            $id = AppMessageService::create($worker['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
            if (!empty($id)) {
                //极光推送
                $jpush_content = str_replace('<br>', ' ', $content);
                workerNotificationJPush($worker['jpush_alias'], $event->data['type'], $id, $title, $jpush_content, $event->data['data_id']);
            }

            //企业号推送
            $news = new NewsItem();
            $news->title = $title;
            $news->description  = str_replace('<br>', "\n", $content);
            $news->url = C('qiyewechat_host') . C('qy_base_path') . C('application_url.worker_order_base_url') . $event->data['data_id'];
            $message = Message::make('news')->item($news);
            sendQyWechatNotification($worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WALLET'));
        } catch (\Exception $e) {

        }

    }

}
