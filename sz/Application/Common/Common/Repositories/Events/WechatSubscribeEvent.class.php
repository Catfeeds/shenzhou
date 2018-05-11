<?php
/**
 * File: SubscribeEvent.class.php
 * User: xieguoqiu
 * Date: 2017/3/16 16:30
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\WechatSubscribe\SendShopMarketingMessage;
use Common\Common\Repositories\Listeners\WechatSubscribe\SendSubscribeWelcomeMessage;

class WechatSubscribeEvent extends EventAbstract
{

    public $open_id;

    protected $listeners = [
        SendSubscribeWelcomeMessage::class,
        SendShopMarketingMessage::class,
    ];

    /**
     * SubscribeEvent constructor.
     * @param $open_id
     */
    public function __construct($open_id)
    {
        $this->open_id = $open_id;
    }


}
