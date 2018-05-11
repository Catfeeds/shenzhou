<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/4/27
 * Time: 19:49
 */

namespace Common\Common\Service;


class OrderExtInfoService
{
    const WORKER_GROUP_SET_TAG_SETTLEMENT_ON_WORKER_MEMBER = 1; // 已与群成员技工结算
    const WORKER_GROUP_SET_TAG_INDEX_KEY_VALUE = [
        self::WORKER_GROUP_SET_TAG_SETTLEMENT_ON_WORKER_MEMBER => 1,
    ];
}
