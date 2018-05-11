<?php
/**
 * File: RegisterProductEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 12:02
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\SendRegisterProductNotification;
use Common\Common\Repositories\Events\EventAbstract;

class RegisterProductEvent extends EventAbstract
{

    public $code;

    public $data;

    protected $listeners = [
        SendRegisterProductNotification::class
    ];

    /**
     * RegisterProductEvent constructor.
     * @param $code
     * @param $data
     */
    public function __construct($code, $data)
    {
        $this->code = $code;
        $this->data = $data;
    }


}
