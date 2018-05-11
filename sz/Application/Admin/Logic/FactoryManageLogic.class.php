<?php
/**
 * File: FactoryManageLogic.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/30
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\ProductFaultService;
use Library\Common\Util;

class FactoryManageLogic extends BaseLogic
{

    public function getFactoryCategory($param)
    {
        //获取参数
        $factory_id = $param['factory_id'];

        //检查参数
        if ($factory_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取厂家分类id
        $factory_model = BaseModel::getInstance('factory');
        $field = 'factory_category,service_charge';
        $factory_info = $factory_model->getOneOrFail($factory_id, $field);
        $factory_category = $factory_info['factory_category'];
        $service_charge = $factory_info['service_charge'];

        $category_list = [];
        if (!empty($factory_category)) {
            //获取分类名称和id
            $where = [
                'list_item_id' => ['in', $factory_category],
                'list_id'      => 12,
            ];
            $opts = [
                'field' => 'list_item_id as id,item_desc as name',
                'where' => $where,
                'order' => 'list_item_id',
            ];
            $cm_model = BaseModel::getInstance('cm_list_item');
            $list = $cm_model->getList($opts);
            $category_list = empty($list) ? [] : $list;
        }

        //获取服务费
        $service_cost_model = BaseModel::getInstance('factory_category_service_cost');
        foreach ($category_list as $key => $val) {
            $cate_id = $val['id'];
            $where = [
                'cat_id'     => $cate_id,
                'factory_id' => $factory_id,
            ];
            $cost = $service_cost_model->getFieldVal($where, 'cost');
            $val['service_cost'] = $cost ? $cost : $service_charge;

            $category_list[$key] = $val;
        }

        return $category_list;
    }

    public function getStandard($param)
    {
        $category_id = $param['category_id'];

        if ($category_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $model = BaseModel::getInstance('product_standard');

        $opts = [
            'field' => 'standard_id,standard_name',
            'where' => [
                'product_id' => $category_id,
            ],
            'order' => 'standard_sort',
        ];

        return $model->getList($opts);

    }

    public function getFaultFeeList($param)
    {
        $factory_id = $param['factory_id'];
        $category_id = $param['category_id']; // 二级品类id
        $standard_id = $param['standard_id']; // 规格
        $fault_type = $param['fault_type']; // 服务项

        //检查参数
        if ($factory_id <= 0 || $category_id <= 0 || $standard_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (!in_array($fault_type, ProductFaultService::FAULT_TYPE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '服务项错误');
        }

        //获取厂家关联品类
        $miscellaneous_model = BaseModel::getInstance('product_miscellaneous');
        $where = ['product_id' => $category_id,];
        $product_fault_ids = $miscellaneous_model->getFieldVal($where, 'product_faults');

        //获取品类对应的维修项
        $product_fault_ids = Util::filterIdList($product_fault_ids);
        $product_fault = empty($product_fault_ids) ? '-1' : $product_fault_ids;
        $fault_order = empty($product_fault_ids) ? null : "field(id," . implode(',', $product_fault_ids) . ")";
        $fault_model = BaseModel::getInstance('product_fault');
        $where = [
            'id'         => ['in', $product_fault],
            'fault_type' => $fault_type,
        ];
        $opts = [
            'field' => 'id as fault_id,fault_name',
            'where' => $where,
            'order' => $fault_order,
        ];
        $faults = $fault_model->getList($opts);

        $fault_ids = array_column($faults, 'fault_id');
        $fault_order = null;
        if (empty($fault_ids)) {
            $fault_ids = '-1';
        } else {
            $fault_order = "field(fault_id," . implode(',', $fault_ids) . ")";
        }

        //厂家维修项费用
        $factory_fault_model = BaseModel::getInstance('factory_product_fault_price');
        $opts = [
            'field' => 'fault_id,factory_in_price,factory_out_price',
            'where' => [
                'factory_id'  => $factory_id,
                'standard_id' => $standard_id,
                'fault_id'    => ['in', $fault_ids],
            ],
//            'order' => 'id',
//            'group' => 'fault_id',
            'order' => $fault_order,
            'index' => 'fault_id',
        ];
        $factory_fault = $factory_fault_model->getList($opts);


        //平台维修项费用
        $admin_fault_model = BaseModel::getInstance('product_fault_price');
        $opts = [
            'field' => 'fault_id,factory_in_price,factory_out_price',
            'where' => [
                'standard_id' => $standard_id,
                'fault_id'    => ['in', $fault_ids],
            ],
//            'order' => 'id',
            'order' => $fault_order,
            'group' => 'fault_id',
            'index' => 'fault_id',
        ];
        $admin_fault = $admin_fault_model->getList($opts);

        //根据设好的排序输出
        foreach ($faults as $key => $fault) {
            $fault_id = $fault['fault_id'];

            //优先获取厂家设置的价目
            if (array_key_exists($fault_id, $factory_fault)) {
                $fault['factory_in_price'] = $factory_fault[$fault_id]['factory_in_price'];
                $fault['factory_out_price'] = $factory_fault[$fault_id]['factory_out_price'];
            } elseif (array_key_exists($fault_id, $admin_fault)) {
                //其次获取平台设置
                $fault['factory_in_price'] = $admin_fault[$fault_id]['factory_in_price'];
                $fault['factory_out_price'] = $admin_fault[$fault_id]['factory_out_price'];
            } else {
                //默认价格
                $fault['factory_in_price'] = C('FACTORY_DEFAULT_FAULT_IN_PRICE');
                $fault['factory_out_price'] = C('FACTORY_DEFAULT_FAULT_OUT_PRICE');
            }

            $faults[$key] = $fault;
        }

        return $faults;
    }

    public function editFaultFee($param)
    {
        $edit_fault_fees = $param['fault_fee_list']; // 提交的新价目表
        $factory_id = $param['factory_id']; // 厂家id
        $category_id = $param['category_id']; // 二级品类id
        $standard_id = $param['standard_id']; // 规格id
        $fault_type = $param['fault_type'];
        $service_cost = $param['service_cost']; // 服务费

        //检查参数
        if (
            empty($edit_fault_fees) ||
            $factory_id <= 0 ||
            $category_id <= 0 ||
            $standard_id <= 0 ||
            $service_cost < 0
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (!in_array($fault_type, ProductFaultService::FAULT_TYPE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '服务类型错误');
        }
        foreach ($edit_fault_fees as $val) {
            $fault_id = $val['fault_id'];
            $in_price = $val['factory_in_price'];
            $out_price = $val['factory_out_price'];

            if ($in_price < 0 || $out_price < 0 || $fault_id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
        }

        //重构提交结构
        $edit_fault_data = [];
        foreach ($edit_fault_fees as $edit_fault_fee) {
            $fault_id = $edit_fault_fee['fault_id'];
            $edit_fault_data[$fault_id] = $edit_fault_fee;
        }
        unset($edit_fault_fees);

        //获取关联的品类
        $miscellaneous_model = BaseModel::getInstance('product_miscellaneous');
        $where = ['product_id' => $category_id,];
        $product_fault_ids = $miscellaneous_model->getFieldVal($where, 'product_faults');

        //获取维修项
        $product_fault_ids = empty($product_fault_ids) ? '-1' : $product_fault_ids;
        $fault_model = BaseModel::getInstance('product_fault');
        $where = [
            'id'         => ['in', $product_fault_ids],
            'fault_type' => $fault_type,
        ];
        $fault_ids = $fault_model->getFieldVal($where, 'id', true); // 维修项id列表
        $fault_ids = empty($fault_ids) ? '-1' : $fault_ids;

        //获取厂家设置的价目表id列表
        $factory_fault_model = BaseModel::getInstance('factory_product_fault_price');
        $opts = [
            'where' => [
                'factory_id'  => $factory_id,
                'standard_id' => $standard_id,
                'fault_id'    => ['in', $fault_ids],
            ],
            'group' => 'fault_id',
        ];
        $factory_fault_ids = $factory_fault_model->getFieldVal($opts, 'fault_id,id', true);
        $factory_fault_ids = empty($factory_fault_ids) ? [] : $factory_fault_ids;

        $insert_data = [];
        $update_data = [];
        foreach ($fault_ids as $fault_id) {
            if (!array_key_exists($fault_id, $edit_fault_data)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '价目表金额为空');
            }

            if (array_key_exists($fault_id, $factory_fault_ids)) {
                $update_data[] = [
                    'id'                => $factory_fault_ids[$fault_id],
                    'factory_in_price'  => $edit_fault_data[$fault_id]['factory_in_price'],
                    'factory_out_price' => $edit_fault_data[$fault_id]['factory_out_price'],
                ];
            } else {
                $insert_data[] = [
                    'factory_id'        => $factory_id,
                    'product_id'        => $category_id,
                    'fault_id'          => $fault_id,
                    'standard_id'       => $standard_id,
                    'factory_in_price'  => $edit_fault_data[$fault_id]['factory_in_price'],
                    'factory_out_price' => $edit_fault_data[$fault_id]['factory_out_price'],
                    'is_expand'         => 1, // 用途不明
                ];
            }
        }

        if (!empty($insert_data)) {
            $factory_fault_model->insertAll($insert_data);
        }

        if (!empty($update_data)) {
            $sql = 'insert into factory_product_fault_price(id,factory_in_price,factory_out_price) values %s on duplicate key update factory_in_price=values(factory_in_price),factory_out_price=values(factory_out_price);';
            $data_str = '';
            foreach ($update_data as $update) {
                $data_str .= "({$update['id']},{$update['factory_in_price']},{$update['factory_out_price']}),";
            }
            $data_str = trim($data_str, ',');
            $sql = sprintf($sql, $data_str);
            M()->execute($sql);
        }

        //修改服务费
        $service_cost_model = BaseModel::getInstance('factory_category_service_cost');
        $where = ['factory_id' => $factory_id, 'cat_id' => $category_id];
        $field = 'id';
        $service_info = $service_cost_model->getOne($where, $field);
        if (empty($service_info)) {
            $insert_data = [
                'factory_id' => $factory_id,
                'cat_id'     => $category_id,
                'cost'       => $service_cost,
            ];
            $service_cost_model->insert($insert_data);
        } else {
            $id = $service_info['id'];
            $update_data = [
                'cost' => $service_cost,
            ];
            $service_cost_model->update($id, $update_data);
        }
    }

    public function resetFaultFee($param)
    {
        $factory_id = $param['factory_id'];

        if ($factory_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $factory_fault_model = BaseModel::getInstance('factory_product_fault_price');
        $where = [
            'factory_id' => $factory_id,
        ];
        $field = 'id';
        $fault = $factory_fault_model->getFieldVal($where, $field, true);

        if (!empty($fault)) {
            $where = ['id' => ['in', $fault]];
            $factory_fault_model->remove($where);
        }
    }


}