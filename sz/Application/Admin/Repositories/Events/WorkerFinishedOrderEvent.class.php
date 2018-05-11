<?php
/**
 * File: WorkerFinishedOrderEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 9:33
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\WorkerFinishedOrderSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class WorkerFinishedOrderEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
//        WorkerFinishedOrderSendNotification::class
    ];

    /**
     * WorkerFinishedOrderEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
