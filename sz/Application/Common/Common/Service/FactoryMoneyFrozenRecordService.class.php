<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/2
 * Time: 19:47
 */

namespace Common\Common\Service;

use Common\Common\Model\BaseModel;

class FactoryMoneyFrozenRecordService
{
    const FROZEN_TYPE_ORDER_STATUS_ING   = 0;    // 进行中的冻结金类型
    const FROZEN_TYPE_WAITING_SETTLEMENT = 1;   // 进行中的冻结金类型

    const TYPE_FACTORY_ADD_ORDER           = 1;             // 工单通过厂家审核(厂家下单)
    const TYPE_CS_MODIFY_PRODUCT           = 2;           // 客服修改工单产品信息
    const TYPE_WORKER_UPLOAD_PRODUCT_FAULT = 3;           // 技工上传工单产品维修项   20180504 移除
    const TYPE_CS_MODIFY_PRODUCT_FAULT     = 4;           // 客服修改工单产品维修项   20180504 移除
    const TYPE_CS_EDIT_FACTORY_FEE         = 5;           // 客服修改厂家费用        20180504 移除
    const TYPE_ORDER_SETTLEMENT            = 6;           // 工单结算成功
    const TYPE_ORDER_CANCEL                = 7;           // 工单取消
    const TYPE_ORDER_READD                 = 8;             // 重新下单
    const TYPE_FACTORY_MODIFY_PRODUCT      = 9;           // 厂家修改工单产品信息
    const TYPE_SYSTEM_ORDER_SETTLEMENT     = 10;          // 自动结算
    const TYPE_CS_OUT_TO_IN                = 11;            // 工单保外转保内
    const TYPE_WORKER_ORDER_PRODUCT_FINSH_REPAIR = 12;    // 工单完成维修            20180504 移除
    const TYPE_ADMIN_ORDER_CONFORM_AUDITOR = 13;          // 客服财务审核通过
    const TYPE_CS_SERVICE_TYPE             = 14;  // 工单服务类型改动

    const ALL_TYPE_ARRAY
        = [
            self::TYPE_FACTORY_ADD_ORDER,
            self::TYPE_CS_MODIFY_PRODUCT,
//            self::TYPE_WORKER_UPLOAD_PRODUCT_FAULT,
//            self::TYPE_CS_MODIFY_PRODUCT_FAULT,
//            self::TYPE_CS_EDIT_FACTORY_FEE,
            self::TYPE_ORDER_SETTLEMENT,
            self::TYPE_ORDER_CANCEL,
            self::TYPE_ORDER_READD,
            self::TYPE_FACTORY_MODIFY_PRODUCT,
            self::TYPE_SYSTEM_ORDER_SETTLEMENT,
            self::TYPE_CS_OUT_TO_IN,
//            self::TYPE_WORKER_ORDER_PRODUCT_FINSH_REPAIR,
            self::TYPE_ADMIN_ORDER_CONFORM_AUDITOR,
            self::TYPE_CS_SERVICE_TYPE,
        ];

    const TYPE_CHANGE_REMARKS_EGT_ZERO_KEY_VALUE = [
        self::TYPE_FACTORY_ADD_ORDER => '新建工单，帐户维修金被冻结:frozen_money元',
        self::TYPE_ORDER_READD => '新建工单，帐户维修金被冻结:frozen_money元',
        self::TYPE_CS_OUT_TO_IN => '工单由保外转为保内，帐户维修金被冻结:frozen_money元',
        self::TYPE_CS_MODIFY_PRODUCT => '工单产品修改，帐户维修金被冻结:frozen_money元',
        self::TYPE_FACTORY_MODIFY_PRODUCT => '工单产品修改，帐户维修金被冻结:frozen_money元',
        self::TYPE_CS_SERVICE_TYPE => '工单服务类型调整，重新冻结维修金:frozen_money元',
        self::TYPE_WORKER_UPLOAD_PRODUCT_FAULT => '技工上传工单产品维修项',
        self::TYPE_CS_MODIFY_PRODUCT_FAULT => '客服修改工单产品维修项',
        self::TYPE_CS_EDIT_FACTORY_FEE => '客服修改厂家费用',
        self::TYPE_WORKER_ORDER_PRODUCT_FINSH_REPAIR => '工单完成维修',
    ];

    const TYPE_CHANGE_REMARKS_LT_ZERO_KEY_VALUE = [
        self::TYPE_CS_MODIFY_PRODUCT => '工单产品修改，释放原产品冻结维修金:frozen_money元',
        self::TYPE_FACTORY_MODIFY_PRODUCT => '工单产品修改，释放原产品冻结维修金:frozen_money元',
        self::TYPE_ORDER_CANCEL => '工单已取消，释放之前冻结维修金:frozen_money元',
        self::TYPE_CS_SERVICE_TYPE => '工单服务类型调整，释放之前冻结维修金:frozen_money元',
        self::TYPE_ORDER_SETTLEMENT => '厂家审核通过，将待结算金额:frozen_money元转为已结算费用',
        self::TYPE_SYSTEM_ORDER_SETTLEMENT => '厂家审核通过，将待结算金额:frozen_money元转为已结算费用',
        self::TYPE_WORKER_UPLOAD_PRODUCT_FAULT => '技工上传工单产品维修项',
        self::TYPE_CS_MODIFY_PRODUCT_FAULT => '客服修改工单产品维修项',
        self::TYPE_CS_EDIT_FACTORY_FEE => '客服修改厂家费用',
        self::TYPE_WORKER_ORDER_PRODUCT_FINSH_REPAIR => '工单完成维修',
    ];

