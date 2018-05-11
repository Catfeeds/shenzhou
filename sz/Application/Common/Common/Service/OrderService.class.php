<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/31
 * Time: 12:31
 */

namespace Common\Common\Service;

use Common\Common\Model\BaseModel;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Common\Common\Service\FaultTypeService;

class OrderService
{
    // 技工默认保险费
    const INSURANCE_FEE_DEFAULT_VALUE = 1.00;

    // out_platform 外部平台
    const OUT_PLATFORM_XINYINGYAN = 1;

    // 服务类型
    const TYPE_WORKER_REPAIR            = 107;
    const TYPE_WORKER_INSTALLATION      = 106;
    const TYPE_PRE_RELEASE_INSTALLATION = 110;
    const TYPE_USER_SEND_FACTORY_REPAIR = 109;
    const TYPE_WORKER_MAINTENANCE       = 108;

    // 工单分类
    // 普通工单
    const CLASSIFICATION_COMMON_ORDER_PREFIX = 'A';
    // 返修单
    const CLASSIFICATION_REWORK_ORDER_PREFIX = 'F';

    const CLASSIFICATION_COMMON_ORDER_TYPE = '1';
    const CLASSIFICATION_REWORK_ORDER_TYPE = '2';

    // 技工支付状态
    const IS_WORKER_PAY_NOT_PAY = 0;   // 未支付
    const IS_WORKER_PAY_IS_PAY = 1;    // 已支付

    // 属于安装的服务类型
    const SERVICE_TYPE_INSTALLATION_TYPE_LIST = [
        self::TYPE_WORKER_INSTALLATION,
        self::TYPE_PRE_RELEASE_INSTALLATION,
    ];

    // 服务类型名称
    const SERVICE_TYPE
        = [
            self::TYPE_WORKER_REPAIR            => '上门维修',
            self::TYPE_WORKER_INSTALLATION      => '上门安装',
            self::TYPE_PRE_RELEASE_INSTALLATION => '预发件安装',
            self::TYPE_USER_SEND_FACTORY_REPAIR => '用户送修',
            self::TYPE_WORKER_MAINTENANCE       => '上门维护',
        ];
    // 服务类型标示value
    const SERVICE_TYPE_VALUE_FOR_APP
        = [
            self::TYPE_WORKER_REPAIR            => 1,
            self::TYPE_WORKER_INSTALLATION      => 2,
            self::TYPE_PRE_RELEASE_INSTALLATION => 2,
            self::TYPE_USER_SEND_FACTORY_REPAIR => 3,
            self::TYPE_WORKER_MAINTENANCE       => 4,
        ];
    // 服务类型名称缩写
    const SERVICE_TYPE_SHORT_NAME_FOR_APP
        = [
            self::TYPE_WORKER_REPAIR            => '维修',
            self::TYPE_WORKER_INSTALLATION      => '安装',
            self::TYPE_PRE_RELEASE_INSTALLATION => '安装',
            self::TYPE_USER_SEND_FACTORY_REPAIR => '送修',
            self::TYPE_WORKER_MAINTENANCE       => '维护',
        ];

    // 服务类型对应的维修项的类型
    const SERVICE_TYPE_FRO_FAULT_TYPE_ARR
        = [
            self::TYPE_WORKER_REPAIR            => FaultTypeService::REPAIR_PRODUCT,
            self::TYPE_WORKER_INSTALLATION      => FaultTypeService::INSTALLATION_PRODUCT,
            self::TYPE_PRE_RELEASE_INSTALLATION => FaultTypeService::INSTALLATION_PRODUCT,
            self::TYPE_USER_SEND_FACTORY_REPAIR => FaultTypeService::REPAIR_PRODUCT,
            self::TYPE_WORKER_MAINTENANCE       => FaultTypeService::MAINTENANCE_PRODUCT,
        ];

    // 工单产品维修项修改次数限制
    const ORDER_PRODUCT_ADMIN_EDIT_FAULT_TIMES = 2;

