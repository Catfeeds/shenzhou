<?php
/**
 * File: FactoryOrderToPlatformEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 16:23
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\FactoryOrderToPlatformSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class FactoryOrderToPlatformEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        FactoryOrderToPlatformSendNotification::class
    ];

    /**
     * UserAddOrderEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
