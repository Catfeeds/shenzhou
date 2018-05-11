<?php
/**
 * File: OrderCancelEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 11:07
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\OrderCancelSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class OrderCancelEvent extends EventAbstract
{
    public $data;

    protected $listeners = [
        OrderCancelSendNotification::class
    ];

    /**
     * OrderCancelEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
