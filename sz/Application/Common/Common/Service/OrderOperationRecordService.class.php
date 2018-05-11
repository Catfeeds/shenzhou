<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/25
 * Time: 12:22
 */

namespace Common\Common\Service;

use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\PushFactoryOrderRecordAppointEvent;
use Common\Common\Repositories\Events\PushFactoryOrderRecordEvent;
use Common\Common\Repositories\Events\PushXinYingYanOrderStatusChangeEvent;

class OrderOperationRecordService
{
    private static $autoactionlog = [];

    // =========================================替换内容start===========================================
    const IS_WORKER_PAY_CONTENT_ONE = '，工单已结算与技工结算';
    // =========================================替换内容end===========================================

    const PERMISSION_CS = 1;
    const PERMISSION_FACTORY = 2;
    const PERMISSION_FACTORY_ADMIN = 4;
    const PERMISSION_WORKER = 8;
    const PERMISSION_WX_USER = 16;
    const PERMISSION_WX_DEALER = 32;

    const EDIT_APPOINT_TYPE_USER_NOTIN_HOME = 1;
    const EDIT_APPOINT_TYPE_WOKRER_HAS_SOMETHING = 2;
    const EDIT_APPOINT_TYPE_USER_NOTGET_PRODUCT = 3;
    const EDIT_APPOINT_TYPE_PRODUCT_HAS_PROBLEM = 4;
    const EDIT_APPOINT_TYPE_ACCESSORY_HAS_PROBLEM = 5;
    const EDIT_APPOINT_TYPE_OTHER = 6;
    const EDIT_APPOINT_TYPE = [
        self::EDIT_APPOINT_TYPE_USER_NOTIN_HOME => '用户不在家',
        self::EDIT_APPOINT_TYPE_WOKRER_HAS_SOMETHING    => '我临时有事',
        self::EDIT_APPOINT_TYPE_USER_NOTGET_PRODUCT => '用户没收到产品',
        self::EDIT_APPOINT_TYPE_PRODUCT_HAS_PROBLEM => '收到的产品有问题',
        self::EDIT_APPOINT_TYPE_ACCESSORY_HAS_PROBLEM   => '收到的配件有问题',
        self::EDIT_APPOINT_TYPE_OTHER   => '其他',
    ];
    const EDIT_APPOINT_TYPE_DESC = [
        self::EDIT_APPOINT_TYPE_USER_NOTIN_HOME => '修改原因：用户不在家。',
        self::EDIT_APPOINT_TYPE_WOKRER_HAS_SOMETHING    => '修改原因：我临时有事。',
        self::EDIT_APPOINT_TYPE_USER_NOTGET_PRODUCT => '修改原因：用户没收到产品。',
        self::EDIT_APPOINT_TYPE_PRODUCT_HAS_PROBLEM => '修改原因：收到的产品有问题。',
        self::EDIT_APPOINT_TYPE_ACCESSORY_HAS_PROBLEM   => '修改原因：收到的配件有问题。',
        self::EDIT_APPOINT_TYPE_OTHER   => '修改原因：其他。',
    ];

    // 快捷方式，后台客服，厂家均可查看
    const PERMISSION_ADMIN_ROLES = self::PERMISSION_CS | self::PERMISSION_FACTORY;

    // 快捷方式 后台客服,厂家,技工均可查看
    const PERMISSION_ADMIN_AND_WORKER_ROLES = self::PERMISSION_CS | self::PERMISSION_FACTORY | self::PERMISSION_WORKER;


