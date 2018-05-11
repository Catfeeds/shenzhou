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
use Library\Common\Util;

class DrawLogic extends BaseLogic
{

    //抽奖列表
    public function getList()
    {
        $time = time();

        !empty(I('get.title')) && $where['title'] = ['LIKE', "%" . I('get.title') . "%"];

        if (I('get.status') !== '' && I('get.status') < 3) {
            $where['status'] = I('get.status');
        } elseif (I('get.status') == 3) {
            $where['status'] = 1;
            $where['start_time'] = ['GT', $time];

        } elseif (I('get.status') == 4) {
            $where['status'] = 1;
            $where['start_time'] = ['ELT', $time];
            $where['end_time'] = ['EGT', $time];
        }

        if (I('get.start_time') && I('get.end_time')) {

            //$where['start_time'] = ['EGT', I('get.start_time')];
            //$where['end_time'] = ['ELT', I('get.end_time')];

            $start_time = I('get.start_time');
            $end_time = I('get.end_time');

            $map['start_time']  = array('between', [$start_time,$end_time]);
            $map['end_time']  = array('between', [$start_time,$end_time]);
            $map['_logic'] = 'or';
            $where['_complex'] = $map;

        } elseif (I('get.start_time')) {
            $where['start_time'] = ['EGT', I('get.start_time')];
        } elseif (I('get.end_time')) {
            $where['end_time'] = ['ELT', I('get.end_time')];
        }

        $params = [
            'field' => 'id,title,status,start_time,end_time,total_draw_user,total_draw_times',
            'where' => $where,
            'order' => 'id DESC',
            'limit' => $this->page()
        ];

        $data = D('DrawRule')->getList($params);
        $count = D('DrawRule')->getNum($params);

        foreach ($data as &$v) {
            if ($v['status'] == 1) {
                $v['status'] = $v['start_time'] > $time ? 3 : 4;
            }
            
            $v['link_url'] = C('C_HOST_URL') . C('C_DRAW_PAGE') . '?draw_id=' . $v['id'];
        }

        return ['data' => $data, 'count' => $count];

    }

    //抽奖新增
    public function add($adminId)
    {
        //表单验证
        $this->formParamsCheck('draw', I('post.'));

        $params['admin_id'] = $adminId;
        $params['status'] = 0;
        $params['title'] = I('post.title');
        $params['start_time'] = I('post.start_time');
        $params['end_time'] = I('post.end_time');
        $params['people_draw_number'] = I('post.people_draw_times');
        $params['max_win_draw_number'] = I('post.max_win_draw_times');
        $params['total_draw_user'] = 0;
        $params['total_draw_times'] = 0;

        $prizeArr = I('post.prizes');

        $prizeParams = [];
        $overArr = [];
        $couponNumber = 0;
        foreach ($prizeArr as $k => $v) {
            $prizeParams[$k]['type'] = $v['type'];
            $prizeParams[$k]['winning_rate'] = $v['winning_rate'] * 100;
            $prizeParams[$k]['prize_name'] = $v['prize_name'];
            $prizeParams[$k]['prize_number'] = $v['prize_number'];
            $prizeParams[$k]['prize_image'] = $v['prize_image'];
            $prizeParams[$k]['prize_result'] = json_encode($v['prize_result']);

            //判断选择优惠券是否超额
            if ($v['type'] < 3) {
                foreach ($v['prize_result'] as $pr) {
                    $coupon = D('CouponRule')->getOne(['id' => $pr['id']]);
                    $couponNum = D('CouponReceiveRecord')->getNum(['coupon_id' => $pr['id']]);

                    $restNum = $coupon['number'] - $couponNum - $coupon['frozen_number'];
                    $newNum = $v['prize_number'] * $pr['number'];
                    $couponData[] = ['coupon_id' => $pr['id'], 'number' => $newNum]; //每种优惠劵的数据量，用于保存成功冻结优惠券数量

                    $result[] = $restNum - $newNum;
                    if ($restNum < $newNum) {
                        $overArr[$pr['id']] = $coupon['title'];
                    }

                    $couponNumber += $newNum;
                }
            }
        }

        if (!empty($overArr)) {
            $overArr = implode(',', $overArr);
            $content = $overArr . '的库存不足，请调整奖品数量或者修改优惠券库存。';
            $this->throwException(ErrorCode::DRAW_RULE_COUPON_OVER, $content);
        }
//       print_r($couponData);exit;

        $model = D('DrawRule');
        $model->startTrans();
        $drawRuleId = $model->insert($params);

        if ($drawRuleId) {
            //冻结期数量
            if ($couponNumber && $couponNumber > 0) {
                foreach ($couponData as $c) {
                    D('CouponRule')->where(['id' => $c['coupon_id']])->setInc('frozen_number', $c['number']);
                }
            }

            foreach ($prizeParams as &$val) {
                $val['draw_rule_id'] = $drawRuleId;
            }
            D('PrizeItem')->insertAll($prizeParams);
        }

        $model->commit();
    }

