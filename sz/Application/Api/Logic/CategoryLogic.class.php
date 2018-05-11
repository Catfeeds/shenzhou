<?php
/**
* 
*/
namespace Api\Logic;

use Api\Common\ErrorCode;
use Library\Common\Util;

class CategoryLogic extends BaseLogic
{

    /**
     * @param $parent_id
     * @param $type 申请类型 1-维修 2-安装
     * @return array
     */
    public function getCategory($parent_id, $type)
    {
        if ($parent_id == 0) {
            $hostUrl =  Util::getServerFileUrl('/Public/images/img_category/');
            if ($type==1) {
                $cates = [
                    ['id' => '659004514', 'parent_id' => '0', 'name' => '电视机', 'thumb'=>$hostUrl.'ico22_tv.png'],
                    ['id' => '659005490', 'parent_id' => '0', 'name' => '家用空调', 'thumb'=>$hostUrl.'ico22_airco.png'],
                    ['id' => '25', 'parent_id' => '0', 'name' => '吸油烟机', 'thumb'=>$hostUrl.'ico22_ventilator.png'],
                    ['id' => '659004533', 'parent_id' => '0', 'name' => '洗衣机', 'thumb'=>$hostUrl.'ico22_washer.png'],
                    ['id' => '30', 'parent_id' => '0', 'name' => '灶具', 'thumb'=>$hostUrl.'ico22_stove.png'],
                    ['id' => '659004686', 'parent_id' => '0', 'name' => '消毒柜', 'thumb'=>$hostUrl.'ico22_disinfection.png'],
                    ['id' => '659004688', 'parent_id' => '0', 'name' => '集成灶', 'thumb'=>$hostUrl.'ico22_integrated.png'],
                    ['id' => '2', 'parent_id' => '0', 'name' => '热水器', 'thumb'=>$hostUrl.'ico22_hotwater.png'],
                    ['id' => '659004690', 'parent_id' => '0', 'name' => '家用净水器', 'thumb'=>$hostUrl.'ico22_purifier.png'],
                    //['id' => '659004711', 'parent_id' => '0', 'name' => '洗碗机', 'thumb'=>$hostUrl.'ico22_dishwasher.png'],
                    ['id' => '659005055', 'parent_id' => '0', 'name' => '跑步机', 'thumb'=>$hostUrl.'ico22_treadmill.png'],
                    //['id' => '659005345', 'parent_id' => '0', 'name' => '智能马桶、马桶盖', 'thumb'=>$hostUrl.'ico22_closestool.png'],
                    ['id' => '659005626', 'parent_id' => '0', 'name' => '家用冰箱冰柜', 'thumb'=>$hostUrl.'ico22_fridge.png']
                ];
            } elseif ($type==2) {
                $cates = [
                    ['id' => '659004514', 'parent_id' => '0', 'name' => '电视机', 'thumb'=>$hostUrl.'ico22_tv.png'],
                    ['id' => '659005490', 'parent_id' => '0', 'name' => '家用空调', 'thumb'=>$hostUrl.'ico22_airco.png'],
                    ['id' => '25', 'parent_id' => '0', 'name' => '吸油烟机', 'thumb'=>$hostUrl.'ico22_ventilator.png'],
                    ['id' => '659004533', 'parent_id' => '0', 'name' => '洗衣机', 'thumb'=>$hostUrl.'ico22_washer.png'],
                    ['id' => '30', 'parent_id' => '0', 'name' => '灶具', 'thumb'=>$hostUrl.'ico22_stove.png'],
                    ['id' => '659004686', 'parent_id' => '0', 'name' => '消毒柜', 'thumb'=>$hostUrl.'ico22_disinfection.png'],
                    ['id' => '659004688', 'parent_id' => '0', 'name' => '集成灶', 'thumb'=>$hostUrl.'ico22_integrated.png'],
                    ['id' => '2', 'parent_id' => '0', 'name' => '热水器', 'thumb'=>$hostUrl.'ico22_hotwater.png'],
                    ['id' => '659004690', 'parent_id' => '0', 'name' => '家用净水器', 'thumb'=>$hostUrl.'ico22_purifier.png'],
                    ['id' => '659004711', 'parent_id' => '0', 'name' => '洗碗机', 'thumb'=>$hostUrl.'ico22_dishwasher.png'],
                    ['id' => '659005055', 'parent_id' => '0', 'name' => '跑步机', 'thumb'=>$hostUrl.'ico22_treadmill.png'],
                    ['id' => '659005345', 'parent_id' => '0', 'name' => '智能马桶', 'thumb'=>$hostUrl.'ico22_closestool.png'],
                    ['id' => '659005626', 'parent_id' => '0', 'name' => '家用冰箱冰柜', 'thumb'=>$hostUrl.'ico22_fridge.png']
                ];

            }
        } else {
            $ids = $this->childCategory($parent_id);
            if (!empty($ids)) {
                $param = [
                    'field' => 'id, parent_id, name, thumb',
                    'where' => ['id' => ['in', $ids]]
                ];
                $cates = D('ProductCategory')->getList($param);
                foreach ($cates as &$val) {
                    $val['thumb'] = Util::getServerFileUrl($val['thumb']);
                }
            }
        }

        return $cates;
    }