    // ================================================ 状态码 ================================================
    /**
     * 1000 SA  核实工单信息
     * 1001 SB  修改用户信息
     * 1002 SC  修改工单产品
     * 1003 SCA 更改上门次数
     * 1004 SD  添加工单产品
     * 1005 SE  删除工单产品 (同意厂家查看技工联系方式,不同意厂家查看技工联系方式)
     * 1006 SE  同意厂家查看技工联系方式
     * 1007 SE  不同意厂家查看技工联系方式
     * 1008 SF  财务内审通过并提交至厂家审核
     * 1009 SH  派发(包括抢单池),技工已接单
     * 1010 SI  提交开点申请
     * 1011 SJ  修改技工结算费用明细,修改后总金额为
     * 1012 SL  确认工单已完成并提交财务审核
     * 1013 SL  确认工单未完成并将工单状态重置为待维修
     * 1014 SO  客服放弃工单
     * 1015 SST 修改工单服务类型
     * 1016 SWG 修改工单产品服务项
     * 1017 SZ  终止工单
     * 1018 ZZ  客服手动添加操作记录
     * 1019  OT  客服转单到客服
     * 1020 N   财务退回给客服处理(平台财务审核不通过)
     * 1021    核实客服接单
     * 1032 --- 客服修改保外单费用
     * 1033 --  客服申请配件单
     */
    // ====================================== 客服工单 ======================================
    const CS_CHECKER_CHECKED = 1000;        // 核实工单信息
    const CS_MODIFY_USER_INFO = 1001;       // 修改用户信息
    const CS_ORDER_MODIFY_PRODUCT = 1002;  // 修改工单产品
    const CS_EDID_ORDER_APPOINT_NUMS = 1003;  // 修改上门次数
    const CS_ORDER_ADD_PRODUCT = 1004;  // 添加工单产品
    const CS_ORDER_DELETE_PRODUCT = 1005;  // 删除工单产品
    const CS_AUDITED_WORKER_ORDER = 1008;  // 神州内审通过并提交至厂家审核
    const CS_DISTRIBUTOR_DISTRIBUTE = 1009; // 客服派单
    const CS_RECRUIT_APPLY = 1010; // 提交开点申请
    const CS_MODIFY_WORKER_FEE = 1011; // 修改技工结算费用明细,修改后总金额为
    const CS_SETTLEMENT_FOR_WORKER = 1012;  // 确认工单已完成并提交财务审核 (回访成功)
    const CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED = 1013;  // 2018/01/02 确认工单未完成并将工单状态重置为已上门 // 2017 确认工单未完成并将工单进入以上门状态 (确认工单未完成并将工单状态重置为待维修)
    const CS_CANCEL_ORDER = 1014;  // CS_CHECKER_RECEIVED
    const CS_MODIFY_SERVICE_TYPE = 1015;  // 修改服务类型
    const CS_MODIFY_SERVICE_FAULT = 1016;  // 修改服务项
    const CS_ORDER_STOP = 1017; // 终止工单
    const CS_ADD_ORDER_OPERATION_RECORD = 1018;  // 客服手动添加操作记录
    const CS_NOT_AUDITED_WORKER_ORDER = 1020; // 财务退回给客服处理
    const CS_CHECKER_RECEIVED = 1021;
    const CS_TRANSFER_ORDER = 1022; // 转单
    const CS_ALLOWANCE_APPLY = 1023; // 补贴申请
    const CS_DISTRIBUTOR_RECEIVED = 1024; // 派单客服接单
    const CS_RETURNEE_RECEIVED = 1025; // 回访客服接单
    const CS_AUDITOR_RECEIVED = 1026; // 平台财务客服接单
    const CS_MODIFY_FACTORY_FEE = 1027; // 修改厂家结算费用明细,修改后总金额为
    const CS_CONFIRM_USER_PAID = 1028; // 确认用户已支付现金给技工
    const CS_CALL_TO_USER = 1029; // 客服电话联系用户(技工,用户,厂家技术支持人)
    const CS_CALL_TO_USER_END = 1030; // 客服电话联系用户,通话完毕(技工,用户,厂家技术支持人)
    const CS_TRANSFER_WORKER_ORDER_TYPE = 1031; // 客服转换工单售后类型
    const CS_MODIFY_NOTINRUANCE_WORKER_FEE = 1032; // 客服修改保外单费用
    const CS_APPLY_ACCESSORY = 1033; // 客服新增配件单，配件名称，配件单号为：AHHHHH


    /**
     * 2000 FB  修改用户信息
     * 2001 FC  修改工单产品
     * 2002 FD  添加工单产品
     * 2003 FE  删除工单产品
     * 2004 FF  审核了工单费用(待审核)
     * 2005 FF  审核了工单费用(不通过)
     * 2006 FF  审核了工单费用(通过)
     * 2007 FG  申请查看技工联系方式
     * 2008 FH  创建工单，提交客服中心审核.产品信息为：
     * 2009 FI  厂家重新下单
     * 2010 FL  厂家确认下单，产品信息为：
     * 2011 FK  自行处理
     * 2012 FJ  延迟工单回收时间至：
     * 2013 FY  取消工单
     */
    // ====================================== 厂家工单 ======================================
    const FACTORY_MODIFY_USER_INFO = 2000;  // 修改用户信息
    const FACTORY_ORDER_MODIFY_PRODUCT = 2001;  // 修改工单产品
    const FACTORY_ORDER_ADD_PRODUCT = 2002;  // 添加工单产品
    const FACTORY_ORDER_DELETE_PRODUCT = 2003;  // 删除工单产品
    const FACTORY_NOT_SETTLEMENT_WORKER_ORDER_FEE = 2005;  // 审核了工单费用(不通过)
    const FACTORY_SETTLEMENT_WORKER_ORDER_FEE = 2006;  // 审核了工单费用(通过)
    const FACTORY_ORDER_CREATE = 2008;  // 创建工单
    const FACTORY_ORDER_READD = 2009;  // 重新下单
    const FACTORY_ORDER_ADD_TO_PLATFORM = 2010;  // 厂家确认下单
    const FACTORY_ORDER_SELF_PROCESSING = 2011;  // 自行处理
    const FACTORY_CANCEL_ORDER = 2013;  // 取消工单
    const FACTORY_TRANSFER_WORKER_ORDER_TYPE = 2014;  // 厂家转换工单售后类型
    const FACTORY_APPLY_ORDER_REWORK = 2015;  // 厂家申请返修
    const FACTORY_ADD_ORDER_REWORK = 2016;  // 厂家发起一张返修单


