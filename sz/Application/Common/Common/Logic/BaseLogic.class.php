<?php

namespace Common\Common\Logic;


use Common\Common\ErrorCode;
use Common\Common\ReminderException;

class BaseLogic
{

    protected function throwException($error_code, $error_msg = '')
    {
        if (is_array($error_msg)) {
            $msg_arr = $error_msg;
            $error_msg = ErrorCode::getMessage($error_code);
            foreach ($msg_arr as $search => $msg) {
                $error_msg = str_replace(':' . $search, $msg, $error_msg);
            }
        } else {
            empty($error_msg) && $error_msg = ErrorCode::getMessage($error_code);
        }
        throw new \Exception($error_msg, $error_code);
    }

    protected function throwReminderException($error_code, $error_msg = '', $data = [])
    {
        if (is_array($error_msg)) {
            $msg_arr = $error_msg;
            $error_msg = ErrorCode::getMessage($error_code);
            foreach ($msg_arr as $search => $msg) {
                $error_msg = str_replace(':' . $search, $msg, $error_msg);
            }
        } else {
            empty($error_msg) && $error_msg = ErrorCode::getMessage($error_code);
        }
        throw new ReminderException($error_msg, $error_code, (Object)$data);
    }

    protected function page($page_no = NULL, $page_num = NULL)
    {
        empty($page_no) && $page_no = I('page_no', 1, 'intval');
        empty($page_num) && $page_num = I('page_size', 10, 'intval');
        $offset = ($page_no - 1) * $page_num;
        $offset = max(0, $offset);
        $page_num = max(0, $page_num);
        return "$offset,$page_num";
    }

    /**
     * 换算坐标距离(经纬度)
     * @param  $lng1 纬度
     * @param  $lat1 经度
     * @param  $lng2
     * @param  $lat2
     * @return number 距离(米)
     */
    protected function getLongDistance($lng1, $lat1, $lng2, $lat2){
        # 角度转换为弧度
        $DEF_PI = 3.14159265359;
        $DEF_2PI = $DEF_PI*2;
        $DEF_PI180 = $DEF_PI/180;
        $DEF_R = 6370693.5;

        $ew1 = $lng1 * $DEF_PI180;
        $ns1 = $lat1 * $DEF_PI180;
        $ew2 = $lng2 * $DEF_PI180;
        $ns2 = $lat2 * $DEF_PI180;
        # 求大圆劣弧与球心所夹的角(弧度)
        $distance = sin($ns1) * sin($ns2) + cos($ns1) * cos($ns2) * cos($ew1 - $ew2);
        # 调整到[-1..1]范围内，避免溢出
        if($distance > 1.0){
            $distance = 1.0;
        }else if($distance < -1.0){
            $distance = -1.0;
        }
        # 求大圆劣弧长度
        $distance = $DEF_R * acos($distance);
        return $distance;
    }

}
