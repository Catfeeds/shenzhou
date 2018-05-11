<?php
/**
 * File: WorkerController.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/8
 */

namespace Admin\Controller;


use Admin\Common\ErrorCode;
use Admin\Logic\WorkerLogic;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkerCheckEvent;
use Common\Common\Service\AuthService;
use Common\Common\Service\WorkerService;
use Monolog\ErrorHandler;

class WorkerController extends BaseController
{

    public function getInfo()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $phone = I('phone');
            if (empty($phone)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $worker_info = BaseModel::getInstance('worker')->getOne([
                'where' => ['worker_telephone' => $phone,],
                'field' => 'worker_id,nickname,worker_telephone as phone,worker_area_ids,worker_address as area_names,worker_detail_address as address,is_qianzai',
            ]);

            if (!empty($worker_info)) {
                $worker_area_ids = $worker_info['worker_area_ids'];
                $area_names = $worker_info['area_names'];
                $area_names = explode('-', $area_names);

                $max_level = 3; // 最高显示地区层级数
                if (count($area_names) > $max_level) {
                    $area_names = $area_names[0] . '-' . $area_names[1] . '-' . $area_names[2];
                } else {
                    $area_names = implode('-', $area_names);
                }

                $area_ids = explode(',', $worker_area_ids);
                $province_id = empty($area_ids[0]) ? null : $area_ids[0];
                $city_id = empty($area_ids[1]) ? null : $area_ids[1];
                $area_id = empty($area_ids[2]) ? null : $area_ids[2];
                $worker_info['province_id'] = $province_id;
                $worker_info['city_id'] = $city_id;
                $worker_info['area_id'] = $area_id;
                $worker_info['area_names'] = $area_names;
            }

            $this->response($worker_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function export()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            (new WorkerLogic())->export();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function workerCheck()
    {
        try {

            $type = I('type', 0, 'intval');
            $worker_id = I('worker_id', 0, 'intval');

            event(new WorkerCheckEvent([
                'type'      => $type,
                'worker_id' => $worker_id,
            ]));

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //重置技工提现密码
    public function resetWorkerWithdrawPassword()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $worker_id = I('get.id', 0, 'intval');
            $where = ['worker_id' => $worker_id];
            $worker_telephone = BaseModel::getInstance('worker')->getFieldVal($where, 'worker_telephone');
            $update = [
                'pay_password' => md5(substr($worker_telephone, -6)),  //获取登录账号后6位
            ];

            BaseModel::getInstance('worker')->update($worker_id, $update);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}