    /**
     * @param $type
     * @param $frozen_money
     * @param int $times 当前出现第几次
     * @return mixed
     */
    public static function getTypeChangeString($type, $frozen_money, $money_type, $times = 1)
    {
        $frozen_reason = '';
//        if ($frozen_money >= 0) {  //当冻结金为 + 时候
        if ($money_type == 2) {  //当冻结金为 + 时候
            if($type == self::TYPE_ADMIN_ORDER_CONFORM_AUDITOR && $times == 1) {  //当神州财务第一次提交给厂家财务审核
                $frozen_reason = '工单提交厂家审核，按实际服务项目冻结'. $frozen_money. '元到待结算费用';
            } elseif ($type == self::TYPE_ADMIN_ORDER_CONFORM_AUDITOR && $times > 1) {  //厂家审核不通过后，神州财务再次提交厂家财务审核
                $frozen_reason = '再次提交厂家审核，按实际服务项目冻结'. $frozen_money. '元到待结算费用';
            } else {
                $frozen_reason = self::TYPE_CHANGE_REMARKS_EGT_ZERO_KEY_VALUE[$type];
            }
        } elseif ($money_type == 1) {  //当冻结金为 - 时候
            if ($type == self::TYPE_ADMIN_ORDER_CONFORM_AUDITOR && $times == 1) {  //当神州财务第一次提交给厂家财务审核
                $frozen_reason = '工单完成服务，释放之前冻结维修金'. $frozen_money. '元';
            } elseif ($type == self::TYPE_ADMIN_ORDER_CONFORM_AUDITOR && $times > 1) {  //厂家审核不通过后，神州财务再次提交厂家财务审核
                $frozen_reason = '厂家审核不通过，释放之前冻结的待结算金额'. $frozen_money. '元到可下单余额';
            } else {
                $frozen_reason = self::TYPE_CHANGE_REMARKS_LT_ZERO_KEY_VALUE[$type];
            }
        }
        $frozen_money = number_format(abs($frozen_money), 2, '.', '');
        return $frozen_reason ? str_replace(':frozen_money', $frozen_money, $frozen_reason) : '';
    }

    /**
     * @param  $order_id
     * @param  $type
     * @param  $frozen
     *
     * @author zjz
     */
    public static function process($order_id, $type, $frozen = 0)
    {
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($order_id ?? 0, 'factory_id,orno');

        $fr_where = ['worker_order_id' => $order_id];
        $fr_model = BaseModel::getInstance('factory_money_frozen');
        $data = $fr_model->getOne($fr_where);
        // 冻结金未发生变动 或者 不存在变动类型  不继续往下执行
        if (bcsub($data['frozen_money'], $frozen, 2) == '0.00' || !in_array($type, self::ALL_TYPE_ARRAY)) {
            return false;
        }

        $frozen = number_format($frozen, 2, '.', '');
        $insert = [
            'factory_id'      => $order['factory_id'],
            'worker_order_id' => $order_id,
            'orno'            => $order['orno'],
            'frozen_money'    => $frozen,
            'type'            => self::FROZEN_TYPE_ORDER_STATUS_ING,
        ];

        // 厂家当前冻结金
        $f_model = BaseModel::getInstance('factory');
        $factory = $f_model->getOneOrFail($order['factory_id'] ?? 0, 'frozen_money');
        $last_factory_total_frozen = $factory['frozen_money'];
        $change_frozen = 0;

        $record = [
            'factory_id'           => $order['factory_id'],
            'worker_order_id'      => $order_id,
            'type'                 => $type,
            'frozen_money'         => '0.00',
            'last_frozen_money'    => $frozen,
            'create_time'          => NOW_TIME,
            'factory_frozen_money' => $factory['frozen_money'],
        ];

        switch ($type) {
            // 新建工单
            case self::TYPE_ORDER_READD: // no break
            case self::TYPE_CS_OUT_TO_IN: // no break
            case self::TYPE_FACTORY_ADD_ORDER:
                $fr_model->remove($fr_where);
                $insert['create_time'] = NOW_TIME;
                $last_factory_total_frozen += $frozen;
                $change_frozen += $frozen;
    			$fr_model->insert($insert);
    			break;

            // 解冻
            case self::TYPE_ORDER_CANCEL:
            case self::TYPE_ORDER_SETTLEMENT:
                $record['frozen_money'] = $data['frozen_money'];
                $record['last_frozen_money'] = '0.00';
                $last_factory_total_frozen -= $data['frozen_money'];
                $change_frozen -= $data['frozen_money'];
    			$fr_model->remove($fr_where);
    			break;
    			
    		// 其他操作
    		default:
                $record['frozen_money']	= $data['frozen_money'];
                $last_factory_total_frozen = $last_factory_total_frozen - $data['frozen_money'] + $frozen;
                $change_frozen = $frozen - $data['frozen_money'];
                if ($data) {
                    $fr_model->update($fr_where, $insert);
                } else {
                    $insert['create_time'] = NOW_TIME;
                    $fr_model->insert($insert);
                }
                break;
        }
        $record['frozen_money'] = number_format($record['frozen_money'], 2, '.', '');
        $record['last_factory_frozen_money'] = number_format($last_factory_total_frozen, 2, '.', '');

        BaseModel::getInstance('factory_money_frozen_record')->insert($record);
//        $f_model->update($order['factory_id'], ['frozen_money' => $last_factory_total_frozen]);
//        $f_model->update($order['factory_id'], ['frozen_money' => "frozen_money + {$change_frozen}"]);
        $f_model->update($order['factory_id'], [
            'frozen_money' => ['exp', "frozen_money + {$change_frozen}"],
        ]);

        return true;
    }