    // ===================================技工操作==============================================
    /**
     * 4000 WA  预约客户成功,（时间）
     * 4001 WB  延长预约时间延长至:（时间）
     * 4002 WC  修改预约,预约时间为:（时间）
     * 4003 WC  退回工单，(不会维修该产品 ，无法满足客户需求 ，及其他原因)
     * 4004 WD  签到失败、成功（但必然算上门的钱）
     * 4005 WF  技工再次预约,预约时间为:
     * 4006 WG  选择产品维修项:（维修项名称）
     * 4007 WH  申请配件
     * 4008 WI  申请费用,费用单号为
     * 4009 WJ  提交产品报告（无法完成、现场完成等）
     * 4010 WK  完成工单本次服务评价为:
     * 4011 WL  提交产品错误
     * 4012 WM  师傅填写相关的费用信息
     * 4013 WN  在用户未支付时，修改费用信息
     * 4014 WO  用户支付成功(保外单)
     * 4015 WP  技工修改工单产品规格
     * 4016 WQ  群主派发工单
     * 4017 WR  技工退回工单给群主
     * 4018 --  技工选择不加收费用
     * 4019 --  技工选择加收费用
     */

    const WORKER_APPOINT_SUCCESS     = 4000;
    const WORKER_EXTEND_APPOINT_TIME = 4001;
    const WORKER_UPDATE_APPOINT_TIME = 4002;
    const WORKER_RETURN_ORDER    = 4003;
    const WORKER_SIGN_SUCCESS    = 4004;
    const WORKER_APPOINT_AGAIN   = 4005;
    const WORKER_SELECT_FAULT    = 4006;
    const WORKER_APPLY_ACCESSORY = 4007;
    const WORKER_APPLY_COST      = 4008;
    const WORKER_SUBMIT_PRODUCT_REPORT  = 4009;
    const WORKER_SERVICE_EVALUATION     = 4010;
    const WORKER_SUBMIT_ERROR           = 4011;
    const WORKER_SUBMIT_WARRANTY_BILL   = 4012;
    const WORKER_UPDATE_WARRANTY_BILL   = 4013;
    const WORKER_ORDER_USER_PAY_SUCCESS = 4014;
    const WORKER_UPDATE_ORDER_PRODUCT   = 4015;
    const WORKER_OWNER_DISTRIBUTE_ORDER = 4016;
    const WORKER_RETURN_ORDER_TO_OWNER  = 4017;
    const WORKER_NOT_ADD_OUT_ORDER_FEE  = 4018;
    const WORKER_ADD_OUT_ORDER_FEE      = 4019;
    const WORKER_REPRESENT_USER_PAY     = 4020;


    // =================================== C端用户 ==============================================
    /**
     * 5000 FH  创建工单，提交厂家审核.产品信息为：
     * 5001    取消工单
     */
    const WX_USER_CREATE_ORDER = 5000;
    const WX_USER_CANCEL_ORDER = 5001;
    const WX_USER_WECHAT_PAY_SUCCESS = 5002;

    // =================================== C端经销商 ==============================================

    /**
     * 7000 AB  工单超过厂家预设时间没有派出，系统自动收回   (不确定是否需要处理 TODO)
     * 7001 AP  厂家超过:day天时间未确认，系统自动结算
     * 7002 --  保外单，系统自动审核工单（审核通过）      [厂家、客服可见]
     * 7003 --  ????? (TODO 谁加的补充注释)
     * 7004 --  删除产品自动结算
     * 7005 --  V3.0 上线后 技工App重复提交配件返件费时，worker_order_fee重置返件费为0的不过修复，更新费用的相关操作激励
     */
    // =================================== 系统处理 ==============================================
    const SYSTEM_SETTLEMENT_WORKER_ORDER_FEE = 7001;
    const SYSTEM_ORDER_OUT_SYSTEM_AUTO_AUDITOR_SUCCESS = 7002;
    const SYSTEM_WX_OUT_ORDER_AUTO_ADD = 7003;
    const SYSTEM_DELETE_PRODUCT_AUTH_FINISH_REPAIR = 7004; // 删除产品自动结算
    const SYSTEM_REPAIR_WORKER_REPEAT_COMMIT_RERURNEE_FEE = 7005; // 删除产品自动结算
    const SYSTEM_REWORK_ORDER_DISTRIBUTOR_AUTO_RECEIVE = 7006; // 返修单母工单的派单跟单客服自动接单
    const SYSTEM_REWORK_ORDER_ORIGIN_WORKER = 7007; // 返修单记录工单原先的接单技工在操作记录上


