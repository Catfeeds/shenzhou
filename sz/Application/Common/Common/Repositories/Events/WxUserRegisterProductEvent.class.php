<?php
/**
 * File: RegisterProductEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 12:02
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\WxUserRegisterProduct\SendRegisterProductNotification;

class WxUserRegisterProductEvent extends EventAbstract
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
