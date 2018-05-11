<?php
/**
 * File: ApiCommonLogic.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 14:37
 */

namespace Api\Logic;

use Api\Repositories\Events\DealerActivatedEvent;
use Api\Repositories\Events\FactoryOrderToPlatformEvent;
use Api\Repositories\Events\FactoryOrderToSelfEvent;
use Api\Repositories\Events\OrderCancelEvent;
use Api\Repositories\Events\UserAddOrderEvent;
use Api\Repositories\Events\WorkerFinishedOrderEvent;
use Api\Repositories\Events\WorkerReserveEvent;
use Api\Repositories\Events\WorkerUnfinishedOrderEvent;

class ApiCommonLogic extends BaseLogic
{

    public function weChatMessage($type, $data)
    {
//        $data = \GuzzleHttp\json_decode(urldecode($data), true);
//        switch ($type) {
//            case 1:     // 1.2 厂家授权通过
//                event(new DealerActivatedEvent($data));
//                break;
//            case 2:     // 2.2 厂家选择下单给神州联保
//                event(new FactoryOrderToPlatformEvent($data));
//                break;
//            case 3:     // 2.3厂家选择自行处理
//                event(new FactoryOrderToSelfEvent($data));
//                break;
//            case 4:     // 2.5师傅上传服务报告>完成维修
//                event(new WorkerFinishedOrderEvent($data));
//                break;
//            case 5:     // 2.5师傅上传服务报告>不能完成维修
//                event(new WorkerUnfinishedOrderEvent($data));
//                break;
//            case 6:     // 2.6工单取消成功(用户取消、厂家取消，客服弃单)
//                event(new OrderCancelEvent($data));
//                break;
//            case 7:     // 2.4维修商已经上传预约成功上门记录
//                event(new WorkerReserveEvent($data));
//                break;
//
//        }
    }

}