    const OPERATION_TYPE_CONTENT = [
        // ====================================== 客服工单 ======================================
        self::CS_CHECKER_CHECKED => '核实工单信息',
        self::CS_MODIFY_USER_INFO => '修改用户信息',
        self::CS_ORDER_MODIFY_PRODUCT => '修改工单产品',
        self::CS_EDID_ORDER_APPOINT_NUMS => '上门次数由:appoint_num次修改为:repair_num次',
        self::CS_ORDER_ADD_PRODUCT => '添加工单产品',
        self::CS_ORDER_DELETE_PRODUCT => '删除工单产品',
        self::CS_AUDITED_WORKER_ORDER => '神州内审通过并提交至厂家审核',
        self::CS_DISTRIBUTOR_DISTRIBUTE => '客服已派单，维修商已接单',
        self::CS_SETTLEMENT_FOR_WORKER => '确认工单已完成并提交财务审核',
        self::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED => '确认工单未修改好，退回给技工重新上门处理', // 确认工单未完成并将工单状态重置为已上门
        self::CS_CANCEL_ORDER => '客服放弃工单',
        self::CS_MODIFY_SERVICE_TYPE => '修改服务类型',
        self::CS_MODIFY_SERVICE_FAULT => '服务项修改为：“:fault_name”+:remark',
        self::CS_ADD_ORDER_OPERATION_RECORD => ':content',
        self::CS_ORDER_STOP => '终止工单',
        self::CS_NOT_AUDITED_WORKER_ORDER => '财务退回给客服处理',
        self::CS_CHECKER_RECEIVED => '客服成功接单（接单客服：:admin_name）',
        self::CS_TRANSFER_ORDER => '客服转单',
        self::CS_RECRUIT_APPLY => '提交开点申请',
        self::CS_MODIFY_WORKER_FEE => '修改技工结算费用明细,修改后总金额为:total_fee',
        self::CS_ALLOWANCE_APPLY => '申请补贴',
        self::CS_DISTRIBUTOR_RECEIVED => '派单客服成功接单（接单客服：:admin_name）',
        self::CS_RETURNEE_RECEIVED => '回访客服成功接单（接单客服：:admin_name）',
        self::CS_AUDITOR_RECEIVED => '财务客服成功接单（接单客服：:admin_name）',
        self::CS_MODIFY_FACTORY_FEE => '修改厂家结算费用明细,修改后总金额为:total_fee',
        self::CS_CONFIRM_USER_PAID => '确认用户已支付现金给技工',
        self::CS_CALL_TO_USER => '客服拨打电话给:user_type-:user_name',
        self::CS_CALL_TO_USER_END => '客服拨打电话给:user_type-:user_name,通话时长为:time_len',
        self::CS_TRANSFER_WORKER_ORDER_TYPE => '工单的售后类型由：厂家保外，转为厂家保内',
        self::CS_MODIFY_NOTINRUANCE_WORKER_FEE => '修改技工结算费用明细,修改后总金额为：:total_fee元',
        self::CS_APPLY_ACCESSORY => '客服新增配件单，:acce_name，配件单号为:acce_number',

        // ====================================== 厂家工单 ======================================
        self::FACTORY_MODIFY_USER_INFO => '修改用户信息',
        self::FACTORY_ORDER_MODIFY_PRODUCT => '修改工单产品',
        self::FACTORY_ORDER_ADD_PRODUCT => '添加工单产品',
        self::FACTORY_ORDER_DELETE_PRODUCT => '删除工单产品',
        self::FACTORY_NOT_SETTLEMENT_WORKER_ORDER_FEE => '审核了工单费用(不通过)',
        self::FACTORY_SETTLEMENT_WORKER_ORDER_FEE => '审核了工单费用(通过)',
        self::FACTORY_ORDER_CREATE => ':pre_text新建工单，产品信息为：:order_products工单类型：:service_type',
        self::FACTORY_ORDER_READD => '厂家重新下单',
        self::FACTORY_ORDER_ADD_TO_PLATFORM => '厂家确认下单',
        self::FACTORY_ORDER_SELF_PROCESSING => '厂家自行处理',
        self::FACTORY_CANCEL_ORDER => '取消工单',
        self::FACTORY_TRANSFER_WORKER_ORDER_TYPE => '工单的售后类型由：厂家保外，转为厂家保内',
        self::FACTORY_APPLY_ORDER_REWORK => '发起一张返修单',
        self::FACTORY_ADD_ORDER_REWORK => '母工单:orno发起一张返修单',




        //=====================================技工操作==========================================
        self::WORKER_APPOINT_SUCCESS     => '预约成功,预约时间为:appoint_time',
        self::WORKER_EXTEND_APPOINT_TIME => '延长预约,时间延长至:appoint_time',
        self::WORKER_UPDATE_APPOINT_TIME => '修改预约,预约时间为:appoint_time',
        self::WORKER_RETURN_ORDER    => '技工退单',
        self::WORKER_SIGN_SUCCESS    => '签到:status',
        self::WORKER_APPOINT_AGAIN   => '再次预约,预约时间为:appoint_time',
        self::WORKER_SELECT_FAULT    => '选择产品服务项：:fault',
        self::WORKER_APPLY_ACCESSORY => '申请配件,:accessory_content',
        self::WORKER_APPLY_COST      => '申请费用,费用单号为:cost_number',
        self::WORKER_SUBMIT_PRODUCT_REPORT  => ':content',
        self::WORKER_SERVICE_EVALUATION     => '完成工单本次服务评价为:',
        self::WORKER_SUBMIT_ERROR           => '提交产品错误',
        self::WORKER_SUBMIT_WARRANTY_BILL   => '师傅填写工单费用信息,并选择:content',
        self::WORKER_UPDATE_WARRANTY_BILL   => ':content',
        self::WORKER_ORDER_USER_PAY_SUCCESS => '用户现金支付成功',
        self::WORKER_UPDATE_ORDER_PRODUCT   => '技工修改工单产品规格',
        self::WORKER_OWNER_DISTRIBUTE_ORDER => ':content',
        self::WORKER_RETURN_ORDER_TO_OWNER  => '群成员退单给群主',
        self::WORKER_NOT_ADD_OUT_ORDER_FEE  => '技工选择不加收费用',
        self::WORKER_ADD_OUT_ORDER_FEE      => '技工选择加收费用,:content',
        self::WORKER_REPRESENT_USER_PAY     => '技工代用户在线支付成功',

        // =================================== C端用户 ==============================================
        self::WX_USER_CREATE_ORDER => '微信用户下单(:insurance_type)，产品信息为：:order_products 工单类型：:service_type',
        self::WX_USER_CANCEL_ORDER => '取消工单',
        self::WX_USER_WECHAT_PAY_SUCCESS    => '用户在线支付成功',

        // =================================== C端经销商 ==============================================

        // =================================== 系统处理 ==============================================
        self::SYSTEM_SETTLEMENT_WORKER_ORDER_FEE => '厂家超过:day天时间未确认，系统自动结算',
        self::SYSTEM_ORDER_OUT_SYSTEM_AUTO_AUDITOR_SUCCESS => '保外单，系统自动审核工单（审核通过）',
        self::SYSTEM_WX_OUT_ORDER_AUTO_ADD => '系统自动下单给神州联保',
        self::SYSTEM_DELETE_PRODUCT_AUTH_FINISH_REPAIR => '工单产品全部已服务，工单状态置为：待回访客服接单',
        self::SYSTEM_REPAIR_WORKER_REPEAT_COMMIT_RERURNEE_FEE => '脚本修复 技工返件费用 数据：:content',
        self::SYSTEM_REWORK_ORDER_DISTRIBUTOR_AUTO_RECEIVE => '母工单:orno的派单跟单客服-自动接单',
        self::SYSTEM_REWORK_ORDER_ORIGIN_WORKER => '母工单:orno的技工是：:worker_name',
    ];

