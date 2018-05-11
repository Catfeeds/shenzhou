<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/10
 * Time: 11:15
 */

namespace Admin\Logic;

use Admin\Model\BaseModel;
use Common\Common\Service\WorkerAddApplyService;

class WorkerAddApplyLogic extends BaseLogic
{

    public function cancelUncompletedApply($worker_order_id)
    {
        BaseModel::getInstance('worker_add_apply')->update([
            'worker_order_id' => $worker_order_id,
            'status' => ['IN', [0, 4]]
        ], [
            'status' => WorkerAddApplyService::STATUS_CANCELED,
            'remark' => '工单取消导致的开单取消'
        ]);
    }

}