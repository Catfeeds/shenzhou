<?php

namespace Admin\Controller;

use Admin\Model\BaseModel;
use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Logic\ExpressTrackLogic;
use Common\Common\Service\AuthService;
use Library\Express\Kuaidi100;

class ExpressController extends BaseController
{
    public function getExpressCompanyByNo()
    {
        try {
            $model = BaseModel::getInstance('express_com');

            $name = I('get.name', '');
            $express_num = I('get.express_num', '');

            $where = [];
            if (strlen($name) > 0) {
                $where['name'] = ['like', '%' . $name . '%'];
            } elseif (!empty($express_num)) {
                $code_com_list = Kuaidi100::autoComCode($express_num);
                if (!empty($code_com_list)) {
                    $in = arrFieldForStr($code_com_list, 'comCode');
                    $where['comcode'] = ['in', $in];
                } else {
                    $where['id'] = ['lt', 0]; // 如果快递100没有数据,默认不展示列表
                }
            }

            $opts = [
                'where' => $where,
                'field' => 'comcode,name',
                'order' => 'id DESC',
            ];
            $list = $model->getList($opts);

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    // 根据输入的字符串搜索物流公司
    public function getExpressCompanyList()
    {
        try {
            $this->requireAuth();

            $name = I('get.name');

            $where = [];
            if ($name) {
                $where['name'] = ['LIKE', "%{$name}%"];
            }

            $model = BaseModel::getInstance('express_com');
            $express_com = $model->getList([
                'field' => 'comcode,name',
                'where' => $where,
                'order' => 'id DESC',
            ]);

            $this->responseList($express_com);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function callback()
    {
        try {
            // 快递100示例demo 这样写,保证回调成功,按照他的写法做
            header("Content-Type:text/html;charset=utf-8");
            $express_id = I('get.express_id', 0, 'intval');

            M()->startTrans();
            (new ExpressTrackingLogic())->ruleExpressCallBack($express_id);
            M()->commit();

            $res = json_encode([
                'result'     => true,
                'returnCode' => 200,
                'message'    => '成功',
            ]);
            die($res);

        } catch (\Exception $e) {
            $res = json_encode([
                'result'     => false,
                'returnCode' => 500,
                'message'    => '失败',
            ]);
            die($res);
        }
    }

    public function getExpress()
    {
        try {

            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'type'       => I('type', 0, 'intval'),
                'data_id'    => I('data_id', 0, 'intval'),
                'express_number'    => I('express_number', ''),
                'is_refresh' => I('is_refresh', 0, 'intval'),
            ];

            $data = (new ExpressTrackingLogic())->getExpress($param);

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editExpress()
    {
        try {

            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $express_code = I('express_code');
            $express_number = I('express_number');
            $data_id = I('data_id', 0, 'intval');
            $type = I('type', 0, 'intval');

            M()->startTrans();
            (new ExpressTrackingLogic())->setExpressTrack($express_code, $express_number, $data_id, $type);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}