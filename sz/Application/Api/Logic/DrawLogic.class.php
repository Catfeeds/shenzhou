<?php
/**
 * Created by PhpStorm.
 * User: 3N
 * Date: 2017/12/7
 * Time: 10:35
 */

namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\ResourcePool\RedisPool;
use Library\Common\Util;
use Common\Common\Service\SMSService;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;

class DrawLogic extends BaseLogic
{
    //活动信息
    public function getDraw($uid)
    {
        $time = time();
        $today = strtotime(date("Y-m-d 00:00:00", time()));
        $draw_id = I('get.draw_id');

        //值为1时,表示预览
        $is_preview = I('get.is_preview', 0, 'intval');

        if ($draw_id) {
            $where = [
                'id' => $draw_id
            ];
        } else {
            $where['status'] = 1;
            $where['start_time'] = ['ELT', $time];
            $where['end_time'] = ['EGT', $time];
        }
//        print_r($where);exit;

        $params = [
            'field' => 'id,title,status,people_draw_number as people_draw_times,start_time,end_time',
            'where' => $where,
            'order' => 'id desc'
        ];
        $data = D('DrawRule')->getOne($params);

        //empty($data) && $this->throwException(ErrorCode::DATA_IS_WRONG, '暂无进行中的活动');

        if (empty($data)) {
            return null;
        }

        $prizeParams = [
            'field' => 'id,prize_name as name,type,winning_rate,prize_image',
            'where' => ['draw_rule_id' => $data['id']],
        ];
        $prizeItem = D('PrizeItem')->getList($prizeParams);

        foreach ($prizeItem as &$v) {
            $v['prize_image'] = $v['prize_image'] ? Util::getServerFileUrl($v['prize_image']) : '';
        }

        if (!empty($uid)) {
            $count = D('DrawRecord')->getNum(['draw_rule_id' => $data['id'], 'wx_user_id' => $uid, 'create_time' => ['EGT', $today]]);
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
            $count = D('DrawRecord')->getNum(['draw_rule_id' => $data['id'], 'ip' => $ip, 'create_time' => ['EGT', $today]]);
        }

        $data['people_draw_times'] = (int)$data['people_draw_times'];

        if ($is_preview == 1) {
            $count = 0;
        }

        $num = ($data['people_draw_times'] - $count);
        $data['user_draw_times'] = $num >= 0 ? $num : 0;
        $data['prizes'] = $prizeItem;

        return $data;
    }

    //中奖名单
    public function winList()
    {
        $drawId = I('get.id');

        $where['p.draw_rule_id'] = $drawId;
        $where['r.status'] = ['GT', 0];
        $where['w.telephone'] = ['GT', 0];

        $params = [
            'alias' => 'r',
            'field' => 'w.telephone as phone,p.prize_name as name',
            'where' => $where,
            'order' => 'r.create_time DESC',
            'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id 
                        LEFT JOIN wx_user as w on w.id = r.wx_user_id 
                       ',
            //'limit' => $this->page()
        ];

        $data = D('DrawRecord')->getList($params);
        $count = D('DrawRecord')->getNum($params);

        $everyCount = 20;
        $lack = $everyCount - $count;

        if ($lack > 0) {
//            $sqlUser = 'SELECT telephone as phone FROM `wx_user` WHERE (telephone != \'\') ORDER BY RAND() LIMIT  ' . $lack;
//            $randArr = M()->query($sqlUser);
            $rand_all = BaseModel::getInstance('wx_user')->getList([
                'field' => 'telephone',
                'where' => [
                    'telephone' => ['NEQ', ''],
                ],
                'order' => 'id DESC',
                'limit' => '1000,2000'
            ]);
            shuffle($rand_all);
            $randArr = array_slice($rand_all, 0, $lack);


            //该活动奖品
            $randPrize = D('PrizeItem')->getFieldVal(
                ['draw_rule_id' => $drawId], 'prize_name', true
            );

            //随机组合
            foreach ($randArr as &$r) {
                $r['name'] = $randPrize[array_rand($randPrize, 1)];
            }

            $data = array_merge($data, $randArr);
            $count = 20;
        }

        return ['data' => $data, 'count' => $count];

    }

