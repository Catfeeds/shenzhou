<?php
/**
 * Created by PhpStorm.
 * User: 3N
 * Date: 2017/12/7
 * Time: 10:35
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Common\Common\Service\SMSService;
use Library\Common\Util;
use Think\Exception;

class CouponRuleLogic extends BaseLogic
{

    public function getList()
    {

        !empty(I('get.title')) && $where['title'] = ['LIKE', "%" . I('get.title') . "%"];

        if (I('get.status')) {
            $where['status'] = I('get.status');
        }
        if (I('get.start_create_time') && I('get.end_create_time')) {
            $where['create_time'] = ['BETWEEN', [strtotime(date('Y-m-d 00:00:00', I('get.start_create_time'))), strtotime(date('Y-m-d 23:59:59', I('get.end_create_time')))]];
        } elseif (I('get.start_create_time')) {
            $where['create_time'] = ['EGT', strtotime(date('Y-m-d 00:00:00', I('get.start_create_time')))];
        } elseif (I('get.end_create_time')) {
            $where['create_time'] = ['ELT', strtotime(date('Y-m-d 23:59:59', I('get.end_create_time')))];
        }

        if (I('get.start_total_receive_times') && I('get.end_total_receive_times')) {
            $where['total_receive_times'] = ['BETWEEN', [I('get.start_total_receive_times'), I('get.end_total_receive_times')]];
        } elseif (I('get.start_total_receive_times')) {
            $where['total_receive_times'] = ['EGT', I('get.start_total_receive_times')];
        } elseif (I('get.end_total_receive_times')) {
            $where['total_receive_times'] = ['ELT', I('get.end_total_receive_times')];
        }

        $params = [
            'field' => 'id, title, full_money, reduce_money, number, total_receive_times, total_receive_user, total_use, status, create_time,frozen_number,effective_type,invalid_time_rule',
            'where' => $where,
            'order' => 'id DESC',
            'limit' => $this->page()
        ];

        $model = new \Admin\Model\CouponRuleModel();
        $data = $model->getList($params);
        foreach ($data as &$val) {
            $val['full_money'] = $val['full_money'] / 100;
            $val['reduce_money'] = $val['reduce_money'] / 100;
            $val['surplus_number'] = $val['number'] - $val['total_receive_times'] - $val['frozen_number'];

//            $val['create_time'] = date('Y-m-d H:i:s', $val['create_time']);

            $_params['end_time'] = ['LT', time()];
            $_params['status'] = ['EQ', $model::COUPON_OVER];
            $_params['_logic'] = 'OR';
            $map['coupon_id'] = $val['id'];
            $map['_complex'] = $_params;
            $val['total_expire'] = D('CouponReceiveRecord')->getNum(['where' => $map]);
//            echo ($val['total_expire'])."\t\n";
//            print_r(D('CouponReceiveRecord')->getLastSql());exit;
        }
        $count = $model->getNum($params);
        return ['data' => $data, 'count' => $count];

    }

    public function add($adminId)
    {
        //表单验证
        $this->formParamsCheck('coupon_rule', I('post.'));

        if (I('post.full_money') < I('post.reduce_money')) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写正确的满减规则');
        }
        if (I('post.full_money') <= 0 || I('post.reduce_money') <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写正确的满减规则');
        }
        if (I('post.number') < 1) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '优惠券数量必须是大于0的正整数');
        }
        if (I('post.invalid_time_rule') < 1) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '使用有效期必须是大于0的正整数');
        }

        $time = time();
        $data = [
            'title' => I('post.title'),
            'admin_id' => $adminId,
            'coupon_type' => 1, //通用优惠劵
            'full_money' => I('post.full_money') * 100,
            'reduce_money' => I('post.reduce_money') * 100,
            'number' => I('post.number'),
            'effective_type' => 1, //固定天数
            'effective_time_rule' => $time,
            'invalid_time_rule' => I('post.invalid_time_rule'),
            'status' => 1, //进行中
            'create_time' => $time
        ];
        D('CouponRule')->insert($data);
    }

    public function update($request, $adminId)
    {
        $coupon_id = I('get.id');

        $receiveNumber = D('CouponReceiveRecord')->getNum(['coupon_id' => $coupon_id]);
        if ($request['number'] < $receiveNumber)
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '库存不能小于领取数量');
        if ($request['number'] <= 0)
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '使用有效期必须是大于0的正整数');

        $time = time();
        $origin_result = D('CouponRule')->getFieldVal(['id' => $coupon_id], 'number');
        if (false !== D('CouponRule')->lock(true)->save(['id' => $coupon_id, 'number' => $request['number'], 'update_time' => $time])) {
            //操作日志
            $logRecord = [
                'coupon_rule_id' => $coupon_id,
                'admin_id' => $adminId,
                'origin_result' => json_encode(['number' => $origin_result]),
                'result' => json_encode(['number' => $request['number']]),
                'create_time' => $time
            ];
            D('CouponOperationRecord')->add($logRecord);
        }

    }

    public function view()
    {
        $id = I('get.id');

        $params = [
            'field' => 'id, title, coupon_type, form, full_money, reduce_money, number, effective_type, invalid_time_rule, status, create_time',
            'where' => ['id' => $id],
        ];
        $data = D('CouponRule')->getOne($params);
        $data['full_money'] = $data['full_money'] / 100;
        $data['reduce_money'] = $data['reduce_money'] / 100;
        return $data;
    }

    public function operate()
    {
        $type = I('post.type');
        $id = I('get.id');

        empty($id) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '优惠劵ID不能为空');
        empty($type) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '操作类型不能为空');

        if (in_array($type, [1, 2])) {
            D('CouponRule')->update($id, ['status' => $type]);
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '非法修改类型');
        }
        return null;
    }

    public function importValid()
    {
        $config['exts'] = ['xls', 'xlsx'];
        $file_info = Util::upload($config);

        $objPHPExcelReader = \PHPExcel_IOFactory::load($file_info['file_path']);
        $sheet = $objPHPExcelReader->getSheet();

        $max_num = C('SEND_COUPON_IMPORT_MAX_NUM');

        $excel_data = [];
        $count = 0;
        $empty_nums = 0;

        $coupon_number = 0;
        foreach ($sheet->getRowIterator() as $key => $row) {
            // 防止导入内容过多时不读取完直接返回错误
            if ($key > ($max_num << 2)) {
                $this->throwException(ErrorCode::ORDER_IMPORT_EXCEL_DATA_NUM_ERROR, '导入订单数量超过限制，最多可导入' . $max_num . '张，请重新导入');
            }
            if ($row->getRowIndex() < 2) {
                continue;
            }
            $has_val = false;
            foreach ($row->getCellIterator() as $cell_key => $cell) {
                $val = trim($cell->setDataType('str')->getFormattedValue());
                if ($val) {
                    if ($cell_key == 'A' && !isMobile($val)) {
//                        echo isMobile($val)."|".$val."\t\n";
                        $this->throwException(ErrorCode::PHONE_GS_IS_WRONG, '第' . $row->getRowIndex() . '行手机号码“' . $val . '”格式不正确，请重新编辑');
                    }
                    if ($cell_key == 'B') {
//                        echo $val."|";
                        if (!isInteger($val)) {
//                        echo isInteger($val)."|".$val."\t\n";
                            $this->throwException(ErrorCode::DATA_WRONG, '第' . $row->getRowIndex() . '行优惠券数量“' . $val . '”格式不正确，请重新编辑');
                        } else {
                            $coupon_number += $val;
                        }
                    }
                    $excel_data[$count][] = $val;
                    $has_val = true;
                } else {
                    if ($cell_key == 'B') {
                        if (!isInteger($val)) {
                            $this->throwException(ErrorCode::DATA_WRONG, '第' . $row->getRowIndex() . '行优惠券数量“' . $val . '”格式不正确，请重新编辑');
                        }
                    }
                }
            };

            if ($has_val) {
                $empty_nums = 0;
            } else {
                ++$empty_nums;
            }
            // 连续出现5条空行则不继续往下读取
            if ($empty_nums >= 5) {
                break;
            }

            ++$count;
        }
        // 重后向前过滤掉空行
//        $excel_data = array_reverse($excel_data);
        foreach ($excel_data as $key => $row) {
            if (count($row) < 2) {
                unset($excel_data[$key]);
            }
//            $has_data = false;
//            foreach ($row as $item) {
//                if ($item) {
//                    $has_data = true;
//                    break;
//                }
//            }
//            if ($has_data) {
//                break;
//            } else {
//                unset($excel_data[$key]);
//            }
        }
//        $excel_data = array_reverse($excel_data);
//        print_r($excel_data);exit;

        // 除去excel表头的说明
        if (count($excel_data) > $max_num + 1) {
            $this->fail(ErrorCode::ORDER_IMPORT_EXCEL_DATA_NUM_ERROR, ['num' => $max_num]);
        }

//        print_r($excel_data);exit;
        return ['data' => $excel_data, 'coupon_number' => $coupon_number];

    }

    public function send()
    {
        $coupon_id = I('get.id');
        $type = I('post.type'); //发放方式：1-单个发，2-批量发

        $model = D('CouponRule');

        M()->startTrans();
        $coupon = $model->getOne(['id' => $coupon_id, 'status' => 1]);
        if ($coupon) {
//            try {
            if ($type == 1) {
                $phone = I('post.phone');
                empty($phone) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写手机号码');
                !isMobile($phone) && $this->throwException(ErrorCode::PHONE_GS_IS_WRONG, '手机号码格式错误，请重试输入');

                $data = ['data' => [[$phone, 1]], 'coupon_number' => 1];
                if ($coupon['number'] - $coupon['total_receive_times'] - $coupon['frozen_number'] >= $data['coupon_number']) {
                    //库存冻结
                    $model->lock(true)->where(['id' => $coupon_id])->setInc('frozen_number', $data['coupon_number']);
                    $couponRecord = $this->sendCoupons($data['data'], $coupon);
                } else {
                    $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '优惠券数量不足发送，请调整');
                }

            } elseif ($type == 2) {
                //Excel解析的数据[135***9580, 1]
                $data = $this->importValid();
//                    print_r($data);exit;

                empty($data) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请给模板填充数据');

                if ($coupon['number'] - $coupon['total_receive_times'] - $coupon['frozen_number'] >= $data['coupon_number']) {
                    //库存冻结
                    $model->lock(true)->where(['id' => $coupon_id])->setInc('frozen_number', $data['coupon_number']);

                    $couponRecord = $this->sendCoupons($data['data'], $coupon);
                } else {
                    $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '优惠券数量不足发送，请调整');
                }
            }
            if (D('CouponReceiveRecord')->insertAll($couponRecord)) {
                //冻结释放
                //$model->lock(true)->setDec('frozen_number', $data['coupon_number']);
                $param = [
                    'field' => 'id, wx_user_id',
                    'where' => ['coupon_id' => $coupon_id],
                    'group' => 'wx_user_id',
                ];
                $receiveUser = D('CouponReceiveRecord')->getList($param);

                $coupon = $model->lock(true)->getOne(['id' => $coupon_id]);
                $model->update(['id' => $coupon_id], [
                    'frozen_number' => $coupon['frozen_number'] - $data['coupon_number'],
                    'total_receive_user' => count($receiveUser),
                    'total_receive_times' => D('CouponReceiveRecord')->getNum(['coupon_id' => $coupon_id])
                ]);
            }
            M()->commit();
//            } catch (\Exception $e) {
//                M()->rollback();
//                $this->getExceptionError(ErrorCode::SYS_SYSTEM_ERROR, $e);
//            }

        } else {
            $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '该优惠劵已暂停或已结束，请重新选择');
        }

        return null;
    }

    /**
     * 优惠券发送
     * @param $data
     * @param $coupon
     * @return array
     */
    public function sendCoupons($data, $coupon)
    {
        $time = time();
        $couponRecord = [];
        foreach ($data as $val) {
            $phone = $val[0];
            $user = D('WxUser')->getOne(['telephone' => $phone]);
            //没有用户注册
            if ($user) {
                $wx_user_id = $user['id'];
            } else {
                //注册用户
                $wx_user_id = D('WxUser')->insert(['telephone' => $phone, 'nickname' => $phone]);
            }

            $couponNum = (int)$val[1];
            for ($i = 0; $i < $couponNum; $i++) {
                $couponRecord[] = [
                    'coupon_id' => $coupon['id'],
                    'cp_full_money' => $coupon['full_money'],
                    'cp_reduce_money' => $coupon['reduce_money'],
                    'wx_user_id' => $wx_user_id,
                    'cp_user_phone' => $phone,
                    'coupon_code' => $coupon['coupon_code'],
                    'receive_mode' => 2, //领奖方式：1-抽奖领取，2-定向发送，3-公众号赠送
                    'start_time' => $time,
                    'end_time' => strtotime(date('Y-m-d 23:59:59', $time + $coupon['invalid_time_rule'] * 86400))
                ];
            }
            //TODO 加入短信队列 模板待提供
            $nickname = $user['nickname']?$user['nickname']:$phone;
            $sendData = ['nickname'=>$nickname, 'full_money'=>$coupon['full_money']/100, 'reduce_money'=>$coupon['reduce_money']/100];
            sendSms($phone, SMSService::TMP_DIRECTIONAL_SEND_COUPON, $sendData);

            //微信消息
            if ($user['openid']) {
                $wxData[] = [
                    'title'=> '优惠券领取成功',
                    'description'=> '送您家电售后优惠券满'.($coupon['full_money']/100).'元减'.($coupon['reduce_money']/100).'元'.$couponNum.'张，点击立即下单吧。',
                    'url'=> C(WEB_C_SITE)
                ];
                sendWechatNotification($user['openid'], $wxData, 'news');
            }
        }

//        print_r($couponRecord);exit;
        return $couponRecord;
    }


}