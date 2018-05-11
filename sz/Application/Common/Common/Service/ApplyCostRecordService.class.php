<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/10
 * Time: 10:18
 */

namespace Common\Common\Service;

use Common\Common\Model\BaseModel;

class ApplyCostRecordService
{
    const TYPE_CS_CHECK_NOT_PASSED = 1001;

    const TYPE_FACTORY_CHECK_NOT_PASSED = 2001;

    const TYPE_OPERATION_CONTENT = [
        self::TYPE_CS_CHECK_NOT_PASSED => '审核费用单(审核费用单)',
        self::TYPE_FACTORY_CHECK_NOT_PASSED => '审核费用单（审核不通过）',
    ];

    public static function create($worker_order_apply_cost_id, $type, $extras = [])
    {
        BaseModel::getInstance('worker_order_apply_cost_record')->insert([
            'worker_order_apply_cost_id' => $worker_order_apply_cost_id,
            'create_time' => NOW_TIME,
            'user_id' => $extras['operator_id'] ?? AuthService::getAuthModel()->getPrimaryValue(),
            'user_type' => $type,
            'operation_content' => self::TYPE_OPERATION_CONTENT[$type],
            'operation_remark' => $extras['remark'] ?? '',
        ]);
    }
}