<?php
/**
 * File: ProductFaultService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/30
 */

namespace Common\Common\Service;


class ProductFaultService
{

    const FAULT_TYPE_REPAIR   = 0; // 维修
    const FAULT_TYPE_MAINTAIN = 1; // 维护
    const FAULT_TYPE_INSTALL  = 2; // 安装

    const FAULT_TYPE_VALID_ARRAY
        = [
            self::FAULT_TYPE_REPAIR,
            self::FAULT_TYPE_MAINTAIN,
            self::FAULT_TYPE_INSTALL,
        ];

}