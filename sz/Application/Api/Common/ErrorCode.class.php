<?php

namespace Api\Common;


class ErrorCode extends \Common\Common\ErrorCode
{
    // =================================================common==============================================
    const AREA_IDS_NOT_EMPTY = -300002;                     // 请选择地区
    const AREA_DESC_NOT_EMPTY = -300003;                    // 请填写详细地址
    const NAME_TELL_NOT_EMPTY = -300004;					// 联系人姓名/电话不能为空
    const PRODUCT_DETAIL_NOT_ACTIVE_TIME = -300005;			// 产品未激活
    const HAD_SAME_PHONE = -300006;                         // 手机号码已被绑定，请重新输入
    const PHONE_GS_IS_WRONG = -300007;                      // 手机号码格式错误，请重试输入
    const PHONE_NOT_EMPTY = -300008;                        // 手机号码不能为空
    const YOU_HAD_SAME_PHONE = -300009;                     // 你已绑定手机号码
    const CODE_IS_WRONG_OR_ED = -300010;                    // 验证码输入有误，请重新输入
    const QIYE_CODE_IS_WRONG_OR_ED = -1300010;              // 验证码输入有误，请重新输入
    const NAME_NOT_EMPTY = -300011;                         // 名称不能为空
    const FILE_UPLOAD_WORNG = -300012;                      // 文件上传错误
    const UPLOAD_ONLY_ONE = -300013;                        // 只能上传一个文件
    const DATA_IS_WRONG = -300014;                          // 参数错误/不足
    const CODE_NOT_PRODUCT = -300015;                       // 二维码未绑定产品
    const NOT_REGISTER_WECHAT = -300016;                    // 激活的手机号码，未关注微信公众账号
    const CODE_NOT_EMPTY    = -300017;                      // 验证码不能为空
    const YOU_NOT_SAME_PHONE = -300018;                     // 你未绑定手机号码
    const SHOP_NAME_NOT_EMPTY = -300019;                    // 购买人姓名不能为空
    const OPENID_NOT_SUBSCRIBE_WECHAT = -300020;            // 请先关注微信公众账号
    const GET_PLEACE_NOT_XY_60 = -300021;                   // 每分钟最多请求一次验证码

    // =================================================订单模块==============================================
    const MYPRODUCT_IS_A_ORDER = -400001;                   //该产品的售后单暂未完成，暂不支持重新下单。
    const CREATE_ORDER_NOT_SERVICETYPE = -400002;           //仅提供上门维修与上门安装服务
    const APPOINT_S_E_TIME_NOT_EMPTY = -400003;             //预约时间不能为空
    const APPOINT_TIME_WRONG = -400004;						// 预约时间错误
    const FAULT_IS_WRONG = -400005;                         // 维修项数据错误
    const IMAGES_NOT_DY_3 = -400006;						// 上传图片不能大于3张
    const CANCEL_WRONG_IS_RECEIVE= -400007;                 // 不能取消工单，该工单已经派发,技工已接单
    const WORKER_ORDER_NOT_OUT = -400008;                   // 工单不是保外单
    const FAULT_LABEL_IS_WRONG = -400009;                   // 维修项标签数据错误
    const DEALER_NOT_CREATE_ORDER = -400010;                // 经销商不允许创建工单
    const ORDER_IS_CANCEL = -400011;                        // 订单已取消
    // =================================================订单(微信)模块==============================================

    // =================================================用户模块==============================================
    const USER_NOT_COMMON_USER = -500001;                   // 您不是普通用户
    const USER_NOT_AGENCY = -500002;                        // 您不是经销商
    const STROE_NAME_NOT_EMPTY = -500003;                   // 店铺名称不能为空
    const DEALER_PRODUCT_NOT_EMPTY = -500004;               // 经营产品不能为空
    const LICENSE_IMG_NOT_EMPTY = -500005;                  // 请上传营业执照
    const DEALER_IMGS_NOT_EMPTY = -500006;                  // 请上店面照片
    const ACTIVE_TIME_HAD_SAME  = -500007;                  // 产品已激活
    const REGISTER_PRODUCT_NOT_POWER  = -500008;            // 你没有激活权限
    const YOU_NOT_PHONE = -500009;                          // 你未绑定手机号码
    const YOU_HAD_FACTORY_ID_AGENCY = -500010;              // 你已申请/拥有该厂家经销商权限
    const PLEACE_SET_AGENCY_INFO =  -500011;                // 请补充经销商资料
    const PHONE_IS_DEALER = -500012;                        // 购买人手机号码不能填写已验证为经销商的号码
    const CAN_NOT_APPLY_DEALER_HAD_LOG = -500013;           // 您的手机号已通过消费者身份验证，无法再申请成为经销商
    const SHOP_TIME_NOT_EMPTY  = -5000014;                  // 购买时间不能为空
    const REGISTER_PRODUCT_DY_10  = -5000015;               // 每个手机号码最多支持登记10个产品
    const SHOP_TIME_DY_NOW_TIME = -5000016;                 // 产品购买时间不能大于今天
    const SHOP_TIME_DY_90_NOT_BILL = -5000017;              // 购买时间大于产品的出厂时间90天，请上传购买小票
    const REGISTER_PRODUCT_DY_1000  = -5000018;             // 每个手机号码最多支持登记1000个产品
    const ACTIVE_TIME_NOT_XY_CHUCHANG_TIME= -5000019;       // 购买时间不能小于出厂时间


