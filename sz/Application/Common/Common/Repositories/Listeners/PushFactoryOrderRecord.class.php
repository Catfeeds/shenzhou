<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/27
 * Time: 16:25
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\PlatformApiService;

class PushFactoryOrderRecord implements ListenerInterface
{
    public function handle(EventAbstract $event)
    {
        $ext_info = $event->ext_info;
        $platform_code = C('PLATFORM_TYPE_TO_SERVICE_KEY_VALUE')[$ext_info['out_platform']];
        if (!$platform_code) {
            return;
        }
        $platform_config = C('PLATFORM_CONFIG')[$platform_code];
        $event->record['operator'] = OrderOperationRecordService::getUserTypeName($event->record['operation_type']);
        $records = [$event->record];
        OrderOperationRecordService::loadAddUserInfo($records);
        $record = reset($records);
        $class = PlatformApiService::getPlatformService($platform_config);
//        $url = $class::MASTER_URL.$class::POST_URL_PUST_FACTORY_ORDER_RECORD['path'];
        $url = $class::TEST_URL.$class::POST_URL_PUST_FACTORY_ORDER_RECORD['path'];
        $id = explode('-', $ext_info['out_trade_number']);
        $push_data = [
                'rpId' => (int)end($id),
                'Note' => $record['content'].($record['remark'] ? ' 备注：'.$record['remark'] : ''),
                'comid' => $record['operator'],
            ] + $class::POST_URL_PUST_FACTORY_ORDER_RECORD['params'];
        curlPostHttps($url, $push_data);
//        $result = curlPostHttps($url, $push_data);
//        $dejson_result = json_decode($result, true);
//        var_dump($dejson_result, $url, $push_data);
//        die;
    }

}
