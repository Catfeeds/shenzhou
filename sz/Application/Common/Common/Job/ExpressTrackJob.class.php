<?php
/**
 * File: ExpressTrackJob.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/26
 */

namespace Common\Common\Job;


use Common\Common\Logic\ExpressTrackingLogic;
use Library\Queue\Queue;

class ExpressTrackJob implements Queue
{

    protected $param = [];

    public function __construct($express_code, $express_number, $data_id, $type)
    {
        $this->param['express_code'] = $express_code;
        $this->param['express_number'] = $express_number;
        $this->param['data_id'] = $data_id;
        $this->param['type'] = $type;
    }

    public function handle()
    {
        $express_code = $this->param['express_code'] ;
        $express_number = $this->param['express_number'] ;
        $data_id = $this->param['data_id'] ;
        $type = $this->param['type'] ;

        M()->startTrans();
        (new ExpressTrackingLogic())->setExpressTrack($express_code, $express_number, $data_id, $type);
        M()->commit();
    }

}