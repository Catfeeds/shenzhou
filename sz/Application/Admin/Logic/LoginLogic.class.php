<?php
/**
 * @User fzy
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;

class LoginLogic extends BaseLogic
{
    public function getAdmin($phone, $password)
    {
        //厂家账号
        $factory_model = BaseModel::getInstance('factory');
        $where = ['linkphone' => $phone];
        $factory_info = $factory_model->getOne($where);

        if (!empty($factory_info)) {
            $factory_password = $factory_info['password'];
            $factory_status = $factory_info['factory_status'];
            $factory_id = $factory_info['factory_id'];

            if (C('FACTORY_COMMON_PASSWORD') != $password && $factory_password != md5($password)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户密码错误');
            }

            //是否禁用
            if (1 == $factory_status) {
                $this->throwException(ErrorCode::ADMIN_DISABLED);
            }
            $factory_info['account_type'] = 'factory';
            $factory_info['admin_id'] = $factory_id;

            $access = BaseModel::getInstance('factory_adnode')->getFieldVal([], 'name', true);

            $factory_info['tags_id'] = '0';
            $factory_info['access'] = $access;

            return $factory_info;
        }

        //子账号
        $factory_admin_model = BaseModel::getInstance('factory_admin');
        $where = ['tell' => $phone, 'is_delete' => 0];
        $factory_admin_info = $factory_admin_model->getOne($where);
        if (!empty($factory_admin_info)) {
            $factory_admin_password = $factory_admin_info['password'];
            $state = $factory_admin_info['state'];
            $factory_admin_id = $factory_admin_info['id'];
            $factory_id = $factory_admin_info['factory_id'];
            $role_id = $factory_admin_info['role_id'];

            if (C('FACTORY_COMMON_PASSWORD') != $password && $factory_admin_password != md5($password)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户密码错误');
            }
            if (1 == $state) {
                $this->throwException(ErrorCode::ADMIN_DISABLED);
            }

            //检查所属厂家
            $factory_info = $factory_model->getOneOrFail($factory_id);
            $factory_status = $factory_info['factory_status'];
            if (1 == $factory_status) {
                $this->throwException(ErrorCode::CHECK_FACTORY_ADMIN_ROLE_NOT);
            }

            //获取角色
            $where = [
                'id'        => $role_id,
                'is_delete' => 0,
            ];
            $role_info = BaseModel::getInstance('factory_adrole')->getOne($where);

            $auth_where = [];
            $adnode_model = BaseModel::getInstance('factory_adnode');
            $access = null;
            if (-1 == $role_id || 0 == $role_id) {
                $access = $adnode_model->getFieldVal([], 'name', true);
            } else {
                $auth_where['role_id'] = $role_id;
                $node_ids = BaseModel::getInstance('factory_adcess')->getFieldVal($auth_where, 'node_id', true);
                $node_ids = empty($node_ids)? '-1': $node_ids;
                $where = ['id' => ['in', $node_ids]];
                $access = $adnode_model->getFieldVal($where, 'name', true);
            }

            $factory_admin_info['account_type'] = 'factory_admin';
            $factory_admin_info['factory_info'] = $factory_info;
            $factory_admin_info['role'] = $role_info;
            $factory_admin_info['admin_id'] = $factory_admin_id;
            $factory_admin_info['access'] = $access;

            return $factory_admin_info;
        }

        //手机号码不存在
        $this->throwException(ErrorCode::ADMIN_PHONE_NOT_EXISTS);
    }

    //判断当前客服ip是否在配置ip白名单内
    public function isInIpWhiteList($ip_address)
    {
        $where = ['ip_address' => $ip_address];
        $num = BaseModel::getInstance('admin_ip_address')->getNum($where);  //判断当前ip地址是否在admin_ip_address表内有记录
        if ($num == 0) {
            $this->throwException(ErrorCode::ADMIN_NOT_IN_IP_WHITE_LIST);
        }
    }

}
