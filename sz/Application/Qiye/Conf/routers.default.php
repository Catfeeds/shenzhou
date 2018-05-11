<?php

define('API_SECRET_PARAM', 'api_url_secret');
define('API_SECRET_CODE',  '0A1B0c2D0e3F0G');

return [
    'URL_ROUTE_RULES' => [
        // 微信登录 zjz
        ['worker/login/weixin$', 'worker/wxLogin',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['worker/check_phone$', 'Worker/checkPhone',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 检查手机号
        ['worker/login$', 'Worker/login',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 登录
        ['worker/verify/send_code$', 'VerifyCode/sendCode',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取验证码
        ['worker/web/code$', 'VerifyCode/webVerifyCode',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取pc验证码

        ['worker/verify_code$', 'Worker/verifyCode',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 验证验证码
        ['worker/forget$', 'Worker/forget',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 提交忘记密码
        ['worker/register$', 'Worker/register',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 注册
        ['worker/edit_password$', 'Worker/editPassword',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 提交登录密码
        ['worker/update_password$', 'Worker/updatePassword',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 修改密码
        ['worker/edit$', 'Worker/edit',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 提交个人信息
        ['worker/myinfo$', 'Worker/info',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取个人信息

        ['upload/image$', 'Upload/image',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 上传图片

        ['jpush/edit$', 'JPush/edit',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 提交极光绑定ID
        ['worker/pc_login$', 'Worker/pcLogin',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 技工pc端登陆
        ['worker/fill$', 'Worker/fillInfo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 企业号完善（添加）技工信息

        //----------------技工模块----------------->
        ['worker/receive_addresses$', 'Worker/addressList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取技工地址列表
        ['worker/receive_address$', 'Worker/address',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取技工默认地址
        ['worker/receive_address/:id\d$', 'Worker/addressEdit',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 地址修改

        //----------------公共模块----------------->
        ['express_companies$', 'Public/expressCompanies',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 物流公司列表
        ['expresses$', 'Public/expresses',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 物流查询
        ['wechat/jssdk/option$', 'Public/jssdkOption', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取微信公众账号企业号 JSSDK 配置
        ['orderat/category$', 'Public/orderAtCategory', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 联保价格
        ['mediaToUrl$', 'Public/mediaToUrl', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 获取微信公众账号企业号 JSSDK 配置

        //----------------工单模块----------------->
        ['orders/:order_id\d/products/:id\d/services$', 'Order/getServices',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 服务项列表
        ['orders/:id\d/accessories$', 'Accessory/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 配件单列表
        ['orders/:id\d/costs$', 'Cost/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 费用单列表
        ['orders/:id\d/appoints$', 'Order/appointmentLog',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 预约历史
        ['orders/:id\d/track$', 'Order/orderTrack',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 工单跟踪
        ['orders/:id\d/charge$', 'Order/orderCharge',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 工单费用明细
        ['orders$', 'Order/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 工单列表
        ['orders/:id\d$', 'Order/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 工单详情
        ['orders/:id\d/appoints$', 'Order/addAppoint',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 工单预约
        ['orders/:id\d/accessories$', 'Accessory/add',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 配件申请
        ['orders/:id\d/costs$', 'Cost/add',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 费用单申请
        ['orders/:order_id\d/order_products/:product_id\d/services/:id\d$', 'Order/selectService',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 选择服务项
        ['orders/:order_id\d/products/:product_id\d/upload$', 'Order/uploadServiceReport',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 上传服务报告
        ['orders/:id\d/update_warranty_fee$', 'Order/updateWarrantyFee',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 保外单修改费用
        ['orders/:id\d/appoints_sign$', 'Order/appointmentSign',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 预约签到
        ['orders/:id\d/appoints$', 'Order/updateAppoint',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 修改预约
        ['orders/:id\d/return$', 'Order/orderReturn',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 工单退回
        ['orders/:id\d/delay$', 'Order/orderDelay',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 工单延时
        ['orders/:id\d/cash_pay_success$', 'Order/cashPaySuccess',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 保外单现金支付
        ['orders/:id\d/warranty_fee_info$', 'Order/warrantyFeeInfo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 保外单费用详情
        ['orders/:order_id\d/products/:id\d/product_standards$', 'Order/productStandards',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 产品规格列表
        ['orders/:order_id\d/order_products/:product_id\d/product_standard/:id\d$', 'Order/updateProductStandard',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 选择产品规格
        // 获取工单上传服务报告相关数据 zjz
        ['orders/:id\d/service_report', 'Order/getServiceReport',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //---------------配件单-------------------->
        ['accessories/:id\d/factory$', 'Accessory/factoryDetail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 配件单厂家信息
        ['accessories/:id\d$', 'Accessory/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 配件单详情
        ['accessories/:id\d/sign_in$', 'Accessory/accessorySignIn',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 配件签收
        ['accessories/:id\d/return$', 'Accessory/accessoryReturn',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 配件回寄

        //---------------费用单-------------------->
        ['costs/:id\d$', 'Cost/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 费用单详情

        //---------------意见反馈-------------------->
        ['feedbacks$', 'FeedBack/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 意见反馈详情
        ['feedbacks/:id\d$', 'FeedBack/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 意见反馈详情
        ['feedbacks$', 'FeedBack/add',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 意见反馈新增

        // -----------------------------------技工钱包相关---------------------------------------------
        // 技工银行卡信息 zjz
        ['worker/bankcard$', 'worker/getBankCardInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 添加银行卡信息 zjz
        ['worker/bankcard$', 'worker/addBankCard', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 修改银行卡信息 zjz
        ['worker/bankcard$', 'worker/upadateBankCard', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 删除银行卡信息 zjz
        ['worker/bankcard$', 'worker/deleteBankCard', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],
        // 银行列表 zjz
        ['banks$', 'common/getBanks', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 修改提现密码 zjz
        ['worker/pay_password$', 'worker/setWorkerPayPassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 验证提现密码 zjz
        ['worker/pay_password_verify$', 'worker/verifyWorkerPayPassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 技工账户余额 zjz
        ['worker/balance$', 'worker/getBalance', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工账户余额变动明细 zjz
        ['worker/balance/logs$', 'worker/getBalanceLogs', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工账户余额变动明细详情 zjz
        ['worker/balance/types/:type/logs/:id\d$', 'worker/getBalanceLogsDetail', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工账户质保金 zjz
        ['worker/balance/quality$', 'worker/balanceQuality', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工钱包申请提现 zjz
        ['worker/balance/extracted$', 'worker/balanceExtracted', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        // -----------------------------------其他---------------------------------------------
        // 指定id的子级地区列表 zjz
        ['areas$', 'common/areaList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 指定id的地区信息 zjz
        ['areas/:id\d$', 'common/areaInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 获取地区全部数据列表（限定前三级）zjz
        ['areas/three$', 'common/areasThree', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // -----------------------------消息模块--------------------------------------
        ['messages$', 'Message/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], //消息列表
        ['messages/:id\d$', 'Message/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 消息详情
        ['messages/systemAnnouncement/:id\d$', 'Message/systemAnnouncementDetail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 系统消息h5
        ['messages/unread_count$', 'Message/unreadCount',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取未读消息数
        ['messages/set_read$', 'Message/setRead',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 标为已读

        // -----------------------------群模块--------------------------------------
        ['groups/check$', 'Group/checkGroup',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 群关联检索
        ['groups/get_worker_status$', 'Group/getWorkerStatus',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 获取当前的用户状态
        ['groups$', 'Group/add',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 创建网点群
        ['groups/:id\d$', 'Group/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 群详情
        ['groups/:id/join$', 'Group/join',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 加入群
        ['groups/:id\d/audit$', 'Group/auditWorker',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 技工审核
        ['groups/:id\d/audit_info$', 'Group/auditWorkerInfo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 技工审核情况
        ['groups/:id\d/workers$', 'Group/groupWorkerList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 群内技工列表
        ['groups/:id\d/remove$', 'Group/remove',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 移除技工
        ['groups/:id\d/statistics/finish_orders$', 'Group/statisticsFinishOrders',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 技工完单统计
        ['groups/:id\d/audit_workers$', 'Group/auditWorkerList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 技工审核列表
        ['groups/:id\d/distribute_order$', 'Group/distributeOrder',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 群主派发工单
        ['groups/check_group_no$', 'Group/checkGroupNo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 群号检索
        ['groups/:id\d/worker_info$', 'Group/groupWorkerInfo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 群内技工详情
        ['groups/recover$', 'Group/groupRecover',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 恢复创建群数据
        // 群主给工单打标记 zjz
        ['orders/:id\d/groups/set_tag', 'Group/setOrderTag',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 恢复创建群数据
        
        //投诉单
        ['complaints/:id\d$', 'Complaint/info',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // -----------------------------发起支付相关--------------------------------------
        // App发起 微信支付所需配置 zjz
        ['orders/:id\d/apppay$', 'Pay/appPay',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/jspay$', 'Pay/qyJsPay',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        ['pay_result/app_notify$', 'Pay/payResultAppNotify',  API_SECRET_PARAM.'='.API_SECRET_CODE],
        ['pay_result/qy_js_notify$', 'Pay/payResultQyNotify',  API_SECRET_PARAM.'='.API_SECRET_CODE],
    ]
];