    // distribute_mode 选择技工的派发模式
    const DISTRIBUTE_MODE_FRIST_WORKER = '0'; // 首选技工
    const DISTRIBUTE_MODE_WORKER_ORDER_POOL = '1'; // 抢单池
    const DISTRIBUTE_MODE_CHOOSE_WORKER = '2'; // 指定维修商
    const DISTRIBUTE_MODE_WORKER_REPAIR_AGAIN = '3'; // 母工单派单技工,返修单
    const DISTRIBUTE_MODE_MASTER_CODE_WORKER = '4'; // 师傅码扫码

    // 核实厂家类型
    const FACTORY_CHECK_ORDER_TYPE_FACTORY = '1';       // 厂家账号
    const FACTORY_CHECK_ORDER_TYPE_FACTORY_ADMIN = '2'; // 厂家子账号

    // 工单来源
    const ORIGIN_TYPE_FACTORY       = 1;
    const ORIGIN_TYPE_FACTORY_ADMIN = 2;
    const ORIGIN_TYPE_OUTER_USER    = 3;
    const ORIGIN_TYPE_WX_USER       = 4;
    const ORIGIN_TYPE_WX_DEALER     = 5;

    // 工单保内外类型
    const ORDER_TYPE_FACTORY_IN_INSURANCE         = 1;
    const ORDER_TYPE_FACTORY_OUT_INSURANCE        = 2;
    const ORDER_TYPE_FACTORY_EXPORT_IN_INSURANCE  = 3;
    const ORDER_TYPE_FACTORY_EXPORT_OUT_INSURANCE = 4;
    const ORDER_TYPE_WX_USER_IN_INSURANCE         = 5;
    const ORDER_TYPE_WX_USER_OUT_INSURANCE        = 6;
    const ORDER_TYPE_PLATFORM_OUT_INSURANCE       = 7;
    const ORDER_TYPE_WEIXIN_OUT_INSURANCE         = 8;
    const ORDER_TYPE_REWORK_IN_INSURANCE          = 9;
    const ORDER_TYPE_REWORK_OUT_INSURANCE         = 10;
    const ORDER_TYPE_MASTER_CODE_OUT_INSURANCE    = 11; // 师傅码保外

    // 保内单类型数组
    const ORDER_TYPE_IN_INSURANCE_LIST
        = [
            self::ORDER_TYPE_FACTORY_IN_INSURANCE,
            self::ORDER_TYPE_FACTORY_EXPORT_IN_INSURANCE,
            self::ORDER_TYPE_WX_USER_IN_INSURANCE,
            self::ORDER_TYPE_REWORK_IN_INSURANCE,
        ];

    //保外单类型数组
    const ORDER_TYPE_OUT_INSURANCE_LIST = [
        self::ORDER_TYPE_FACTORY_OUT_INSURANCE,
        self::ORDER_TYPE_FACTORY_EXPORT_OUT_INSURANCE,
        self::ORDER_TYPE_WX_USER_OUT_INSURANCE,
        self::ORDER_TYPE_PLATFORM_OUT_INSURANCE,
        self::ORDER_TYPE_WEIXIN_OUT_INSURANCE,
        self::ORDER_TYPE_REWORK_OUT_INSURANCE,
    ];

    //保外可申请配件单数组
    const ORDER_TYPE_OUT_ACCESSORY_LIST = [
        self::ORDER_TYPE_FACTORY_OUT_INSURANCE,
        self::ORDER_TYPE_FACTORY_EXPORT_OUT_INSURANCE,
        self::ORDER_TYPE_WX_USER_OUT_INSURANCE,
    ];

    // 取消类型
    const CANCEL_TYPE_NULL          = 0;      // 未取消
    const CANCEL_TYPE_WX_USER       = 1;      // C端用户取消
    const CANCEL_TYPE_WX_DEALER     = 2;    // C端经销商取消
    const CANCEL_TYPE_FACTORY       = 3;      // 厂家取消
    const CANCEL_TYPE_CS            = 4;           // 客服取消
    const CANCEL_TYPE_CS_STOP       = 5;      // 客服终止工单（可结算）
    const CANCEL_TYPE_FACTORY_ADMIN = 6;      // 厂家子账号取消

