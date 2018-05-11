<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/10/3
 * Time: 19:30
 */
namespace Admin\Model;


class FactoryMoneyModel extends BaseModel
{
    protected $trueTableName = 'factory_money_frozen';

    const ORDER_TABLE_NAME = 'worker_order';
    const ORDER_FEE_TABLE_NAME = 'worker_order_fee';

    //获取厂家总冻结金额
    public function factory_total_frozen($factory_id)
    {
        $condition = [];
        $condition['factory_id'] = $factory_id;
        $sum = M('factory_money_frozen')->where($condition)->sum('frozen_money');
        $sum = empty($sum)? 0.00 : $sum;
        return $sum;
    } 

}