    //点击抽奖
    public function draw($uid)
    {
        //值为1时,表示预览
        $is_preview = I('get.is_preview', 0, 'intval');

        //微信浏览器抽奖时判断登录
        if ($is_preview !== 1 && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $auth_user = AuthService::getAuthModel();
            if (empty($auth_user)) {
                $this->throwException(ErrorCode::SYS_USER_VERIFY_FAIL);
            }
        }

        $time = time();
        $today = strtotime(date("Y-m-d 00:00:00", $time));

        $channel = I('get.channel');

        $drawId = I('get.id');
        $draw = D('DrawRule')->getOne(['id' => $drawId, 'status' => 1]);

        $ip = $_SERVER["REMOTE_ADDR"];

        if ($is_preview != 1) {
            //查询活动是否已结束
            if (!$draw) {
                $this->throwException(ErrorCode::DATA_IS_WRONG, '很抱歉，该抽奖活动已经结束');
            }

            ($draw['end_time'] <= $time) && $this->throwException(ErrorCode::DATA_IS_WRONG, '很抱歉，该抽奖活动已经结束');

            ($draw['start_time'] > $time) && $this->throwException(ErrorCode::DATA_IS_WRONG, '该抽奖活动还未开始');

            //判断当前ip是否已在当前活动抽奖超过30次
            $allCount = D('DrawRecord')->getNum(['ip' => $ip, 'draw_rule_id' => $drawId]);

            if (empty($uid) && $allCount > 30) {
                $this->throwException(ErrorCode::DATA_IS_WRONG, '您的抽奖次数已经超过限制');
            }

            //判断当前用户、ip当天抽奖次数
            if ($uid) {
                $todayCount = D('DrawRecord')->getNum(['draw_rule_id' => $drawId, 'wx_user_id' => $uid, 'draw_rule_id' => $drawId, 'create_time' => ['EGT', $today]]);
            } else {
                $todayCount = D('DrawRecord')->getNum(['draw_rule_id' => $drawId, 'ip' => $ip, 'draw_rule_id' => $drawId, 'create_time' => ['EGT', $today]]);
            }

            if (($draw['people_draw_number'] <= $todayCount)) {
                $this->throwException(ErrorCode::DATA_IS_WRONG, '您今天的抽奖机会已经用完，请明日再试');
            }
        }

        //抽奖活动相关奖品
        $prizeRates = D('PrizeItem')->getFieldVal(
            ['draw_rule_id' => $drawId], 'id,winning_rate', true
        );

        //判断奖品数量足够
        foreach ($prizeRates as $k => $p) {
            $prizeItem = D('PrizeItem')->getOne(['id' => $k]);

            //TODO队列
            //$num = $this->newPrizeCount($k, $drawId);

            $num = $this->newPrizeCount($k);

            if ($num <= 0) {
                unset($prizeRates[$k]);
            }

            //TODO 优惠券库存(已冻结数量，实际是够抽奖的，不需要判断)
            if ($prizeItem['type'] < 3) {
                $couponR = json_decode($prizeItem['prize_result'], ture);

                foreach ($couponR as $c) {
                    $coupon = D('CouponRule')->getOne(['id' => $c['id'], 'status' => 1]);
                    $couponNum = D('CouponReceiveRecord')->getNum(['coupon_id' => $c['id']]);
                    $num = $coupon['number'] - $couponNum; // - $coupon['frozen_number'];
                    if ($num < $c['number']) {
                        unset($prizeRates[$k]);
                    }
                }
            }
        }

        //判断当前用户的中奖次数
        if ($uid && ($is_preview != 1)) {
            $winCount = D('DrawRecord')->getNum(['wx_user_id' => $uid, 'draw_rule_id' => $drawId, 'status' => ['gt', 0]]);
            ($winCount >= $draw['max_win_draw_number']) && $prizeRates = [];
        }

        $sum = array_sum($prizeRates);
        //'-1'为谢谢回顾的键值
        $prizeRates['-1'] = 10000 - $sum;

        $result = '';
        //概率数组的总概率精度
        $proSum = array_sum($prizeRates);

        //概率数组循环
        foreach ($prizeRates as $key => $cur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $cur) {
                $result = $key;
                break;
            } else {
                $proSum -= $cur;
            }
        }

        //----------------flock方式-------------------

        $fp = fopen("./Application/Api/Common/lock.text", "w+");

