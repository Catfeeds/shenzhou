<?php
/**
 * File: CostOrderCheckEvent.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 15:00
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\CostOrderCheckSendNotification;

class CostOrderCheckEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        CostOrderCheckSendNotification::class
    ];

    /**
     * CostOrderCheckEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
