<?php
/**
 * File: FactoryOrderToSelfEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 16:27
 */

namespace Api\Repositories\Events;

use Admin\Repositories\Listeners\FactoryOrderToSelfSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class FactoryOrderToSelfEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        FactoryOrderToSelfSendNotification::class
    ];

    /**
     * FactoryOrderToSelfEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
