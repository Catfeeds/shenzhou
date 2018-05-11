<?php
/**
 * File: WorkbenchConfigLogic.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/12
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\WorkbenchCacheModel;

class WorkbenchConfigLogic extends BaseLogic
{

    public function getList()
    {
        return WorkbenchCacheModel::getAll();
    }

    public function edit($param)
    {
        $field = [
            'exceed_admin_check',
            'exceed_admin_distribute',
            'exceed_worker_appoint',
            'exceed_worker_visit',
            'exceed_admin_check_accessory',
            'exceed_factory_check_accessory',
            'exceed_factory_send_accessory',
            'exceed_worker_send_back_accessory',
            'exceed_admin_check_cost',
            'exceed_factory_check_cost',
            'exceed_last_update_time',
            'exceed_admin_return',
            'exceed_admin_auditor',
        ];

        $param = array_filter($param);

        $keys = array_keys($param);

        $update_fields = array_intersect($field, $keys);

        $update = [];

        foreach ($update_fields as $update_field) {
            if (empty($param[$update_field])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }

            if ($param[$update_field] <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }

            $update[$update_field] = $param[$update_field];
        }

        if (!empty($update)) {

            $model = BaseModel::getInstance('worker_order_workbench_config');

            $where = [
                'name' => ['in', $update_fields]
            ];
            $list = $model->getList($where, 'id,name');

            $sql = 'INSERT INTO worker_order_workbench_config (id,val) VALUES %s ON DUPLICATE KEY UPDATE val=VALUES(val);';
            $data = '';
            foreach ($list as $val) {
                $id = $val['id'];
                $name = $val['name'];

                $data .= sprintf('(%s,%s),', $id, $update[$name]);
            }

            $data = trim($data, ',');
            $sql = sprintf($sql, $data);

            $model->execute($sql);

            WorkbenchCacheModel::addCacheAll();

        }

    }


}