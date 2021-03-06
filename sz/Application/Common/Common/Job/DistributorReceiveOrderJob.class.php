<?php
/**
 * File: DistributorReceiveOrderJob.class.php
 * Function:
 * User: sakura
 * Date: 2018/2/6
 */

namespace Common\Common\Job;


use Admin\Logic\SystemReceiveOrderLogic;
use Admin\Repositories\Events\WorkbenchEvent;
use Carbon\Carbon;
use Library\Queue\Queue;

class DistributorReceiveOrderJob implements Queue
{

    protected $param = [];

    public function __construct($worker_order_id)
    {
        $this->param['worker_order_id'] = $worker_order_id;

    }

    public function getWorkerOrderId()
    {
        return $this->param['worker_order_id'];
    }

    public function handle()
    {
        $worker_order_id = $this->param['worker_order_id'];

        $param = [
            'worker_order_id'   => $worker_order_id,
            'receive_role_type' => C('AUTO_RECEIVE_ROLE_TYPE.DISTRIBUTOR'),
            'timestamp'         => time(),
        ];
        (new SystemReceiveOrderLogic())->workerOrderReceive($param);
        event(new WorkbenchEvent(['worker_order_id' => $worker_order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_DISTRIBUTOR_RECEIVE')]));
    }

}