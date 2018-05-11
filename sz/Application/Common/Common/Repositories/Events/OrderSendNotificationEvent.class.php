<?php
/**
 * File: OrderSettlementEvent.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 16:14
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\OrderSendNotification;

class OrderSendNotificationEvent extends EventAbstract
{
    public $data;

    protected $listeners = [
        OrderSendNotification::class,
    ];

    /**
     * OrderSendNotification constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
