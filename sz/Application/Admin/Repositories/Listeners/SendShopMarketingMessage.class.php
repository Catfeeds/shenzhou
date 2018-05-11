<?php
/**
 * File: SendShopMarketingMessage.class.php
 * User: xieguoqiu
 * Date: 2017/3/16 16:26
 */

namespace Admin\Repositories\Listeners;

use Admin\Repositories\Events\SendShopMarketingMessageEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Library\Common\Util;

class SendShopMarketingMessage implements ListenerInterface
{
    /**
     * @param $event
     */
    public function handle(EventAbstract $event)
    {
        $news_list[] = [
            'title' => '送您30元代金劵，无需注册，一键到账',
            'description' => '恭喜您收到一个红包！！！',
            'image' => Util::getServerFileUrl('/Public/shop_news_image.jpg'),
            'url' => 'http://mp.weixin.qq.com/s?__biz=MzA3OTYwMTYwMA==&mid=2648558398&idx=1&sn=add3082865bfbed4caaac189df9c2daf&chksm=87986191b0efe887c6092fdb72bec128b6746d1350095a467e064f4c37aa4f9bcf827c167fed&mpshare=1&scene=1&srcid=0315ClvR55R516S4eppvHECy#rd'
        ];

        D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($event->open_id, $news_list, 'news');
    }

}
