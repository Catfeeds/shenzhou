<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/4/2
 * Time: 12:01
 */

namespace Common\Common\Repositories\Listeners;


use Carbon\Carbon;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Service\PlatformApiService;

class PushFactoryOrderAppontRecord  implements ListenerInterface
{
    public function handle(EventAbstract $event)
    {
        $ext_info = $event->ext_info;
        $platform_code = C('PLATFORM_TYPE_TO_SERVICE_KEY_VALUE')[$ext_info['out_platform']];
        if (!$platform_code) {
            return;
        }
        $record = $event->record;
        $xf_order_id = $event->xf_order_id;
        $appoint = $event->appoint;

        $obj = new Carbon($appoint['appoint_time']);
        $am_pm = '03';
        if (0 <= $obj->hour && $obj->hour < 12) {
            $am_pm = '01';
        } elseif (12 <= $obj->hour && $obj->hour < 20) {
            $am_pm = '02';
        }
        
        $platform_config = C('PLATFORM_CONFIG')[$platform_code];
        $class = PlatformApiService::getPlatformService($platform_config);

//        $url = $class::MASTER_URL.$class::POST_URL_FACTORY_ORDER_APPOINT_RECORD['path'];
        $url = $class::TEST_URL.$class::POST_URL_FACTORY_ORDER_APPOINT_RECORD['path'];
        $push_data = [
                'rpId' => (int)$xf_order_id,
                'centerPromiseDate' => $appoint['appoint_time'],
                'promiseNote' => $appoint['remark'],
                'handler' => $record['operator'],
                'centerPromiseTimespan' => $am_pm,
            ] + $class::POST_URL_FACTORY_ORDER_APPOINT_RECORD['params'];
        curlPostHttps($url, $push_data);
    }

}
