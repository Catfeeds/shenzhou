<?php
/**
 * File: WorkerFinishedOrderEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 9:33
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\WorkerCheckSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class WorkerCheckEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        WorkerCheckSendNotification::class
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
