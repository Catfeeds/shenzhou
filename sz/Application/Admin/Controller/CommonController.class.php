<?php
/**
 * File: CommonController.class.php
 * User: xieguoqiu
 * Date: 2017/4/7 9:58
 */

namespace Admin\Controller;

use Common\Common\Logic\ExpressTrackLogic;
use Common\Common\Model\BaseModel;
use Library\Common\Util;
use Common\Common\Service\AuthService;
use Library\Queue\Worker;

class CommonController extends BaseController
{
    public function dataLastCache()
    {
        try {
            $token_id = $this->requireAuth();
            $where = [
                'user_type' => AuthService::getModel(),
                'user_id' => AuthService::getAuthModel()->factory_id,
                'content_type' => I('get.type', 0),
            ];
            $field = 'id,content';
            $data = BaseModel::getInstance('data_last_cache')->getOne($where, $field);
            if (!$data) {
                $this->response();
            }
            $content = (array)json_decode($data['content'], true);
            unset($data['content']);
            $data += $content;
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function qrImage()
    {
        $content = htmlEntityDecode(I('get.content', ''));
        vendor('phpqrcode.qrlib');
        \QRcode::png($content, false, 'H', 50, 2);
    }

    public function yimaApplyAndExcel()
    {
        set_time_limit(0);
        try {
            if (I('get.co') !== 'cocococo') {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }
            D('FactoryExcelData', 'Logic')->yimaApplyAndExcel();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryExcelToYima()
    {
        set_time_limit(0);
        try {
            if (I('get.co') !== 'cocococo') {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }
            $arr = ['0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f'];
            if (in_array(I('get.model_name'), $arr)) {
                $arr = [I('get.model_name')];
            }

            $logic = D('FactoryExcelData', 'Logic');
            file_put_contents("./sql_backup/yima_v2.0/all.sql", '');
            file_put_contents("./sql_backup/yima_v2.0/all_2.sql", '');
            foreach ($arr as $k => $v) {
                $name = 'factory_excel_datas_'.$v;
                // $logic->factoryExcelToYima($name, $v);
                $logic->factoryExcelToYima2($name, $v);
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function factoryExcelForyima()
    {
        set_time_limit(0);
        try {
            $logic = D('FactoryExcelData', 'Logic');
            $logic->factoryExcelForyima();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaareaids()
    {
        set_time_limit(0);
        try {
            $logic = D('FactoryExcelData', 'Logic');
            $logic->yimaareaids();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryoldqr()
    {
        set_time_limit(0);
        try {
            $logic = D('FactoryExcelData', 'Logic');
            $this->response($logic->factoryoldqr());
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryqrbindagen()
    {
        set_time_limit(0);
        try {
            $logic = D('FactoryExcelData', 'Logic');
            $this->response($logic->factoryqrbindagen());
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    public function queue()
    {
        try {
            $queue_name = I('queue');
            (new Worker())->work($queue_name);
            
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
