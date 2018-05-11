<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/30
 * Time: 21:18
 */

namespace Api\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;

class CreateOrderEvent extends EventAbstract
{
    public $order;

    public $listeners = [

    ];

    /**
     * CreateEvent constructor.
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }


}