    // 工单取消类型 不是取消状态的状态
    const CANCEL_TYPE_NOT_CALCEL_LIST = [
        self::CANCEL_TYPE_NULL,
        self::CANCEL_TYPE_CS_STOP,
    ];

    // 保外单支付类型
    const USER_PAY_TYPE_FOR_WECHAT = 1;    //保外单微信支付
    const USER_PAY_TYPE_FOR_CASH   = 2;    //保外单现金支付

    // 保外单支付状态
    const USER_PAY_TYPE_FOR_TURE   = 1;    //保外单已支付

    /**
     * 0创建工单,1自行处理,2外部工单经过厂家审核（待平台核实客服接单）,3平台核实客服接单（待平台核实客服核实信息），4平台核实客服核实用户信息
     * （待平台派单客服接单）,5平台派单客服接单 （待派发）,6已派发 （抢单池）,7技工接单成功 （待技工预约上门）,8预约成功
     * （待上门服务）,9已上门（待全部维修项完成维修）,10完成维修 （待平台回访客服接单）,11平台回访客服接单
     * （待回访）,12平台回访客服回访不通过 （已上门）,13平台回访客服已回访 （待平台财务客服接单）,14平台财务客服接单
     * （待平台财务客服审核）,15平台财务客服审核不通过 （重新回访客服回访）,16平台财务客服审核 （待厂家财务审核）,17厂家财务审核不通过
     * （平台财务重新审核）,18厂家财务审核 （已完成工单）
     */
    const STATUS_CREATED                                                            = 0;    // 0创建工单
    const STATUS_FACTORY_SELF_PROCESSED                                             = 1;    // 1自行处理
    const STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE                         = 2;    // 2外部工单经过厂家审核（待平台核实客服接单）
    const STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK                            = 3;    // 3平台核实客服接单（待平台核实客服核实信息）
    const STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE                       = 4;    // 4平台核实客服核实用户信息 （待平台派单客服接单）
    const STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE               = 5;    // 5平台派单客服接单 （待派发）
    const STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL                        = 6;    // 6已派发 （抢单池）
    const STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT = 7;    // 7技工接单成功 （待技工预约上门）
    const STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE                           = 8;    // 8预约成功 （待上门服务）
    const STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE                     = 9;    // 9已上门（待全部维修项完成维修）
    const STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE                    = 10;   // 10完成维修 （待平台回访客服接单）
    const STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT                          = 11;   // 11平台回访客服接单 （待回访）
    const STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE           = 12;   // 12平台回访客服回访不通过 （已上门）
    const STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE                          = 13;   // 13平台回访客服已回访 （待平台财务客服接单）
    const STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT                            = 14;   // 14平台财务客服接单 （待平台财务客服审核）
    const STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT                   = 15;   // 15平台财务客服审核不通过 （重新回访客服回访）
    const STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT                             = 16;   // 16平台财务客服审核 （待厂家财务审核）
    const STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT                    = 17;   // 17厂家财务审核不通过 （平台财务重新审核）
    const STATUS_FACTORY_AUDITED_AND_FINISHED                                       = 18;   // 18厂家财务审核 （已完成工单）
    // 状态的中文别名
    const WORKER_ORDER_STATUS_CHINESE_NAME_ARR = [
        self::STATUS_CREATED                                                            => '创建工单',
        self::STATUS_FACTORY_SELF_PROCESSED                                             => '自行处理',
        self::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE                         => '外部工单经过厂家审核（待平台核实客服接单）',
        self::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK                            => '平台核实客服接单（待平台核实客服核实信息）',
        self::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE                       => '平台核实客服核实用户信息 （待平台派单客服接单）',
        self::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE               => '平台派单客服接单 （待派发）',
        self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL                        => '已派发 （抢单池）',
        self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT => '技工接单成功 （待技工预约上门）',
        self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE                           => '预约成功 （待上门服务）',
        self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE                     => '已上门（待全部维修项完成维修）',
        self::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE                    => '完成维修 （待平台回访客服接单）',
        self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT                          => '平台回访客服接单 （待回访）',
        self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE           => '平台回访客服回访不通过 （已上门）',
        self::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE                          => '平台回访客服已回访 （待平台财务客服接单）',
        self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT                            => '平台财务客服接单 （待平台财务客服审核）',
        self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT                   => '平台财务客服审核不通过 （重新回访客服回访）',
        self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT                             => '平台财务客服审核 （待厂家财务审核）',
        self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT                    => '厂家财务审核不通过 （平台财务重新审核）',
        self::STATUS_FACTORY_AUDITED_AND_FINISHED                                       => '厂家财务审核 （已完成工单）',

    ];

