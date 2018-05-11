<?php
/**
 * File: AdminService.class.php
 * User: sakura
 * Date: 2017/11/21
 */

namespace Common\Common\Service;


class AdminService
{

    const STATE_ENABLED = '0';
    const STATE_FORBIDDEN = '1';
    const STATE_ALL_ARR = [
        self::STATE_ENABLED,
        self::STATE_FORBIDDEN,
    ];

}