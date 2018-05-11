<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/14
 * Time: 11:28
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Repositories\Listeners\PushXinYingYanOrderStatus;

class PushXinYingYanOrderStatusChangeEvent extends EventAbstract
{

    public $order_ids;
    public $type;

    protected $listeners = [
        PushXinYingYanOrderStatus::class,
    ];

    public function __construct($order_ids)
    {
        $this->order_ids = $order_ids;
    }

}
