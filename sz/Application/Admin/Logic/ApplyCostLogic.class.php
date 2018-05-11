<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/10
 * Time: 09:39
 */

namespace Admin\Logic;

use Admin\Model\BaseModel;
use Common\Common\Service\ApplyCostRecordService;
use Common\Common\Service\ApplyCostService;
use Common\Common\Service\AuthService;

class ApplyCostLogic extends BaseLogic
{
    public function cancelOrderApplyCost($worker_order_id)
    {
        $update_data = [
            'last_update_time' => NOW_TIME,
        ];
        if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
            $update_data['status'] = ApplyCostService::STATUS_CS_CHECK_NOT_PASSED;
            $record_status = ApplyCostRecordService::TYPE_CS_CHECK_NOT_PASSED;
        } else {
            $update_data['status'] = ApplyCostService::STATUS_FACTORY_CHECK_NOT_PASSED;
            $record_status = ApplyCostRecordService::TYPE_FACTORY_CHECK_NOT_PASSED;
        }
        $worker_order_apply_cost_ids = BaseModel::getInstance('worker_order_apply_cost')->getFieldVal([
            'worker_order_id' => $worker_order_id,
            'status' => ['IN', [1, 3]],
        ], 'id');
        if ($worker_order_apply_cost_ids) {
            BaseModel::getInstance('worker_order_apply_cost')->update(['id' => ['IN', $worker_order_apply_cost_ids]], $update_data);
            foreach ($worker_order_apply_cost_ids as $worker_order_apply_cost_id) {
                ApplyCostRecordService::create($worker_order_apply_cost_id, $record_status, [
                    'remark' => '工单取消导致的费用单取消'
                ]);
            }
        }
    }
}