        if (flock($fp, LOCK_EX) && $result > 0) {

            //该奖品数量
            $num = $this->newPrizeCount($result);

            if ($num <= 0) {
                $result = -1;
            } else {
                D('PrizeItem')->setNumDec(['id' => $result], 'prize_number', 1);
            }

            flock($fp, LOCK_UN);
        }

        fclose($fp);

        //----------------flock方式-------------------


        //----------------队列统计方式-------------------
        //TODO:队列方式
        /*        $redis = connectRedis();
                $key_name = 'draw_' . $drawId . '_';

                if ($result > 0 && $is_preview != 1) {
                    $newNum = $this->newPrizeCount($result, $drawId);
                    if ($newNum > 0) {
                        $redis->INCR($key_name . $result);
                    } else {
                        $result = -1;
                    }
                }*/
        //----------------队列统计方式-------------------*/

        //奖品的领取情况
        ($result > 0) && $data = $this->draw_result($result, $uid, $time, $is_preview);

        $data['status'] = $result > 0 ? (string)$data['status'] : '0';
        $data['prize_item_id'] = $result > 0 ? $result : '';
        ($result > 0) && $data['prize_image'] = $data['prize_image'] ? Util::getServerFileUrl($data['prize_image']) : '';

        //预览时直接返回,不生成记录
        if ($is_preview == 1) {
            return $data;
        }

        //领取过期时间
        $i = $data['days'] ? $data['days'] : 3;
        $expire_time = $today + 86400 * ($i + 1) - 1;
//        print_r(date('Y-m-d H:i:s',$expire_time));exit;

        //新增抽奖记录
        $drawRecord = [
            'draw_rule_id' => $drawId,
            'prize_item_id' => $result > 0 ? $result : '',
            'wx_user_id' => $uid > 0 ? $uid : null,
            'channel' => $channel,
            'status' => $data['status'],
            'create_time' => $time,
            'expire_time' => $result > 0 ? $expire_time : null,
            'ip' => $ip,
        ];

        $model = D('DrawRecord');
        $model->startTrans();

        $drawRecordId = $model->insert($drawRecord);

        //生成优惠券领取记录
        $couponArr = $data['couponArr'];
        unset($data['couponArr']);

        if (!empty($couponArr) && $data['type'] < 3) {
            foreach ($couponArr as &$ca) {
                $ca['draw_record_id'] = $drawRecordId;
            }

            if (D('CouponReceiveRecord')->insertAll($couponArr)) {

                foreach ($data['coupon_result'] as $c) {
                    //释放优惠劵冻结数量
                    D('CouponRule')->where(['id' => $c['id']])->setDec('frozen_number', $c['number']);
                    //累计优惠券已领取数量
                    D('CouponRule')->where(['id' => $c['id']])->setInc('total_receive_times', $c['number']);

                    //查询该用户是否已领取过该优惠券
                    $is_exist_user = D('CouponReceiveRecord')->getNum(['coupon_id' => $c['id'], 'wx_user_id' => $uid]);
                    if ($is_exist_user <= 1) {
                        //累计优惠券领取人数
                        D('CouponRule')->where(['id' => $c['id']])->setInc('total_receive_user', 1);
                    }
                }
            }
        }

        //更新抽奖规则里抽奖人数和抽奖次数
        $this->updateTotal($uid, $drawId, $ip);

        //TODO：Flock
        if ($result > 0) {
            D('PrizeItem')->setNumInc(['id' => $result], 'prize_number', 1);
        }

        $model->commit();

        $data['id'] = $drawRecordId;

