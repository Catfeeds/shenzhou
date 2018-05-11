<?php
/**
 * File: OrderCancelEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 11:07
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\OrderCancel\OrderCancelSendNotification;

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
