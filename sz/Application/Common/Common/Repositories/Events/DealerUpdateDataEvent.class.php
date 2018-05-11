<?php
/**
 * File: DealerUpdateDataEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/25 10:29
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Listeners\FactoryRecheckDealer;
use Common\Common\Repositories\Events\EventAbstract;

class DealerUpdateDataEvent extends EventAbstract
{

    protected $listeners = [
        FactoryRecheckDealer::class,
    ];

    public $data;

    public $dealer_phone;

    /**
     * DealerUpdateDataEvent constructor.
     * @param $dealer_phone
     * @param $data
     */
    public function __construct($dealer_phone, $data)
    {
        $this->dealer_phone = $dealer_phone;
        $this->data = $data;
    }

}
