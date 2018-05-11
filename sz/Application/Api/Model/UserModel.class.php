<?php
/**
* @User zjz
* @Date 2016/12/09
*/
namespace Api\Model;

use Api\Model\BaseModel;
use Api\Common\ErrorCode;
use Common\Common\Service\AuthService;

class UserModel extends BaseModel
{



    protected $trueTableName = 'wx_user';

    public function getMyProductContactHistoriesById($id = 0, $type = 0, $user = [])
    {
    	$return = [];
    	$user = ($user['id'] && isset($user['user_type'])) ? $user : $this->getOneOrFail($id);
        
    	if ($user['user_type'] == 1) {
    		$return = $this->getDealerInfoById($id, 'area_ids');
    	} else {
    		$infos = BaseModel::getInstance('wx_user_product')->getOne([
				'where' => ['wx_user_id' => $id],
				'order' => 'id DESC',
			]);

            if ($infos['code']) {
                $data = (new \Api\Model\YimaModel())->getYimaInfoByCode($infos['code']);
            } elseif ($infos['md5code']) {
                $data = D('FactoryExcel')->getExcelDataByMyPidOrFail($infos['md5code']);
            }
            // die(M()->_sql());
            $return =  [
                'name' => $data['user_name'],
                'phone' => $data['user_tel'],
                'user_address' => $data['user_address'],
                'register_time' => $data['register_time'],
            ];

            $return['phone'] = $user['telephone'];
    		$address = json_decode($return['user_address'], true);
    		unset($return['user_address']);
    		if (is_array($address)) {
    			$return['area_ids'] = $address['ids'];
    			$return['area_desc'] = $address['address'];
    		}
    	}
    	return $return;
    }

    public function getMyOrderContactHistoriesById($id = 0, $type = 0, $user = [])
    {
    	$return = [];
    	$user = $this->getOneOrFail($id);
    	if (!$user['user_type']) {

    		$where = [
    			'WUO.wx_user_id' => $id,
    		];

    		$opt = [
    			'alias' => 'WUO',
    			'where' => $where,
    			'join'  => 'LEFT JOIN worker_order WO ON WUO.order_id = WO.order_id',
    			'field' => 'WO.full_name as name,WO.tell as phone,WO.area_full as area_ids,WO.address as area_desc',
    			'order' => 'WUO.id DESC',
    		];
    		$return = BaseModel::getInstance('wx_user_order')->getOne($opt);

            if (!$return) {
                return $this->getMyProductContactHistoriesById($id, $type, $user);
            } else {
                $return['phone'] = $user['telephone'];
            }


    	}
    	return $return;
    }

    public function getOrderAndRegisterLastAreasById($id = 0)
    {
        $return = [];
        $user = $this->getOneOrFail($id);

        // 普通用户
        switch ($user['user_type']) {
            case 0:
                $check = [];
                $order = BaseModel::getInstance('worker_order_user_info')->getOne([
                    'where' => [
                        'wx_user_id' => $id,
                    ],
                    'join' => 'INNER JOIN worker_order ON worker_order.id=worker_order_user_info.worker_order_id',
                    'field' => 'real_name name,phone,province_id,city_id,area_id,cp_area_names area_ids_desc,address area_desc,create_time datetime',
                    'order' => 'worker_order_id DESC',
                ]);
                $order['area_ids'] = "{$order['province_id']},{$order['city_id']},{$order['area_id']}";
                unset($order['province_id'], $order['city_id'], $order['area_id']);
                str_replace('-', ',', $order['area_ids_desc']);
                $pro = $this->getMyProductContactHistoriesById($id);
                $order['datetime'] && $check[$order['datetime']] = $order;
                $pro['register_time'] && $check[$pro['register_time']] = $pro;
                krsort($check);
                array_filter($check) && $return = reset($check);
                unset($return['datetime'], $return['register_time']);
                break;

            case 1:
                $return = $this->getDealerInfoById($id, 'area_ids');
                break;
        }

        // var_dump($return);die;

        return (array)$return;
    }

    public function getDealerInfoById($id = 0, $field = '*')
    {
    	$where = [
    		'wx_user_id' => $id,
    	];
    	$opt = [
    		'where' => $where,
    		'order' => 'id DESC',
    		'field' => $field,
    	];
    	$data = BaseModel::getInstance('dealer_info')->getOne($opt);
    	return $data;
    }



}
