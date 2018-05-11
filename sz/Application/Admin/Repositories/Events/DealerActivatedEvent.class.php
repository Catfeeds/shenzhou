<?php
/**
 * File: DealerActivatedEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 14:44
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\DealerActivatedSendNotification;
use Common\Common\Repositories\Events\EventAbstract;

class DealerActivatedEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        DealerActivatedSendNotification::class
    ];

    /**
     * DealerActivatedEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }


}