        return $data;
    }

    //计算奖品可领取数量
    public function newPrizeCount($prizeId, $drawId = '')
    {
        //该奖品数量
        $prizeItemNum = D('PrizeItem')->getFieldVal(
            ['id' => $prizeId], 'prize_number'
        );

        //奖品过期数量
        $prizeOverNum = D('DrawRecord')->getNum(
            ['prize_item_id' => $prizeId,
                'status' => 3
            ]
        );

        //中奖数
        $prizeGotNum = D('DrawRecord')->getNum(
            ['prize_item_id' => $prizeId,
                'status' => ['gt', 0]
            ]
        );

        //TODO 队列 取出队列中的抽奖次数
        if (!empty($drawId)) {
            $redis = connectRedis();
            $key_name = 'draw_' . $drawId . '_' . $prizeId;
            $queueNum = $redis->get($key_name);
            if ($queueNum >= $prizeGotNum) {
                $prizeGotNum = $queueNum;
            } else {
                $redis->set($key_name, $prizeGotNum);
            }
        }

        $num = $prizeItemNum + $prizeOverNum - $prizeGotNum;

        return $num;
    }

    //更新抽奖规则里抽奖人数和抽奖次数
    public function updateTotal($userId, $drawId, $ip)
    {
        if (empty($userId)) {
            $userNum = D('DrawRecord')->getNum
            ([
                'wx_user_id' => array('exp', 'is null'),
                'draw_rule_id' => $drawId,
                'ip' => $ip
            ]);
        } else {
            $userNum = D('DrawRecord')->getNum([
                'wx_user_id' => $userId,
                'draw_rule_id' => $drawId
            ]);
        }

        ($userNum == 1) && D('DrawRule')->setNumInc(['id' => $drawId], 'total_draw_user', 1);

        D('DrawRule')->setNumInc(['id' => $drawId], 'total_draw_times', 1);
    }

    /* @
     * 奖品的领取情况
     * $result 中奖奖项id
     * $uid 用户id
     * $time 抽奖时间
     * $is_preview 是否预览 1为预览
     * */
    public function draw_result($result, $uid, $time, $is_preview = '')
    {

        $today = strtotime(date("Y-m-d 00:00:00", $time));

        !empty($uid) && $user = M('WxUser')->where('id=' . $uid)->find();

        $data = D('PrizeItem')->getOne(['id' => $result]);
        $data['status'] = 1;

        //若为预览，不领取优惠券
        if ($is_preview == 1) {
            return $data;
        }

        $drawRecord = [];

        /*
         * $drawRecord 抽奖记录
         *  days 兑换、使用有效天数
         *  status 抽奖记录状态
         *
         * */

        if (empty($uid)) {
            //unionid 与 手机号码的情况
            if ($data['type'] < 3) {
                $drawRecord['days'] = 30;

            } elseif ($data['type'] == 3) {
                $drawRecord['days'] = 3;
            }

        } else {
            //如果是优惠券 则领取
            if ($data['type'] < 3) {
                $data['status'] = 2; //领奖记录为为已领取

                $prize = json_decode($data['prize_result'], true);

                $days = 0;
                foreach ($prize as $r) {
                    //取得优惠券最长有效期
                    $num = D('CouponRule')->getFieldVal(
                        ['id' => $r['id']], 'invalid_time_rule'
                    );
                    ($num > $days) && $days = $num;

                    //构建优惠券领取记录数组
                    $couponInfo = D('couponRule')->getOne(['id' => $r['id']]);

                    for ($n = 0; $n < $r['number']; $n++) {
                        $couponArr[] = [
                            'coupon_id' => $r['id'],
                            'wx_user_id' => $uid,
                            'receive_mode' => 1,
                            'start_time' => $time,
                            'end_time' => $today + 86400 * ($num + 1) - 1,
                            'status' => 1,
                            'cp_user_phone' => $user['telephone'],
                            'cp_full_money' => $couponInfo['full_money'],
                            'cp_reduce_money' => $couponInfo['reduce_money'],
                            'prize_item_id' => $result,
                            'draw_record_id' => 0
                        ];
                    }
                }

                //D('CouponReceiveRecord')->insertAll($couponArr);

            } elseif ($data['type'] == 3) {
                $days = 3;
            }

            $drawRecord['phone'] = $user['telephone'];
            $drawRecord['days'] = $days;
        }

        $data += $drawRecord;

        $data['couponArr'] = $couponArr;
        $data['coupon_result'] = $prize;

        return $data;
    }

    //获得手机验证码
    public function getCode()
    {
        $phone = I('get.phone');
        $verify = (string)mt_rand(111111, 999999);

        $key = 'C_REGISTER_VERIFY_' . $phone;
        S($key, $verify, ['expire' => 30 * 60]);

        empty($phone) && $this->throwException(ErrorCode::PHONE_NOT_EMPTY);

        if (!empty(I('get.is_return'))) {
            return $data = ['phone' => $phone, 'code' => (string)$verify];
        } else {
            sendSms($phone, SMSService::TMP_WX_USER_REGISTER_PHONE_CODE, [
                'verify' => $verify]);
        }
    }

    //手机验证
    public function validate($user_id)
    {
        $drawRecord = I('get.id');
        $phone = I('put.phone');
        $code = I('put.code');

        empty($phone) && $this->throwException(ErrorCode::PHONE_NOT_EMPTY);
        empty($code) && $this->throwException(ErrorCode::DATA_IS_WRONG);

        $key = 'C_REGISTER_VERIFY_' . $phone;
        $value = S($key);

        if ($code != $value) {
            $this->throwException(ErrorCode::DATA_WRONG, '短信验证码不匹配');
        } else {
            if ($user_id) {
                $userId = $this->validateWithToken($user_id, $drawRecord, $phone, $code);
            } else {
                $userId = $this->validateNoToken($drawRecord, $phone);
            }

            $s = 24 * 3600;
            $token_data = [
                'user_id' => $userId,
                'type' => 'wxuser',
                'expire_time' => NOW_TIME + $s,
            ];
            $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);

            $data['token'] = $token;
            $data['user_id'] = $userId;

            return $data;
        }
    }

    //微信端验证手机号码
    public function validateWithToken($uid, $drawRecordId, $phone, $code)
    {
        $user = D('User')->getOne(['id' => $uid]);
        //微信端绑定手机号码
        if ($user['telephone'] != $phone) {
            //D('User', 'Logic')->wxBindUser($user['unionid'], $phone);
            $userId = D('User', 'Logic')->setWxPhone($phone, $code, $user['user_type']);
        }

        //判断是否超出兑换时间
        $drawRecordInfo = D('DrawRecord')->getOne(['id' => $drawRecordId]);
        if ($drawRecordInfo['expire_time'] < time()) {
            $this->throwException(ErrorCode::DATA_IS_WRONG, '很抱歉，由于您没有在兑奖有效期内兑换奖品，奖品失效');
        }

        return $userId;

    }

    //浏览器端验证手机号码
    public function validateNoToken($drawRecordId, $phone)
    {
        //手机用户
        $model = D('User');
        $model->startTrans();
        $user = $model->getOne(['telephone' => $phone]);
        $userId = $user['id'];

        //创建用户
        if (empty($user)) {
            $userId = $this->userNew($phone);
        }

        $drawRecordWhere['ip'] = $_SERVER["REMOTE_ADDR"];
        $drawRecordWhere['status'] = 1;
        $drawRecordWhere['wx_user_id'] = array('exp', 'is null');

        //user_id 为空的领取记录对应的奖品
        $drawRecordWhere2 = $drawRecordWhere;
        $drawRecordWhere2['p.type'] = ['in', '1,2'];

        $params = [
            'alias' => 'r',
            'field' => 'r.id,r.prize_item_id as pid,p.prize_number',
            'where' => $drawRecordWhere2,
            'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id',
        ];

        $prizeItem = D('DrawRecord')->getList($params);

        //更新同IP 且user_id为空的领奖记录
        D('DrawRecord')->update($drawRecordWhere, ['wx_user_id' => $userId]);

        foreach ($prizeItem as $p) {
            $data = $this->draw_result($p['pid'], $userId, time());

            //生成优惠券领取记录
            $couponArr = $data['couponArr'];

            if (!empty($couponArr) && $data['type'] < 3) {
                foreach ($couponArr as &$c) {
                    $c['draw_record_id'] = $drawRecordId;
                }

                //生成优惠券领取记录
                if (D('CouponReceiveRecord')->insertAll($couponArr)) {

                    foreach ($data['coupon_result'] as $c) {
                        //释放优惠劵冻结数量
                        D('CouponRule')->where(['id' => $c['id']])->setDec('frozen_number', $c['number']);
                        //累计优惠券已领取数量
                        D('CouponRule')->where(['id' => $c['id']])->setInc('total_receive_times', $c['number']);

                        //查询该用户是否已领取过该优惠券
                        $is_exist_user = D('CouponReceiveRecord')->getOne(['coupon_id' => $c['id'], 'wx_user_id' => $userId]);
                        if (empty($is_exist_user)) {
                            //累计优惠券领取人数
                            D('CouponRule')->where(['id' => $c['id']])->setInc('total_receive_user', 1);
                        }
                    }
                }

            }

            D('DrawRecord')->update($p['id'], ['status' => 2]);
        }

        $model->commit();

        return $userId;
    }

    //填写收件人信息
    public function receiver()
    {
        $time = time();
        $drawRecord = I('get.id');
        $name = I('post.name');
        $phone = I('post.phone');
        $area = I('post.area');
        $address = I('post.address');

        empty($phone) && $this->throwException(ErrorCode::PHONE_NOT_EMPTY);
        empty($name) && $this->throwException(ErrorCode::DATA_IS_WRONG, '收件人姓名不能为空');
        empty($area) && $this->throwException(ErrorCode::DATA_IS_WRONG, '所在省市不能为空');
        empty($address) && $this->throwException(ErrorCode::DATA_IS_WRONG, '详细地址不能为空');

        $drawRecordInfo = D('DrawRecord')->getOne(['id' => $drawRecord]);

        if ($drawRecordInfo['expire_time'] < $time) {
            $this->throwException(ErrorCode::DATA_IS_WRONG, '很抱歉，由于您没有在兑奖有效期内兑换奖品，奖品失效');
        }

        $address_json = [
            'name' => $name,
            'phone' => $phone,
            'area' => $area,
            'address' => $address,
            'province_id' => I('post.province_id'),
            'city_id' => I('post.city_id'),
            'area_id' => I('post.area_id'),
        ];
        $address_json = json_encode($address_json);
        D('DrawRecord')->update($drawRecord, ['address_json' => $address_json, 'update_time' => $time, 'status' => 2]);
    }

    //我的奖品
    public function userPrizes($user_id)
    {
        $this->prizeOverTime();

        $uid = $user_id;
        $drawId = I('get.draw_rule_id');
        empty($drawId) && $this->throwException(ErrorCode::DATA_IS_WRONG, '抽奖活动ID不能为空');

        $ip = $_SERVER["REMOTE_ADDR"];

        $where['r.draw_rule_id'] = $drawId;
        $where['r.status'] = ['neq', 0];

        if (!empty($uid)) {
            $where['r.wx_user_id'] = $uid;
        } else {
            $where['r.wx_user_id'] = array('exp', ' is null');
            $where['r.ip'] = $ip;
        }

        $params = [
            'alias' => 'r',
            'field' => 'r.id,p.prize_name,r.status,p.type,r.create_time as valid_start_time,r.expire_time as valid_end_time,w.telephone as phone,p.prize_image',
            'where' => $where,
            'order' => 'r.create_time DESC',
            'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id 
                       LEFT JOIN wx_user as w on w.id = r.wx_user_id ',
            //'limit' => $this->page()
        ];

        $data = D('DrawRecord')->getList($params);
        $count = D('DrawRecord')->getNum($params);

        foreach ($data as &$v) {
            $v['prize_image'] = $v['prize_image'] ? Util::getServerFileUrl($v['prize_image']) : '';

            if ($v['type'] < 3 && $v['status'] > 1) {

                $startTimeArr = D('CouponReceiveRecord')->getFieldVal(
                    ['draw_record_id' => $v['id']], 'min(start_time)'
                );
                $endTimeArr = D('CouponReceiveRecord')->getFieldVal(
                    ['draw_record_id' => $v['id']], 'max(end_time)'
                );

                $v['valid_start_time'] = $startTimeArr;
                $v['valid_end_time'] = $endTimeArr;
            }
        }

        return ['data' => $data, 'count' => $count];

    }

    //我的优惠券
    public function userCoupons()
    {
        $this->prizeOverTime();

        $uid = I('get.id');
        $status = I('get.status');
        $money = I('get.money');

        $where['r.wx_user_id'] = $uid;

        if ($status == 1) {
            $where['r.status'] = $status;
            $where['c.status'] = 1;
        } elseif ($status == 2 || $status == 3) {
            $where['r.status'] = $status;
        } elseif ($status == 4) {
            $where['_string'] = 'r.status = 3 or ( r.status != 2 and r.end_time <= ' . time() . ' ) ';
        }

        if (!empty($money)) {
            $where['r.status'] = 1;
            $where['c.status'] = 1;
            $where['r.cp_full_money'] = ['ELT', $money * 100];
        }

        $params = [
            'alias' => 'r',
            'field' => 'r.id as coupon_receive_record_id ,r.wx_user_id,r.coupon_id as id,c.title,r.status,c.coupon_type,c.form,
                        r.cp_full_money as full_money,r.cp_reduce_money as reduce_money,r.start_time,r.end_time ',
            'where' => $where,
            'join' => 'LEFT JOIN coupon_rule as c on c.id = r.coupon_id',
            'order' => 'r.end_time DESC',
            'limit' => $this->page()
        ];

        $data = D('CouponReceiveRecord')->getList($params);
        $count = D('CouponReceiveRecord')->getNum($params);

        foreach ($data as &$v) {
            $v['full_money'] /= 100;
            $v['reduce_money'] /= 100;
        }

        return ['data' => $data, 'count' => $count];

    }

    //奖品详情（商品）
    public function goods()
    {
        $this->prizeOverTime();

        $drawRecord = I('get.id');

        $params = [
            'alias' => 'r',
            'field' => 'r.id, p.prize_name ,r.status,p.type,r.update_time as exchange_time
                        ,r.create_time as start_valid_time,r.expire_time as end_valid_time,p.prize_image,r.address_json as receiver,r.express_compay,r.express_sn',
            'where' => ['r.id' => $drawRecord, 'p.type' => 3],
            'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id',
        ];

        $data = D('DrawRecord')->getOne($params);

        if (!empty($data)) {
            $data['prize_image'] = $data['prize_image'] ? Util::getServerFileUrl($data['prize_image']) : '';

            $data['receiver'] = json_decode($data['receiver'], true);
            $data['express'] = [
                'company' => $data['express_compay'],
                'number' => $data['express_sn'],
            ];
            unset($data['express_compay']);
            unset($data['express_sn']);
            return $data;
        }
    }

    //奖品详情（优惠券）
    public function coupons()
    {
        $this->prizeOverTime();

        $drawRecord = I('get.id');

        $params = [
            'alias' => 'r',
            'field' => 'r.id,r.status, p.prize_name ,p.type,r.prize_item_id,p.prize_image,r.create_time as start_valid_time,r.expire_time as end_valid_time,p.prize_result as coupons',
            'where' => ['r.id' => $drawRecord, 'p.type' => array('between', '1,2')],
            'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id',
        ];
        $data = D('DrawRecord')->getOne($params);

        if (!empty($data)) {

            $data['prize_image'] = $data['prize_image'] ? Util::getServerFileUrl($data['prize_image']) : '';

            $couponRecord = D('CouponReceiveRecord')->getList([
                'alias' => 'crr',
                'field' => 'crr.coupon_id as id , crr.status,crr.start_time,crr.end_time,crr.draw_record_id,c.title',
                'where' => ['crr.draw_record_id' => $drawRecord],
                'join' => 'LEFT JOIN coupon_rule as c on c.id = crr.coupon_id',
                'order' => 'crr.id asc'
            ]);

            $data['coupons'] = $couponRecord;

            if ($data['status'] > 1) {
                foreach ($couponRecord as $c) {
                    $startTimeArr[] = $c['start_time'];
                    $endTimeArr[] = $c['end_time'];
                }

                $data['start_valid_time'] = min($startTimeArr);
                $data['end_valid_time'] = max($endTimeArr);

            }
        }

        return $data;
    }

    //更新过期的领奖记录、优惠券领取记录状态
    public function prizeOverTime()
    {
        $time = time();
        $drawRecord = [
            'status' => 1,
            'expire_time' => array('lt', $time)
        ];
        D('DrawRecord')->update($drawRecord, ['status' => 3]);

        $couponRecord = [
            'status' => 1,
            'end_time' => array('lt', $time)
        ];
        D('CouponReceiveRecord')->update($couponRecord, ['status' => 4]);
    }

    //创建新用户并迁移工单用户信息
    public function userNew($phone)
    {
        $model = D('User');
        $model->startTrans();

        $time = time();
        $user = [
            'telephone' => $phone,
            'add_time' => $time,
            'bind_time' => $time,
        ];

        $userId = $model->insert($user);
        D('WorkerOrderUserInfo')->update(['phone' => $phone], ['wx_user_id' => $userId]);

        $model->commit();
        return $userId;
    }


}