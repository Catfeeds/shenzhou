<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/27
 * Time: 16:21
 */

namespace Common\Common\Repositories\Events;


use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Listeners\PushFactoryOrderAppontRecord;
use Common\Common\Service\OrderOperationRecordService;

class PushFactoryOrderRecordAppointEvent extends EventAbstract
{

    public $appoint;
    public $record;
    public $xf_order_id;
    public $ext_info;

    protected $listeners = [
        PushFactoryOrderAppontRecord::class,
    ];

    public function __construct($ext_info, $record, $appoint)
    {
        $this->ext_info = $ext_info;
        $id = explode('-', $ext_info['out_trade_number']);
        $this->xf_order_id = $id[2];

        $record['operator'] = OrderOperationRecordService::getUserTypeName($record['operation_type']);
        $records = [$record];
        OrderOperationRecordService::loadAddUserInfo($records);

        $this->record = reset($records);
        $this->appoint = $appoint;
    }

}
