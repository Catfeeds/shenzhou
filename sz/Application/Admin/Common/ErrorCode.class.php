<?php

namespace Admin\Common;


class ErrorCode extends \Common\Common\ErrorCode
{

    const ORDER_IMPORT_EXCEL_DATA_NUM_ERROR = -100001;
    const ORDER_IMPORT_FACTORY_NO_PERMISSION = -100002;
    const ORDER_IMPORT_NO_ENOUGH_MONEY = -100003;
    const NOT_AGEN_CHECK = -100004;                                 // 只允许审核一次
    const NOT_CHECK = -100005;                                      // 未审核
    const IS_NOT_CHECK_WRONG = -100006;                             // 审核不通过

    //登录
    const ADMIN_PHONE_NOT_EXISTS = -100203;                                 // 手机号码不存在
    const ADMIN_PASSWORD_ERROR = -100209;                                 // 密码错误
    const ADMIN_DISABLED = -100111;                                 // 您的账号已被禁用，如有疑问，请联系系统管理管理员
    const ADMIN_NO_PERMISSION = -100999;                            // 您无权限进行该操作,请联系管理员
    const ADMIN_NOT_IN_IP_WHITE_LIST = -100520;                     // 不可在当前网络IP下登录，请联系管理员处理

    //添加技工
    const WORKER_PHONE_EXISTS = -101001;                                 // 该手机号码已经在
    const CHECK_IS__EXIST = -102001;                                     // 已存在
    const PHONE_GS_IS_WRONG = -103001;                                   // 手机号码格式错误，请重试输入
    const CHECK_IS_NOT__NULL = -104001;                                  //参数不能为空
    const SMS_SEND_FAIL = -105001;                                     //短信发送失败
    const FILE_UPLOAD_WORNG = -106001;                      // 文件上传错误
    const CHECK_IS__SUPPORTPERSON  = -107001;                          //只有一个默认技术支持人
    const CHECK_SUPPORTPERSON_AND_PHONE_IS_NULL  = -108001;            //技术支持人或者手机号码是否为空
    const CHECK_FACTORY_ADMIN_ROLE_NOT  = -109001;            //您的账号已被禁用，如有疑问，请联系系统管理管理员

    // 产品
    const PRODUCT_CATEGORY_NO_MAINTENANCE_ITEM = -110001;         // xx分类下无可用维修项,请重新选择

    // 厂家
    const FACTORY_MONEY_NOT_ENOUGH_ADD_ORDER = -111001;         // 资金不足,添加订单失败

    const FACTORY_DATE_EXPIRE = -200001;
    const EXPRESS_NAME_NOT_EMPTY = -200002;                        // 快递公司不能为空
    const EXPRESS_NUMBER_NOT_EMPTY = -200003;                      // 快递单号不能为空
    const EXPRESS_CODE_NOT_EMPTY = -200004;                        // 快递公司代码不能为空

    const IMAGES_NOT_DY_3 = -400006;                                // 上传图片不能大于3张

    const NOT_SERVER_PRICE_INFO = -600016;                          // 无维修费用信息，请通知客服
    const EXPRESS_TRACK_IS_WRONG = -600035;                         // 同步物流信息错误，请重试

    const ORDER_STATUS_CATNOT_EDIT_SERVICE = -700001;               // 当前订单状态不允许修改产品服务项
    const NOT_ADMIN = -700002;                                      // 当前用户不是平台客服
    const ORDER_STATUS_CATNOT_EDIT_SERVICETYPE = -700003;           // 当前订单状态不允许修改工单服务项类型
    const AT_ADDRESS_NOT_EMPTY = -700004;                           // 归属地址不能为空 
    const ORDER_DETAIL_EDIT_FAULT_DY_TWO = -700005;                 // 工单每个产品的服务项最多只能修改2次 
    const COST_ORDER_IS_WORKER_CANCEL = -700006;                    // 费用单已取消
    const COST_ORDER_FACTORY_IS_CHECKOU = -700007;                  // 费用单厂家已审核
    const COST_ORDER_CS_IS_NOT_CHECKOU = -700008;                   // 费用单客服未审核
    const IMAGES_NOT_EMPTY = -700009;                               // 图片不能为空
    const NOT_FACTORY = -700010;                                    // 当前用户不是厂家客服
    const SHENGCHAN_TIME_NOT_EMPTY = -700011;                       // 生产时间不能为空
    const CHUCHANG_TIME_NOT_EMPTY = -700012;                        // 出厂时间不能为空
    const CHUCHANG_TIME_NOT_LT_SHENGCHAN_TIME = -700013;            // 出厂时间不能小于生产时间
    const BIND_BEEN_CODE_IS_WRONG = -700014;                        // 码段数据有误
    const BIND_BEEN_CODE_IS_USED = -700015;                         // 需要绑定的码段已有易码被绑定
    const BIND_NUMS_IS_FULL = -700016;                              // 剩余码数不足
    const YIMA_IS_ACTIVE = -700018;                                 // 易码已被激活

