<?php
/**
 * File: WorkbenchEvent.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/29
 */

namespace Admin\Repositories\Events;


use Admin\Repositories\Listeners\WorkbenchListener;
use Common\Common\Repositories\Events\EventAbstract;

class WorkbenchEvent extends EventAbstract
{
    protected $listeners = [
        WorkbenchListener::class,
    ];

    public $data;

    /**
     * WorkbenchEvent constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
}