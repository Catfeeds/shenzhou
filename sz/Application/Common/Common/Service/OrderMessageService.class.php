<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/1
 * Time: 12:21
 */

namespace Common\Common\Service;

class OrderMessageService
{
    //发起留言角色
    const ADD_TYPE_CS = 1; // 客服
    const ADD_TYPE_FACTORY = 2; // 厂家
    const ADD_TYPE_FACTORY_ADMIN = 3;  // 厂家子账号
    const ADD_TYPE_FACTORY_WORKER = 4; // 技工
    const ADD_TYPE_FACTORY_WX_USER = 5; // 工单客户
    const ADD_TYPE_FACTORY_WX_DEALER = 6; // 经销商

    //接收留言角色
    const RECEIVE_TYPE_CS = 1;
    const RECEIVE_TYPE_FACTORY = 2;
    const RECEIVE_TYPE_FACTORY_ADMIN = 3;
    const RECEIVE_TYPE_FACTORY_WORKER = 4;
    const RECEIVE_TYPE_FACTORY_WX_USER = 5;
    const RECEIVE_TYPE_FACTORY_WX_DEALER = 6;
}