    // 工单
    const WORKER_ORDER_STATUS_NOT_RETURNEE_RECEIVE = -800001;       // 当前工单状态不允许回访客服接单
    const WORKER_ORDER_STATUS_NOT_SETTLEMENT_FOR_WORKER = -800002;  // 当前工单状态不允许结算操作
    const WORKER_ORDER_STATUS_NOT_AUDITOR_RECEIVE = -800003;        // 当前工单状态不允许财务客服接单
    const WORKER_ORDER_STATUS_NOT_AUDITED_ORDER = -800004;          // 当前工单状态不允许财务客服审核财务
    const HAS_APPLY_ACCESSORY_NOT_COMPLETE = -800005;               // 该工单有未完成的配件单
    const HAS_APPLY_ALLOWANCE_NOT_COMPLETE = -800006;               // 该工单有未完成的补贴单
    const WORKER_ORDER_FACTORY_SETTLEMENT_NOT_MONEY = -800007;      // 工单费用结算失败，余额不足
    const WORKER_ORDER_ADMIN_NO_PERMISSION = -800010;               // 您的角色无权限操作当前工单
    const WORKER_ORDER_STATUS_NOT_AUDITED_ORDER_FACTORY = -800011;  // 当前工单状态不允许厂家客服审核财务
    const APPOINT_NUMS_NOT_EDIT_AGAIN        = -800012;             // 已更改过上门次数
    const HAS_APPLY_COST_NOT_COMPLETE = -800013;                    // 该工单有未完成的费用单
    const IS_WORKER_PAY_NOT_RETURN_AGEN = -800014;                  // 该工单已与技工结算，不能退回给客服
    const OUT_ORDER_USER_IS_NOT_PAY = -800015;                      // 用户还未支付费用，请先与师傅用户核实
    const OUT_ORDER_USER_IS_PAY = -800016;                          // 用户已支付费用，请与技工结算
    const MUST_SELECT_CITY =  -800017;                              // 至少选到市级单位
    const NOTINSUREANCE_FEE_MODIFY_NOT_LT =  -800018;               // 修改后的总金额不能小于技工原先提交的总金额
    const WORKER_ORDER_STATUS_NOT_CS_APPLY_ACCESSORY = -800019;     // 当前工单状态不允许客服申请配件单

    //配件单
    const ACCESSORY_CANCELED = -1000001;                            // 配件单已取消
    const ACCESSORY_FACTORY_FORBIDDEN = -1000002;                   // 配件单厂家审核不通过
    const ACCESSORY_FACTORY_CHECKED = -1000003;                     // 配件单厂家已审核
    const ACCESSORY_STATUS_ERROR = -1000004;                        // 配件单审核时状态异常
    const ACCESSORY_COMPLETED = -1000005;                           // 配件单审核时状态异常
    const ACCESSORY_NOT_SEND_BACK = -1000006;                       // 厂家已设置不需要返件
    const ACCESSORY_NOT_UNCOMPLETED = -1000007;                     // 配件单未完结
    const ACCESSORY_GIVE_UP_SEND_BACK = -1000008;                   // 配件单已放弃返件
    const ACCESSORY_CS_FORBIDDEN = -1000009;                        // 配件单已放弃返件
    const ACCESSORY_CS_CHECKED = -1000010;                          // 配件单客服已审核


    //费用单
    const COST_STATUS_ERROR = -2000001; // 厂家审核时状态异常
    const COST_FACTORY_FORBIDDEN = -2000002; // 厂家审核不通过
    const COST_FACTORY_CHECKED = -2000003; // 厂家已审核
    const COST_CS_CHECKED = -2000004; // 客服已审核
    const COST_CS_FORBIDDEN = -2000005; // 客服审核不通过

