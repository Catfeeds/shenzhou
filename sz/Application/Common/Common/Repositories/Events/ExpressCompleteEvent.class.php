<?php
/**
 * File: ExpressCompleteEvent.class.php
 * User: sakura
 * Date: 2017/11/10
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ExpressCompleteSendNotification;

class ExpressCompleteEvent extends EventAbstract
{

    protected $data;

    protected $listeners = [
        ExpressCompleteSendNotification::class,
    ];

    /**
     * ExpressCompleteEvent constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

}