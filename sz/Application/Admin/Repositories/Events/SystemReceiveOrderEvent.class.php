<?php
/**
 * File: SystemReceiveOrderEvent.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/29
 */

namespace Admin\Repositories\Events;


use Admin\Repositories\Listeners\SystemReceiveOrderListener;
use Common\Common\Repositories\Events\EventAbstract;

class SystemReceiveOrderEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        SystemReceiveOrderListener::class,
    ];

    /**
     * SystemReceiveOrderEvent constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}