    const OPERATION_TYPE_SEE_AUTH_MAP = [
        // ====================================== 客服工单 ======================================
        self::CS_CHECKER_CHECKED => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_MODIFY_USER_INFO => self::PERMISSION_ADMIN_ROLES,
        self::CS_ORDER_MODIFY_PRODUCT => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_EDID_ORDER_APPOINT_NUMS => self::PERMISSION_CS,
        self::CS_ORDER_ADD_PRODUCT => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_ORDER_DELETE_PRODUCT => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_AUDITED_WORKER_ORDER => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_DISTRIBUTOR_DISTRIBUTE => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_SETTLEMENT_FOR_WORKER => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_CANCEL_ORDER => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_MODIFY_SERVICE_TYPE => self::PERMISSION_CS,
        self::CS_MODIFY_SERVICE_FAULT => self::PERMISSION_CS,
        self::CS_ADD_ORDER_OPERATION_RECORD => self::PERMISSION_CS,      // TODO 参数传递
        self::CS_ORDER_STOP => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_NOT_AUDITED_WORKER_ORDER => self::PERMISSION_CS,
        self::CS_CHECKER_RECEIVED => self::PERMISSION_ADMIN_ROLES,
        self::CS_TRANSFER_ORDER => self::PERMISSION_CS,
        self::CS_RECRUIT_APPLY => self::PERMISSION_ADMIN_ROLES,
        self::CS_MODIFY_WORKER_FEE => self::PERMISSION_CS,
        self::CS_ALLOWANCE_APPLY => self::PERMISSION_CS,
        self::CS_DISTRIBUTOR_RECEIVED => self::PERMISSION_CS,
        self::CS_RETURNEE_RECEIVED => self::PERMISSION_CS,
        self::CS_AUDITOR_RECEIVED => self::PERMISSION_CS,
        self::CS_MODIFY_FACTORY_FEE => self::PERMISSION_CS,
        self::CS_CONFIRM_USER_PAID => self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::CS_CALL_TO_USER => self::PERMISSION_ADMIN_ROLES,
        self::CS_CALL_TO_USER_END => self::PERMISSION_CS,
        self::CS_TRANSFER_WORKER_ORDER_TYPE => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::CS_MODIFY_NOTINRUANCE_WORKER_FEE => self::PERMISSION_CS,
        self::CS_APPLY_ACCESSORY => self::PERMISSION_CS | self::PERMISSION_FACTORY | self::PERMISSION_FACTORY_ADMIN | self::PERMISSION_WORKER,

        // ====================================== 厂家工单 ======================================
        self::FACTORY_MODIFY_USER_INFO => self::PERMISSION_ADMIN_ROLES,
        self::FACTORY_ORDER_MODIFY_PRODUCT => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_ORDER_ADD_PRODUCT => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_ORDER_DELETE_PRODUCT => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_NOT_SETTLEMENT_WORKER_ORDER_FEE => self::PERMISSION_ADMIN_ROLES,
        self::FACTORY_SETTLEMENT_WORKER_ORDER_FEE => self::PERMISSION_CS | self::PERMISSION_FACTORY,
        self::FACTORY_ORDER_CREATE => self::PERMISSION_ADMIN_ROLES,
        self::FACTORY_ORDER_READD => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_ORDER_ADD_TO_PLATFORM => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_ORDER_SELF_PROCESSING => self::PERMISSION_ADMIN_ROLES,
        self::FACTORY_CANCEL_ORDER => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_TRANSFER_WORKER_ORDER_TYPE => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_APPLY_ORDER_REWORK => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,
        self::FACTORY_ADD_ORDER_REWORK => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WORKER,


        //=====================================技工操作==========================================
        self::WORKER_APPOINT_SUCCESS     => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_EXTEND_APPOINT_TIME => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_UPDATE_APPOINT_TIME => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_RETURN_ORDER    => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_SIGN_SUCCESS    => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_APPOINT_AGAIN   => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_SELECT_FAULT    => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_APPLY_ACCESSORY => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_APPLY_COST      => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_SUBMIT_PRODUCT_REPORT => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_SERVICE_EVALUATION    => self::PERMISSION_ADMIN_AND_WORKER_ROLES,       // TODO 未使用
        self::WORKER_SUBMIT_ERROR          => self::PERMISSION_ADMIN_AND_WORKER_ROLES,       // TODO 未使用
        self::WORKER_SUBMIT_WARRANTY_BILL  => self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_UPDATE_WARRANTY_BILL  => self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_ORDER_USER_PAY_SUCCESS=> self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_UPDATE_ORDER_PRODUCT  => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::WORKER_OWNER_DISTRIBUTE_ORDER=> self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_RETURN_ORDER_TO_OWNER => self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_NOT_ADD_OUT_ORDER_FEE  => self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_ADD_OUT_ORDER_FEE      => self::PERMISSION_CS | self::PERMISSION_WORKER,
        self::WORKER_REPRESENT_USER_PAY => self::PERMISSION_CS | self::PERMISSION_WORKER,

        // =================================== C端用户 ==============================================
        self::WX_USER_CREATE_ORDER => self::PERMISSION_ADMIN_ROLES,
        self::WX_USER_CANCEL_ORDER => self::PERMISSION_ADMIN_ROLES | self::PERMISSION_WX_USER,
        self::WX_USER_WECHAT_PAY_SUCCESS => self::PERMISSION_ADMIN_AND_WORKER_ROLES | self::PERMISSION_WX_USER,

        // =================================== 系统处理 ==============================================
        self::SYSTEM_SETTLEMENT_WORKER_ORDER_FEE => self::PERMISSION_ADMIN_ROLES,
        self::SYSTEM_ORDER_OUT_SYSTEM_AUTO_AUDITOR_SUCCESS => self::PERMISSION_ADMIN_ROLES,
        self::SYSTEM_WX_OUT_ORDER_AUTO_ADD => self::PERMISSION_ADMIN_ROLES,
        self::SYSTEM_DELETE_PRODUCT_AUTH_FINISH_REPAIR => self::PERMISSION_ADMIN_AND_WORKER_ROLES,
        self::SYSTEM_REPAIR_WORKER_REPEAT_COMMIT_RERURNEE_FEE => self::PERMISSION_CS,
        self::SYSTEM_REWORK_ORDER_DISTRIBUTOR_AUTO_RECEIVE => self::PERMISSION_ADMIN_ROLES,
        self::SYSTEM_REWORK_ORDER_ORIGIN_WORKER => self::PERMISSION_CS,

    ];

