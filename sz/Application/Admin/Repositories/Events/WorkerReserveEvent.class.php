<?php
/**
 * File: WorkerReserveEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 17:07
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\WorkerReserveSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class WorkerReserveEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        WorkerReserveSendNotification::class
    ];

    /**
     * WorkerReserveEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