    //工单留言
    const ORDER_MESSAGE_NOT_AUTH = -3000001;

    //抽奖管理
    const DRAW_RULE_OVERTIME = -4000001;
    const DRAW_RULE_CROSS = -4000002;
    const DRAW_RULE_COUPON_OVER = -4000003;


    public static $customMessage = [
        self::ORDER_IMPORT_EXCEL_DATA_NUM_ERROR => '导入订单数量超过限制，最多可导入:num张，请重新导入',
        self::ORDER_IMPORT_FACTORY_NO_PERMISSION => '您无权限导入，请与客服联系',
        self::ORDER_IMPORT_NO_ENOUGH_MONEY => '您本次批量导入工单产品:total个，由于维修金可用余额不足，只能导入:num个工单产品，请选择后重新提交，或维修金充值后重新导入工单。',
        self::NOT_AGEN_CHECK => '只允许审核一次',
        self::NOT_CHECK => '未审核',
        self::IS_NOT_CHECK_WRONG => '审核不通过',

        self::FACTORY_DATE_EXPIRE => '您的合约已到期，请与客服联系',
        self::EXPRESS_NAME_NOT_EMPTY => '快递公司不能为空',
        self::EXPRESS_NUMBER_NOT_EMPTY => '快递单号不能为空',
        self::EXPRESS_CODE_NOT_EMPTY => '快递公司代码不能为空',

        self::FILE_UPLOAD_WORNG => '文件上传错误',
        self::IMAGES_NOT_DY_3 => '上传图片不能大于3张',

        self::NOT_SERVER_PRICE_INFO => '无维修费用信息，请通知客服',
        self::EXPRESS_TRACK_IS_WRONG => '同步物流信息错误，请重试',

        self::ORDER_STATUS_CATNOT_EDIT_SERVICE => '当前订单状态不允许修改产品服务项',
        self::NOT_ADMIN => '当前用户不是平台客服',
        self::ORDER_STATUS_CATNOT_EDIT_SERVICETYPE => '当前订单状态不允许修改工单服务项类型',
        self::AT_ADDRESS_NOT_EMPTY => '归属地址不能为空 ',
        self::ORDER_DETAIL_EDIT_FAULT_DY_TWO => '工单每个产品的服务项最多只能修改2次 ',
        self::COST_ORDER_IS_WORKER_CANCEL => '费用单已取消',
        self::COST_ORDER_FACTORY_IS_CHECKOU => '费用单厂家已审核',
        self::COST_ORDER_CS_IS_NOT_CHECKOU => '费用单客服未审核',
        self::IMAGES_NOT_EMPTY => '图片不能为空',
        self::NOT_FACTORY => '当前用户不是厂家客服',
        self::SHENGCHAN_TIME_NOT_EMPTY => '生产时间不能为空',
        self::CHUCHANG_TIME_NOT_EMPTY => '出厂时间不能为空',
        self::CHUCHANG_TIME_NOT_LT_SHENGCHAN_TIME => '出厂时间不能小于生产时间',
        self::BIND_BEEN_CODE_IS_WRONG => '码段数据有误',
        self::BIND_BEEN_CODE_IS_USED => '需要绑定的码段已有易码被绑定',
        self::BIND_NUMS_IS_FULL => '剩余码数不足',
        self::YIMA_IS_ACTIVE => '易码已被激活',

        self::ADMIN_PHONE_NOT_EXISTS => '手机号码不存在',
        self::ADMIN_PASSWORD_ERROR => '密码错误',
        self::ADMIN_DISABLED => '您的账号已被禁用，如有疑问，请联系管理员',
        self::ADMIN_NO_PERMISSION => '您无权限进行该操作,请联系管理员',
        self::ADMIN_NOT_IN_IP_WHITE_LIST => '不可在当前网络IP下登录，请联系管理员处理',

        self::WORKER_PHONE_EXISTS => '该手机号码已经在',
        self::CHECK_IS__EXIST => '已存在',
        self::CHECK_IS_NOT__NULL => '参数不能为空',

        self::SMS_SEND_FAIL => '短信发送失败',
        self::PHONE_GS_IS_WRONG => '手机号码格式错误，请重试输入',

        self::CHECK_IS__SUPPORTPERSON => '只能选择一个默认技术支持人',
        self::CHECK_SUPPORTPERSON_AND_PHONE_IS_NULL => '技术支持人名称或者电话号码不能为空',

        self::CHECK_FACTORY_ADMIN_ROLE_NOT => '您的账号已被禁用，如有疑问，请联系系统管理管理员',

        self::PRODUCT_CATEGORY_NO_MAINTENANCE_ITEM => ':product_category_name分类下无可用维修项,请重新选择',

        self::FACTORY_MONEY_NOT_ENOUGH_ADD_ORDER => '资金不足,添加订单失败',

        self::WORKER_ORDER_STATUS_NOT_RETURNEE_RECEIVE => '当前工单状态不允许回访客服接单',
        self::WORKER_ORDER_STATUS_NOT_SETTLEMENT_FOR_WORKER => '当前工单状态不允许结算操作',
        self::WORKER_ORDER_STATUS_NOT_AUDITOR_RECEIVE => '当前工单状态不允许财务客服接单',
        self::WORKER_ORDER_STATUS_NOT_AUDITED_ORDER => '当前工单状态不允许财务客服审核财务',
        self::HAS_APPLY_ACCESSORY_NOT_COMPLETE => '该工单有未完成的配件单',
        self::HAS_APPLY_ALLOWANCE_NOT_COMPLETE => '该工单有未完成的补贴单',
        self::WORKER_ORDER_FACTORY_SETTLEMENT_NOT_MONEY => '工单费用结算失败，余额不足',
        self::WORKER_ORDER_ADMIN_NO_PERMISSION => '您的角色无权限操作当前工单',
        self::WORKER_ORDER_STATUS_NOT_AUDITED_ORDER_FACTORY => '当前工单状态不允许厂家客服审核财务',
        self::APPOINT_NUMS_NOT_EDIT_AGAIN => '已更改过上门次数',
        self::HAS_APPLY_COST_NOT_COMPLETE => '该工单有未完成的费用单',
        self::IS_WORKER_PAY_NOT_RETURN_AGEN => '该工单已与技工结算，不能退回给客服',
        self::OUT_ORDER_USER_IS_NOT_PAY => '用户还未支付费用，请先与师傅用户核实',
        self::OUT_ORDER_USER_IS_PAY => '用户已支付费用，请与技工结算',
        self::MUST_SELECT_CITY => '至少选到市级单位',
        self::NOTINSUREANCE_FEE_MODIFY_NOT_LT => '修改后的总金额不能小于技工原先提交的总金额',
        self::WORKER_ORDER_STATUS_NOT_CS_APPLY_ACCESSORY => '当前工单状态不允许客服申请配件单',

        self::ACCESSORY_CANCELED => '配件单已取消',
        self::ACCESSORY_FACTORY_FORBIDDEN => '配件单厂家审核不通过',
        self::ACCESSORY_FACTORY_CHECKED => '配件单厂家已审核',
        self::ACCESSORY_STATUS_ERROR => '配件单审核时状态异常',
        self::ACCESSORY_COMPLETED => '配件单已完成',
        self::ACCESSORY_NOT_SEND_BACK => '厂家已设置不需要返件',
        self::ACCESSORY_NOT_UNCOMPLETED => '配件单未完结',
        self::ACCESSORY_GIVE_UP_SEND_BACK => '配件单已放弃返件',
        self::ACCESSORY_CS_FORBIDDEN => '配件单已放弃返件',
        self::ACCESSORY_CS_CHECKED => '配件单客服已审核',

        self::COST_STATUS_ERROR => '厂家审核时状态异常',
        self::COST_FACTORY_FORBIDDEN => '厂家审核不通过',
        self::COST_FACTORY_CHECKED => '厂家已审核',
        self::COST_CS_CHECKED => '客服已审核',
        self::COST_CS_FORBIDDEN => '客服审核不通过',

        self::ORDER_MESSAGE_NOT_AUTH => '当前工单状态,不允许此角色留言',



        self::DRAW_RULE_OVERTIME => '抽奖活动已过时间',
        self::DRAW_RULE_CROSS => '与其他抽奖活动时间发生冲突',
        self::DRAW_RULE_COUPON_OVER => '所选优惠券库存不足',
    ];

}
