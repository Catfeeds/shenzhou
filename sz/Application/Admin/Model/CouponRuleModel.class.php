<?php

namespace Admin\Model;

class CouponRuleModel extends BaseModel
{
    protected $trueTableName = 'coupon_rule';

    const COUPON_ING   = 1; //进行中
    const COUPON_STOP  = 2; //已停止
    const COUPON_OVER  = 3; //已结束

}