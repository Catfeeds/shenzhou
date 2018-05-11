<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/11
 * Time: 12:07
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Listeners\ReturneePayForWorker\SendNotificationToWorker;

class ReturneePayForWorkerEvent extends EventAbstract
{
    public $worker_order_id;

    protected $listeners = [
        SendNotificationToWorker::class,
    ];

    /**
     * ReturneePayForWorkerEvent constructor.
     * @param $worker_order_id
     */
    public function __construct($worker_order_id)
    {
        $this->worker_order_id = $worker_order_id;
    }

}