    public function standards()
    {
        $cate_id = I('get.id');
        $type = I('get.type', 0);

        empty($type) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '类型不能为空，请确定是维修还是安装');

        $fault_type = $type==1 ? 0 : 1;

        $condition = array();
        $condition['product_id'] = $cate_id;

        //去产品分类下的所有规格列表
        $list =  M('product_standard')->where($condition)->order('standard_sort asc , standard_id desc')->select();
//        print_r($list);exit;

        //取到产品分类下的 所有关联的服务项
        $link = D('ProductMiscellaneous')->getOne(['product_id'=>$cate_id]);

        $service_items_info_condition  = array();
        $service_items_info_condition['a.id']  = array('in' , $link['product_faults']);

        //尝试获取所有服务项目
        foreach($list as $key=>$val)
        {
            $service_items_info_condition['b.standard_id'] = ['eq', $val['standard_id']];
            $service_items_info_condition['a.fault_type'] = ['eq', $fault_type];
            //获取关联的服务项信息 列表
            $service_items_list[$val['standard_id']] = [
                'id'=> $val['standard_id'],
                'name'=> $val['standard_name'],
                'category_id'=> $val['product_id']
            ];
            $service_items_list[$val['standard_id']]['faults'] = M('product_fault')
                ->alias('a')
                ->field('b.fault_id as id, a.fault_name as name, b.worker_out_price')
                ->join('LEFT JOIN product_fault_price b ON a.id=b.fault_id')
                ->where($service_items_info_condition)
                ->order('a.sort ASC,a.id ASC')->select();

        }

        return array_values($service_items_list);
//        print_r($service_items_list);exit;

    }


    public function standards_test()
    {
        $list_item_id = empty($_REQUEST['id']) ?  0 : $_REQUEST['id'];
        $itemInfo = M('cm_list_item')->where('list_item_id='.$list_item_id)->find();

        $condition = array();
        $condition['product_id'] = $list_item_id;

        //去产品分类下的所有规格列表
        $list =  M('product_standard')->where($condition)->order('standard_sort asc , standard_id desc')->select();

        //尝试获取所有服务项目
        foreach($list as $key=>$val)
        {
            //取到产品分类下的 所有关联的服务项
            $link = M('product_miscellaneous')->where('product_id='.$val['product_id'])->find();

            $service_items_info_condition  = array();
            $service_items_info_condition['id']  = array('in' , $link['product_faults']);
            //获取关联的服务项信息 列表
            $service_items_list = M('product_fault')->where($service_items_info_condition)->order('sort ASC,id ASC')->select();


            $one_standard_service_price_info = array();
            foreach($service_items_list as  $key2 =>$one_service_item){

                $default_service_price = array();
                $default_service_price['fault_id']         = $one_service_item['id'];
                $default_service_price['standard_id']      = $val['standard_id'];
                $default_service_price['fault_name']       = $one_service_item['fault_name'];
                $default_service_price['factory_in_price'] = '0.00';
                $default_service_price['factory_out_price']= '0.00';
                $default_service_price['worker_in_price']  = '0.00';
                $default_service_price['worker_out_price'] = '0.00';
                $default_service_price['worker_out_price'] = '0.00';
                $default_service_price['weihu_time']       = '0';
                $default_service_price['fault_type']       = $one_service_item['fault_type'];
                $default_service_price['sign']             = 'add';


                //查找 这个服务项  是否在 价格表中有信息  有则用这个记录  无则用默认的价格
                $price_condition = array();
                $price_condition['fault_id']     = $one_service_item['id'];
                $price_condition['standard_id']  = $val['standard_id'];
                $price_condition['product_id']   = $list_item_id;
                $one_price = M('product_fault_price')->where($price_condition)->find();
                //AAA($price_condition);
                if(!empty($one_price)){
                    $one_price['fault_name'] = $one_service_item['fault_name'];
                    $one_price['fault_type'] = $one_service_item['fault_type'];
                    $one_price['sign']       = 'edit';

                    $one_standard_service_price_info[]= $one_price;
                }else{
                    $one_standard_service_price_info[]= $default_service_price;
                }
            }

            $list[$key]['all_check']='true';
            $list[$key]['service_price_info'] = $one_standard_service_price_info;
            $list[$key]['fault_ids_arr'] = empty($val['fault_ids'] ) ? array() :explode(',',$val['fault_ids']) ;

        }

        $result 			=	array();
        $result['status'] 	= 	0;
        $result['list'] 	= $list;
        $result['itemInfo'] = $itemInfo;

        if( count($list)>0 ){
            $result['status'] = 1;
        }else{
            $result['status'] = 0;
        }

        return $result;
    }

    public function childCategory($parentId)
    {

        $childs =  [
            659004514=> [659004515],
            659005490=> [659005491,659005604,659005492,659005605,659005497,659005498],
            25=> [26,27,28,29],
            659004533=> [659005532,659004534,659004535, 659004536,659004537,659004538],
            30=> [659004645,659004644,659004519,659004671],
            659004686 =>[659004687],
            659004688=>[659004689],
            2=>[3,4,5,10,6,659005826,659005091],
            659004690=>[659004696,659004697,659004698,659004699,659004700],
            659004711=>[659004712],
            659005055=>[659005119],
            659005345=>[659005346,659005347],
            659005626=>[659005627]
        ];
        return $childs[$parentId];

    }
}