    // 会影响订单状态的操作记录
    const WORKER_ORDER_STATUS_CHANGE_OPERATION_ARR = [
        self::CS_CHECKER_CHECKED,
        self::CS_AUDITED_WORKER_ORDER,
        self::CS_DISTRIBUTOR_DISTRIBUTE,
        self::CS_SETTLEMENT_FOR_WORKER,
        self::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED,
        self::CS_CANCEL_ORDER,
        self::CS_ORDER_STOP,
        self::CS_NOT_AUDITED_WORKER_ORDER,
        self::CS_DISTRIBUTOR_RECEIVED,
        self::CS_RETURNEE_RECEIVED,
        self::CS_AUDITOR_RECEIVED,
        self::FACTORY_ORDER_READD,
        self::FACTORY_CANCEL_ORDER,
        self::WORKER_RETURN_ORDER,
        self::WORKER_SUBMIT_PRODUCT_REPORT,
        self::WX_USER_CANCEL_ORDER,
        self::SYSTEM_SETTLEMENT_WORKER_ORDER_FEE,
        self::SYSTEM_ORDER_OUT_SYSTEM_AUTO_AUDITOR_SUCCESS,
        self::SYSTEM_DELETE_PRODUCT_AUTH_FINISH_REPAIR,
        self::WORKER_SIGN_SUCCESS,
    ];


