<?php
/**
 * Created by PhpStorm.
 * User: 嘉诚
 * Date: 2017/12/14
 * Time: 15:47
 */

namespace Qiye\Model;



class OrderModel extends BaseModel
{
    protected $trueTableName = 'worker_order';

    const factory_table       = 'factory';
    const factory_admin_table = 'factory_admin';

    /*
     * 获取下单人联系信息
     */
    public function getAddUserInfo($id, $origin_type)
    {
        if ($origin_type == '1') {
            $model = BaseModel::getInstance(self::factory_table);
            $where = [
                'factory_id' => $id
            ];
            $field = 'linkman, linkphone';
        } elseif ($origin_type == '2') {
            $model = BaseModel::getInstance(self::factory_admin_table);
            $where = [
                'id' => $id
            ];
            $field = 'nickout as linkman, tell as linkphone';
        } else {
            return null;
        }
        $add_user_info = $model->getOne([
            'where' => $where,
            'field' => $field
        ]);
        return $add_user_info;
    }

    /*
     * 获取费用信息
     */
    public function getWorkerOrderFee($order_id, $field = '*')
    {
        $fee_info = BaseModel::getInstance('worker_order_fee')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => $field
        ]);
        return $fee_info;
    }

}