    // 客服申请配件单时。工单的状态
    const CS_APPLY_ACCESSORY_WORKER_ORDER_STATUS_LIST = [
        self::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
        self::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
        self::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
        self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL,
        self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
        self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
        self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
        self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
    ];

    // 转单的时候工单状态数据 核实客服 的订单状态 (核实合法状态)
    const DELEGATE_CHECKED_VALID_STATUS_LIST = [
        self::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
    ];
    // 转单的时候工单状态数据 派单客服 的订单状态 (派单合法状态)
    const DELEGATE_DISTRIBUTOR_VALID_STATUS_LIST = [
        self::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
        self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL,
        self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
        self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
        self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
        self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
    ];
    // 转单的时候工单状态数据 回访客服 的订单状态 (回访合法状态)
    const DELEGATE_RETURNEE_VALID_STATUS_LIST = [
        self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
        self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
    ];
    // 转单的时候工单状态数据 财务客服 的订单状态 (财务合法状态)
    const DELEGATE_AUDITOR_VALID_STATUS_LIST = [
        self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
        self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
        self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
    ];

    // 允许回访操作的状态
    const CAN_RETURNEE_RETURN_WORKER_ORDER_STATUS_ARRAY
        = [
            self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
            self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
        ];

    // 允许平台财务操作的状态
    const CAN_AUDITOR_AUDITED_WORKER_ORDER_STATUS_ARRAY
        = [
            self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
        ];

    const CS_CANCEL_REASON
        = [
            1 => '没网点',
            2 => '用户原因',
            3 => '厂家原因',
            4 => '其他',
        ];
    const FACTORY_CANCEL_REASON
        = [
            1 => '用户自行处理好了',
            2 => '信息填写错误',
            3 => '重复下单',
            4 => '其他',
        ];

