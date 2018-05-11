<?php
/**
 * File: WorkerUnfinishedOrderEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 10:13
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\WorkerUnfinishedOrderSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class WorkerUnfinishedOrderEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        WorkerUnfinishedOrderSendNotification::class
    ];

    /**
     * WorkerUnfinishedOrderEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
