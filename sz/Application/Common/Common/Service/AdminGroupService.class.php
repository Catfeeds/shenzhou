<?php
/**
 * File: AdminGroupService.class.php
 * Function:
 * User: sakura
 * Date: 2018/2/20
 */

namespace Common\Common\Service;


class AdminGroupService
{

    //状态
    const IS_DISABLE_NO  = '0'; // 启用
    const IS_DISABLE_YES = '1'; // 禁用

    const IS_DISABLE_VALID_ARRAY = [
        self::IS_DISABLE_YES,
        self::IS_DISABLE_NO,
    ];

    //是否已删除
    const IS_DELETE_YES = 1; // 是
    const IS_DELETE_NO  = 0; // 否

    const IS_DELETE_VALID_ARRAY = [
        self::IS_DELETE_YES,
        self::IS_DELETE_NO,
    ];

}