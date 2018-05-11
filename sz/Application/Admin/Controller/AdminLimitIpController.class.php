<?php
/**
 * Created by PhpStorm.
 * File: AdminLimitIpController.class.php
 * User: chenzhiliang
 * Date: 2017/4/9 15:33
 */

namespace Admin\Controller;

use Common\Common\Service\AuthService;
use Admin\Common\ErrorCode;
use Common\Common\Service\OrderService;
use Admin\Model\BaseModel;

class AdminLimitIpController extends BaseController
{

    //显示ip列表
    public function getList()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $model = BaseModel::getInstance('admin_ip_address');
            $nums = $model->getNum();
            !$nums && $this->paginate();

            $field = 'id,ip_address,create_time';
            $list = $model->getList([
                'field' => $field,
                'limit' => getPage(),
                'order' => 'id asc',
            ]);

            //将取出来的ip数据表中ip_address还原为IP地址
            foreach($list as $key => $val) {
                $ip_address_change = long2ip($val['ip_address']);

                $list[$key]['ip_address'] = $ip_address_change;
            }

            $this->paginate($list, $nums);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //新增ip地址
    public function insert()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $ip_address = I('ip_address', '');

            empty($ip_address) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, 'IP地址不能为空');
            $ip_address_change = ip2long($ip_address);  //将获取到的ip转换成长整型

            $nums = BaseModel::getInstance('admin_ip_address')->getNum(['ip_address' => $ip_address]);
            $nums && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'IP地址已存在');

            $insert = [
                'ip_address' => $ip_address_change,
                'create_time' => NOW_TIME,
            ];

            BaseModel::getInstance('admin_ip_address')->insert($insert);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //IP详情
    public function getInfo()
    {
        $id = I('get.id', 0);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $field = 'id,ip_address,create_time';
            $info = BaseModel::getInstance('admin_ip_address')->getOneOrFail($id, $field);
            $info['ip_address'] = long2ip($info['ip_address']);  //将取出来的ip数据表中ip_address还原为IP地址
            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //修改IP
    public function update()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $id = I('get.id', 0);
            $ip_address = I('put.ip_address', '');

            empty($ip_address) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, 'IP地址不能为空');
            $ip_address_change = ip2long($ip_address);  //将获取到的ip转换成长整型

            BaseModel::getInstance('admin_ip_address')->getOneOrFail($id, 'id');
            $nums = BaseModel::getInstance('admin_ip_address')->getNum([
                'ip_address' => $ip_address_change,
                'id' => ['neq', $id],
            ]);
            $nums && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'IP地址已存在');

            $update = [
                'ip_address' => $ip_address_change,
                'last_update_time' => NOW_TIME,
            ];

            BaseModel::getInstance('admin_ip_address')->update($id, $update);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //删除IP
    public function remove()
    {
        $id = I('get.id', 0);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $where = ['id' => $id];

            BaseModel::getInstance('admin_ip_address')->remove($where);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
}