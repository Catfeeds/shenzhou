<?php
/**
 * File: WorkerExtractedEvent.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:19
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\CashSendNotification;

class CashEvent extends EventAbstract
{
    public $data;

    protected $listeners = [
        CashSendNotification::class,
    ];

    /**
     * WorkerExtractedEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
