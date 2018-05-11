<?php
/**
 * @User fzy
 */
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Api\Repositories\Events\OrderCancelEvent;
use Common\Common\Service\AuthService;
use Library\Common\BaiDuLbsApi;
use Library\Crypt\AuthCode;
use Library\Common\Util;

class MastercodeLogic extends BaseLogic
{
    public function Export($worker_id)
    {
        $masterInfo = $worker_id? BaseModel::getInstance('worker')->getList([
            'where' => [
                'worker_id' => ['in', $worker_id],
            ],
            'field' => 'worker_id, worker_telephone, nickname',
            'order' => 'worker_id desc',
        ]) : [];

        $scandata = $worker_id? BaseModel::getInstance('worker_qr_scanning')->getList([
            'where' => [
                'worker_id' => ['in', $worker_id],
            ],
            'field' => 'worker_id, SUM(nums) as nums',
            'index' => 'worker_id',
            'group' => 'worker_id',
        ]) : [];
        foreach ($masterInfo as $k => $v){
            $v['scanData'] = $scandata[$v['worker_id']];
            $masterInfo[$k] = $v;
        }
        return $masterInfo;
    }
    public function MasterInfo($worker_id, $ip)
    {
        //技工信息
        $result['masterinfo'] = $worker_id? BaseModel::getInstance('worker')->getOne([
            'where' => [
                'worker_id' => $worker_id,
            ],
            'field' => 'worker_id, worker_telephone, nickname, thumb, worker_address, worker_detail_address',
        ]) : [];
        $result['masterinfo']['thumb_full'] = Util::getServerFileUrl($result['masterinfo']['thumb']);
        unset($result['masterinfo']['thumb']);
        !$result['masterinfo'] && $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
        $address = str_replace('-', '', $result['masterinfo']['worker_address']);
        $result['masterinfo']['address'] = $address.$result['masterinfo']['worker_detail_address'] ;

        //记录访问次数
        $ipdata = $ip? [
            'worker_id' => $worker_id,
            'ip' => $ip,
        ] : [];
        $ipInfo = BaseModel::getInstance('worker_qr_scanning')->getOne([
            'where' => $ipdata,
        ]);
        //判断客户是否已经访问过技工
        if (empty($ipInfo)){
            BaseModel::getInstance('worker_qr_scanning')->insert($ipdata);
        }else{
            BaseModel::getInstance('worker_qr_scanning')->setNumInc($ipdata, 'nums', 1);
        }

        //服务N位客户
        $datanum = BaseModel::getInstance('worker_order')->getList([
            'where' => [
                'worker_id' => $worker_id,
//                'is_complete' => 1,
//                'is_return' => 0,
            ],
            'field' => 'id,worker_id',
        ]);
        $result['masterinfo']['service_nums'] = count($datanum);

        //服务时效星级
        $sql       			 = "SELECT (sum(appiont_fraction)+sum(arrive_fraction) + sum(ontime_fraction) + sum(return_fraction) )/count(*)/4 as nums FROM  `worker_order_reputation` WHERE worker_id =$worker_id AND is_complete=1";
        $res 			 	 = M()->query($sql);
        $time_start= intval($res[0]['nums'],0);
        $result['masterinfo']['time_start'] = $time_start;

        //用户满意星级
        $sql       			 = "SELECT ( sum(sercode_fraction) + sum(revcode_fraction) )/count(*)/2 as nums FROM  `worker_order_reputation` WHERE worker_id =$worker_id AND is_complete=1";
        $res 			 	 = M()->query($sql);
        $satisfy_start= intval($res[0]['nums'],0);
        $result['masterinfo']['satisfy_start'] = $satisfy_start;

        //服务规范星级
        $sql       			 = "SELECT ( sum(quality_standard_fraction)  )/count(*)/3 as nums FROM  `worker_order_reputation` WHERE worker_id =$worker_id AND is_complete=1";
        $res 			 	 = M()->query($sql);
        $standard_start= intval($res[0]['nums'],0);
        $result['masterinfo']['standard_start'] = $standard_start;

        //服务质量星级
        $sql       			 = "SELECT ( sum(repair_nums_fraction)  ) as nums FROM  `worker_order_reputation` WHERE worker_id =$worker_id AND is_complete=1";
        $res 			 	 = M()->query($sql);
        $quality = $res[0]['nums'];

        $sql       			 = "SELECT ( count(*)*30   ) as nums FROM  `worker_order_quality` WHERE worker_id =$worker_id AND is_detect=1";
        $res 			 	 = M()->query($sql);
        $detect  = $res[0]['nums'];

        $sql       			 = "SELECT ( count(*)*30   ) as nums FROM  `worker_order_quality` WHERE worker_id =$worker_id AND is_fault=1";
        $res 			 	 = M()->query($sql);
        $fault   = $res[0]['nums'];

        $sql       			 = "SELECT ( count(*)  ) as nums FROM  `worker_order_reputation` WHERE worker_id =$worker_id AND is_complete=1";
        $res 			 	 = M()->query($sql);
        $qualiAll = $res[0]['nums'];

        $quality_start = intval(($quality - $detect - $fault)/$qualiAll,0);
        $result['masterinfo']['quality_start'] = $quality_start;

        //服务
        $datainfo = BaseModel::getInstance('worker_coop_busine')->getOne([
            'where' => [
                'worker_id' => $worker_id,
            ],
            'field' => 'id, worker_id, first_distribute_pros, home_service_area',
        ]);

        //服务产品
        $product = $datainfo? BaseModel::getInstance('cm_list_item')->getList([
            'where' => [
                'list_item_id' => ['in', $datainfo['first_distribute_pros']],
                'list_id' => 12,
            ],
            'field' => 'list_item_id, item_desc',
        ]) : [];
        $result['masterinfo']['product'] = $product;


        //服务区域
        $region = $datainfo? BaseModel::getInstance('cm_list_item')->getList([
            'where' => [
                'list_item_id' =>  ['in', $datainfo['home_service_area']],
                'list_id' => 13,
            ],
            'field' => 'list_item_id, item_desc, item_parent',
        ]) : [];

        foreach ($region as $k => $v){
            $province = strpos($v['item_desc'], '省');
            $city     = strpos($v['item_desc'], '市');
            $pos      = strpos($v['item_desc'], '区');

            if (!$pos && !$city && !$province){
                //街道
                $data = BaseModel::getInstance('cm_list_item')->getOne([
                    'where' => [
                        'list_item_id' =>  $v['item_parent'],
                        'list_id' => 13,
                    ],
                    'field' => 'list_item_id as area_id, item_desc as area_name',
                ]);

                $v['area'] = $data;
                unset($v['item_parent']);
                $server_area[] = $v;
            }

            if($pos){
                //区
                unset($v['item_parent']);
                $server_area[] = $v;

            }

        }

        $result['masterinfo']['server_area'] = $server_area;
        $res = $result['masterinfo'];
        unset($res['worker_address']);
        unset($res['worker_detail_address']);

        return $res;
    }

}
