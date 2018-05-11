<?php
/**
* @User 嘉诚
* @Date 2017/11/13
* @mess 订单
*/
namespace Qiye\Logic;

use Common\Common\Service\AppMessageService;
use Common\Common\Service\GroupService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SMSService;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Library\Common\Util;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Logic\ExpressTrackLogic;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;

class PublicLogic extends BaseLogic
{
    /*
     * 获取物流公司列表
     */
    public function expressCompanies()
    {
        $model = BaseModel::getInstance('express_com');
        $name = I('get.name', '');
        $express_num = I('get.express_num', '');
        $where = [];
        if (!empty($name)) {
            $where['name'] = ['like', '%'.$name.'%'];
        }
        if (!empty($express_num)) {
            $code_com_list = (new ExpressTrackLogic())->autoComCode($express_num);
            $in = arrFieldForStr($code_com_list, 'comCode');
            if (empty($in)) {
                $in = '0';
            }
            $where['comcode'] = ['in', $in];
        }
        $opt = [
            'where' => $where,
            'limit' => getPage(),
            'order' => 'id ASC',
        ];
        $count = $model->getNum($where);
        $list = $count ? $model->getList($opt) : [];
        return $this->paginate($list, $count);
    }

    /*
     * 物流查询
     */
    public function expresses($request)
    {
        $where['type'] = $request['type'];
        $where['data_id'] = $request['data_id'];
        if (!empty($request['express_number'])) {
            $where['express_number'] = $request['express_number'];
        }
        $opt = [
            'where' => $where,
            'field' => '*'
        ];
        $model = BaseModel::getInstance('express_tracking');
        $data = $model->getOne($opt);

        // 未订阅并且在数据里，并且订阅查询率为两分钟
        if ($data && ($data['is_book'] == 0 || !$data['is_book']) && $data['last_update_time'] + 120 < NOW_TIME) {
            // 订阅物流信息
            expressTrack($data['express_code'], $data['express_number'], $data['data_id'], $data['type']);
        }

        $data['comcode_name'] = BaseModel::getInstance('express_com')->getFieldVal(['comcode' => $data['express_code']], 'name');

        if (!empty($data['content'])) {
            $data['content'] = json_decode($data['content'], true);
        }

        if (in_array($request['type'], ['1', '2'])) {
            //配件单物流
            $factory = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
                'alias' => 'aa',
                'where' => [
                    'aa.id' => $request['data_id']
                ],
                'join'  => 'left join factory as f on f.factory_id=aa.factory_id',
                'field' => 'f.receive_person as linkman, f.receive_tell as linkphone'
            ]);
        } elseif ($request['type'] == '3') {
            //预发件安装单发件
            $factory = BaseModel::getInstance('worker_order')->getOne([
                'alias' => 'wo',
                'where' => [
                    'wo.id' => $request['data_id']
                ],
                'join'  => 'left join factory as f on f.factory_id=wo.factory_id',
                'field' => 'f.receive_person as linkman, f.receive_tell as linkphone'
            ]);
        } else {
            return null;
        }

        return [
            'express' => $data,
            'factory' => $factory,
        ];
    }

    /*
     * 联保价格
     */
    public function orderAtCategory($user_id)
    {
        $user_type = BaseModel::getInstance('worker')->getFieldVal($user_id, 'type');
        if ($user_type == GroupService::WORKER_TYPE_GROUP_MEMBER) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '暂不允许您访问该页面');
        }
        $return = [];
        $ids = '659004514,659005490,659004533,25,30,659004686,2,659004522,659004599,659004587,659004584,659004690';
        $model = D('Product');
        $list_1 = $model->getCmListItemByIds($ids, false, 'item_sort ASC');

        $list_2 = $model->getCmChildrens($ids, '*', false, 'item_sort ASC');
        $ids_2 = arrFieldForStr($list_2, 'list_item_id');

        $standard_field = 'standard_id,product_id as product_category,standard_name,fault_ids';
        $standard = $ids_2 ? BaseModel::getInstance('product_standard')->getList([
            'where' => [
                'product_id' => ['in', $ids_2],
            ],
            'field' => $standard_field,
            'order' => 'standard_sort ASC',
        ]) : [];

        $faults = $ids_2 ? BaseModel::getInstance('product_miscellaneous')->getList([
            'where' => [
                'product_id' => ['in', $ids_2],
            ],
            'index' => 'product_id',
        ]) : [];

        $category_second_fault = [];
        foreach ($list_2 as $k => $v) {
            isset($faults[$v['list_item_id']]) && ($category_second_fault[$v['list_item_id']] = $faults[$v['list_item_id']]['product_faults']);
        }

        $check_fault_ids = implode(',', array_unique(array_filter(explode(',', arrFieldForStr($faults, 'product_faults')))));
        $price = $check_fault_ids ? BaseModel::getInstance('product_fault')->getList([
            'alias' => 'PF',
            'join'  => 'LEFT JOIN product_fault_price PFP ON PF.id = PFP.fault_id',
            'where' => [
                'PF.id' => ['in', $check_fault_ids],
                'PF.fault_type' => ['in', '0,1,2'],
            ],
            'field' => 'PF.id,PF.fault_name,PF.fault_type,PFP.product_id as product_category,PFP.standard_id,PFP.factory_in_price,PFP.factory_out_price,PFP.worker_in_price,PFP.worker_out_price',
            'order' => 'PF.sort ASC',
        ]) : [];

        $return['category_first'] = $list_1;
        $return['category_second'] = $list_2;
        $return['category_second_fault'] = $category_second_fault;
        $return['standard'] = $standard;
        $return['price'] = $price;

        return $return;
    }

    public function sendSmsToFactoryAndWorker()
    {
        $time = '';
        if (!empty($time)) {
            //厂家
            $factory_phones = BaseModel::getInstance('factory')->getFieldVal([
                'group' => 'linkphone'
            ],'linkphone', true);
            $factory_send_phones = $this->getPhone($factory_phones);
            foreach ($factory_send_phones as $v) {
                sendSms($v, SMSService::TMP_ALL_FACTORY_TO_SEND, [
                    'time' => $time
                ]);
            }

            //安卓技工
            $android_worker_phones = BaseModel::getInstance('worker')->getFieldVal([
                'alias' => 'w',
                'where' => [
                    'w.app_version' => ['neq', ''],
                    'w.last_login_time' => ['gt', (NOW_TIME - 60 * 86400)]
                ],
                'group' => 'worker_telephone'
            ], 'worker_telephone', true);
            $android_send_phones = $this->getPhone($android_worker_phones);
            foreach ($android_send_phones as $v) {
                sendSms($v, SMSService::TMP_ALL_WORKER_FOR_ANDROID_TO_SEND);
            }

            //ios技工
            $ios_worker_phones = BaseModel::getInstance('worker')->getFieldVal([
                'alias' => 'w',
                'where' => [
                    'w.app_version' => ['eq', ''],
                    'w.last_login_time' => ['gt', (NOW_TIME - 60 * 86400)]
                ],
                'group' => 'worker_telephone'
            ], 'worker_telephone', true);
            $ios_send_phones = $this->getPhone($ios_worker_phones);
            foreach ($ios_send_phones as $v) {
                sendSms($v, SMSService::TMP_ALL_WORKER_FOR_IOS_TO_SEND);
            }
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '发送失败，请检查时间');
        }
    }

    public function getPhone($phones)
    {
        $phones = array_chunk(array_filter($phones),1000,true);
        $send_phones = [];
        foreach ($phones as $v) {
            $phone_str = '';
            foreach ($v as $value) {
                $phone_str .= $value.',';
            }
            $send_phones[] = substr($phone_str, 0, -1);
        }
        return $send_phones;
    }

    /*
     * 旧保外单修改支付状态为已完成
     */
    public function updateOldWarrantyOrder()
    {
        set_time_limit(0);
        $order_ids = BaseModel::getInstance('worker_order')->getList([
            'alias' => 'wo',
            'where' => [
                'wo.cancel_status' => OrderService::CANCEL_TYPE_NULL,
                'wo.worker_order_status' => ['in', [
                    OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED
                ]],
                'wo.worker_order_type' => ['not in', OrderService::ORDER_TYPE_IN_INSURANCE_LIST],
                'woui.is_user_pay' => ['not in', '1,2']
            ],
            'join'  => 'left join worker_order_user_info as woui on woui.worker_order_id=wo.id',
            'field' => 'wo.id'
        ]);
        M()->startTrans();

        foreach ($order_ids as $v) {
            //添加操作记录
            $data = [
                'worker_order_id' => $v['id'],
                'worker_order_product_id' => 0,
                'create_time' => NOW_TIME,
                'operator_id' => 0,
                'operation_type' => OrderOperationRecordService::SYSTEM_ORDER_OUT_SYSTEM_AUTO_AUDITOR_SUCCESS,
                'content' => '保外单，系统自动审核工单（审核通过）',
                'remark'  => '保外单旧数据，默认将用户的支付状态改为：已支付（现金支付）',
                'is_super_login' => 0,
                'see_auth' => 1,
                'is_system_create' => 0
            ];
            $insert_data[] = $data;
        }

        $order_ids = !empty($order_ids) ? implode(',', $order_ids) : '0';

        BaseModel::getInstance('worker_order_user_info')->update([
            'worker_order_id' => ['in', $order_ids]
        ], [
            'pay_type' => 2,
            'is_user_pay' => 1
        ]);
        if (!empty($insert_data)) {
            BaseModel::getInstance('worker_order_operation_record')->insertAll($insert_data);
        }

        M()->commit();
    }

}
