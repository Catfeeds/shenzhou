<?php
/**
 * File: IncrementScanTimes.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 19:54
 */

namespace Common\Common\Repositories\Listeners\ScanYimaQrcode;

use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Events\ScanYimaQrcodeEvent;
use Common\Common\Repositories\Listeners\ListenerInterface;

class IncrementScanTimes implements ListenerInterface
{

    /**
     * @param ScanYimaQrcodeEvent $event
     */
    public function handle(EventAbstract $event)
    {
        // $suffix = substr($event->md5Code , 0, 1 );
        // $table_name = 'factory_excel_datas_' . $suffix;
        // BaseModel::getInstance($table_name)->setNumInc(['md5code' => $event->md5Code], 'saomiao');
        $yima_model = new \Api\Model\YimaModel();
    	$data = $yima_model->getYimaInfoByCode($event->code, true);
        $model = BaseModel::getInstance(yimaCodeToModelName($data['code']));

        $add = $data;
        if ($data['not_true']) {
            $add['active_json'] = json_encode($add['active_json']);
            $add['saomiao'] = 1;
            $model->insert($add);
        } else {
            $model->setNumInc(['code' => $data['code']], 'saomiao');
        }
    }
    
}