    //抽奖编辑
    public function update($request, $adminId)
    {
        $drawId = I('get.id');

        $params['admin_id'] = $adminId;
        !empty($request['title']) && $params['title'] = $request['title'];
        !empty($request['start_time']) && $params['start_time'] = $request['start_time'];
        !empty($request['end_time']) && $params['end_time'] = $request['end_time'];
        !empty($request['people_draw_times']) && $params['people_draw_number'] = $request['people_draw_times'];
        !empty($request['max_win_draw_times']) && $params['max_win_draw_number'] = $request['max_win_draw_times'];

        //解冻抽奖活动奖品中优惠券数量
        $this->unfrozen($drawId);

        $model = D('DrawRule');
        $model->startTrans();
        $model->update($drawId, $params);

        //删除奖项
        if ($request['delete_prize_ids']) {
            $deleteId = explode(',', $request['delete_prize_ids']);
            if ($deleteId) {
                foreach ($deleteId as $del) {
                    D('PrizeItem')->remove(['id' => $del, 'draw_rule_id' => $drawId]);
                }
            }
        }

        $prizeArr = $request['prizes'];
        $updateArr = [];
        $insertArr = [];
        $couponNumber = 0;

        foreach ($prizeArr as $k => $v) {

            //判断选择优惠券是否超额
            if ($v['type'] < 3) {
                foreach ($v['prize_result'] as $pr) {
                    $coupon = D('CouponRule')->getOne(['id' => $pr['id']]);
                    $couponNum = D('CouponReceiveRecord')->getNum(['coupon_id' => $pr['id']]);

                    $restNum = $coupon['number'] - $couponNum - $coupon['frozen_number'];
                    $newNum = $v['prize_number'] * $pr['number'];

                    //每种优惠劵的数据量，用于保存成功冻结优惠券数量
                    $couponData[] = ['coupon_id' => $pr['id'], 'number' => $newNum];

                    $result[] = $restNum - $newNum;
                    if ($restNum < $newNum) {
                        $overArr[$pr['id']] = $coupon['title'];
                    }

                    $couponNumber += $newNum;
                }

                if (!empty($overArr)) {
                    $overArr = implode(',', $overArr);
                    $content = $overArr . '的库存不足，请调整奖品数量或者修改优惠券库存。';
                    $this->throwException(ErrorCode::DRAW_RULE_COUPON_OVER, $content);
                }
            }

            if ($v['id']) {
                $updateArr['draw_rule_id'] = $drawId;
                $updateArr['type'] = $v['type'];
                $updateArr['winning_rate'] = $v['winning_rate'] * 100;
                $updateArr['prize_name'] = $v['prize_name'];
                $updateArr['prize_number'] = $v['prize_number'];
                $updateArr['prize_image'] = $v['prize_image'];
                $updateArr['prize_result'] = json_encode($v['prize_result']);

                //更新已有奖项
                D('PrizeItem')->update($v['id'], $updateArr);

            } else {
                $insertArr[$k]['draw_rule_id'] = $drawId;
                $insertArr[$k]['type'] = $v['type'];
                $insertArr[$k]['winning_rate'] = $v['winning_rate'] * 100;
                $insertArr[$k]['prize_name'] = $v['prize_name'];
                $insertArr[$k]['prize_number'] = $v['prize_number'];
                $insertArr[$k]['prize_image'] = $v['prize_image'];
                $insertArr[$k]['prize_result'] = json_encode($v['prize_result']);

            }
        }

        //新增奖项
        $insertArr = array_merge($insertArr);
        !empty($insertArr) && D('PrizeItem')->insertAll($insertArr);

        //冻结期数量
        if ($couponNumber && $couponNumber > 0) {
            foreach ($couponData as $c) {
                D('CouponRule')->where(['id' => $c['coupon_id']])->setInc('frozen_number', $c['number']);
            }
        }

        $model->commit();
    }

