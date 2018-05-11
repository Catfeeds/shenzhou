<?php

define('API_SECRET_PARAM', 'api_url_secret');
define('API_SECRET_CODE',  '0A1B0c2D0e3F0G');

return [
    'URL_ROUTE_RULES' => [
        // 易码旧数据转移脚本
        ['factoryexcel$', 'common/yimaApplyAndExcel',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factoryima$', 'common/factoryExcelToYima',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factoryexcelyima$', 'common/factoryExcelForyima',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yimaareaids$', 'common/yimaareaids',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // yilian支付test 创建支付 zjz
        // ['yilian/test$', 'pay/testPayPc',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // yilian支付 同步回调 zjz
        ['ylsyn/:type$', 'pay/returnedSyn',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // yilian支付 异步回调 zjz
        ['ylasyn/:type$', 'pay/returnedAsyn',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        // 产品列表 zjz
        ['products$', 'product/lists',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 产品详情 zjz
        ['products/:id\d$', 'product/info',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 产品易码启用与关闭 zjz
        ['products/:id\d/yima/status$', 'product/yimaStatus',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        // ================================================= 易码 ==============================================================
        // 易码类型与规格 zjz
        ['yima/types$', 'product/yimaTypes',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 申请二维码段记录 zjz
        ['factory/yima/applies$', 'factory/addYimaApply',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 申请二维码段列表 zjz
        ['factory/yima/applies$', 'factory/yimaApplies',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 所有厂家 易码情况列表 zjz
        ['admin/yima/applies$', 'admin/yimaAppliesCount',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 取消易码记录申请 zjz
        ['yima/applies/:id\d/cancel$', 'factory/cancelYimaApplies',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 审核易码记录申请 zjz
        ['yima/applies/:id\d/check$', 'admin/checkYimaApplies',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 易码最近绑定产品分类 zjz
        ['yima/applies/bind/categories$', 'factory/getYimaAppliesBindCategory',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 易码最近绑码规格 zjz
        ['yima/applies/bind/guiges$', 'factory/getYimaAppliesBindGuiges',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 易码最近绑定产品 zjz
        ['yima/applies/bind/products$', 'factory/getYimaAppliesBindProduct',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 码段绑定产品 zjz
        ['yima/applies/bind/check$', 'factory/yimaAppliesBindInfoCheck',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['yima/applies/bind$', 'factory/yimaAppliesBindInfo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 申请记录码段绑定详情详情 zjz
        ['yima/applies/:id\d/info$', 'factory/yimaAppliesGetInfo',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 申请通过的易码申请记录所有二维码导出
        ['yima/applies/:id\d/excel$', 'admin/yimaAppliesExcelYima',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 指定厂家的绑定码段数据（并标出已被激活的二维码，无分页）
        ['factory/:id\d/applies/binds$', 'factory/yimaAppliesAndBind',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 客服清空指定的厂家码段
        ['factory/:id\d/yima/applies$', 'admin/yimaAppliesDelete',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],

        // ================================================= 厂家 ==============================================================
        // 当前厂家用户信息 zjz
        ['factory/info$', 'factory/info',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/factory/category$', 'product/factoryCategory',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/factory/brand$', 'product/factoryBrand',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //  码段搜索 zjz
        ['yima/search/between$', 'yima/searchBetweenCode',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 厂家 技术支持
        ['factory/technology$', 'factory/technology',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //厂家管理 xgq
        ['factory/all$', 'Factory/all', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factories$', 'Factory/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/group$', 'Factory/factoryGroup', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 厂家资金充值 zjz
        ['factory/money/recharge$', 'factory/yilianMoneyRecharge', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 厂家资金统计(厂家，平台客服) zjz
        ['factories/:id/money/total$', 'factory/moneyTotal', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 厂家查看自己的充值记录 zjz
        ['factories/:id/recharges$', 'factory/recharges', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['factories/:id/recharges/export$', 'factory/recharges', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        // 厂家工单相关资金状况 zjz
        ['factories/:id\d/orders/money$', 'factory/workerOrderMoneies', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['factories/:id\d/orders/money/export$', 'factory/workerOrderMoneies', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],

        //厂家资金冻结解冻记录 czl
        ['factories/:id\d/frozens$', 'factory/moneyFrozenThawRecord', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // ================================================= 厂家经销商 ==============================================================
        ['factory/dealer$', 'factory/addDealer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factory/dealer$', 'factory/dealerList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/dealer/:id\d$', 'factory/showDealer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/dealer/:id\d$', 'factory/updateDealer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factory/dealer/:id\d/activated$', 'factory/getDealerActiveRecord',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/dealer/import$', 'factory/importDealer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        // ================================================= 产品 ==============================================================
        ['product/category/standard$', 'product/standard',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 二维码列表 zjz
        ['yimas$', 'yima/yimasForQr',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //  二维码详情 zjz
        ['yimas/info$', 'yima/yimaDetail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //  修改二维码详情 zjz
        ['yimas/:code$', 'yima/updateDetail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        //  停用二维码 zjz
        ['yimas/:code/disable$', 'yima/disableDetail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 导出易码详情 xgq
        ['yima/excel/:id\d/export$', 'yima/exportYimaData',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 易码概况 xgq
        ['yima/factory/repair$', 'yima/repairYimaDate',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yima/factory/summary$', 'yima/summary',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yima/factory/monthSummary$', 'yima/monthSummary',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yima/factory/areaSellSummary$', 'yima/areaSellSummary',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yima/factory/productSummary$', 'yima/productSummary',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yima/factory$', 'yima/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['yima/detail/:code$', 'yima/exportYimaData',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],


        // 产品分类
        ['productCategories$', 'ProductCategory/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ================================================= 统计信息 ==============================================================
        ['statistics/auditor/fee$', 'statistics/orderFeeExport', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['statistics/week$', 'statistics/week', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['statistics/worker/score$', 'statistics/calWorkerScore', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ================================================= 客服 ==============================================================
        ['admins/all$', 'Admin/all', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['admins$', 'Admin/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['admins$', 'Admin/addHandle', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['admins/:id\d$', 'Admin/editHandle', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['admins/:id\d$', 'Admin/adminInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['admin/login$', 'Login/adminLogin', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['admin/info$', 'Admin/info', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['admin/editPassword$', 'Admin/editPassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['admins/permission/config$', 'Admin/importAdminExcelConfig', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 客服详情 zjz
//        ['admins/:id\d$', 'Admin/infoById', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],


        // 查看厂家的充值记录 zjz
        ['factories/recharges$', 'admin/factoryRecharges', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['factories/recharges/export$', 'admin/factoryRecharges', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        // 调整厂家资金 zjz
        ['adjust/factories/:id\d/money$', 'admin/factoryRechargeMoney', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 调整厂家资金配置信息 zjz
        ['adjust/factories/:id\d/feeconfig$', 'admin/factoryFeeConfig', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 厂家资金配置信息调整记录 zjz
        ['adjust/factories/:id\d/feeconfig$', 'admin/getFactoryFeeConfig', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ================================================= 订单 ==============================================================
        ['orders$', 'order/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['orders/all$', 'order/getListAll',  API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0&show_all=1', ['method' => 'get']],
        ['orders/export$', 'order/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['orders/:id\d$', 'order/getDetail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/userInfo$', 'Order/confirmOrderUserInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/:id\d/cancel$', 'Order/cancel', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:id\d/products/faults$', 'Order/getOrderProductFaults', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/products$', 'Order/operateOrderProducts', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/statistics/type$', 'Order/getTypeNum', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/services$', 'Order/detailsServices', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/services$', 'Order/updateOrdersProductsServices', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:id\d/workerTest', 'Order/workerHandleOrderTest', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ================================================= 客服工单 ==============================================================
        ['orders/:id\d/checker/receive$', 'OrderAdmin/checkerReceive', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/:id\d/checker/confirm$', 'OrderAdmin/confirmOrderInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/:id\d/distributor/receive$', 'OrderAdmin/distributorReceive', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/:id\d/receiversFirst$', 'OrderAdmin/receiversFirst', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/distributor/distribute$', 'OrderAdmin/distribute2Worker', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/:id\d/serviceType$', 'OrderAdmin/modifyServiceType', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:id\d/auditRemarks$', 'OrderAdmin/addAuditRemark', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/:id\d/auditRemarks$', 'OrderAdmin/getAuditRemark', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:id\d/operationRecords$', 'OrderAdmin/addOrderOperationRecord', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/operationRecords/:id\d$', 'OrderAdmin/getOrderOperationRecord', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/operationRecords/:id\d$', 'OrderAdmin/updateOrderOperationRecord', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:id\d/fee$', 'OrderAdmin/adjustOrderFee', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:id\d/userPaid$', 'OrderAdmin/userPaid', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        // ================================================= 厂家工单 ==============================================================
        ['orders$', 'orderFactory/add',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factory/orderServices$', 'Factory/getFactoryServiceTypes', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/order/customer$', 'orderFactory/customerInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/order/:id\d/reAdd$', 'orderFactory/reAdd', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factory/order/:id\d/orderOrNot$', 'orderFactory/factoryOrderOrNot', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factory/order/recentProducts$', 'factoryProduct/getRecentProduct', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/order/recentCategories$', 'factoryProduct/getRecentOrderCategory', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/order/recentSpecifications$', 'factoryProduct/getRecentOrderSpecification', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/productCategories$', 'factoryProduct/getAllCategory', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/productSpecifications$', 'factoryProduct/getAllSpecification', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/productBranches$', 'factoryProduct/getAllBranch', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/productModes$', 'factoryProduct/getProductMode', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/product/categoryFaults$', 'factoryProduct/getCategoryFault', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/rework$', 'orderFactory/applyRework',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],


        // ================================================= 投诉单 ==============================================================
        ['complaints$', 'complaint/getList', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['complaints/export$', 'complaint/getList', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['complaints$', 'complaint/create', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['complaints/types$', 'complaint/getComplainType', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['complaints/:id\d$', 'complaint/info', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['complaints/:id\d/verify$', 'complaint/verify', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['complaints/:id\d/reply$', 'complaint/reply', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['complaints/:id\d/prompt_complaint_to$', 'complaint/promptComplaintTo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['complaints/complaint_from$', 'complaint/getComplaintFrom', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],


        // ================================================= 物流 ==============================================================
        ['express/search/code$', 'express/getExpressCompanyByNo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['express/callback/:express_id\d$', 'express/callback', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 物流公司搜索
        ['express/search/expressCode$', 'express/getExpressCompanyList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ================================================= 其他 ==============================================================
        // 最后缓存数据
        ['active/cache$', 'common/dataLastCache',  API_SECRET_PARAM.'='.API_SECRET_CODE.'&type=1', ['method' => 'get']],
        //  生成预览的二维码
        ['qr/image$', 'common/qrImage',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 地区
        // 地区分组
        ['area/group$', 'area/group',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 地区列表
        ['area$', 'area/index',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //  导单 订单
        ['orders/import/getData$', 'orderFactory/getBatchImportData', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orders/import/batch$', 'orderFactory/batchImportOrders', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //============================================ 厂家后台迁移 =============================================================//
        //登录 fzy
        ['login$', 'Login/login', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['verify$', 'Login/verify', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //短信验证码 fzy
        ['login/smsCode$', 'Login/getPhoneCode', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['login/checkCode$', 'Login/checkCode', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factory/changePwd$', 'Login/changePassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['getUserByToken$', 'Login/getUserByToken', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['getToken$', 'Login/getToken',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        ['workerlist$', 'Worker/workerList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workers$', 'Worker/workers', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workers/export$', 'Worker/export', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workers/getInfo$', 'Worker/getInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //重置技工提现密码  czl
        ['workers/:id\d/reset/withdraw_password$', 'Worker/resetWorkerWithdrawPassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],


        //厂家信息 fzy
        ['factory/factoryInfo$', 'FactoryManage/factoryInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factoryInfo/edit$', 'FactoryManage/editFactoryInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        //技术支持人 fzy
        ['factory/person/list$', 'FactoryManage/technicalSupportPerson', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/person/add$', 'FactoryManage/addTechnicalSupportPerson', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factory/person/edit$', 'FactoryManage/editTechnicalSupportPerson', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 技术支持人管理 xgq
        ['factory/helper/operate$', 'FactoryManage/operateFactoryHelper', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        //批量删除
        ['factory/person/batch/del$', 'FactoryManage/delBatchTechnicalSupportPerson', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],
        ['factory/person/:id\d\d/del$', 'FactoryManage/delTechnicalSupportPerson', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],

        //图片上传 fzy
        ['files/base64$', 'file/uploadsBase64', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        //重置密码 fzy
        ['factory/chPwd$', 'FactoryManage/factoryChangePassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //系统管理 fzy
        //添加角色
        ['factoryAdmin/role/add$', 'FactoryAdmin/addRole', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        //编辑角色 fzy
        ['factoryAdmin/role/edit$', 'FactoryAdmin/editRole', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factoryAdmin/role/One/:id\d$', 'FactoryAdmin/getOneRole', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //角色列表 fzy
        ['factoryAdmin/role/list$', 'FactoryAdmin/roleList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //角色权限 fzy
        ['factoryAdmin/roleAuth$', 'FactoryAdmin/roleAuth', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 厂家权限节点列表 fzy
        ['factoryAdmin/auth/list$', 'FactoryAdmin/authList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        //编辑角色权限（授权） fzy
        ['factoryAdmin/auth/setAccess$', 'FactoryAdmin/setAccess', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        //组别 fzy
        ['factoryAdmin/tags/add$', 'FactoryAdmin/addTags', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factoryAdmin/tags/edit$', 'FactoryAdmin/editTags', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factoryAdmin/tags/del$', 'FactoryAdmin/deleteTag', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factoryAdmin/tags/lists$', 'FactoryAdmin/tagsLists', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factoryAdmin/tags/all$', 'FactoryAdmin/getAllTags', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factoryAdmin/tags/info/:id\d$', 'FactoryAdmin/getTag', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //子账号 fzy
        ['factoryAdmin/admin/add$', 'FactoryAdmin/addAdmin', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['factoryAdmin/admin/edit$', 'FactoryAdmin/editAdmin', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        ['factoryAdmin/admin/del$', 'FactoryAdmin/delAdmin', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factoryAdmin/admin/list$', 'FactoryAdmin/adminList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factoryAdmin/admin/info/:id\d$', 'FactoryAdmin/adminInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //帮助文档 fzy
        ['factory/help/list$', 'FactoryManage/factoryHelpDocument', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/help/info$', 'FactoryManage/getOneHelp', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //产品品牌 fzy
        ['product/factoryPro$', 'Product/addFactoryProduct', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['product/brand/add$', 'Product/addFactoryBrand', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['product/brand/list$', 'Product/getProductBrandList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/Brand/list/one$', 'Product/getOneProductBrand', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/Brand/edit$', 'Product/editProductBrand', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['product/Brand/del/:id\d$', 'Product/hideProductBrand', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['product/Brand/operate$', 'Product/operateProductBrand', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 产品型号
        ['product/model/operate$', 'Product/operateProductModel', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        //属性 fzy
        ['product/attr$', 'Product/addProductAttr', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['product/attr/list$', 'Product/getProductAttr', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/attr/del$', 'Product/hideProductAttr', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/attr/one/:id\d$', 'Product/getOneAttr', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //获取一个厂家产品的详细信息 FZY
        ['product/one$', 'Product/getOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/del$', 'Product/delOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 回收 fzy
        ['product/recycle$', 'Product/recycle', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['product/recycle/one$', 'Product/recoveryOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //============================================ 厂家后台迁移 zjz =============================================================//
        // 派单：接单人列表 （智能排单）zjz
        ['orders/:id\d/receivers$', 'orderAdmin/receivers',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 回访客服接单 zjz
        ['orders/:id\d/returnee/receive$', 'orderAdmin/returneeReceiveOrder',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 回访客服是否确认与维修商结算 zjz
        ['orders/:id\d/returnee/payforworker$', 'orderAdmin/isPayForWorker',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 平台财务客服接单 zjz
        ['orders/:id\d/auditor/receive$', 'orderAdmin/auditorReceiveOrder',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 平台财务客服审核(与维修商结算) zjz
        ['orders/:id\d/auditor/audited$', 'orderAdmin/auditedOrder',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 厂家财务客服审核 zjz
        ['orders/:id\d/factory/audited$', 'orderFactory/auditedOrder',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //配件单
        ['accessories$', 'accessory/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['accessories/status$', 'accessory/getStatusCnt', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['accessories/:accessory_id$', 'accessory/info', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['accessories/:accessory_id/factoryCheck$', 'accessory/factoryCheck', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/factoryDelaySend$', 'accessory/factoryDelaySend', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/factoryConfirmSend$', 'accessory/factoryConfirmSend', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/giveUpReturn$', 'accessory/giveUpReturn', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/factoryConfirmSendBack$', 'accessory/factoryConfirmSendBack', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/factoryStop$', 'accessory/factoryStop', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/csCheck$', 'accessory/adminCheck', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['accessories/:accessory_id/csStop$', 'accessory/adminStop', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 修改收件人地址
        ['accessories/:accessory_id/address$', 'accessory/addressEdit', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 客服申请配件单 zjz
        ['orders/:id\d/detail/:product_id\d/accessories$', 'accessory/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //费用单
        ['cost$', 'cost/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['cost/status$', 'cost/getStatusCnt', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['cost/:cost_id\d$', 'cost/info', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['cost/:cost_id\d/factoryCheck$', 'cost/factoryCheck', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['cost/:cost_id\d/csCheck$', 'cost/adminCheck', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['cost/:cost_id\d/pendingTrial$', 'cost/pendingTrial', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //物流
        ['express$', 'express/getExpress', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['express$', 'express/editExpress', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //工单留言
        ['orderMessages$', 'orderMessage/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orderMessages$', 'orderMessage/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //补贴单
        ['allowances$', 'Allowance/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['allowances/export$', 'Allowance/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['allowances/batchStatus$', 'Allowance/batchStatus', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['allowances/status$', 'Allowance/status', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['allowances/history$', 'Allowance/history', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['allowances/:allow_id$', 'Allowance/info', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['allowances$', 'Allowance/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //工单详情按钮
        ['orders/:order_id/delegate$', 'OrderTransfer/delegate', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/delegateBatch$', 'OrderTransfer/delegateBatch', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/receiveBatch$', 'OrderTransfer/receiveBatch', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:order_id/userList$', 'OrderTransfer/userList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/userListBatch$', 'Admin/getAvailableList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/:order_id/stop$', 'OrderTransfer/stop', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['orders/:id/transfer_order_type$', 'OrderTransfer/workerOrderType', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //技工标签
        ['workerLabels$', 'WorkerLabel/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workerLabels/:worker_id/history$', 'WorkerLabel/getHistoryLabel', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workerLabels/:worker_id/adminHistory$', 'WorkerLabel/getAdminHistoryLabel', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workerLabels/:worker_id$', 'WorkerLabel/label', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['worker_labels/:worker_id$', 'WorkerLabel/deleteLabel', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],

        //开点单
        ['recruits$', 'Recruit/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['recruits$', 'Recruit/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['recruits/:apply_id$', 'Recruit/info', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['recruits/:apply_id/userList$', 'Recruit/userList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['recruits/:apply_id/designate$', 'Recruit/designate', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['recruits/:apply_id/evaluate$', 'Recruit/evaluate', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['recruits/:order_id/history$', 'Recruit/history', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['recruits/:apply_id/cancel$', 'Recruit/cancel', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['recruits/:apply_id/feedback$', 'Recruit/feedback', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['recruits/:apply_id/workerList$', 'Recruit/workerList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //联系记录
        ['orderContacts$', 'OrderContact/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['orderContacts/export$', 'OrderContact/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['orderContacts$', 'OrderContact/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orderContacts/addAndRegister$', 'OrderContact/addAndRegister', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['orderContacts/history$', 'OrderContact/history', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //资金调整
        ['adjustments$', 'WorkerAdjustment/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['adjustments/export$', 'WorkerAdjustment/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['adjustments$', 'WorkerAdjustment/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //技工提现
        ['withdraw$', 'WorkerWithdraw/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['withdraw/export$', 'WorkerWithdraw/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['withdraw/bank$', 'WorkerWithdraw/bank', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['withdraw/excelHistory$', 'WorkerWithdraw/excelHistory', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['withdraw/excelDownload$', 'WorkerWithdraw/excelDownload', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['withdraw/processed$', 'WorkerWithdraw/processed', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['withdraw/:withdraw_id\d$', 'WorkerWithdraw/edit', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['withdraw/withdrawBatch$', 'WorkerWithdraw/editBatch', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //收入记录
        ['incomes$', 'WorkerIncome/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['incomes/export$', 'WorkerIncome/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],

        //质保金
        ['qualities$', 'WorkerQuality/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=0', ['method' => 'get']],
        ['qualities/export$', 'WorkerQuality/index', API_SECRET_PARAM.'='.API_SECRET_CODE.'&is_export=1', ['method' => 'get']],
        ['qualities$', 'WorkerQuality/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //系统消息
        ['systemMessage$', 'SystemMessage/index', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['systemMessage/:msg_id\d/read$', 'SystemMessage/read', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['systemMessage/readAll$', 'SystemMessage/readAll', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //厂家管理
        ['factory/:factory_id/getFactoryCategory$', 'FactoryManage/getFactoryCategory', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/getStandard$', 'FactoryManage/getStandard', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/:factory_id/faultFeeList$', 'FactoryManage/getFaultFeeList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['factory/:factory_id/faultFeeList$', 'FactoryManage/editFaultFee', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['factory/:factory_id/resetFaultFee$', 'FactoryManage/resetFaultFee', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        
        //定时任务
        ['orders/settle$', 'Cron/settle', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/clear$', 'Cron/clear', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/promptWorkerUploadAccessoryReport$', 'Cron/promptWorkerUploadAccessoryReport', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/promptWorkerAppointTomorrow$', 'Cron/promptWorkerAppointTomorrow', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/promptFactoryAccessoryConfirmSend$', 'Cron/promptFactoryAccessoryConfirmSend', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/promptWorkerAccessorySendBack$', 'Cron/promptWorkerAccessorySendBack', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['orders/promptWorkerUploadReport$', 'Cron/promptWorkerUploadReport', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //意见反馈
        ['feedbacks/:id\d/event$', 'Feedback/feedbackEvent',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 意见反馈推送事件

        ['workers/:worker_id\d/event/:type\d$', 'Worker/workerCheck',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 技工信息审核


        ['factories/recharge/request$', 'FactoryRecharge/request',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 厂家充值
        ['factories/recharge/callback$', 'FactoryRecharge/callback',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 厂家充值
        ['factories/recharge/sync$', 'FactoryRecharge/sync',  API_SECRET_PARAM.'='.API_SECRET_CODE], // 厂家充值
        ['factories/recharge/qrcode$', 'FactoryRecharge/qrcode',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 厂家充值

        //平台支付列表
        ['payPlatforms$', 'PayPlatform/index',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['payPlatforms/:id\d$', 'PayPlatform/info',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //抽奖管理
        ['draw_rules$', 'Draw/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['draw_rules$', 'Draw/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['draw_rules/:id\d$', 'Draw/update', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['draw_rules/:id\d$', 'Draw/read', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['draw_rules/:id\d/operate$', 'Draw/operate', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['draw_rules/:id\d/win_list$', 'Draw/winList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['draw_records/:id\d/express$', 'Draw/express', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['draw_rules/:id\d/draw_list$', 'Draw/drawList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['draw_rules/:id\d/draw_data$', 'Draw/drawData', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['draw_rules/:id\d/prize_list$', 'Draw/prizeList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['draw_rules/:id\d/prizes$', 'Draw/prizes', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //脚本
        //抽奖自动结束
        ['draw_rules/statusScript$', 'Draw/statusScript', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        //统计前一天数据
        ['draw_rules/statisticsScript$', 'Draw/statisticsScript', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],


        //优惠券管理
        ['coupons$', 'CouponRule/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['coupons$', 'CouponRule/add', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['coupons/:id\d$', 'CouponRule/update', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        ['coupons/:id\d$', 'CouponRule/view', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['coupons/:id\d/operate$', 'CouponRule/operate', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['coupons/:id\d/send$', 'CouponRule/send', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        //优惠劵领取记录管理
        ['coupons/:id\d/receive_records$', 'CouponReceiveRecord/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['coupons/:coupon_id\d/receive_records/:id\d/operate$', 'CouponReceiveRecord/operate', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],


        //用户管理
        ['users$', 'User/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['users/:id\d$', 'User/read', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['users/:id\d/products$', 'User/products', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['users/:id\d/worker_orders$', 'User/worker_orders', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //宣传图配置
        ['ad_positions$', 'adPosition/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['ad_positions/:id\d$', 'adPosition/read', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['ad_positions/:id\d/ad_photos$', 'adPosition/update', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //电信云
        ['webcall$', 'Webcall/create',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        ['webcall/hangup$', 'Webcall/hangup',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //============================================ 180123客服 zjz =============================================================//
        // 客服kpi
        ['admin/kpi/export$', 'admin/adminKpiExport', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //保外单转换保内单
        ['orders/:id\d/transfer_order_type$', 'OrderTransfer/workerOrderType', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //群管理
        ['groups$', 'Group/groupList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 网点群列表
        ['groups/:id\d$', 'Group/detail',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 网点群详情
        ['groups/:id\d/workers$', 'Group/groupWorkerList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']], // 群内技工列表接口
        ['groups/:id\d/audit$', 'Group/audit',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']], // 群审核
        ['groups/:id\d$', 'Group/update',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']], // 群信息修改

        //============================================ 权限模块 zjz =============================================================//
        // 所有角色列表 zjz
        ['admin_roles/all$', 'adminRole/getAllList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 角色列表 zjz
        ['admin_roles$', 'adminRole/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 角色详情 zjz
        ['admin_roles/:id\d$', 'adminRole/getOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 修改角色信息 zjz
        ['admin_roles/:id\d$', 'adminRole/updateOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 新建角色信息 zjz
        ['admin_roles$', 'adminRole/insertOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 修改角色权限 zjz
        ['admin_roles/:id\d/frontend_routings$', 'adminRole/updateAdminRoleFrontendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],

        // 所有组别列表 zjz
        ['admin_groups/all$', 'adminGroup/getAllList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 组别列表 zjz
        ['admin_groups$', 'adminGroup/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 组别详情 zjz
        ['admin_groups/:id\d$', 'adminGroup/getOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 修改组别信息 zjz
        ['admin_groups/:id\d$', 'adminGroup/updateOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 新建组别信息 zjz
        ['admin_groups$', 'adminGroup/insertOne', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 客服所属组别 xgq
        ['admins/admin_groups$', 'adminGroup/ownGroup', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 组别成员 xgq
        ['admin_groups/members$', 'adminGroup/groupMember', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 重置指定账号的密码
        ['admins/:id\d/reset_password$', 'admin/resetOtherPassword', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        // 前端路由列表 zjz
        ['frontend_routings$', 'routing/getFrontendRoutings', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 前端路由列表 树状图 zjz
        ['frontend_routings/tree$', 'routing/getFrontendRoutingsTree', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 角色前端路由列表 zjz
        ['frontend_routings/roles/:id\d$', 'routing/getRoleFrontendRoutings', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 前端路由详情 zjz
        ['frontend_routings/:id\d$', 'routing/getFrontendRoutingById', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 新建前端路由 zjz
        ['frontend_routings$', 'routing/addFrontendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 修改前端路由 zjz
        ['frontend_routings/:id\d$', 'routing/updateFrontendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 删除前端路由 zjz
        ['frontend_routings/:id\d$', 'routing/removeFrontendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],

        // 后端路由列表 zjz
        ['backend_routings$', 'routing/getBackendRoutings', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 后端路由详情 zjz
        ['backend_routings/:id\d$', 'routing/getBackendRoutingById', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 更新后端路由信息 zjz
        ['backend_routings/:id\d$', 'routing/updateBackendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 新增后端路由 zjz
        ['backend_routings', 'routing/addBackendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // 删除后端路由 zjz
        ['backend_routings/:id\d$', 'routing/removeBackendRouting', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],

        //============================================ 新迎燕(XYY) zjz =============================================================//
        // 批量下单：预发件安装单 zjz
        ['xyy/orders/express_installation$', 'XinYingYan/createExpressInstallationOrders', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        //  新迎燕跳转页面
        ['xyy/login/skip', 'xinYingYan/loginAndSkip', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //  新迎燕订单数据推送
        ['xyy/push/orders_data', 'xinYingYan/pushWorderOrderStatus', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //工作台
        ['workbench$', 'Workbench/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workbench/stats$', 'Workbench/statsList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workbench_config$', 'WorkbenchConfig/getList',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['workbench_config$', 'WorkbenchConfig/edit',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],

        //============================================ IP管理模块 czl =============================================================//
        //IP管理列表 czl
        ['admins/limit_ips$', 'adminLimitIp/getList', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 新建IP信息 czl
        ['admins/limit_ips$', 'adminLimitIp/insert', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        // IP详情 czl
        ['admins/limit_ips/:id\d$', 'adminLimitIp/getInfo', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 修改IP信息 czl
        ['admins/limit_ips/:id\d$', 'adminLimitIp/update', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'put']],
        // 删除IP信息 czl
        ['admins/limit_ips/:id\d$', 'adminLimitIp/remove', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'delete']],

    ]
];
