<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/10
 * Time: 09:51
 */

namespace Common\Common\Service;

class ApplyCostService
{
    const TYPE_REMOTE_HOME_FEE = 1;
    const TYPE_COST_OF_ACCESSORIES = 2;
    const TYPE_COST_OF_DISASSEMBLE_AND_PACK = 3;
    const TYPE_COST_OF_OLD_MACHINE_RETURN_FACTORY = 4;
    const TYPE_OTHER = 4;

    const STATUS_CREATED_AND_NEED_CS_CHECK = 0;
    const STATUS_CS_CHECK_NOT_PASSED = 1;
    const STATUS_CS_CHECK_PASSED_AND_NEED_FACTORY_CHECK = 2;
    const STATUS_FACTORY_CHECK_NOT_PASSED = 3;
    const STATUS_FACTORY_CHECK_PASSED = 4;

    const STATUS_IS_ONGOING = [
        self::STATUS_CREATED_AND_NEED_CS_CHECK,
        self::STATUS_CS_CHECK_PASSED_AND_NEED_FACTORY_CHECK,
    ];
}