    //抽奖详情
    public function read()
    {
        $drawId = I('get.id');

        $params = [
            'field' => 'id,title,status,start_time,end_time,people_draw_number as people_draw_times ,
                max_win_draw_number as max_win_draw_times  ',
            'where' => ['id' => $drawId],
        ];
        $data = D('DrawRule')->getOne($params);

        $prizeItem = D('PrizeItem')->getList(['draw_rule_id' => $drawId]);

        foreach ($prizeItem as &$v) {

            $v['prize_image_full'] = $v['prize_image'] ? Util::getServerFileUrl($v['prize_image']) : '';

            $v['winning_rate'] = (string)($v['winning_rate'] / 100);

            if ($v['prize_result']) {
                $v['prize_result'] = json_decode($v['prize_result'], true);

                foreach ($v['prize_result'] as $k => $coupons) {

                    $coupon = D('CouponRule')->getOne(['id' => $coupons['id']]);

                    $v['coupons'][$k] = $coupons;
                    $v['coupons'][$k]['title'] = $coupon['title'];
                    $v['coupons'][$k]['full_money'] = $coupon['full_money'];
                    $v['coupons'][$k]['reduce_money'] = $coupon['reduce_money'];
                }
            }
        }

        if (!empty($data)) {
            $data['prizes'] = $prizeItem;
        }

        return $data;
    }

    //抽奖发布、结束
    public function operate()
    {
        $time = time();
        $type = I('post.type');
        $drawId = I('get.id');

        if ($type == 1) {

            $draw = D('DrawRule')->getOne([
                'id' => $drawId,
                'status' => 0
            ]);

            empty($draw) && $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);

            if ($draw['end_time'] <= $time) {
                $this->throwException(ErrorCode::DRAW_RULE_OVERTIME, '很抱歉，已经过了活动设置的有效时间，请重新设置');
            }

            $sTime = $draw['start_time'];
            $eTime = $draw['end_time'];

            $where['status'] = 1;
            $goingDraw = D('DrawRule')->getList($where);

            foreach ($goingDraw as $g) {
                $result = max($sTime, $g['start_time']) - min($eTime, $g['end_time']);
                if ($result < 0) {
                    $this->throwException(ErrorCode::DRAW_RULE_CROSS, '很抱歉，该活动的时间与其他已发布的活动时间冲突，请调整时间，或结束已发布的活动');
                }
            }

        } elseif ($type == 2) {
            //结束时解冻抽奖活动奖品中优惠券数量
            $this->unfrozen($drawId, true);
        }

