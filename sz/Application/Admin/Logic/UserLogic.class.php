<?php
/**
 * Created by PhpStorm.
 * User: 3N
 * Date: 2017/12/20
 * Time: 10:35
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Controller\BaseController;
use Common\Common\Service\AreaService;
use Illuminate\Support\Arr;
use Library\Common\Util;
use Admin\Model\BaseModel;
use EasyWeChat\Foundation\Application;
use Think\Model;
use Common\Common\Service\OrderService;

class UserLogic extends BaseLogic
{
    //用户列表
    public function getList()
    {
        $where = [];

        // 省市区
        if ($area_id = I('area_id')) {
            $dealer_info_wx_user_area_ids = BaseModel::getInstance('dealer_info')->getFieldVal([
                'area_id' => $area_id,
            ], 'wx_user_id', true);
//            $where[] = [
//                'area_id' => $area_id,
//                'id' => ['IN', $dealer_info_wx_user_area_ids ?: '-1'],
//                '_logic' => 'OR',
//            ];
            $where[] = [
                [
                    'area_id' => $area_id,
                    'user_type' => 0,
                ],
                [
                    'user_type' => 1,
                    'id' => ['IN', $dealer_info_wx_user_area_ids ?: '-1'],
                ],
                '_logic' => 'OR',
            ];
        } elseif ($city_id = I('city_id')) {
            $dealer_info_wx_user_city_ids = BaseModel::getInstance('dealer_info')->getFieldVal([
                'city_id' => $city_id,
            ], 'wx_user_id', true);
//            $where[] = [
//                'city_id' => $city_id,
//                'id' => ['IN', $dealer_info_wx_user_city_ids ?: '-1'],
//                '_logic' => 'OR',
//            ];
            $where[] = [
                [
                    'city_id' => $city_id,
                    'user_type' => 0,
                ],
                [
                    'user_type' => 1,
                    'id' => ['IN', $dealer_info_wx_user_city_ids ?: '-1'],
                ],
                '_logic' => 'OR',
            ];
        } elseif ($province_id = I('get.province_id')) {
            $dealer_info_wx_user_province_ids = BaseModel::getInstance('dealer_info')->getFieldVal([
                'province_id' => $province_id,
            ], 'wx_user_id', true);
//            $where[] = [
//                'province_id' => $province_id,
//                'id' => ['IN', $dealer_info_wx_user_province_ids ?: '-1'],
//                '_logic' => 'OR',
//            ];
            $where[] = [
                [
                    'province_id' => $province_id,
                    'user_type' => 0,
                ],
                [
                    'user_type' => 1,
                    'id' => ['IN', $dealer_info_wx_user_province_ids ?: '-1'],
                ],
                '_logic' => 'OR',
            ];
        }


        // 手机
        if ($phone = I('phone')) {
            $where['telephone'] = $phone;
        }

        // 用户类型
        $user_type = I('user_type');
        if ($user_type !== '') {
            $where['user_type'] = $user_type;
        }

        // 是否绑定
        $is_bind = I('is_bind');
        if ($is_bind == 1) {
            $where['bind_time'] = ['GT', 0];
        } elseif ($is_bind == 2) {
            $where['bind_time'] = 0;
        }

        // 绑定时间
        $start_time = I('start_time');
        $end_time = I('end_time');

        if ($start_time && $end_time) {
            $where['bind_time'] = ['BETWEEN', [$start_time, $end_time]];
        } elseif ($start_time) {
            $where['bind_time'] = ['EGT', $start_time];
        } elseif ($end_time) {
            $where['bind_time'] = ['ELT', $end_time];
        }
     
        // 名称
        if ($name = I('name')) {
            $dealer_info_wx_user_name_ids = BaseModel::getInstance('dealer_info')->getFieldVal([
                'name' => ['like', '%' . $name . '%'],
            ], 'wx_user_id', true);
            $where[] = [
                [
                    'user_type' => 0,
                    'real_name' => ['like', '%' . $name . '%'],
                ],
                [
                    'user_type' => 1,
                    'id' => ['IN', $dealer_info_wx_user_name_ids ?: '-1'],
                ],
                '_logic' => 'OR',
            ];
        }


        $data_tmp = BaseModel::getInstance('wx_user')->getList([
            'field' => 'id',
            'where' => $where,
            'order' => 'id DESC',
            'limit' => getPage(),
        ]);

        $tmp_wx_user_ids = Arr::pluck($data_tmp, 'id');

        $data = $tmp_wx_user_ids ? BaseModel::getInstance('wx_user')->getList([
            'field' => 'id,telephone as phone,real_name as name,nickname,user_type,area_ids as address,bind_time,province_id,city_id,area_id',
            'where' => ['id' => ['IN', $tmp_wx_user_ids]],
            'order' => 'id DESC',
        ]) : [];

        $count = BaseModel::getInstance('wx_user')->getNum($where);

        if (empty($data)) {
            return ['data' => $data, 'count' => $count];
        }

        $area_ids = [];
        $wx_user_ids = [];
        $dealer_user_ids = [];

        foreach ($data as $item) {
            $wx_user_ids[] = $item['id'];

            if ($item['user_type'] == 1) {
                $dealer_user_ids[] = $item['id'];
            } else {
                [$area_ids[], $area_ids[], $area_ids[]] = [$item['province_id'], $item['city_id'], $item['area_id']];
            }
        }

        // 获取经销商信息
        $dealer_users = $dealer_user_ids ? BaseModel::getInstance('dealer_info')->getList([
            'where' => ['wx_user_id' => ['IN', $dealer_user_ids]],
            'field' => 'province_id,city_id,area_id,name,wx_user_id',
            'index' => 'wx_user_id',
        ]) : [];
        foreach ($dealer_users as $dealer_user) {
            [$area_ids[], $area_ids[], $area_ids[]] = [$dealer_user['province_id'], $dealer_user['city_id'], $dealer_user['area_id']];
        }

        $area_ids = array_unique($area_ids);
        $area_id_name_map = AreaService::getAreaNameMapByIds($area_ids);

        $wx_user_order_num = BaseModel::getInstance('worker_order_user_info')->getList([
            'where' => ['wx_user_id' => ['IN', $wx_user_ids]],
            'field' => 'count(*) num, wx_user_id,phone',
            'group' => 'wx_user_id',
            'index' => 'wx_user_id',
        ]);

        $user_yima_num = BaseModel::getInstance('wx_user_product')->getList([
            'where' => ['wx_user_id' => ['IN', $wx_user_ids]],
            'field' => 'count(*) num, wx_user_id',
            'group' => 'wx_user_id',
            'index' => 'wx_user_id',
        ]);

        if (!empty($dealer_user_ids)) {
            $dealer_yima_num = BaseModel::getInstance('dealer_bind_products')->getList([
                'where' => ['dealer_id' => ['IN', $dealer_user_ids]],
                'field' => 'count(*) num, dealer_id',
                'group' => 'dealer_id',
                'index' => 'dealer_id',
            ]);
        }

        foreach ($data as $key => $item) {
            if ($item['user_type'] == 1) {
                $data[$key]['name'] = $dealer_users[$item['id']]['name'];
                $data[$key]['address'] = $dealer_users[$item['id']]['province_id'] ? $area_id_name_map[$dealer_users[$item['id']]['province_id']]['name'] . '-' . $area_id_name_map[$dealer_users[$item['id']]['city_id']]['name'] . '-' . $area_id_name_map[$dealer_users[$item['id']]['area_id']]['name'] : '';

                $data[$key]['yima_number'] = $dealer_yima_num[$item['id']]['num'];

            } else {
                $data[$key]['address'] = $item['province_id'] ? $area_id_name_map[$item['province_id']]['name'] . '-' . $area_id_name_map[$item['city_id']]['name'] . '-' . $area_id_name_map[$item['area_id']]['name'] : '';
                $data[$key]['yima_number'] = $user_yima_num[$item['id']]['num'];
            }
            $data[$key]['order_number'] = $wx_user_order_num[$item['id']]['num'];
        }


        return ['data' => $data, 'count' => $count];

    }

    //用户详情
    public function read()
    {
        $userId = I('get.id');

        $params = [
            'field' => 'id,openid,telephone as phone,real_name as name,nickname,user_type,bind_time',
            'where' => ['id' => $userId],
        ];
        $data = D('WxUser')->getOne($params);

        if (empty($data)) {
            return $data;
        }

        //用户是否已关注公众号
        if (!empty($data['openid'])) {
            $isSubscribe = $this->isSubscribe($data['openid']);
        }

        $data['is_subscribe'] = $isSubscribe ? '1' : '0';
        if ($isSubscribe == 'error') {
            $data['is_subscribe'] = null;
        }

        if ($data['user_type'] == 1) {

            $dealerParams = [
                'field' => 'name,store_name,dealer_product_ids as products, area_ids as area,area_desc,license_image,dealer_images',
                'where' => ['wx_user_id' => $userId],
            ];

            $dealerInfo = D('DealerInfo')->getOne($dealerParams);

            if (!empty($dealerInfo)) {
                $data['name'] = $dealerInfo['name'];

                $dealerInfo['license_image'] = $dealerInfo['license_image'] ? Util::getServerFileUrl($dealerInfo['license_image']) : '';
                //店面照片
                $dealer_images = json_decode($dealerInfo['dealer_images'], true);
                foreach ($dealer_images as &$image) {
                    $image = $image ? Util::getServerFileUrl($image) : '';
                }
                $dealerInfo['dealer_images'] = $dealer_images;

                //经营产品
                $dealerInfo['products'] = D('DealerProduct')->getFieldVal(
                    ['id' => ['IN', $dealerInfo['products']]], 'name', true
                );

                //省市区
                $address = D('Area')->getFieldVal(
                    ['id' => ['IN', $dealerInfo['area']]], 'name', true
                );
                $address = implode('-', $address);
                $dealerInfo['area'] = $address;

                //所属厂家
                $FactoryParams = [
                    'alias' => 'wl',
                    'field' => 'wl.factory_id as id,wl.status,f.factory_full_name as factory_name',
                    'where' => ['wl.user_name' => $data['phone']],
                    'join' => 'LEFT JOIN factory as f on f.factory_id = wl.factory_id',
                    'order' => 'wl.id desc'
                ];

                $factory = D('FactoryProductWhiteList')->getList($FactoryParams);

                $dealerInfo['factories'] = $factory;
            }

        }

        $data['dealer_info'] = $dealerInfo;

        return $data;
    }

    //用户是否已关注公众账号
    public function isSubscribe($open_id = '')
    {
        try {
            $config = C('easyWeChat');
            $app = new Application($config);
            $this->user = $app->user;
            $user = $this->user->get($open_id);
            return $user->subscribe ? true : false;
        } catch (\Exception $e) {
            return 'error';
        }
    }

    //激活产品列表
    public function products()
    {
        $uid = I('get.id');
        $type = I('get.user_type');
        ($type === '') && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '用户身份不能为空');

        if ($type == 0) {
            $data = $this->userProduct($uid);
        } elseif ($type == 1) {
            $data = $this->dealerProduct($uid);
        }

        if (empty($data)) {
            return null;
        }

        foreach ($data['data'] as &$d) {
            //质保相关
            if (!empty($d['factory_id'])) {
                $yimaTable = factoryIdToModelName($d['factory_id']);

                $result = BaseModel::getInstance($yimaTable)->getOne([
                    'field' => 'register_time as active_time,active_time as buy_time,zhibao_time as warranty_time, active_json',
                    'where' => [
                        'product_id' => $d['product_id'],
                        'factory_id' => $d['factory_id'],
                        'code' => $d['code'],
                        'register_time' => ['GT', 0],
                    ]
                ]);


                if (!empty($result)) {
                    $activeJson = json_decode($result['active_json'], true);
                    $result['warranty_time'] = $result['warranty_time'] + $activeJson['active_reward_moth'];
                    $warranty_time = get_limit_date($result['buy_time'], $result['warranty_time']);
                    $result['warranty_expire_time'] = (string)$warranty_time;
                    $d += $result;
                }
            }

            $d['user_phone'] = '';

            if ($type == 0) {

                //产品激活人
                $deal_info = BaseModel::getInstance('dealer_bind_products')->getOne([
                    'alias' => 'bp',
                    'field' => 'bp.id as id,u.id as user_id,u.telephone,u.nickname,u.real_name,d.name',
                    'where' => [
                        'bp.product_id' => $d['product_id'],
                        'bp.phone' => $d['phone'],
                        'bp.code' => $d['code']
                    ],
                    'join' => 'LEFT JOIN dealer_info as d on d.wx_user_id = bp.dealer_id
                           LEFT JOIN wx_user as u on u.id = d.wx_user_id
                ',
                ]);


                //购买人信息
                !empty($d['code']) && $user_data = (new \Api\Model\YimaModel())->getYimaInfoByCode($d['code']);

                if (!empty($deal_info)) {
                    $d['active_user_type'] = '1';
                    $d['user_phone'] = $deal_info['telephone'];
                    $d['user_name'] = $deal_info['name'];
                } else {
                    $d['active_user_type'] = '0';
                }

            } elseif ($type == 1) {

                //购买人信息
                $user_data = (new \Api\Model\YimaModel())->getYimaInfoByCode($d['code']);

                $d['user_name'] = $user_data['user_name'];
                $d['user_phone'] = $user_data['user_tel'];

            }

            // 售后次数
            $d['worker_order_nums'] = 0;

            if ($d['code']) {
                $about_worker_order = BaseModel::getInstance('worker_order')->getList([
                    'alias' => 'WO',
                    'where' => [
                        'WOP.yima_code' => $d['code'],
                    ],
                    'join' => 'LEFT JOIN worker_order_product WOP ON WO.id = WOP.worker_order_id',
                    'field' => 'WO.id order_id,WO.orno,WO.create_time datetime',
                    'group' => 'WO.id',
                ]);

                $d['worker_order_nums'] = count($about_worker_order);
            }

        }

        return $data;
    }

    //用户激活产品
    public function userProduct($uid)
    {
        $params = [
            'alias' => 'up',
            'field' => 'up.id,up.wx_user_id,up.wx_product_id as product_id,up.code,up.wx_factory_id as factory_id,f.factory_full_name as factory,fp.product_xinghao,pc.name as product_category ,ps.standard_name as product_guige ,pb.product_brand as product_brand,u.telephone as phone',

            'where' => ['up.wx_user_id' => $uid],
            'join' => 'LEFT JOIN factory as f on f.factory_id = up.wx_factory_id
                       LEFT JOIN wx_user as u on u.id = up.wx_user_id
                       LEFT JOIN factory_product as fp on fp.product_id = up.wx_product_id
                       LEFT JOIN product_category as pc on pc.id = fp.product_category
                       LEFT JOIN product_standard as ps on ps.standard_id = fp.product_guige
                       LEFT JOIN factory_product_brand as pb on pb.id = fp.product_brand
                      ',
            'order' => 'up.id DESC',
            'limit' => $this->page()
        ];

        $data = D('WxUserProduct')->getList($params);
        $count = D('WxUserProduct')->getNum($params);

        return (['data' => $data, 'count' => $count]);
    }

    //经销商激活产品
    public function dealerProduct($uid)
    {

        $params = [
            'alias' => 'bp',
            'field' => 'bp.id,bp.product_id, bp.dealer_id as wx_user_id,bp.code,bp.factory_id,f.factory_full_name as factory,fp.product_xinghao,pc.name as product_category ,ps.standard_name as product_guige ,pb.product_brand as product_brand,bp.phone as phone',
            'where' => ['bp.dealer_id' => $uid],
            'join' => 'LEFT JOIN factory as f on f.factory_id = bp.factory_id
                       LEFT JOIN factory_product as fp on fp.product_id = bp.product_id
                       LEFT JOIN product_category as pc on pc.id = fp.product_category
                       LEFT JOIN product_standard as ps on ps.standard_id = fp.product_guige
                       LEFT JOIN factory_product_brand as pb on pb.id = fp.product_brand
            ',
            'order' => 'bp.id DESC',
            'limit' => $this->page()
        ];

        $data = D('DealerBindProducts')->getList($params);
        $count = D('DealerBindProducts')->getNum($params);

        return (['data' => $data, 'count' => $count]);
    }

    //售后工单列表
    public function worker_orders()
    {
        $uid = I('get.id');
        $type = I('get.user_type');
        ($type === '') && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '用户身份不能为空');

        $user_phone = D('WxUser')->getFieldVal(['id' => $uid], 'telephone');

        //$origin_type = ($type == 1) ? 5 : 4;
        //$where = ['o.add_id' => $uid, 'o.origin_type' => $origin_type];

//        $where_order['ui.wx_user_id'] = $uid;
//        $where_order['ui.phone'] = $user_phone;
//        $where_order['_logic'] = 'or';
//        $map['_complex'] = $where_order;
//        $map['ui.wx_user_id']  = array('gt',0);
//        $map['ui.phone']  = array('gt',0);

        $join = 'INNER JOIN worker_order_user_info as ui on ui.worker_order_id = o.id';

        $params = [
            'alias' => 'o',
            'field' => 'o.add_id,o.origin_type,ui.wx_user_id,o.id,o.orno,o.worker_order_status as status,o.create_time,
                   o.factory_audit_time,o.worker_order_type,o.service_type,cancel_status',
            'where' => ['ui.wx_user_id' => $uid],
            //'where' => $map,
            'join' => $join,
            'order' => 'o.create_time DESC',
            'limit' => $this->page()
        ];


        $data = D('WorkerOrder')->getList($params);
        $count = D('WorkerOrder')->getNum($params);


        foreach ($data as &$v) {
            $others = [
                'alias' => 'op',
                'field' => '
                   op.product_id,
                   fp.product_xinghao,
                   op.cp_category_name as product_category ,
                   op.cp_product_standard_name as product_guige ,
                   op.cp_product_brand_name as product_brand,
                   op.factory_repair_fee_modify,
                   op.worker_repair_fee_modify
                   ',
                'where' => ['op.worker_order_id' => $v['id']],
                'join' => '
                    LEFT JOIN factory_product as fp on fp.product_id = op.product_id',
            ];

            $others_info = BaseModel::getInstance('worker_order_product')->getOne($others);

            $v += $others_info;

            $v['worker_order_status_name'] = OrderService::getStatusStr($v['status'], $v['cancel_status']);

        }

        return (['data' => $data, 'count' => $count]);

    }

    /**
     * 工单用户注册
     */
    public function workerUserRegister()
    {
        set_time_limit(0);
        //print_r(M()->getLastSql());exit;

        $total = 500000;  //一共有多少记录
        $batch = 2000; //一次处理多少记录
        $times = ceil($total / $batch);
        $stime = time();
        M()->startTrans();
        try {
            for ($i = 0; $i <= $times; $i++) {
                $regex = '^1(3[0-9]|4[57]|5[0-35-9]|7[0135678]|8[0-9])\\\d{8}$';
                $sql = 'select a.area_id, a.province_id, a.city_id, real_name, a.phone, cp_area_names from worker_order_user_info a ' .
                    'where worker_order_id = (select max(worker_order_id) from worker_order_user_info where phone=a.phone and phone REGEXP "' . $regex . '") ' .
                    'order by a.worker_order_id desc limit ' . ($i * $batch) . ',' . $batch;
                $result = BaseModel::getInstance('wx_user')->query($sql);

                for ($j = 0; $j < count($result); $j++) {
                    $check = M('WxUser')->field('id')->where(['telephone' => $result[$j]['phone']])->find();
                    if (empty($check)) {
                        $user[] = [
                            'telephone' => $result[$j]['phone'],
                            'real_name' => $result[$j]['real_name'],
                            'province_id' => $result[$j]['province_id'],
                            'city_id' => $result[$j]['city_id'],
                            'area_id' => $result[$j]['area_id'],
                            'area' => $result[$j]['cp_area_names'],
                            'area_ids' => $result[$j]['province_id'] . ',' . $result[$j]['city_id'] . ',' . $result[$j]['area_id'],
                            'add_time' => time()
                        ];
                    }
                }
                !empty($user) && BaseModel::getInstance('wx_user')->insertAll($user);

                unset($result);
                unset($user);

                sleep(2);
            }
            M()->commit();
        } catch (\Exception $e) {
            M()->rollback();
        }

        echo "已完成注册，所用时间：" . (time() - $stime) . 'S' . "\t\n";

    }

}