    public static function getOperationContentByType($operation_type, $content_replace = [])
    {
        if (isset(self::OPERATION_TYPE_CONTENT[$operation_type])) {
            $content = self::OPERATION_TYPE_CONTENT[$operation_type];
            if ($content_replace) {
                foreach ($content_replace as $key => $item) {
                    $content = str_replace(":{$key}", $item, $content);
                }
            }
            return $content;
        }
        throw new \Exception(ErrorCode::getMessage(ErrorCode::ORDER_OPERATION_TYPE_NOT_EXISTS), ErrorCode::ORDER_OPERATION_TYPE_NOT_EXISTS);
    }

    /**
     * 添加工单操作记录
     * @param int $worker_order_id 工单id
     * @param int $operation_type 操作类型，厂家子账号与厂家共用相同操作类型，添加时会自动进行区分
     * @param array $extras is_factory_admin:是否厂家子账号，未设置则根据当前登录角色判断；worker_order_product_id:工单产品id；operator_id:操作人id，如果未设置则使用当前登录用户id；remark:备注；is_super_login:是否超级登录；is_system_create:是否为系统自动添加的记录；content_replace:数组，操作类型对饮的中文说明中需要替换的值；see_auth查看权限，未传则使用默认的；original_handle_worker_id:原来负责工单的技工id；original_worker_order_status:原工单状态
     *
     * @throws \Exception
     */
    public static function create($worker_order_id, $operation_type, $extras = [])
    {
        if (!isset($extras['see_auth']) && !self::OPERATION_TYPE_SEE_AUTH_MAP[$operation_type]) {
            throw new \Exception('请填写操作记录可见范围');
        }
        $is_factory_admin = $extras['is_factory_admin'] ?? AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN;

        $record = [
            'worker_order_id'         => $worker_order_id,
            'worker_order_product_id' => $extras['worker_order_product_id'] ?? 0,
            'create_time'             => time(),
            'operator_id'             => $extras['operator_id'] ?? AuthService::getAuthModel()
                    ->getPrimaryValue(),
            'operation_type'          => $is_factory_admin && ($operation_type >= 2000 && $operation_type < 3000) ? $operation_type + 1000 : $operation_type,
            'content'                 => self::getOperationContentByType($operation_type, $extras['content_replace']),
            'remark'                  => $extras['remark'] ?? '',
            'is_super_login'          => $extras['is_super_login'] ?? 0,
            'see_auth'                => $extras['see_auth'] ?? self::OPERATION_TYPE_SEE_AUTH_MAP[$operation_type],
            'is_system_create'        => $extras['is_system_create'] ?? 0,
        ];
        BaseModel::getInstance('worker_order_operation_record')->insert($record);
        self::autoActionLog($worker_order_id, $operation_type, $extras);
        if (in_array($operation_type, self::WORKER_ORDER_STATUS_CHANGE_OPERATION_ARR)) {
            $ext_info = $ext_info ?? BaseModel::getInstance('worker_order_ext_info')->getOne($worker_order_id);
            if ($ext_info['out_platform'] == 1 && $ext_info['out_trade_number']) {
                event(new PushXinYingYanOrderStatusChangeEvent($worker_order_id));
            }
        }
        if (($record['see_auth'] & self::PERMISSION_FACTORY) || ($record['see_auth'] & self::PERMISSION_FACTORY_ADMIN) || $record['see_auth'] == 0) {
            $ext_info = $ext_info ?? BaseModel::getInstance('worker_order_ext_info')->getOne($worker_order_id, 'out_trade_number,out_platform');
//            if (isset(C('OUT_PLATFORM_FOR_FACTORY_ID')[$ext_info['out_platform']]) && $ext_info['out_trade_number']) {
            if ($ext_info['out_platform'] == 2 && $ext_info['out_trade_number']) {
                // 预约与改约特殊区分
                if (in_array($operation_type, [self::WORKER_APPOINT_SUCCESS, self::WORKER_APPOINT_AGAIN])) {
                    $appoint = [
                        'appoint_time' => $extras['content_replace']['appoint_time'],
                        'remark' => $extras['remark'],
                    ];
                    event(new PushFactoryOrderRecordAppointEvent($ext_info, $record, $appoint));
                } else {
                    if (in_array($operation_type, [self::FACTORY_ORDER_CREATE])) {
                        $order = BaseModel::getInstance('worker_order')->getOne($worker_order_id, 'orno');
                        $record['content'] = "新建工单:{$order['orno']}";
                    }
                    event(new PushFactoryOrderRecordEvent($ext_info, $record));
                }
            }
        }
    }

