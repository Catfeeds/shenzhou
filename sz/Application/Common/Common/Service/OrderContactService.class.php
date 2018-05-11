<?php
/**
 * File: OrderContactService.class.php
 * User: sakura
 * Date: 2017/11/15
 */

namespace Common\Common\Service;


class OrderContactService
{

    //联系方式
    const METHOD_PHONE  = 1; // 电话
    const METHOD_WEIXIN = 2; // 微信
    const METHOD_QQ     = 3; // QQ
    const METHOD_SMS    = 4; // 短信

    //联系类型
    const TYPE_DISTRIBUTE_CONSULT = 1; // 派单咨询
    const TYPE_ROUTINE            = 2; // 例行联系
    const TYPE_OFFER              = 3; // 维修报价
    const TYPE_TECHNOLOGY_CONSULT = 4; // 技术咨询
    const TYPE_SEARCH_WEBSITE     = 5; // 代找网点
    const TYPE_OTHER              = 6; // 其他

    //联系结果
    const RESULT_PASS     = 1; //可以
    const RESULT_NOT_PASS = 2; //不可用
    const RESULT_OTHER    = 3; // 其他

    //客服评估
    const REPORT_PASS    = 1; //可以合作
    const REPORT_THINK   = 2; // 考虑
    const REPORT_GIVE_UP = 3; // 不再考虑
    const REPORT_OTHER   = 4; // 其他

    //对象类型 对应object_type
    const OBJECT_TYPE_OTHER                 = 0; // 其他
    const OBJECT_TYPE_WORKER                = 1; // 维修商
    const OBJECT_TYPE_SHOPKEEPER            = 2; // 零售商
    const OBJECT_TYPE_SHOPKEEPER_AND_WORKER = 3; // 零售商兼维修商
    const OBJECT_TYPE_BUSINESS              = 4; // 商家
    const OBJECT_TYPE_VENDOR                = 5; // 批发商
    const OBJECT_TYPE_VENDOR_AND_WORKER     = 6; // 批发兼维修商


    public static function getMethodStr($constant)
    {
        switch ($constant) {
            case self::METHOD_PHONE:
                return '电话';
            case self::METHOD_WEIXIN:
                return '微信';
            case self::METHOD_QQ:
                return 'QQ';
            case self::METHOD_SMS:
                return '短信';
        }

        return '';
    }

    public static function getTypeStr($constant)
    {
        switch ($constant) {
            case self::TYPE_DISTRIBUTE_CONSULT:
                return '派单咨询';
            case self::TYPE_ROUTINE:
                return '例行联系';
            case self::TYPE_OFFER:
                return '维修报价';
            case self::TYPE_TECHNOLOGY_CONSULT:
                return '技术咨询';
            case self::TYPE_SEARCH_WEBSITE:
                return '代找网点';
            case self::TYPE_OTHER:
                return '其他';
        }

        return '';
    }

    public static function getResultStr($constant)
    {
        switch ($constant) {
            case self::RESULT_PASS:
                return '可以';
            case self::RESULT_NOT_PASS:
                return '不可用';
            case self::RESULT_OTHER:
                return '其他';
        }

        return '';
    }

    public static function getReportStr($constant)
    {
        switch ($constant) {
            case self::REPORT_PASS:
                return '可以合作';
            case self::REPORT_THINK:
                return '考虑';
            case self::REPORT_GIVE_UP:
                return '不再考虑';
            case self::REPORT_OTHER:
                return '其他';
        }

        return '';
    }

    public static function getObjectType($constant)
    {
        switch ($constant) {
            case self::OBJECT_TYPE_OTHER :
                return '其他';
            case self::OBJECT_TYPE_WORKER :
                return '维修商';
            case self::OBJECT_TYPE_SHOPKEEPER :
                return '零售商';
            case self::OBJECT_TYPE_SHOPKEEPER_AND_WORKER :
                return '零售商兼维修商';
            case self::OBJECT_TYPE_BUSINESS :
                return '商家';
            case self::OBJECT_TYPE_VENDOR :
                return '批发商';
            case self::OBJECT_TYPE_VENDOR_AND_WORKER :
                return '批发兼维修商';
        }

        return '';
    }

}