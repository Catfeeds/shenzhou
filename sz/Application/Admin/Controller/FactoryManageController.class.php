<?php
/**
 * File: OrderController.class.php
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\SupportPersonLogic;
use Common\Common\CacheModel\FactoryAdminCacheModel;
use Common\Common\Service\AuthService;
use Admin\Model\BaseModel;
use Common\Common\Service\FactoryService;
use Common\Common\Service\OrderService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class FactoryManageController extends BaseController
{
    public function factoryInfo()
    {
        try {
            $this->requireAuth();
            $factory_id = $this->requireAuth('factory');
            if (empty($factory_id)) {
                $this->throwException(ErrorCode::CHECK_IS_NOT__NULL);
            }
            $opt = [
                'where' => ['factory_id' => $factory_id],
                'field' => 'factory_id,linkphone,factory_full_name,factory_service_model,receive_person,receive_tell,
            receive_address,qrcode_person,qrcode_tell,factory_logo,content',
            ];
            $factory_info = BaseModel::getInstance('factory')->getOne($opt);
            $factory_info['img_full'] = $factory_info['img'] ? Util::getServerFileUrl($factory_info['img']) : '';
            $factory_info['factory_logo'] = $factory_info['factory_logo'] ? Util::getServerFileUrl($factory_info['factory_logo']) : '';
            $factory_info['content'] = htmlspecialchars_decode($factory_info['content']);
            //            $res = servicetype($factory_info['factory_service_model']);
            $factory_info['factory_service_model'] = OrderService::getFactoryServiceByTypes($factory_info['factory_service_model']);
            $this->responseList($factory_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    //执行编辑厂家信息
    public function editFactoryInfo()
    {
        try {
            $data = [];
            $data['content'] = I('content');
            $data['factory_service_model'] = array_filter(explode(',', I('factory_service_model')));
            $data['factory_logo'] = empty(I('factory_logo')) ? '' : I('factory_logo');
            $data['receive_person'] = empty(I('receive_person')) ? '' : I('receive_person');
            $data['receive_tell'] = empty(I('receive_tell')) ? '' : I('receive_tell');
            $data['receive_address'] = empty(I('receive_person')) ? '' : I('receive_address');
            $data['qrcode_person'] = empty(I('qrcode_person')) ? '' : I('qrcode_person');
            $data['qrcode_tell'] = empty(I('qrcode_tell')) ? '' : I('qrcode_tell');
            $data['factory_service_model'] = implode(',', $data['factory_service_model']);
            $this->requireAuth();
            $factory_id = $this->requireAuth('factory');
            if (empty($data['receive_person']) || empty($data['receive_tell']) || empty($data['receive_address'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '收件人信息不能为空');
            }
            $condition = [];
            $condition['factory_id'] = $factory_id;
            //            $factory_id = '123';
            M()->startTrans();

            //修改厂家信息
            $res = BaseModel::getInstance('factory')
                ->update($condition['factory_id'], $data);
            //新增技术支持人
            if (!empty(I('add_supportPerson', 0))) {
                $addSupportPerson = htmlEntityDecodeAndJsonDecode(I('add_supportPerson', 0));
                D('SupportPerson', 'Logic')->addTechnicalSupportPerson(I('factory_id', 0), $addSupportPerson);
                //                $this->addTechnicalSupportPerson(I('factory_id', 0), $addSupportPerson);
            }
            //修改技术支持信息
            if (!empty(I('edit_supportPerson', 0))) {

                $editSupportPerson = htmlEntityDecodeAndJsonDecode(I('edit_supportPerson', 0));
                D('SupportPerson', 'Logic')->editTechnicalSupportPerson($factory_id, $editSupportPerson);
                //                $this->editTechnicalSupportPerson($factory_id, $editSupportPerson);
            }
            //批量删除技术支持人
            if (!empty(I('del_supportPerson', 0))) {
                $delSupportPerson = array_filter(explode(',', I('del_supportPerson', 0)));
                D('SupportPerson', 'Logic')->delBatchTechnicalSupportPerson($delSupportPerson);
                //                $this->delBatchTechnicalSupportPerson($delSupportPerson);
            }
            M()->commit();
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //删除技术支持人
    public function delTechnicalSupportPerson()
    {
        try {
            $id = htmlEntityDecode(I('get.id'));
            $id = array_filter(explode(',', str_replace('&', ',', $id)));
            $id = implode(',', $id);
            BaseModel::getInstance('factory_helper')->remove($id);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //技术支持人列表
    public function technicalSupportPerson()
    {
        try {
            $factory_id = I('get.factory_id');
            if (empty($factory_id)) {
                $this->throwException(ErrorCode::CHECK_IS_NOT__NULL);
            }
            $help_person = BaseModel::getInstance('factory_helper')
                ->getList(['factory_id' => $factory_id]);
            $this->responseList($help_person);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function operateFactoryHelper()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();

            $delete = array_filter(I('delete'));
            $update = array_filter(I('update'));
            $add = array_filter(I('add'));

            $factory_helper_model = BaseModel::getInstance('factory_helper');
            $default_helper = array_merge(Arr::pluck($update, 'is_default'), Arr::pluck($add, 'is_default'));
            if (array_sum($default_helper) > 1) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '不能同时设置多个默认技术支持人');
            }
            $factory_helper_model->startTrans();
            if ($delete) {
                $factory_helper_model->remove(['id' => ['IN', $delete]]);
            }

            if ($update) {
                $phone_list = Arr::pluck($update, 'phone');
                $helper_ids = Arr::pluck($update, 'id');
                if (count(array_unique($phone_list)) != count($update)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技术支持人手机号码不能重复,请确认~');
                }
                $exists_product = $factory_helper_model->getOne([
                    'factory_id' => $factory_id,
                    'telephone'  => ['IN', $phone_list],
                    'id'         => ['NOT IN', $helper_ids],
                ], 'id');
                if ($exists_product) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技术支持人手机号码已存在,请确认~');
                }
                $helper = [];
                $has_default = 0;
                foreach ($update as $item) {
                    if (!$item['phone'] || !$item['name']) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写完整支持人信息');
                    }
                    $item['is_default'] = intval($item['is_default']);
                    $item['is_default'] && $has_default = 1;
                    $helper[] = "({$item['id']},{$item['phone']},'{$item['name']}',{$item['is_default']})";
                }
                if ($has_default) {
                    (new SupportPersonLogic())->resetFactoryDefaultHelper($factory_id);
                }
                $helper = implode(',', $helper);
                $sql = "INSERT INTO `factory_helper`(`id`,`telephone`,`name`,`is_default`) VALUES{$helper} ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`telephone`=VALUES(`telephone`),`is_default`=VALUES(`is_default`)";
                $factory_helper_model->execute($sql);
            }
            if ($add) {
                $phone_list = Arr::pluck($add, 'phone');
                if (count(array_unique($phone_list)) != count($add)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技术支持人手机号码不能重复,请确认~');
                }
                $exists_product = $factory_helper_model->getOne(['factory_id' => $factory_id, 'telephone' => ['IN', $phone_list]], 'id');
                if ($exists_product) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技术支持人手机号码已存在,请确认~');
                }
                $add_helper = [];
                $has_default = 0;
                foreach ($add as $item) {
                    if (!$item['phone'] || !$item['name']) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写完整支持人信息');
                    }
                    $add_helper[] = [
                        'factory_id' => $factory_id,
                        'name'       => $item['name'],
                        'telephone'  => $item['phone'],
                        'is_default' => intval($item['is_default']),
                    ];
                    $item['is_default'] && $has_default = 1;
                }
                if ($has_default) {
                    (new SupportPersonLogic())->resetFactoryDefaultHelper($factory_id);
                }
                $factory_helper_model->insertAll($add_helper);
            }
            $factory_helper_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryChangePassword()
    {
        try {
            $this->requireAuth();
            $table = AuthService::getModel();
            $id = AuthService::getAuthModel()->getPrimaryValue();
            if ($table == 'factory') {
                $where['factory_id'] = $id;
            } else {
                $where['id'] = $id;
            }
            if (empty(I('post.pwded'))) {
                $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS, '当前密码不能为空');
            }
            $data = BaseModel::getInstance($table)->getOne($id, 'password');
            if ($data['password'] != md5(I('post.pwded'))) {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '当前密码错误,请重新输入');
            }
//            $count = BaseModel::getInstance($table)->getNum([
//                'where' => [
//                    $where,
//                    'password' => md5(I('post.pwded'))],
//            ]);
//
//            !$count && $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '当前密码错误,请重新输入');
            if (empty(I('post.pwd')) || empty(I('post.repwd'))) {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '密码不能为空');
            }
            if (I('post.pwd') != I('post.repwd')) {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '新密码与确认密码填写的不一致,请重新输入');
            }
            if ((strlen(trim(I('post.pwd'))) < 6) || (strlen(trim(I('post.pwd'))) > 16)) {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '新密码与确认密码填写的不一致,请重新输入');
            }
            $pwd['password'] = md5(trim(I('post.pwd')));
            if ($table == 'factory') {
                BaseModel::getInstance($table)->update($where, $pwd);
            } else {
                FactoryAdminCacheModel::update($id, $pwd);
            }
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //厂家帮助文档列表
    public function factoryHelpDocument()
    {
        try {
            $condition = [];
            if (I('is_show') != '-1' && I('is_show') != '') {
                $condition['is_show'] = I('is_show');
            }
            $count = BaseModel::getInstance('factory_help_article')
                ->getNum($condition);
            $list = BaseModel::getInstance('factory_help_article')->getList([
                'where' => $condition,
                'limit' => getPage(),
                'order' => 'id desc',
                'field' => 'id,title,content,add_time,sort',
            ]);
            foreach ($list as $k => $v) {
                $v['add_time'] = date('Y.m.d H:i', $v['add_time']);
                $list[$k] = $v;
            }
            $this->paginate($list, $count);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function getOneHelp()
    {
        try {
            $id = I('id', 0);
            if (empty($id)) {
                $this->throwException(ErrorCode::CHECK_IS_NOT__NULL);
            }
            $data['id'] = $id;
            $rs = BaseModel::getInstance('factory_help_article')->getOne($data);
            $rs['add_time'] = date('Y.m.d H:i', $rs['add_time']);
            $rs['content'] = Util::buildImgTagSource($rs['content']);

            $this->response($rs);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function getFactoryCategory()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'factory_id' => I('factory_id', 0, 'intval'),
            ];

            $data = D('FactoryManage', 'Logic')->getFactoryCategory($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getStandard()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'category_id' => I('category_id', 0, 'intval'),
            ];

            $data = D('FactoryManage', 'Logic')->getStandard($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFaultFeeList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'factory_id'  => I('factory_id', 0, 'intval'),
                'category_id' => I('category_id', 0, 'intval'),
                'standard_id' => I('standard_id', 0, 'intval'),
                'fault_type'  => I('fault_type'),
            ];

            $data = D('FactoryManage', 'Logic')->getFaultFeeList($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editFaultFee()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'factory_id'     => I('get.factory_id', 0, 'intval'),
                'category_id'    => I('category_id', 0, 'intval'),
                'standard_id'    => I('standard_id', 0, 'intval'),
                'fault_type'     => I('fault_type'),
                'fault_fee_list' => I('fault_fee_list'),
                'service_cost'   => I('service_cost', -1, 'floatval'),
            ];

            M()->startTrans();
            D('FactoryManage', 'Logic')->editFaultFee($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function resetFaultFee()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'factory_id' => I('get.factory_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('FactoryManage', 'Logic')->resetFaultFee($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


}
