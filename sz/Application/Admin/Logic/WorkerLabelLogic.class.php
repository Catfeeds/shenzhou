<?php
/**
 * File: WorkerLabelLogic.class.php
 * User: sakura
 * Date: 2017/11/20
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AuthService;
use Library\Common\Util;

class WorkerLabelLogic extends BaseLogic
{

    protected $tableName = 'worker_label';

    public function getList($param)
    {
        //获取参数
        $keyword = $param['keyword'];

        //获取全部标签
        $where = ['list_id' => 49];
        if (!empty($keyword)) {
            $where['item_desc'] = ['like', '%' . $keyword . '%'];
        }
        $model = BaseModel::getInstance('cm_list_item');
        $field = 'list_item_id as label_id, item_desc as label_name';
        $data = $model->getList($where, $field);

        return $data;
    }

    public function getHistoryLabel($param)
    {
        $worker_id = $param['worker_id'];

        if (empty($worker_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        BaseModel::getInstance('worker')
            ->getOneOrFail($worker_id, 'worker_id');

        $opts = [
            'field' => 'label_id,name,count(*) as cnt',
            'where' => ['worker_id' => $worker_id],
            'group' => 'label_id',
            'order' => 'id',
        ];
        $model = BaseModel::getInstance($this->tableName);

        return $model->getList($opts);
    }

    public function getAdminHistoryLabel($param)
    {
        //获取参数
        $worker_id = $param['worker_id'];

        //检查参数
        if (empty($worker_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取
        BaseModel::getInstance('worker')
            ->getOneOrFail($worker_id, 'worker_id');

        //获取客服
        $admin = AuthService::getAuthModel();
        $user_id = $admin['id'];

        //获取标签
        $opts = [
            'field' => 'label_id,name',
            'where' => ['worker_id' => $worker_id, 'admin_id' => $user_id],
            'order' => 'id',
        ];
        $model = BaseModel::getInstance($this->tableName);

        return $model->getList($opts);
    }

    public function label($param)
    {
        //获取参数
        $label_ids = $param['label_ids'];
        $label_ids = array_filter($label_ids);

        $worker_id = $param['worker_id'];

        //检查参数
        if (empty($worker_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取工单
        BaseModel::getInstance('worker')
            ->getOneOrFail($worker_id, 'worker_id');

        //获取客服
        $admin = AuthService::getAuthModel();
        $user_id = $admin['id'];

        $model = BaseModel::getInstance($this->tableName);

        //获取旧标签
        $where = ['worker_id' => $worker_id, 'admin_id' => $user_id];
        $prev_label_ids = $model->getFieldVal($where, 'label_id', true);
        $prev_label_ids = empty($prev_label_ids) ? [] : $prev_label_ids;

        $del_label_ids = array_diff($prev_label_ids, $label_ids);
        $new_label_ids = array_diff($label_ids, $prev_label_ids);

        if (!empty($del_label_ids)) {
            $delete_where = [
                'worker_id' => $worker_id,
                'admin_id'  => $user_id,
                'label_id'  => ['in', $del_label_ids],
            ];
            $model->remove($delete_where);
        }

        if (!empty($new_label_ids)) {
            $labels = $this->getLabels($new_label_ids);

            $insert_data = [];
            foreach ($new_label_ids as $label_id) {
                //获取标签
                $label_name = isset($labels[$label_id]) ? $labels[$label_id]['label_name'] : '';

                $insert_data[] = [
                    'label_id'  => $label_id,
                    'worker_id' => $worker_id,
                    'admin_id'  => $user_id,
                    'name'      => $label_name,
                ];
            }
            $model->insertAll($insert_data);
        }
    }

    public function deleteLabel($param)
    {
        $label_ids = Util::filterIdList($param['label_ids']);

        if (!empty($label_ids)) {
            BaseModel::getInstance($this->tableName)->remove([
                'worker_id' => $param['worker_id'],
                'label_id'  => ['in', $label_ids],
            ]);
        }
    }

    protected function getLabels($label_ids)
    {
        if (empty($label_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('cm_list_item');

        $field = 'list_item_id as label_id,item_desc as label_name';
        $where = ['cm_list_item' => 49, 'list_item_id' => ['in', $label_ids]];

        $list = $model->getList($where, $field);

        $data = [];

        foreach ($list as $val) {
            $label_id = $val['label_id'];

            $data[$label_id] = $val;
        }

        return $data;
    }


}