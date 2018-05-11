<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/21
 * Time: 15:21
 */

namespace PlatformApi\Controller;


use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\PlatformApiService;
use PlatformApi\Logic\OrderLogic;

class OrderController extends BaseController
{

    public function createOrder()
    {
        try {
//            $this->requireAuth();
//            OrderOperationRecordService::create(658927, 4000, [
//                'remark' => '6666',
//                'content_replace' => [
//                    'appoint_time' => date('Y-m-d H:i:s', NOW_TIME+3600),
//                ],
//            ]);
//            die;
            $this->platFormRequireAuth();
//            $a = PlatformApiService::publicEncrypt([
//                'service_type' => '107', //   服务类型 107 上门维修, 106 上门安装, 110 预发件安装, 109 用户送修, 108 上门维护 (暂不支持 110 预发件安装单)
//                'order_type' => '1', // 保单类型 1 保内, 2 保外
//                'remark' => '', // 备注
//                'data' => [
//                    [
//                        'out_trade_number' => 'AB929u1412367826',  // 外部单号
//                        'user_info' => [
//                            'contact_type' => '1', // 联系号码的类型 1 手机号码； 暂支持1
//                            'contact_number' => '15011914383,15011914322',
//                            'contact_name' => 'lololoaahaks',
//                            'areas' => '广东-广州市-天河区',
//                            'area_detail' => '111222223333111',
//                        ],
//                        'products' => [
//                            [
//                                'category_code' => 'a7EydP2L57Y',
//                                'standard_code' => 'wlnz4QkJovG',
//                                'brand_code' => '96oPbw1K0qz',
//                                'brand_name' => '新飞',
//                                'model_code' => 'Qq65ZRAwlvR',
//                                'model_name' => 'BCD126DA',
//                                'nums' => 1,
//                                'service_request' => '服务要求',
//                            ]
//                        ],
//                        'express' => [], // 物流信息 配合预发件安装单使用
//                    ],
//                ],
//            ]);
//            var_dump($a);die;
//            OrderOperationRecordService::create(658658, OrderOperationRecordService::FACTORY_ORDER_CREATE);
//            die;
            $logic = new OrderLogic();
            M()->startTrans();
            $result = $logic->createOrder();
            M()->commit();
            $this->response($result);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
