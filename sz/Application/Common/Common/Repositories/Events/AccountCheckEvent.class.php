<?php
/**
 * File: AccessoryCheckEvent.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 10:23
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\AccountCheckSendNotification;

class AccountCheckEvent extends EventAbstract
{
    public $data;

    protected $listeners = [
        AccountCheckSendNotification::class
    ];

    /**
     * AccountCheckEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
