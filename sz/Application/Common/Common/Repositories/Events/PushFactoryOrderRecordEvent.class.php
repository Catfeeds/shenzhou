<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/27
 * Time: 16:21
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Repositories\Listeners\PushFactoryOrderRecord;

class PushFactoryOrderRecordEvent extends EventAbstract
{

    public $ext_info;
    public $record;

    protected $listeners = [
        PushFactoryOrderRecord::class,
    ];

    public function __construct($ext_info, $record)
    {
        $this->ext_info = $ext_info;
        $this->record= $record;
    }

}
