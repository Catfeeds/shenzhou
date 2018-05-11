<?php
/**
 * Created by PhpStorm.
 * User: 3N
 * Date: 2017/12/7
 * Time: 10:35
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;

class CouponReceiveRecordLogic extends BaseLogic
{

    public function getList()
    {

        !empty(I('get.phone')) && $where['a.cp_user_phone'] = ['LIKE', "%" . I('get.phone') . "%"];
        $where['a.coupon_id'] = I('get.id');
        if (I('get.status') && I('get.status')<4) {
            $where['a.status'] = I('get.status');
        } elseif (I('get.status') && I('get.status')==4) {
            $where['a.end_time'] = ['LT',  time()];
        }
//        if (I('get.start_time') && I('get.end_time')) {
//            $where['a.start_time'] = ['EGT', I('get.start_time')];
//            $where['a.end_time'] = ['ELT', I('get.end_time')];
//        } elseif (I('get.start_time')) {
//            $where['a.start_time'] = ['EGT', I('get.start_time')];
//        } elseif (I('get.end_time')) {
//            $where['a.end_time'] = ['ELT', I('get.end_time')];
//        }
        if (I('get.start_time') && I('get.end_time')) {
            $where['a.start_time'] = ['EGT', strtotime(date('Y-m-d 00:00:00', I('get.start_time')))];
//            $where['_logic'] = 'OR';
            $where['a.end_time'] = ['ELT', strtotime(date('Y-m-d 23:59:59', I('get.end_time')))];
//            $_params['a.start_time'] = ['EGT', strtotime(date('Y-m-d 00:00:00', I('get.start_time')))];
//            $_params['_logic'] = 'OR';
//            $_params['a.end_time'] = ['EGT', strtotime(date('Y-m-d 23:59:59', I('get.end_time')))];
//            $where['_complex'] = $_params;
        } elseif (I('get.start_time')) {
            $where['a.start_time'] = ['EGT', strtotime(date('Y-m-d 00:00:00', I('get.start_time')))];
        } elseif (I('get.end_time')) {
            $where['a.end_time'] = ['EGT', strtotime(date('Y-m-d 23:59:59', I('get.end_time')))];
        }
        if (I('get.receive_mode')) {
            $where['a.receive_mode'] = I('get.receive_mode');
        }
//        print_r($where);exit;
        $params = [
            'alias' => 'a',
            'field' => 'a.id, a.coupon_id, b.openid, a.receive_mode, a.cp_user_phone as user_phone, a.cp_full_money as full_money, a.cp_reduce_money as reduce_money, 
                        a.start_time, a.end_time, a.use_time, a.cp_orno as use_orno, a.status',
            'join' => 'LEFT JOIN wx_user b ON a.wx_user_id = b.id',
            'where' => $where,
            'order' => 'a.id DESC',
            'limit' => $this->page()
        ];


        $data = D('CouponReceiveRecord')->getList($params);
        foreach ($data as &$val) {
//            $val['start_time'] = date('Y-m-d H:i:s', $val['start_time']);
//            $val['end_time'] = date('Y-m-d H:i:s', $val['end_time']);
            $val['status'] = $val['status']<3 && $val['end_time'] < time() ? 4 : $val['status'];
        }
        $count = D('CouponReceiveRecord')->getNum($params);
        return ['data' => $data, 'count' => $count];

    }

    public function view()
    {
        return null;
    }

    public function operate()
    {
        $type = I('post.type');
        $id = I('get.id');
        $coupon_id = I('get.coupon_id');

        empty($id) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '优惠劵ID不能为空');
        empty($type) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '操作类型不能为空');

        if (in_array($type, [1])) {
            D('CouponReceiveRecord')->where(['id'=>$id, 'coupon_id'=>$coupon_id])->save(['status' => 3]); //已作废
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '非法修改类型');
        }
        return null;
    }

}