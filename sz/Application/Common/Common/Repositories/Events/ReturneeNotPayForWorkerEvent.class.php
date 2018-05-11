<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/11
 * Time: 14:51
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Listeners\ReturneeNotPayForWorker\SendNotificationToWorker;

class ReturneeNotPayForWorkerEvent extends EventAbstract
{
    public $worker_order_id;
    protected $listeners = [
        SendNotificationToWorker::class,
    ];

    /**
     * ReturneeNotPayForWorkerEvent constructor.
     * @param $worker_order_id
     */
    public function __construct($worker_order_id)
    {
        $this->worker_order_id = $worker_order_id;
    }

}