        D('DrawRule')->update($drawId, ['status' => $type]);

    }

    //中奖名单
    public function winList()
    {
        $drawId = I('get.id');

        $where['p.draw_rule_id'] = $drawId;
        $where['r.status'] = ['GT', 0];

        !empty(I('get.phone')) && $where['w.telephone'] = ['LIKE', "%" . I('get.phone') . "%"];
        !empty(I('get.prize_id')) && $where['r.prize_item_id'] = I('get.prize_id');
        !empty(I('get.exchange')) && $where['r.status'] = I('get.exchange');
        !empty(I('get.channel')) && $where['r.channel'] = I('get.channel');

        if (I('get.express') == 1) {
            $where['p.type'] = 3;
            $where['r.express_sn'] = array('exp', 'is not null');
        } elseif (I('get.express') == 2) {
            $where['p.type'] = 3;
            $where['r.express_sn'] = array('exp', 'is null');
        } elseif (I('get.express') == 3) {
            //3：不需要邮寄（非商品+ 过期商品）
            $where['_string'] = ' ( p.type != 3 )  OR ( r.status = 3 ) ';
        }

        if (I('get.start_time') && I('get.end_time')) {
            $where['r.create_time'] = ['BETWEEN', I('get.start_time') . ',' . I('get.end_time')];
        } elseif (I('get.start_time')) {
            $where['r.create_time'] = ['EGT', I('get.start_time')];
        } elseif (I('get.end_time')) {
            $where['r.create_time'] = ['ELT', I('get.end_time')];
        }

        $params = [
            'alias' => 'r',
            'field' => 'r.id,w.openid,w.telephone as phone,w.nickname,p.prize_name,r.channel,r.create_time,
                        r.status as exchange,r.address_json as receiver,r.express_sn as express,
                        r.express_compay,r.express_sn,p.type
                        ',
            'where' => $where,
            'order' => 'r.create_time DESC',
            'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id 
                        LEFT JOIN wx_user as w on w.id = r.wx_user_id 
                       ',
            'limit' => $this->page()
        ];

        $data = D('DrawRecord')->getList($params);

        $count = D('DrawRecord')->getNum($params);

        foreach ($data as &$v) {

            $v['receiver'] = json_decode($v['receiver'], true);

            if ($v['type'] != 3) {
                $v['express'] = 3;
            } elseif ($v['type'] == 3) {
                $v['express'] = $v['express_sn'] ? 1 : 2;
            }

            $v['express_info'] = [
                'company' => $v['express_compay'],
                'number' => $v['express_sn']
            ];

            unset($v['express_compay']);
            unset($v['express_sn']);
        }

        return ['data' => $data, 'count' => $count];

    }

    //邮寄
    public function express()
    {
        $drawRecordId = I('get.id');

        $params['update_time'] = time();
        !empty(I('post.express_sn')) && $params['express_sn'] = I('post.express_sn');
        !empty(I('post.express_code')) && $params['express_code'] = I('post.express_code');
        !empty(I('post.express_compay')) && $params['express_compay'] = I('post.express_compay');


        D('DrawRecord')->update($drawRecordId, $params);

    }

    //抽奖名单
    public function drawList()
    {
        $drawId = I('get.id');

        $where = ['draw_rule_id' => $drawId];
        $having = [];
        if ($phone = I('phone')) {
            $wx_user_ids = BaseModel::getInstance('wx_user')->getList([
                'where' => [
                    'telephone' => ['LIKE', "%{$phone}%"]
                ]
            ]);
            $where['wx_user_id'] = $wx_user_ids ? ['IN', $wx_user_ids] : '-1';
        }
        if ($min_draw_times = I('min_draw_times')) {
            $having[] = " draw_times>={$min_draw_times} ";
        }
        if ($max_draw_times = I('max_draw_times')) {
            $having[] = " draw_times<={$max_draw_times} ";
        }
        if ($min_win_times = I('min_win_times')) {
            $having[] = " win_times>={$min_win_times} ";
        }
        if ($max_win_times = I('max_win_times')) {
            $having[] = " win_times<={$max_win_times} ";
        }
        if ($start_time = I('start_time')) {
            $having[] = " draw_time>={$start_time} ";
        }
        if ($end_time = I('end_time')) {
            $having[] = " draw_time<={$end_time} ";
        }
        $having = $having ? implode(' AND ', $having) : null;
        $draw_list = BaseModel::getInstance('draw_record')->getList([
            'where' => $where,
            'field' => 'wx_user_id,max(create_time) draw_time,count(*) draw_times,count(if(status>0,1, null)) win_times',
            'group' => 'wx_user_id',
            'having' => $having,
            'order' => 'draw_time DESC',
            'limit' => getPage(),
        ]);

        $wx_user_ids = array_filter(array_column($draw_list, 'wx_user_id'));

        $wx_user_list = $wx_user_ids ? BaseModel::getInstance('wx_user')->getList([
            'field' => 'id,telephone,nickname,openid',
            'where' => ['id' => ['IN', $wx_user_ids]],
            'index' => 'id',
        ]) : [];
        foreach ($draw_list as &$item) {
            $item['openid'] = $wx_user_list[$item['wx_user_id']]['openid'] ?? null;
            $item['nickname'] = $wx_user_list[$item['wx_user_id']]['nickname'] ?? null;
            $item['phone'] = $wx_user_list[$item['wx_user_id']]['telephone'] ?? null;
        }

        $num_sql = BaseModel::getInstance('draw_record')->getSql([
            'where' => $where,
            'field' => 'wx_user_id,max(create_time) draw_time,count(*) draw_times,count(if(status>0,1, null)) win_times',
            'group' => 'wx_user_id',
            'having' => $having,
        ]);
        $count = BaseModel::getInstance('draw_record')->query("SELECT count(*) num FROM {$num_sql} a");
        $count = $count[0]['num'];

//        $drawId = I('get.id');
//
//        $having = '(1=1)';
//        if (!empty(I('get.phone'))) {
//            $having .= " and phone LIKE '%" . I('get.phone') . "%' ";
//        }
//
//        if (!empty(I('get.min_draw_times')) && !empty(I('get.max_draw_times'))) {
//            $having .= ' and (draw_times between  ' . I('get.min_draw_times') . ' AND ' . I('get.max_draw_times') . ' )';
//        } elseif (!empty(I('get.min_draw_times'))) {
//            $having .= ' and draw_times >= ' . I('get.min_draw_times');
//        } elseif (!empty(I('get.max_draw_times'))) {
//            $having .= ' and draw_times <= ' . I('get.max_draw_times');
//        }
//
//        if (!empty(I('get.min_win_times')) && !empty(I('get.max_win_times'))) {
//            $having .= ' and (win_times between  ' . I('get.min_win_times') . ' AND ' . I('get.max_win_times') . ' )';
//        } elseif (!empty(I('get.min_win_times'))) {
//            $having .= ' and win_times >= ' . I('get.min_win_times');
//        } elseif (!empty(I('get.max_win_times'))) {
//            $having .= ' and win_times <= ' . I('get.max_win_times');
//        }
//
//        $startTime = I('get.start_time');
//        $endTime = I('get.end_time');
//
//        if (!empty(I('get.start_time')) && !empty(I('get.end_time'))) {
//            $having .= ' and (draw_time between  ' . $startTime . ' AND ' . $endTime . ' )';
//        } elseif (!empty(I('get.start_time'))) {
//            $having .= ' and draw_time >= ' . $startTime;
//        } elseif (!empty(I('get.end_time'))) {
//            $having .= ' and draw_time <= ' . $endTime;
//        }
//
//        $data = D('DrawRecord a')
//            ->where(['a.draw_rule_id' => $drawId])
//            ->join('left join wx_user as u on u.id = a. wx_user_id')
//            ->field('a.wx_user_id as id,u.nickname, count(a.id) as draw_times ,
//                     max(a.create_time) as draw_time,
//                     u.telephone as phone ,u.openid,
//                     if(a.wx_user_id > 0,
//                     (select count(draw_record.id) from draw_record where a.wx_user_id = draw_record.wx_user_id and status >0 and draw_rule_id =
//                     ' . $drawId . '),
//                     (select count(draw_record.id) from draw_record where draw_record.wx_user_id is null and status >0 and draw_rule_id =
//                     ' . $drawId . ')
//                     ) as win_times
//                 ')
//            ->group('a.wx_user_id')
//            ->having($having)
//            ->limit($this->page())
//            ->order('a.create_time DESC')
//            ->select();
//
//        $countBuild = D('DrawRecord a')
//            ->where(['a.draw_rule_id' => $drawId])
//            ->join('left join wx_user as u on u.id = a. wx_user_id')
//            ->field('a.wx_user_id as id, count(a.id) as draw_times ,
//                     max(a.create_time) as draw_time,a.create_time,
//                     u.telephone as phone ,u.openid,
//                     if(a.wx_user_id > 0,
//                     (select count(draw_record.id) from draw_record where a.wx_user_id = draw_record.wx_user_id and status >0 ),
//                     (select count(draw_record.id) from draw_record where draw_record.wx_user_id is null and status >0 )
//                     ) as win_times
//                 ')
//            ->group('a.wx_user_id')
//            ->having($having)
//            ->buildSql();
//
//        $count = M()->table($countBuild . ' d')->count();

        return ['data' => $draw_list, 'count' => $count];

    }

    //抽奖数据
    public function drawData()
    {
        $drawId = I('get.id');

        $time = time();
        $today = strtotime(date("Y-m-d 00:00:00", time()));

        $where['s.draw_rule_id'] = $drawId;

        $startTime = I('get.start_time');
        $endTime = I('get.end_time') ? I('get.end_time') : '';

        $is_today = 0;

        if (empty($startTime) && empty($endTime)) {
            $startTime = $today - 24 * 3600 * 7;
            $endTime = $today + 24 * 3600;
            $is_today = 1;
        }

        if ($startTime && $endTime) {
            $where['s.create_time'] = ['BETWEEN', $startTime . ',' . $endTime];

            if (($startTime <= $time) && ($endTime >= $time)) {
                $is_today = 1;
            }
        } elseif ($startTime) {
            $where['s.create_time'] = ['EGT', $startTime];
            ($startTime <= $time) && $is_today = 1;

        } elseif ($endTime) {
            $where['s.create_time'] = ['ELT', $endTime];
            ($endTime >= $time) && $is_today = 1;
        }

        //当需要今天的统计信息时
        if ($is_today == 1) {
            $peopleP = [
                'alias' => 'r',
                'field' => 'r.id,r.wx_user_id',
                'where' => ['p.draw_rule_id' => $drawId, 'r.create_time' => array('EGT', $today)],
                'join' => 'LEFT JOIN prize_item as p on p.id = r.prize_item_id '
            ];
            $drawRecord = D('DrawRecord')->getList($peopleP);
            foreach ($drawRecord as &$d) {
                $new[] = $d['wx_user_id'];
            }

            $todayPeople = count(array_unique($new));
            $todayCount = count($drawRecord);
        }


        $params = [
            'alias' => 's',
            'field' => 's.create_time,s.draw_number,s.draw_people_number,s.channel',
            'order' => 's.create_time asc',
            'where' => $where,
        ];

        $draw = D('DrawStatistics')->getList($params);

        $drawLine = [];
        foreach ($draw as $d) {
            $drawLine[$d['create_time']]['draw_number'] += $d['draw_number'];

            $thisToday = strtotime(date("Y-m-d 00:00:00", $d['create_time']));
            $from = $thisToday - 3600 * 24;
            $to = $thisToday - 1;

            $userCount = D('DrawRecord')->getFieldVal(
                [
                    'draw_rule_id' => $drawId,
                    'create_time' => ['Between', $from . ',' . $to]
                ],
                'wx_user_id', true
            );
            $userCount = count(array_unique($userCount));
            $drawLine[$d['create_time']]['draw_people_number'] = $userCount;

            $drawLine[$d['create_time']]['aaa'] = $userCount;

        }


        //折线图
        $draw_number = array_column($drawLine, 'draw_number');
        $draw_people_number = array_column($drawLine, 'draw_people_number');
        $create_time = array_keys($drawLine);

        if ($todayPeople && $todayCount) {
            $draw_number[] = $todayCount;
            $draw_people_number[] = $todayPeople;
            $create_time['today'] = (string)($today);
        }

        foreach ($create_time as $k => &$t) {

            if ($k !== 'today') {
                $t = strtotime(date("Y-m-d 00:00:00", $t));
                $t -= 1;
            }

            $t = date("m.d", $t);
        }

        $create_time = array_values($create_time);

        $legend1 = [
            'legend' => 1,
            'x_axis' => $create_time,
            'y_axis' => $draw_number
        ];
        $legend2 = [
            'legend' => 2,
            'x_axis' => $create_time,
            'y_axis' => $draw_people_number
        ];

        if ($create_time) {
            $data['line_chart'][] = $legend1;
            $data['line_chart'][] = $legend2;
        }

        //饼形图
        $channel = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
        ];

        $pie_chart = [];

        foreach ($draw as $v) {
            $channel[$v['channel']] += $v['draw_people_number'];
        }

        foreach ($channel as $k => $c) {
            $pie_chart[] = [
                'name' => C('CHANNEL')[$k],
                'value' => $c
            ];
        }
        if ($channel) {
            $data['pie_chart'][] = $pie_chart;
        }

        return $data;

    }

    //获奖情况
    public function prizeList()
    {
        $this->isOverTime();

        $drawId = I('get.id');

        $where['draw_rule_id'] = $drawId;
        $prizeArr = D('PrizeItem')->getList($where);

        $data = [];
        foreach ($prizeArr as $k => $p) {
            $data[$k]['id'] = $p['id'];
            $data[$k]['prize_name'] = $p['prize_name'];
            $data[$k]['prize_number'] = $p['prize_number'];


            $winning_number = D('DrawRecord')->getNum(['prize_item_id' => $p['id'], 'status' => array('gt', 0)]);
            $exchange_number = D('DrawRecord')->getNum(['prize_item_id' => $p['id'], 'status' => 2]);
            $expire_number = D('DrawRecord')->getNum(['prize_item_id' => $p['id'], 'status' => 3]);

            $data[$k]['winning_number'] = $winning_number;
            $data[$k]['exchange_number'] = $exchange_number;
            $data[$k]['expire_number'] = $expire_number;
            $data[$k]['rest_number'] = $p['prize_number'] - $winning_number + $expire_number;
        }

        return $data;

    }

    //更新过期的领奖记录状态
    public function isOverTime()
    {
        $time = time();
        $drawRecord = [
            'status' => 1,
            'expire_time' => array('lt', $time)
        ];
        D('DrawRecord')->update($drawRecord, ['status' => 3]);
    }

    //奖品列表
    public function prizes()
    {
        $drawId = I('get.id');

        $params = [
            'field' => 'id,prize_name as name',
            'where' => ['draw_rule_id' => $drawId],
            'order' => 'winning_rate asc '
        ];
        $data = D('PrizeItem')->getList($params);

        return $data;
    }

    /*自动结束脚本*/
    public function statusScript()
    {
        $time = time();
        $where = ['status' => 1, 'end_time' => array('ELT', $time)];

        $draw_rule_ids = D('DrawRule')->getFieldVal($where, 'id', true);

        D('DrawRule')->update(['id' => ['IN', $draw_rule_ids]], ['status' => 2]);

        //结束时解冻抽奖活动奖品中优惠券数量
        foreach ($draw_rule_ids as $v) {
            $this->unfrozen($v, true);
        }
    }

    /*每日统计脚本*/
    public function statisticsScript()
    {
        //昨天 00:00:00
        $yesterdayStart = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));
        //昨天 23:59:59
        $yesterdayEnd = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));


        $where['r.create_time'] = ['BETWEEN', $yesterdayStart . ',' . $yesterdayEnd];

        $params = [
            'alias' => 'r',
            'field' => 'r.id,r.channel,r.wx_user_id,r.draw_rule_id',
            'where' => $where
        ];

        $data = D('DrawRecord')->getList($params);
        if (!empty($data)) {

            $Arr = [];
            $userArr = [];
            $countArr = [];

            foreach ($data as $v) {
                $userArr[$v['draw_rule_id']][$v['channel']][] = $v['wx_user_id'];
                $countArr[$v['draw_rule_id']][$v['channel']][] = $v['id'];
            }

            foreach ($countArr as $k1 => $a) {
                foreach ($a as $k2 => $b) {
                    $Arr[$k1][$k2]['idCount'] = count($b);
                    $Arr[$k1][$k2]['userCount'] = count(array_unique($userArr[$k1][$k2]));
                }
            }
            $statistics = [];
            foreach ($Arr as $ka1 => $va) {
                foreach ($va as $ka2 => $va2) {
                    $Arr[$k1][$k2]['idCount'] = count($b);
                    $Arr[$k1][$k2]['userCount'] = count(array_unique($userArr[$k1][$k2]));
                    $statistics[] = [
                        'draw_rule_id' => $ka1,
                        'channel' => $ka2,
                        'draw_number' => $va2['idCount'],
                        'draw_people_number' => $va2['userCount'],
                        'create_time' => time()
                    ];
                }
            }
            D('DrawStatistics')->insertAll($statistics);
        } else {
            return false;
        }
    }

    //解冻抽奖活动奖品中优惠券数量
    public function unfrozen($drawId, $stop = false)
    {
        $where = ['draw_rule_id' => $drawId, 'type' => array('LT', 3)];
        $prize_item = D('PrizeItem')->getFieldVal($where, 'id,prize_number,prize_result', true);

        if (!empty($prize_item)) {
            foreach ($prize_item as &$v) {

                if ($stop) {

                    //中奖数
                    $couponGotNum = D('DrawRecord')->getNum(['prize_item_id' => $v['id'], 'status' => ['gt', 0]]);
                    //奖品过期数量
                    $prizeOverNum = D('DrawRecord')->getNum(['prize_item_id' => $v['id'],'status' => 3]);

                }

                $prize_number = $v['prize_number'] - $couponGotNum + $prizeOverNum;
                $prize_item_coupon = json_decode($v['prize_result'], true);

                foreach ($prize_item_coupon as &$val) {
                    $number = $prize_number * $val['number'];
                    if ($number > 0) {
                        D('CouponRule')->where(['id' => $val['id']])->setDec('frozen_number', $number);
                    }
                }
            }
        }
    }

}