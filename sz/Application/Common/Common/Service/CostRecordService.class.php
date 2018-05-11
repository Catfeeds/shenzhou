<?php
/**
 * File: CostRecordService.class.php
 * User: sakura
 * Date: 2017/11/9
 */

namespace Common\Common\Service;

use Common\Common\Model\BaseModel;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Library\Common\Util;

class CostRecordService
{
    protected $tableName = 'worker_order_apply_cost_record';

    protected $record = null;

    const TYPE_CS_CHECKED               = 1000;
    const TYPE_CS_FORBIDDEN             = 1001;
    const TYPE_CS_ACT_FACTORY_CHECKED   = 1002;
    const TYPE_CS_ACT_FACTORY_FORBIDDEN = 1003;

    const TYPE_FACTORY_CHECKED   = 2000;
    const TYPE_FACTORY_FORBIDDEN = 2001;

    const TYPE_FACTORY_ADMIN_CHECKED   = 3000;
    const TYPE_FACTORY_ADMIN_FORBIDDEN = 3001;

    const TYPE_WORKER_APPLY = 4000;


    public function searchRecord($cost_id)
    {
        $record_db = BaseModel::getInstance($this->tableName);

        $field = 'create_time,user_id,type,operation_content,operation_remark';
        $opts = [
            'where' => ['worker_order_apply_cost_id' => $cost_id],
            'field' => $field,
            'order' => 'id desc',
        ];

        $record = $record_db->getList($opts);


        $factory_ids = [];
        $factory_admin_ids = [];
        $admin_ids = [];
        $worker_ids = [];

        foreach ($record as $val) {
            $type = $val['type'];
            $user_id = $val['user_id'];

            if ($type >= 1000 && $type <= 1999) {
                $admin_ids[] = $user_id;
            } elseif ($type >= 2000 && $type <= 2999) {
                $factory_ids[] = $user_id;
            } elseif ($type >= 3000 && $type <= 3999) {
                $factory_admin_ids[] = $user_id;
            } elseif ($type >= 4000 && $type <= 4999) {
                $worker_ids[] = $user_id;
            }
        }

        $factories = $this->getFactories($factory_ids);
        $factory_admins = $this->getFactoryAdmins($factory_admin_ids);
        $workers = $this->getWorkers($worker_ids);
        $admins = $this->getAdmins($admin_ids);

        foreach ($record as $key => $val) {
            $type = $val['type'];
            $user_id = $val['user_id'];

            $user_type = 0;
            $user_name = '';

            if ($type >= 1000 && $type <= 1999) {
                $user_name = isset($admins[$user_id])? $admins[$user_id]['user_name']: '';
                $user_type = '1';
            } elseif ($type >= 2000 && $type <= 2999) {
                $user_name = isset($factories[$user_id])? $factories[$user_id]['linkman']: '';
                $user_type = '2';
            } elseif ($type >= 3000 && $type <= 3999) {
                $user_name = isset($factory_admins[$user_id])? $factory_admins[$user_id]['nickout']: '';
                $user_type = '3';
            } elseif ($type >= 4000 && $type <= 4999) {
                $user_name = isset($workers[$user_id])? $workers[$user_id]['nickname']: '';
                $user_type = '4';
            }

            $remark = $val['operation_remark'];

            $remark = preg_replace_callback("#src=(['\\\"])([^'\"]*)\\1#i", function($matches){
                $url = $matches[2];
                if (!preg_match('#^https?://#', $url)) {
                    $url = Util::getServerFileUrl($url);
                }
                return 'src="'.$url.'"';
            }, $remark);
            $val['operation_remark'] = $remark;

            $val['user_name'] = $user_name;
            $val['user_type'] = $user_type;

            $record[$key] = $val;
        }

        $this->record = $record;
    }

    public function getRecord()
    {
        return $this->record;
    }

    protected function getUserInfo($type, $user_id)
    {
        $user_info_type = 0;
        if ($type >= 1000 && $type <= 1999) {
            $user_info_type = '1';
        } elseif ($type >= 2000 && $type <= 2999) {
            $user_info_type = '2';
        } elseif ($type >= 3000 && $type <= 3999) {
            $user_info_type = '3';
        } elseif ($type >= 4000 && $type <= 4999) {
            $user_info_type = '4';
        }

        $user_obj = UserTypeService::getTypeData($user_info_type, $user_id, UserInfoType::USER_COMMON_TYPE);

        return [
            'name'      => $user_obj->getName(),
            'user_type' => $user_info_type,
        ];
    }

    protected function getFactories($factory_ids)
    {
        if (empty($factory_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory');
        $where = ['factory_id' => ['in', $factory_ids]];
        $filed = 'factory_id,linkman,group_id';
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $factory_id = $val['factory_id'];

            $data[$factory_id] = $val;
        }

        return $data;
    }

    protected function getFactoryAdmins($factory_admin_ids)
    {
        if (empty($factory_admin_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory_admin');
        $opts = [
            'field' => 'fa.id,fa.nickout',
            'alias' => 'fa',
            'where' => ['fa.id' => ['in', $factory_admin_ids]],
            'join'  => ['left join factory_adtags as fat on fat.id=fa.tags_id'],
        ];
        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $id = $val['id'];

            $data[$id] = $val;
        }

        return $data;

    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout,user_name';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        $role = AuthService::getModel();

        foreach ($list as $val) {
            $admin_id = $val['id'];

            if (AuthService::ROLE_ADMIN == $role) {
                $val['user_name'] = $val['nickout'];
            }
            unset($val['nickout']);

            $data[$admin_id] = $val;
        }

        return $data;
    }

    protected function getWorkers($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $filed = 'worker_id,nickname';
        $where = ['worker_id' => ['in', $worker_ids]];
        $model = BaseModel::getInstance('worker');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id] = $val;
        }

        return $data;
    }

    public static function create($cost_id, $type, $content, $remark)
    {
        $user_id = AuthService::getAuthModel()->getPrimaryValue();

        $insert_data = [
            'worker_order_apply_cost_id' => $cost_id,
            'user_id'                    => $user_id,
            'type'                       => $type,
            'operation_content'          => $content,
            'operation_remark'           => $remark,
            'create_time'                => NOW_TIME,
        ];
        $record_db = BaseModel::getInstance('worker_order_apply_cost_record');
        $record_db->insert($insert_data);
    }
}