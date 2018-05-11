<?php
/**
 * File: UserController.class.php
 * User: chenjunyu
 * Date: 2017/12/22
 */

namespace Script\Controller;

use Common\Common\Service\AreaService;
use Script\Model\BaseModel;

class UserController extends \Script\Controller\BaseController
{
    public function setUserAddressForlastWorkerOrder()
    {
        try {
            $u_model = BaseModel::getInstance('wx_user');
            $sql = 'select SUBSTRING_INDEX(group_concat(worker_order_id order by worker_order_id desc), \',\', 1) as worker_order_id from worker_order_user_info where province_id != 0 and city_id != 0 and area_id != 0  and phone in (select telephone from wx_user where province_id = 0 and  city_id = 0 and  area_id = 0 and telephone != 0 and telephone != \'\' group by telephone) group by phone;';
            $o_list = M()->query($sql);
            $order_ids = arrFieldForStr($o_list, 'worker_order_id');
            $u_list = $order_ids ? M()->query('select phone,area_id,city_id,province_id,address from worker_order_user_info where worker_order_id in ('.$order_ids.');') : [];
//            $this->responseList($u_list);
//            die;
            $area_ids = [];
            foreach ($u_list as $v) {
                $area_ids[$v['province_id']] = $v['province_id'];
                $area_ids[$v['city_id']] = $v['city_id'];
                $area_ids[$v['area_id']] = $v['area_id'];
            }

            $area_ids = array_unique(array_filter($area_ids));
            $areas = AreaService::getAreaNameMapByIds(implode(',', $area_ids));
            M()->startTrans();
            foreach ($u_list as $v) {
                $cp_name_arr = [
                    $areas[$v['province_id']]['name'],
                    $areas[$v['city_id']]['name'],
                    $areas[$v['area_id']]['name'],
                ];
                $cp_ids = "{$v['province_id']},{$v['city_id']},{$v['area_id']}";
                $cp_names = array_filter($cp_name_arr) ? implode('-', $cp_name_arr) : '';
                $where = [
                    'telephone' => $v['phone'],
                ];
                $update = [
                    'area_ids' => $cp_ids,
                    'area_id' => $v['area_id'],
                    'city_id' => $v['city_id'],
                    'province_id' => $v['province_id'],
                    'area' => $v['address'],
//                    'cp_area_names' => $cp_names,
                ];
//                var_dump($where, $update);die;
                $u_model->update($where, $update);
            }
            M()->commit();

            $this->responseList($u_list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function userArea()
    {
        $time1 = time();

        $params = [
            'field' => 'id,area_ids,province_id,city_id,area_id',
            'where' => [
                'area_ids' => [['neq', ''], ['neq', ',,']],
                'province_id|city_id|area_id' => ['exp', ' is null'],
            ],
        ];

        $userCount = D('User')->getNum($params);

        $num = ceil($userCount / 100);

        for ($i = 0; $i < $num; $i++) {

            $params ['limit'] = 100;
            $user = D('User')->getList($params);

            foreach ($user as &$v) {
                $area_ids = explode(',', $v['area_ids']);

                $area['province_id'] = $area_ids[0];
                $area['city_id'] = $area_ids[1];
                $area['area_id'] = $area_ids[2];

                D('User')->update($v['id'], $area);
            }
        }
        $time2 = time();
        echo "allCount:" . $userCount;
        echo "\nspendTime:" . ($time2 - $time1);
        die;
    }


    public function dealerInfoArea()
    {
        $time1 = time();

        $params = [
            'field' => 'id,area_ids,province_id,city_id,area_id',
            'where' => [
                'area_ids' => [['neq', ''], ['neq', ',,'] , ['exp', ' is not null']],
                'province_id|city_id|area_id' => ['exp', ' = 0 '],
            ],
        ];

        $dealerInfoCount = D('DealerInfo')->getNum($params);

        $num = ceil($dealerInfoCount / 100);

        for ($i = 0; $i < $num; $i++) {

            $params ['limit'] = 100;
            $dealerInfo = D('DealerInfo')->getList($params);

            foreach ($dealerInfo as &$v) {
                $area_ids = explode(',', $v['area_ids']);

                $area['province_id'] = $area_ids[0];
                $area['city_id'] = $area_ids[1];
                $area['area_id'] = $area_ids[2];

                D('DealerInfo')->update($v['id'], $area);
            }
        }

        $time2 = time();
        echo "allCount:" . $dealerInfoCount;
        echo '<br/>';
        echo "\nspendTime:" . ($time2 - $time1);
        die;
    }
}