    public static function generateOrno($classification = self::CLASSIFICATION_COMMON_ORDER_PREFIX)
    {
        $timeStr = date('ymdHi');
        $randStr = mt_rand(0, 99999);
        $randStr = str_pad($randStr, 5, '0', STR_PAD_LEFT);
        $orno = $classification . $timeStr . $randStr;

        $condition = [];
        $condition['orno'] = ['eq', $orno];

        $od = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'orno' => $orno,
            ],
            'field' => 'id',
        ]);

        if ($od) {
            return self::generateOrno();
        } else {
            return $orno;
        }
    }

    public static function getServiceByTypes($types)
    {
        if (!is_array($types)) {
            $types = explode(',', $types);
        }
        $service_type = [];
        foreach ($types as $type) {
            if (isset(self::SERVICE_TYPE[$type])) {
                $service_type[] = [
                    'id'   => $type,
                    'name' => self::SERVICE_TYPE[$type],
                ];
            }
        }

        return $service_type;
    }

    public static function getFactoryServiceByTypes($types)
    {
        if ($types && !is_array($types)) {
            $types = explode(',', $types);
        }
        if (!$types) {
            $types = [self::TYPE_WORKER_REPAIR, self::TYPE_WORKER_INSTALLATION];
        }

        return self::getServiceByTypes($types);
    }

    public static function getAllService()
    {
        return self::SERVICE_TYPE;
    }

    public static function loadAddUserInfo(&$data)
    {
        foreach ($data as $key => $item) {
            $user_info = UserTypeService::getTypeData($item['origin_type'], $item['add_id'], UserInfoType::USER_ORDER_TYPE);
            $data[$key]['add_user_name'] = $user_info->getName()['name'];
            $data[$key]['add_user_phone'] = $user_info->getPhone();
            $data[$key]['add_user_group'] = $user_info->getName()['group_name'];
        }
    }

    public static function isInsurance($worker_order_type)
    {
        return in_array($worker_order_type, self::ORDER_TYPE_IN_INSURANCE_LIST);
    }

    public static function getOriginTypeStr($origin_type)
    {
        switch ($origin_type)
        {
            case self::ORIGIN_TYPE_FACTORY: return '厂家';
            case self::ORIGIN_TYPE_FACTORY_ADMIN: return '厂家子账号';
            case self::ORIGIN_TYPE_OUTER_USER: return '厂家外部客户';
            case self::ORIGIN_TYPE_WX_USER: return '普通用户';
            case self::ORIGIN_TYPE_WX_DEALER: return '经销商';
            default: return '';
        }
    }

    public static function getServiceType($service_type)
    {
        switch ($service_type) {
            case self::TYPE_WORKER_REPAIR: return '上门维修';
            case self::TYPE_WORKER_INSTALLATION: return '上门安装';
            case self::TYPE_PRE_RELEASE_INSTALLATION: return '预发件安装';
            case self::TYPE_USER_SEND_FACTORY_REPAIR: return '用户送修';
            case self::TYPE_WORKER_MAINTENANCE: return '上门维护';
            default : return '';
        }
    }

    public static function getOrderTypeName($worker_order_type)
    {
        // 1 厂家保内；2 厂家保外；3 厂家导单保内；4 厂家导单保外；5 微信端（厂家外部客户）保内；6 微信端（厂家外部客户）保外；7 平台保外
        switch ($worker_order_type) {
            case 1:
                return '厂家保内';
                break;
            case 2:
                return '厂家保外';
                break;
            case 3:
                return '厂家导单保内';
                break;
            case 4:
                return '厂家导单保外';
                break;
            case 5:
                return '微信端（厂家外部客户）';
                break;
            case 6:
                return '微信端（厂家外部客户）保外';
                break;
            case 7:
                return '平台保外';
                break;
            case self::ORDER_TYPE_MASTER_CODE_OUT_INSURANCE:
                return '师傅码保外';
            default:
                return '';
        }

    }

    /**
     * 获取核实状态
     * @return array
     */
    public static function getOrderCheck()
    {
        return [self::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE];
    }

    /**
     * 获取派单状态
     * @return array
     */
    public static function getOrderDistribute()
    {
        return [self::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE, self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL,];
    }

    /**
     * 获取技工预约状态
     * @return array
     */
    public static function getOrderAppoint()
    {
        return [self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT, self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE];
    }

    /**
     * 获取技工上门状态
     * @return array
     */
    public static function getOrderVisit()
    {
        return [self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, self::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE];
    }

    /**
     * 获取技工待服务状态
     * @return array
     */
    public static function getOrderInService()
    {
        return [self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE, self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE];
    }

    /**
     * 获取技工未结算的工单状态
     * @return array
     */
    public static function getOrderStatusUnsettled()
    {
        return [self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT, self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE, self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE];
    }

    /**
     * 获取回访状态
     * @return array
     */
    public static function getOrderReturn()
    {
        return [self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT, self::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE];
    }

    /**
     * 获取平台财务状态
     * @return array
     */
    public static function getOrderPlatformAudit()
    {
        return [self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT, self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT];
    }

    /**
     * 获取群内已完成的工单状态
     * @return array
     */
    public static function getOrderCompleteInGroup()
    {
        return [
            self::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
            self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
            self::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
            self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
            self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
            self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
            self::STATUS_FACTORY_AUDITED_AND_FINISHED
        ];
    }

    /**
     * 普通技工获取待结算的工单状态
     * @return array
     */
    public static function getOrderCompleteIn()
    {
        return [
            self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
            self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
            self::STATUS_FACTORY_AUDITED_AND_FINISHED
        ];
    }


    /**
     * 获取厂家状态
     * @return array
     */
    public static function getOrderFactoryAudit()
    {
        return [self::STATUS_FACTORY_AUDITED_AND_FINISHED];
    }

    public static function getStatusStr($order_status, $cancel_status, $role = null)
    {
        $role = $role?? AuthService::getModel();

        if (self::isCanceledOrder($cancel_status)) {
            return '已取消';
        }

        if (AuthService::ROLE_ADMIN == $role) {
            switch ($order_status) {
                case self::STATUS_CREATED:
                    return '待厂家审核下单';
                case self::STATUS_FACTORY_SELF_PROCESSED:
                    return '厂家自行处理';
                case self::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE:
                    return '待核实客服接单';
                case self::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK:
                    return '待客服核实';
                case self::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE:
                    return '待派发客服接单';
                case self::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE:
                    return '待客服派单';
                case self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL:
                    return '待技工接单';
                case self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT:
                    return '待技工预约';
                case self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE:
                    return '待技工服务';
                case self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE:
                    return '技工服务中';
                case self::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE:
                    return '待回访客服接单';
                case self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT:
                    return '待回访';
                case self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE:
                    return '回访不通过';
                case self::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE:
                    return '待平台财务接单';
                case self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT:
                    return '待平台财务审核';
                case self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT:
                    return '平台财务审核不通过';
                case self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT:
                    return '待厂家财务审核';
                case self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT:
                    return '厂家审核不通过';
                case self::STATUS_FACTORY_AUDITED_AND_FINISHED:
                    return '已完结';
            }
        } elseif (
            AuthService::ROLE_FACTORY == $role ||
            AuthService::ROLE_FACTORY_ADMIN == $role
        ) {
            switch ($order_status) {
                case self::STATUS_CREATED:
                    return '创建工单';
                case self::STATUS_FACTORY_SELF_PROCESSED:
                    return '厂家自行处理';
                case self::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE:
                    return '待客服接单';
                case self::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK:
                    return '待客服核实';
                case self::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE:
                case self::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE:
                    return '待客服派单';
                case self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL:
                    return '待技工接单';
                case self::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT:
                    return '待技工预约';
                case self::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE:
                    return '待技工服务';
                case self::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE:
                case self::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE:
                    return '技工服务中';
                case self::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE:
                case self::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT:
                case self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT:
                    return '待回访';
                case self::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE:
                case self::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT:

                    return '待平台财务审核';
                case self::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT:
                    return '待厂家财务审核';
                case self::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT:
                    return '厂家财务审核不通过';
                case self::STATUS_FACTORY_AUDITED_AND_FINISHED:
                    return '已完结';
            }
        }
    }

    public static function isCanceledOrder($cancel_status)
    {
        return in_array($cancel_status, [self::CANCEL_TYPE_WX_USER, self::CANCEL_TYPE_WX_DEALER, self::CANCEL_TYPE_FACTORY, self::CANCEL_TYPE_CS, self::CANCEL_TYPE_FACTORY_ADMIN]) ? 1 : 0;
    }

    public static function getClassificationByOrno($orno)
    {
        $prefix = $orno{0};
        if ($prefix == self::CLASSIFICATION_REWORK_ORDER_PREFIX) {
            return self::CLASSIFICATION_REWORK_ORDER_TYPE;
        } else {
            return self::CLASSIFICATION_COMMON_ORDER_TYPE;
        }
    }
}