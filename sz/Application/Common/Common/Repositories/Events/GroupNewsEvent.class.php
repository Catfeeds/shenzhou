<?php
/**
 * File: AccessoryCheckEvent.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 10:23
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\GroupNewsSendNotification;

class GroupNewsEvent extends EventAbstract
{
    public $data;

    protected $listeners = [
        GroupNewsSendNotification::class
    ];

    /**
     * AccessoryCheckEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
