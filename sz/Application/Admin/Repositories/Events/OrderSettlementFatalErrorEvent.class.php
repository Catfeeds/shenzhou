<?php
/**
 * File: OrderSettlementFatalErrorEvent.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/15
 */

namespace Admin\Repositories\Events;


use Admin\Repositories\Listeners\OrderFatalErrorLog;
use Common\Common\Repositories\Events\EventAbstract;

class OrderSettlementFatalErrorEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        OrderFatalErrorLog::class
    ];

    /**
     * OrderCancelEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}