    // =================================================qiye==============================================
    const YOU_NOT_HAD_PAY_PASSWORD = -600053;                       // 你尚未设置提现密码


    public static $customMessage = [
        // =================================================common==============================================
        self::AREA_IDS_NOT_EMPTY => '请选择地区',
        self::AREA_DESC_NOT_EMPTY => '请填写详细地址',
        self::NAME_TELL_NOT_EMPTY => '联系人姓名/电话不能为空',
        self::PRODUCT_DETAIL_NOT_ACTIVE_TIME => '产品未激活',
        self::DATA_IS_WRONG => '参数错误/不足',
        self::HAD_SAME_PHONE => '手机号码已被绑定，请重新输入',
        self::PHONE_GS_IS_WRONG => '手机号码格式错误，请重试输入',
        self::PHONE_NOT_EMPTY => '手机号码不能为空',
        self::YOU_HAD_SAME_PHONE => '你已绑定手机号码',
        self::CODE_IS_WRONG_OR_ED => '验证码输入有误，请重新输入',
        self::QIYE_CODE_IS_WRONG_OR_ED => '验证码输入有误，请重新输入',
        self::NAME_NOT_EMPTY => '名称不能为空',
        self::FILE_UPLOAD_WORNG => '文件上传错误',
        self::UPLOAD_ONLY_ONE => '只能上传一个文件',
        self::CODE_NOT_PRODUCT => '二维码未绑定产品',
        self::NOT_REGISTER_WECHAT => '激活的手机号码，未关注微信公众账号',
        self::CODE_NOT_EMPTY => '验证码不能为空',
        self::YOU_NOT_SAME_PHONE => '你未绑定手机号码',
        self::SHOP_NAME_NOT_EMPTY => '购买人姓名不能为空',
        self::OPENID_NOT_SUBSCRIBE_WECHAT => '请先关注微信公众账号',
        self::GET_PLEACE_NOT_XY_60 => '每分钟最多请求一次验证码',
        // =================================================订单模块==============================================
        self::MYPRODUCT_IS_A_ORDER => '该产品的售后单暂未完成，暂不支持重新下单。', // 该产品已经报修/报装 请耐心等待客服受理！
        self::CREATE_ORDER_NOT_SERVICETYPE => '仅提供上门维修与上门安装服务',
        self::APPOINT_S_E_TIME_NOT_EMPTY => '预约时间不能为空',
        self::APPOINT_TIME_WRONG => '预约时间错误',
        self::FAULT_IS_WRONG => '维修项数据错误',
        self::IMAGES_NOT_DY_3 => '上传图片不能大于3张',
        self::CANCEL_WRONG_IS_RECEIVE => '不能取消工单，该工单已经派发',
        self::WORKER_ORDER_NOT_OUT => '工单不是保外单',
        self::FAULT_LABEL_IS_WRONG => '维修项标签数据错误',
        self::DEALER_NOT_CREATE_ORDER => '经销商不允许创建工单',
        self::ORDER_IS_CANCEL => '订单已取消',

        // =================================================用户模块==============================================
        self::USER_NOT_COMMON_USER => '您不是普通用户',
        self::USER_NOT_AGENCY => '您不是经销商',
        self::STROE_NAME_NOT_EMPTY => '店铺名称不能为空',
        self::DEALER_PRODUCT_NOT_EMPTY => '经营产品不能为空',
        self::LICENSE_IMG_NOT_EMPTY => '请上传营业执照',
        self::DEALER_IMGS_NOT_EMPTY => '请上店面照片',
        self::ACTIVE_TIME_HAD_SAME => '产品已激活',
        self::SHOP_TIME_NOT_EMPTY => '购买时间不能为空',
        self::REGISTER_PRODUCT_NOT_POWER => '你没有激活权限',
        self::YOU_NOT_PHONE => '你未绑定手机号码',
        self::YOU_HAD_FACTORY_ID_AGENCY => '你已申请/拥有该厂家经销商权限',
        self::PLEACE_SET_AGENCY_INFO => '请补充经销商资料',
        self::PHONE_IS_DEALER => '购买人手机号码不能填写已验证为经销商的号码',
        self::CAN_NOT_APPLY_DEALER_HAD_LOG => '您的手机号已通过消费者身份验证，无法再申请成为经销商',
        self::REGISTER_PRODUCT_DY_10 => '每个手机号码最多支持登记10个产品',
        self::SHOP_TIME_DY_NOW_TIME => '产品购买时间不能大于今天',
        self::SHOP_TIME_DY_90_NOT_BILL => '购买时间大于产品的出厂时间90天，请上传购买小票',
        self::REGISTER_PRODUCT_DY_1000 => '每个手机号码最多支持登记1000个产品',
        self::ACTIVE_TIME_NOT_XY_CHUCHANG_TIME => '购买时间不能小于出厂时间',

        // =================================================qiye==============================================
        self::YOU_NOT_HAD_PAY_PASSWORD => '你尚未设置提现密码',

    ];

}
