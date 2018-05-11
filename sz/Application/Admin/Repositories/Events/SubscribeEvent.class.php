<?php
/**
 * File: SubscribeEvent.class.php
 * User: xieguoqiu
 * Date: 2017/3/16 16:30
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\SendShopMarketingMessage;
use Admin\Repositories\Listeners\SendSubscribeWelcomeMessage;
use Common\Common\Repositories\Events\EventAbstract;

class SubscribeEvent extends EventAbstract
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
