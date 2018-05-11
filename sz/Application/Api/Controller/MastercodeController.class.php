<?php
/**
* @User fzy
*/
namespace Api\Controller;

use Api\Controller\BaseController;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;
use Library\Common\Util;

class MastercodeController extends BaseController
{
	public function export()
	{
	    try{

            // $worker_id='2,3,5,8,14';
           $worker_id = I('get.id', 0);

            !$worker_id && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            $masterInfo = D('Mastercode', 'Logic')->Export($worker_id);

            $filePath = './Public/master_excel' . '.xls';
            Vendor('PHPExcel.PHPExcel');
            $objPHPExcel = \PHPExcel_IOFactory::load($filePath);

            $row = 2;

            foreach ($masterInfo as $item => $v){
            $column = 'A';
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($column++ . $row, $v['nickname'])
                ->setCellValue($column++ . $row, $v['worker_telephone'])
                ->setCellValue($column++ . $row, C('MASTER_SCAN_URL') . encodeWorkerCode($v['worker_id']))
                ->setCellValue($column++ . $row, $v['scanData']['nums']);
                $row++;
            }

            $fileName = '师傅码源数据导出-' . date("Y.m.d",time()) . '.xls';

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename='.$fileName);
            header('Cache-Control: max-age=0');

            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
        }catch (\Exception $e){
	        $this->getExceptionError($e);
        }
	}

	public function scan()
    {
        try {
            $id = I('get.id', 0);

            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING,'扫描失败，请重新扫描');
            }
            // header('Location: http://shenzhou.3ncto.cn/#/worker-info/'.$id);//跳转到前端
            $location = C('C_HOST_URL').C('SCAN_LOCATION');
            header('Location: '.$location.$id);//跳转到前端
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function masterinfo()
    {
        try{
            $ip = get_client_ip();
            $worker_id = decodeWorkerCode(I('get.id', 0));
            !$worker_id && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            $result = D('Mastercode', 'Logic')->MasterInfo($worker_id, $ip);
            !$result && $this->throwException(ErrorCode::SYS_SYSTEM_ERROR);
            $this->response($result);
        }catch (\Exception $e){
            $this->getExceptionError($e);
        }
    }



}
