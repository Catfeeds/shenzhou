<?php
/**
 * File: SendSubscribeWelcomeMessage.class.php
 * User: xieguoqiu
 * Date: 2017/3/16 17:48
 */

namespace Admin\Repositories\Listeners;

use Admin\Repositories\Events\SubscribeEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class SendSubscribeWelcomeMessage implements ListenerInterface
{
    /**
     * @param SubscribeEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $text = '欢迎关注“家电帮帮”，家电售后更轻松！消费者扫码激活的产品自动保存在“我的家电库”里，随时可查询产品说明和质保期限，需要售后服务时可以一键申请！';

        D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($event->open_id, $text, 'text');
    }

}
