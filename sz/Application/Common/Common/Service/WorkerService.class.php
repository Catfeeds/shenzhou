<?php
/**
 * File: WorkerService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/22
 */

namespace Common\Common\Service;

class WorkerService
{
    const BANK_ID_OTHER = 659004728;

    const CHECK_PASS = 1;           // 正式 对应 is_check
    const CHECK_FORBIDDEN = 2;      // 停用 对应 is_check

    const IDENTIFY_POTENTIAL = 0;   // 潜在 对应 is_qianzai
    const IDENTIFY_OFFICIAL = 1;    // 正式 对应 is_qianzai

    const DATA_UNCHECKED = 2;       // 对应 is_complete_info 待审核
    const DATA_PASS = 1;            // 对应 is_complete_info 通过
    const DATA_NOT_PASS = 0;        // 对应 is_complete_info 不通过

    // 技工类型
    const TYPE_MEMBER_WORKER = 1;         // 普通技工
    const TYPE_GROUP_MASTER_WORKER = 2;   // 群主
    const TYPE_GROUP_MEMBER_WORKER = 3;   // 群成员
    // 群关联操作类型 group_apply_status
    const GROUP_APPLY_STATUS_NULL    = 0;   // 无操作
    const GROUP_APPLY_STATUS_MEMBER  = 1;   // 申请入群
    const GROUP_APPLY_STATUS_MASTER  = 2;   // 申请建群

    // 能接单客服派单的技工类型 distribute_time
    const ADMIN_DISTRIBUTE_TYPE_ARR = [
        self::TYPE_MEMBER_WORKER,
        self::TYPE_GROUP_MASTER_WORKER,
    ];
    // 不能接单客服派单的群关联操作类型
    const ADMIN_NOT_DISTRIBUTE_GROUP_APPLY_STATUS_ARR = [
        self::GROUP_APPLY_STATUS_MEMBER,
    ];

    // 技工资金变动类型
    const WORKER_MONEY_RECORD_REPAIR =          WorkerMoneyRecordService::TYPE_REPAIR_INCOME;           // 工单保内单（维修金收入）
    const WORKER_MONEY_RECORD_ADJUST =          WorkerMoneyRecordService::TYPE_REWARD_AND_PUNISH;       // 客服调整 (奖惩)
    const WORKER_MONEY_RECORD_WITHDRAWCASHING = WorkerMoneyRecordService::TYPE_WITHDRAW_APPLY;          // 提现中 (技工提现)
    const WORKER_MONEY_RECORD_WITHDRAWCASHED =  WorkerMoneyRecordService::TYPE_WITHDRAW_PASS;           // 提现成功 (技工提现)
    const WORKER_MONEY_RECORD_REPAIR_OUT =      WorkerMoneyRecordService::TYPE_REPAIR_OUT_ORDER;        // 工单保外单（维修金收入）
    const WORKER_MONEY_SYSTEM_ADJUST =          WorkerMoneyRecordService::TYPE_REPAIR_SYSTEM_ADJUST;    // 系统调整 (奖惩)
    const WORKER_MONEY_RECORD_QUALITY =         WorkerMoneyRecordService::WORKER_MONEY_RECORD_QUALITY;  // 质保金变动
    // 技工变动类型搜索类型(技工变动记录搜索用)
    const WORKER_MONEY_REPAIR_TYPE        = 1;          // 工单保内单（维修金收入）,工单保外单（维修金收入）
    const WORKER_MONEY_ADJUST_RECORD_TYPE = 2;          // 客服调整 (奖惩)、系统调整 (奖惩)
    const WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE = 3;    // 提现中 (技工提现)、提现成功 (技工提现)
    const WORKER_MONEY_RECORD_QUALITY_TYPE = 4;         // 质保金变动
    const WORKER_MONEY_RECORD_ALL_TYPE_VALUE = [
        self::WORKER_MONEY_REPAIR_TYPE,
        self::WORKER_MONEY_ADJUST_RECORD_TYPE,
        self::WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE,
        self::WORKER_MONEY_RECORD_QUALITY_TYPE,
    ];

    //派单排序状态
    const RECEIVE_STATUS_SHIELD = 0;    //屏蔽
    const RECEIVE_STATUS_NORMAL = 1;    //正常
    const RECEIVE_STATUS_RECOMMEND = 2; //置顶

}