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
use Common\Common\Repositories\Events\AccountCheckEvent;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;

class AccountCheckSendNotification implements ListenerInterface
{
    /*
     * @param AccountCheckEvent $event
     * worker_id 技工id
     * is_complete_info    是否完善资料 0不通过 1通过 2待审核
     */
    public function handle(EventAbstract $event)
    {
        try {
            //企业号推送
            $worker = BaseModel::getInstance('worker')->getOne($event->data['worker_id'], 'worker_telephone, jpush_alias');
            if ($event->data['is_complete_info'] == 1) {
                $news = new NewsItem();
                $news->title = '神州帮帮企业号使用指南';
                $news->description  = '点击可查看：接单、处理工单、查看结算费用等的使用操作方式';
                $news->url = 'https://qy.weixin.qq.com/cgi-bin/wap_getnewsmsg?action=get&__biz=MjM5OTU3MjkyOA==&mixuin=MjI4NDkyNTA1MDY3MzM1OTI1OQ==&mid=10000037&idx=1&sn=7d24d89a34f550b3a69a333e60908d28';
                $message = Message::make('news')->item($news);
                sendQyWechatNotification($worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_ID'));
            } else {
                $text = [
                    '您的帐户审核未通过，请重新完善帐户信息后再次提交',
                    '',
                    '您的帐户正在审核中，如有疑问请联系客服专员020-81316728。'
                ];
                sendQyWechatNotification($worker['worker_telephone'], $text[$event->data['is_complete_info']], C('SEND_NEWS_MESSAGE_APPLICATION_ID'));
            }
        } catch (\Exception $e) {

        }
    }

}
