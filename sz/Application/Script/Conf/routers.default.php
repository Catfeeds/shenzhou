<?php

define('API_SECRET_PARAM', 'api_url_secret');
define('API_SECRET_CODE',  '0A1B0c2D0e3F0G');

return [
    'URL_ROUTE_RULES' => [
        // 表结构处理 共用脚本生成 zjz
        ['db/structures$', 'common/dbStructures',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 数据转移 zjz
        ['data/transfer$', 'common/sqlDataTransfer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 获取数据转移结果 zjz
        ['get/data/transfer$', 'common/getSqlDataTransfer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 数据迁移恢复 zjz
        ['reset/data/transfer$', 'common/resetSqlDataTransfer',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 数据库迁移表时间结构 zjz
        ['add/sync/time$', 'common/addDbStructuresSyncTime',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['delete/sync/time$', 'common/deleteDbStructuresSyncTime',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工钱包金额检查 zjz
        ['worker/money/checkout$', 'worker/workerMoneyCheckout',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 厂家钱包金额检查 zjz
        ['factory/money/checkout$', 'factory/factoryMoneyCheckout',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 完结后的配件费 厂家钱包金额检查 zjz
        ['factory/money/checkout/accefee$', 'factory/factoryMoneyCheckoutAccefee',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工提现记录withdrawcash_excel_id更新 zjz
        ['set/worker/withdraw/excelid$', 'factory/setWorkerWithdrawExcelId',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 技工变动记录更新 zjz
        ['worker/money/set/default$', 'worker/workerMoneySetDefault',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['worker/money/set/default/txt$', 'worker/workerMoneySetDefaultTxt',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 冻结金统计并更新厂家数据 zjz
        ['factory/fronzen/set/default$', 'factory/factoryFronzenSetDefault',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 带财务审核时的费用修改：修改厂家与技工操作记录 数据中 查看权限改为客服可见
//        ['worker/operation/seeauth/edit/fee$', 'record/workerOperationSeeauthEditFee',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 指定技工的所有变动记录
        ['worker/order/record/list/orderat$', 'worker/workerOrderRecordListOrderat',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //工单结算注意事项 --fzy
        ['record/auditRemark$', 'Record/auditRemark',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //技工联系记录 --fzy
        ['record/worker/contact$', 'Record/workerContact',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //工单回访记录 --fzy
        ['record/order/revisit$', 'Record/orderRevisit',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //工单留言记录 --fzy
        ['worker/order/message$', 'Record/orderMessage',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //物流跟踪信息 --fzy
        ['worker/order/express$', 'Record/express',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //工单投诉 --fzy
        ['worker/order/complaint$', 'Record/complaint',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //worker_order_statistics 统计更新 zjz
        ['worker/order/statistics', 'Record/workerOrderStatistics',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ==========================================================================================
        //脚本生成 zjz
        ['sql/sqldumpv3', 'common/sqldumpv3',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 为了上线后比较好验证资金问题，希望数据迁移后，可以将系统上所有的厂家、左右的技工的当前余额导成excel zjz
        ['worker/factory/money/excel', 'common/workerFactoryMoneyExcel',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        //  重复结算工单资金处理脚本 浙江夏宝电器有限公司（浙江夏宝电器）zjz
        ['factory/order/agen/money/change/set/default$', 'factory/factoryOrderAgenMoneyChangeSetDefault',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ===========================================fix-v3.0===============================================
        // 配件单技工id与工单技工id不一直数据处理 zjz
        ['set/acce/workerid/order/default/workerid$', 'worker/setAcceWorkeridOrderDefaultWorkerid',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['accessories/autoCompleted$', 'Accessory/complete',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['accessories/express$', 'Accessory/express',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // bug（https://szlb.5upm.com/bug-view-1649.html）上线后以生成的数据处理 zjz
        ['set/order/extinfo/default/service/evaluate$', 'common/setOrderExtinfoDefaultServiceEvaluate',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 数据迁移前 技工的调整记录中有工单编号 迁移后没有工单id的数据处理 zjz
        ['set/worker/money/record/adjust/not/orno/default$', 'worker/setWorkerMoneyRecordAdjustNotOrnoDefault',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //bug https://szlb.5upm.com/bug-view-1720.html
        ['complaint/setAdmin$', 'Complaint/setAdmin',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['complaint/transferResponseType$', 'Complaint/transferResponseType',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //修复技工余额
        ['worker/money/checkWorker$', 'WorkerMoney/checkWorker',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['worker/quality/checkWorker$', 'WorkerQuality/checkWorker',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //bug https://szlb.5upm.com/bug-view-1720.html
        ['complaint/setAdmin$', 'Complaint/setAdmin',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        ['complaint/transferResponseType$', 'Complaint/transferResponseType',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 导出厂家 账号状态及有效时间段
        ['factory/validtime/export$', 'factory/exportFactoryValidTime',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 修复 技工APP 重复提交返件费时，第二次将第一次数据覆盖并且变成0的bug数据检查修复，并将修复的工单号 生成一个文件
        ['factory/and/worker/acceReturnFeeBug', 'factory/factoryAndWorkerAcceReturnFeeBug',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // ===========================================fix-抽奖领券===============================================
        // 从worker_order 获取信息 填充 wx_user用户地址信息
        ['set/user/address/forlast/worker/order$', 'user/setUserAddressForlastWorkerOrder',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //wx_user 拆分字段area_ids 为: province_id , city_id , area_id
        ['user/area/explode$', 'User/userArea',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //wx_user_products 同步code字段
        ['products/wx_user_product_code$', 'Product/codeUpdate',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        //dealer_info 拆分字段area_ids 为: province_id , city_id , area_id
        ['dealer_info/area/explode$', 'User/dealerInfoArea',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 厂家资金变动记录检查脚本 zjz
        ['moneyRecordMoneyEQNextLastMoney$', 'factory/moneyRecordMoneyEQNextLastMoney',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 全部资金有问题的厂家检查脚本 zjz
        ['newCheckoutFactoryMoney$', 'factory/newCheckoutFactoryMoney',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 配件申请单主表操作时间填充 zjz
        ['workerOrderApplyAccessoryActionTime$', 'workerOrder/workerOrderApplyAccessoryActionTime',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 费用申请单主表操作时间填充 zjz
        ['workerOrderApplyCostActionTime$', 'workerOrder/workerOrderApplyCostActionTime',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],


        ['order/return_time$', 'Workbench/returnTime',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 厂家费用维修 问题 数据修复 zjz
        ['factoryFaultPriceBackCheckAndDelete$', 'factory/factoryFaultPriceBackCheckAndDelete',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        ['factoryFaultPriceSetId$', 'factory/factoryFaultPriceSetId',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],

        // 保外单流程优化 加收费用 数据库变动，旧数据处理 zjz
        ['outOrderAddFeeRule', 'order/outOrderAddFeeRule',  API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
    ]

];
