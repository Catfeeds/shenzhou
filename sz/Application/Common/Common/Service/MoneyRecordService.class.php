<?php
/**
 * Created by PhpStorm.
 * User: 嘉诚
 * Date: 2017/12/22
 * Time: 10:52
 */

namespace Common\Common\Service;

use Common\Common\Model\BaseModel;

class MoneyRecordService
{
    //交易记录状态

    const TYPE_MAINTENANCE_MONEY_INCOME  = 1; //工单维修金收入
    const TYPE_REWARD_AND_PUNISHMENT     = 2; //奖惩记录（客服手动调整钱包）
    const TYPE_WORKER_APPLY_CASH         = 3; //技工提现申请(提现中)
    const TYPE_WORKER_APPLY_CASH_SUCCESS = 4; //技工提现申请(提现成功)
    const TYPE_WORKER_WARRANTY_INCOME    = 5; //工单保外单收入

    //维修收入
    const TYPE_INCOME = [
        self::TYPE_MAINTENANCE_MONEY_INCOME,
        self::TYPE_WORKER_WARRANTY_INCOME
    ];

    public static function insertMoneyRecord($order_id, $type)
    {
        $money_record_model = BaseModel::getInstance('worker_money_record');
        $order_info = BaseModel::getInstance('worker_order')->getOne(['id'=>$order_id]);
        $fee_info   = BaseModel::getInstance('worker_order_fee')->getOne(['worker_order_id'=>$order_id]);
        if (in_array($type, self::TYPE_INCOME)) {
            //如果是维修收入，需要计算质保金
            $worker_info = BaseModel::getInstance('worker')->getOne([
                'where' => [
                    'worker_order' => $order_info['worker_id']
                ],
                'field' => 'quality_money, quality_money_need'
            ]);
            //技工还需要交的质保金
            $need_qualit_money = $worker_info['quality_money_need'] - $worker_info['quality_money'];
            if ($need_qualit_money > 0) {
                $money_record_model->insert([
                    'worker_id' => $order_info['worker_id']
                ]);
            }
        }
    }

}