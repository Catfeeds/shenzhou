<?php

namespace Admin\Logic;

use Library\Common\Validate;

class ValidateFormLogic extends BaseLogic
{
    protected function validateRuleMsg($table, $params, $action)
    {
        switch ($table) {
            case 'draw' :
                $rule = [
                    'title|活动标题' => 'require',
                    'start_time|活动开始时间' => 'require',
                    'end_time|活动结束时间' => 'require',
                    'people_draw_times|每人每天可抽次数' => 'require',
                    'max_win_draw_times|每人最多可中奖次数' => 'require',
                    'prizes|奖项设置' => 'require',
                ];

                $rule_msg['rule'] = $rule;
                break;
            case 'coupon_rule' :
                $rule = [
                    'title|优惠劵标题' => 'require',
                    'number|优惠劵数量' => 'require',
                    'invalid_time_rule|过期天数'=> 'require'
                ];

                $rule_msg['rule'] = $rule;
                break;
            default:
                $rule_msg = null;
                break;
        }
        return $rule_msg;
    }

    public function formParamsCheck($table, $params, $action)
    {
        $rule_msg = $this->validateRuleMsg($table, $params, $action);
        $validate = new Validate($rule_msg['rule'], $rule_msg['msg']);
        if (!$validate->check($params)) {
            return $validate->getError();
        }
        return null;
    }
}