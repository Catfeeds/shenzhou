<?php
/**
 * File: PromptComplaintToEvent.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/9
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Repositories\Listeners\ComplaintToNotification;

class PromptComplaintToEvent extends EventAbstract
{

    public $data;

    protected $listeners = [
        ComplaintToNotification::class,
    ];

    /**
     * WorkerExtractedEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}