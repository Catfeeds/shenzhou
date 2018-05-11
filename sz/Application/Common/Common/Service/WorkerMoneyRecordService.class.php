<?php
/**
 * File: WorkerMoneyRecordService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/26
 */

namespace Common\Common\Service;


class WorkerMoneyRecordService
{
    const TYPE_REPAIR_INCOME = 1;               // 工单维修金收入 (保内单)
    const TYPE_REWARD_AND_PUNISH = 2;           // 奖惩记录（客服手动调整钱包）
    const TYPE_WITHDRAW_APPLY = 3;              // 技工提现申请 (提现中)
    const TYPE_WITHDRAW_PASS = 4;               // 技工提现申请 (提现成功)
    const TYPE_REPAIR_OUT_ORDER = 5;            // 工单维修金收入 (保外单)
    const TYPE_REPAIR_SYSTEM_ADJUST = 6;        // 系统调整 (奖惩)
    const WORKER_MONEY_RECORD_QUALITY = 7;      // 质保金变动
}