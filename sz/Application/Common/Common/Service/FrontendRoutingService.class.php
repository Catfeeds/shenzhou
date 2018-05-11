<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/2/12
 * Time: 17:52
 */

namespace Common\Common\Service;


class FrontendRoutingService
{

    const IS_MENU_NO = '0'; // 不是菜单
    const IS_MENU_YES = '1'; // 是菜单
    const IS_MENU_VALID_ARRAY = [
        self::IS_MENU_NO,
        self::IS_MENU_YES,
    ];

    const IS_SHOW_NO = '0'; // 不显示菜单
    const IS_SHOW_YES = '1'; // 显示菜单
    const IS_SHOW_VALID_ARRAY = [
        self::IS_SHOW_NO,
        self::IS_SHOW_YES,
    ];

    const IS_DELETE_NO = '0'; // 未删除
}