    /**
     * @param array $opts           设置项
     *                              |-worker_order_id string 工单id
     *                              |-extras array 附加项,结果与create一样
     * @param int   $operation_type 操作类型，厂家子账号与厂家共用相同操作类型，添加时会自动进行区分
     *
     * @return bool
     */
    public static function createMany($opts, $operation_type)
    {
        if (empty($opts)) {
            return false;
        }
        $records = [];
        $cur_role = AuthService::getModel();

        $worker_order_ids = array_column($opts, 'worker_order_id');
        $worker_order_ids = array_unique($worker_order_ids);
        $worker_order_ids = array_filter($worker_order_ids);

        foreach ($opts as $opt) {
            $worker_order_id = $opt['worker_order_id']?? null;
            $extras = $opt['extras']?? null;

            $content_replace = $extras['content_replace']?? '';

            if (is_null($worker_order_id)) {
                continue;
            }

            $is_factory_admin = $extras['is_factory_admin'] ?? $cur_role == AuthService::ROLE_FACTORY_ADMIN;

            $records[] = [
                'worker_order_id'         => $worker_order_id,
                'worker_order_product_id' => $extras['worker_order_product_id'] ?? 0,
                'create_time'             => NOW_TIME,
                'operator_id'             => $extras['operator_id'] ?? AuthService::getAuthModel()->getPrimaryValue(),
                'operation_type'          => $is_factory_admin ? $operation_type + 1000 : $operation_type,
                'content'                 => self::getOperationContentByType($operation_type, $content_replace),
                'remark'                  => $extras['remark'] ?? '',
                'is_super_login'          => $extras['is_super_login'] ?? 0,
                'see_auth'                => $extras['see_auth'] ?? self::OPERATION_TYPE_SEE_AUTH_MAP[$operation_type],
                'is_system_create'        => $extras['is_system_create'] ?? 0,
            ];
        }

        BaseModel::getInstance('worker_order_operation_record')
            ->insertAll($records);

        if (!empty($worker_order_ids)) {
            foreach ($worker_order_ids as $worker_order_id) {
                self::autoActionLog($worker_order_id, $operation_type);
            }
        }

        return true;
    }

    public static function autoActionLog($worker_order_id, $operation_type, $extras = [])
    {
        static::$autoactionlog[] = [
            'worker_order_id'   => $worker_order_id,
            'operation_type'    => $operation_type,
            'extras'            => $extras,
        ];
    }

    public static function getAutoActionLog()
    {
        return static::$autoactionlog;
    }

    public static function deleteAutoActionLog()
    {
        static::$autoactionlog = [];
        return static::$autoactionlog;
    }

    public static function getOperationRecordSeeAuth($see_auth)
    {
        $auth_list = [];
        if ($see_auth == 0) {
            $auth_list[] = '所有';
        } else {
            ($see_auth & self::PERMISSION_CS) && $auth_list[] = '客服';
            ($see_auth & self::PERMISSION_FACTORY) && $auth_list[] = '厂家';
//            ($see_auth & self::PERMISSION_FACTORY_ADMIN) && $auth_list[] = '厂家子账号';
            ($see_auth & self::PERMISSION_WORKER) && $auth_list[] = '技工';
            ($see_auth & self::PERMISSION_WX_USER) && $auth_list[] = '微信普通用户';
            ($see_auth & self::PERMISSION_WX_DEALER) && $auth_list[] = '经销商';
        }

        return $auth_list;
    }

    public static function getUserTypeName($operation_type)
    {
        $type = substr($operation_type, 0, 1);
        switch ($type) {
            case 1:
                return '客服';
            case 2:
                return '厂家';
            case 3:
                return '厂家';
            case 4:
                return '技工';
            case 5:
                return '微信用户';
            default:
                return '系统';
        }
    }

    public static function setEditAppointDesc(&$record)
    {
        if ($record['operation_type'] == self::WORKER_UPDATE_APPOINT_TIME) {
            $remarks = json_decode($record['remark'], true);
            $type_desc = self::EDIT_APPOINT_TYPE_DESC[$remarks['type']] ?? '';
            $remarks['remarks'] && $remarks['remarks'] = '备注：'.$remarks['remarks'];
            $record['remark'] = is_array($remarks) ? $type_desc.$remarks['remarks'] : $record['remark'];
        }
    }

    public static function loadAddUserInfo(&$data)
    {
        foreach ($data as $key => $item) {
            $user_info = UserTypeService::getTypeData(substr($item['operation_type'], 0, 1), $item['operator_id']);
            $data[$key]['operator'] .= $user_info->getName() ? '(' . $user_info->getName() . ')' : '';
        }
    }

    /*
     * 获取工单跟踪状态
     */
    public static function getOrderTrackStatus()
    {
        //1009,1033,4000,4001,4002,4003,4004,4005,4007,4008,4009,4012,4013,4014,5002
        return [
            self::CS_DISTRIBUTOR_DISTRIBUTE,
            self::WORKER_APPOINT_SUCCESS,
            self::WORKER_EXTEND_APPOINT_TIME,
            self::WORKER_UPDATE_APPOINT_TIME,
            self::WORKER_RETURN_ORDER,
            self::WORKER_SIGN_SUCCESS,
            self::WORKER_APPOINT_AGAIN,
            self::WORKER_APPLY_ACCESSORY,
            self::CS_APPLY_ACCESSORY,
            self::WORKER_APPLY_COST,
            self::WORKER_SUBMIT_PRODUCT_REPORT,
            self::WORKER_SUBMIT_WARRANTY_BILL,
            self::WORKER_UPDATE_WARRANTY_BILL,
            self::WORKER_ORDER_USER_PAY_SUCCESS,
            self::WX_USER_WECHAT_PAY_SUCCESS
        ];
    }

}