    /**
     * 获取厂家产品冻结金额
     *
     * @param int   $service_type           服务类型
     * @param int   $product_category_id    产品分类ID
     * @param int   $product_standard_id    产品规格ID
     * @param float $factory_default_frozen 厂家默认冻结金额
     *
     * @return float|mixed
     */
    public static function getInsuredOrderProductFrozenPrice($service_type, $factory_id, $product_category_id, $product_standard_id, $factory_default_frozen)
    {
        if (in_array($service_type, [OrderService::TYPE_WORKER_INSTALLATION, OrderService::TYPE_PRE_RELEASE_INSTALLATION])) {
            $in_price = self::getProductCategoryInPrice($factory_id, $product_category_id, $product_standard_id);
            $product_frozen_money = $in_price > 0 ? $in_price : ($factory_default_frozen > 0 ? $factory_default_frozen : C('ORDER_DEFAULT_FROZEN_MONEY'));

        } else {
            if ($factory_default_frozen > 0) {
                $product_frozen_money = round($factory_default_frozen, 2);
            } else {
                $product_frozen_money = round(C('ORDER_DEFAULT_FROZEN_MONEY'), 2);
            }
        }

        return $product_frozen_money;
    }

    public static function getProductCategoryInPrice($factory_id, $category_id, $standard_id)
    {
        $fault_ids = BaseModel::getInstance('product_miscellaneous')
            ->getFieldVal([
                'product_id' => $category_id,
            ], 'product_faults');

        if (!$fault_ids) {
            $product_category_name = BaseModel::getInstance('product_category')
                ->getFieldVal($category_id, 'name');
            throw new \Exception($product_category_name . '分类下无可用维修项,请重新选择');
        }

        $product_fault_ids = BaseModel::getInstance('product_fault')
            ->getFieldVal([
                'where' => [
                    'id'         => ['IN', $fault_ids],
                    'fault_type' => 1,
                ],
                'order' => 'sort ASC,id ASC',
            ], 'id', true);
        $in_price = 0;

        if ($product_fault_ids) {
            $product_fault_str_ids = implode(',', $product_fault_ids);
            $order = count($product_fault_ids) > 1 ? "field(fault_id,$product_fault_str_ids)" : null;
            $in_price = BaseModel::getInstance('factory_product_fault_price')
                ->getFieldVal([
                    'where' => [
                        'factory_id'  => $factory_id,
                        'product_id'  => $category_id,
                        'fault_id'    => ['IN', $product_fault_str_ids],
                        'standard_id' => $standard_id,
                    ],
                    'order' => $order,
                ], 'factory_in_price');

            $in_price <= 0 && $in_price = BaseModel::getInstance('product_fault_price')
                ->getFieldVal([
                    'where' => [
                        'product_id'  => $category_id,
                        'fault_id'    => ['IN', $product_fault_ids],
                        'standard_id' => $standard_id,
                    ],
                    'order' => $order,
                ], 'factory_in_price');

            //平台没有设置金额,设置默认金额
            $in_price = $in_price <= 0? C('FACTORY_DEFAULT_FAULT_IN_PRICE'): $in_price;
        }
        //        $in_price = BaseModel::getInstance('product_fault')->getFieldVal([
        //            'alias' => 'PF',
        //            'join' => 'LEFT JOIN factory_product_fault_price FPFP ON PF.id = FPFP.fault_id',
        //            'where' => [
        //                'PF.id' => ['IN', $fault_ids],
        //                'FPFP.standard_id' => $standard_id,
        //                'PF.fault_type' => 1,
        //            ],
        //            'order' => 'PF.sort ASC,FPFP.factory_in_price ASC',
        //        ], 'factory_in_price');
        return $in_price;
    }
}