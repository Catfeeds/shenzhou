<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/2
 * Time: 17:07
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Repositories\Listeners\WorkerReceiveOrder\SendNotificationToWorker;

class WorkerReceiveOrderEvent extends EventAbstract
{
    public $worker_order_id;

    protected $listeners = [
        SendNotificationToWorker::class,
    ];

    /**
     * DistributeOrderToWorkerEvent constructor.
     * @param $worker_order_id
     */
    public function __construct($worker_order_id)
    {
        $this->worker_order_id = $worker_order_id;
    }


}