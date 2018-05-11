<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/7/31
 * Time: 下午6:30
 */

namespace Script\Controller;

//use Api\Common\ErrorCode;
use Common\Common\ErrorCode;
use QiuQiuX\IndexedArray\IndexedArray;
use Script\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Common\Util;

class RecordController extends BaseController
{
    const ORDER_TABLE_NAME = 'worker_order';
    const ORDER_APPLY_COST_TABLE_NAME = 'worker_order_apply_cost';
    const ORDER_COMPLAINT_TABLE_NAME = 'worker_order_complaint';
    const ORDER_APPLY_ALLOWANCE_TABLE_NAME = 'worker_order_apply_allowance';
    const ORDER_ADD_APPLY_TABLE_NAME = 'worker_add_apply';
    const ORDER_APPLY_ACCESSORY_TABLE_NAME = 'worker_order_apply_accessory';
    const ORDER_MESSAGE_TABLE_NAME = 'worker_order_message';
    const ORDER_STATISTICS_TABLE_NAME = 'worker_order_statistics';

    public function workerOrderStatistics()
    {
        set_time_limit(0);
        $rule_num = 10000;

        $default_insert = [
            'worker_order_id'                   => '0',
            'accessory_order_num'               => '0',
            'cost_order_num'                    => '0',
            'allowance_order_num'                 => '0',
            'complaint_order_num'               => '0',
            'total_accessory_num'               => '0',
            'accessory_unsent_num'              => '0',
            'accessory_worker_unreceive_num'    => '0',
            'accessory_unreturn_num'            => '0',
            'total_message_num'                 => '0',
            'unread_message_num'                => '0',
            'unread_message_worker'             => '0',
            'unread_message_factory'            => '0',
            'unread_message_admin'              => '0',
            'worker_add_apply_num'              => '0',
        ];
        try {
            $where = [];
            $model = BaseModel::getInstance(self::ORDER_TABLE_NAME);
            do {
                $list = $model->getList([
                    'field' => 'id',
                    'where' => $where,
                    'limit' => $rule_num,
                    'order' => 'id asc',
                ]);
                $where['_string'] = ' id > '.end($list)['id'].' ';

                $order_ids = arrFieldForStr($list, 'id');

                if ($order_ids) {
                    // cost_order_num       => worker_order_apply_cost          => count(worker_order_id)
                    $cost_order_num = BaseModel::getInstance(self::ORDER_APPLY_COST_TABLE_NAME)->getList([
                            'field' => 'worker_order_id,count(worker_order_id) as cost_order_num',
                            'where' => [
                                'worker_order_id' => ['in', $order_ids],
                            ],
                            'group' => 'worker_order_id',
                            'index' => 'worker_order_id',
                        ]);
                    // complaint_order_num  => worker_order_complaint           => count(worker_order_id)
                    $complaint_order_num = BaseModel::getInstance(self::ORDER_COMPLAINT_TABLE_NAME)->getList([
                            'field' => 'worker_order_id,count(worker_order_id) as complaint_order_num',
                            'where' => [
                                'worker_order_id' => ['in', $order_ids],
                            ],
                            'group' => 'worker_order_id',
                            'index' => 'worker_order_id',
                        ]);

                    // allowance_order_num    => worker_order_apply_allowance     => count(worker_order_id)
                    $allowance_order_num = BaseModel::getInstance(self::ORDER_APPLY_ALLOWANCE_TABLE_NAME)->getList([
                            'field' => 'worker_order_id,count(worker_order_id) as allowance_order_num',
                            'where' => [
                                'worker_order_id' => ['in', $order_ids],
                            ],
                            'group' => 'worker_order_id',
                            'index' => 'worker_order_id',
                        ]);
                    // complaint_order_num  => worker_order_complaint           => count(worker_order_id)
                    // $complaint_order_num = BaseModel::getInstance(self::ORDER_APPLY_ALLOWANCE_TABLE_NAME)->getList([
                    //         'field' => 'worker_order_id,count(worker_order_id) as complaint_order_num',
                    //         'where' => [
                    //             'worker_order_id' => ['in', $order_ids],
                    //         ],
                    //         'group' => 'worker_order_id',
                    //         'index' => 'worker_order_id',
                    //     ]);
                    // worker_add_apply_num => worker_add_apply => count(worker_order_id)
                    $worker_add_apply_num = BaseModel::getInstance(self::ORDER_ADD_APPLY_TABLE_NAME)->getList([
                            'field' => 'worker_order_id,count(worker_order_id) as worker_add_apply_num',
                            'where' => [
                                'worker_order_id' => ['in', $order_ids],
                            ],
                            'group' => 'worker_order_id',
                            'index' => 'worker_order_id',
                        ]);
                    // accessory_order_num  => worker_order_apply_accessory     => count(worker_order_id)
                    // total_accessory_num  => worker_order_apply_accessory     => count(worker_order_id)
                    // accessory_unsent_num => worker_order_apply_accessory     => count(cancel_status=0,accessory_status=1,3,5)
                    // accessory_worker_unreceive_num => worker_order_apply_accessory => count(cancel_status=0,accessory_status=6) 
                    // accessory_unreturn_num => worker_order_apply_accessory => count(is_giveup_return=0,accessory_status=7)
                    $accessory = BaseModel::getInstance(self::ORDER_APPLY_ACCESSORY_TABLE_NAME)->getList([
                            'field' => 'worker_order_id,count(worker_order_id) as accessory_order_num,count(worker_order_id) as total_accessory_num,SUM(IF(cancel_status=0&&(accessory_status=1||accessory_status=3||accessory_status=5),1,0)) as accessory_unsent_num,SUM(IF(cancel_status=0&&accessory_status=6,1,0)) as accessory_worker_unreceive_num,SUM(IF(is_giveup_return=0&&accessory_status=7,1,0)) as accessory_unreturn_num',
                            'where' => [
                                'worker_order_id' => ['in', $order_ids],
                            ],
                            'group' => 'worker_order_id',
                            'index' => 'worker_order_id',
                        ]);
                    // total_message_num    => worker_order_message => count(worker_order_id)
                    // unread_message_num   => worker_order_message => count(is_read=0)
                    // unread_message_worker => worker_order_message => count(is_read=0,receive_type=4)
                    // unread_message_factory => worker_order_message => count(is_read=0,receive_type=2,3)
                    // unread_message_admin => worker_order_message => count(is_read=0,receive_type=1)
                    $messages = BaseModel::getInstance(self::ORDER_MESSAGE_TABLE_NAME)->getList([
                            'field' => 'worker_order_id,count(worker_order_id) as total_message_num,SUM(IF(is_read=0,1,0)) as unread_message_num,SUM(IF(is_read=0&&receive_type=4,1,0)) as unread_message_worker,SUM(IF(is_read=0&&(receive_type=2||receive_type=3),1,0)) as unread_message_factory,SUM(IF(is_read=0&&receive_type=1,1,0)) as unread_message_admin',
                            'where' => [
                                'worker_order_id' => ['in', $order_ids],
                            ],
                            'group' => 'worker_order_id',
                            'index' => 'worker_order_id',
                        ]);

                    $all = $insert = null;
                    foreach ($list as $k => $v) {
                        $default_insert['worker_order_id'] = $v['id'];
                        $insert = array_merge(
                            (array)$cost_order_num[$v['id']],
                            (array)$complaint_order_num[$v['id']],
                            (array)$allowance_order_num[$v['id']],
                            (array)$worker_add_apply_num[$v['id']],
                            (array)$accessory[$v['id']],
                            (array)$messages[$v['id']]
                        );

                        // var_dump($insert, array_merge($default_insert, $insert));

                        $all[] = array_merge($default_insert, $insert);
                    }
                    if ($all) {
                        M()->startTrans();
                        $statistics = BaseModel::getInstance(self::ORDER_STATISTICS_TABLE_NAME);
                        $statistics->remove(['worker_order_id' => ['in', $order_ids]]);
                        $statistics->insertAll($all);
                        M()->commit();      
                    }
                }
            } while ($list);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function auditRemarkExample()
    {
        set_time_limit(0);
        try {
            $table_name = 'worker_order_settle_note';
            $backup_name = 'worker_order_settle_note_backup';
            $rename_name = 'worker_order_audit_remark';

            $is_exists = [];
            $is_exists['table_name']  = M()->query("SHOW TABLES LIKE '{$table_name}'")  ? true : false;
            $is_exists['backup_name'] = M()->query("SHOW TABLES LIKE '{$backup_name}'") ? true : false;
            $is_exists['rename_name'] = M()->query("SHOW TABLES LIKE '{$rename_name}'") ? true : false;
                
            $this->response($is_exists);

            $recondinfo = BaseModel::getInstance('worker_order_settle_note')->getList([
                'field' => 'group_concat(id) as ids,adName as add_name',
                'group' => 'add_name',
                'index' => 'add_name',
            ]);
            $name_arr = array_keys($recondinfo);
            $name_arr_filter = array_filter($name_arr);

            $admin_list = $name_arr_filter ? BaseModel::getInstance('admin')->getList([
                    'where' => [
                        'user_name' => ['in', $name_arr_filter],
                    ],
                    'field' => 'id,user_name',
                ]) : [];

            $this->response($admin_list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function auditRemark()
    {
        set_time_limit(0);
        $next_span_nums = 10000;
        $where_id = 0;
        try {
            M()->startTrans();
            $admin_info = BaseModel::getInstance('admin')->getList([
                'field'  => 'id,user_name',
                'index' => 'user_name'
            ]);
            do {
                $recondinfo = BaseModel::getInstance('worker_order_settle_note_backup')->getList([
                    'where' => [
                        'id' => ['GT', $where_id],
                    ],
                    'order' => 'id asc',
                    'limit' => $next_span_nums,
                ]);
                $list = [];
                foreach ($recondinfo as $k => $v) {
                    switch ($v['adname'])
                    {
                        case '' :
                            $v['adname'] = '客服001';
                            break;
                        case '黄彩云' :
                            $v['adname'] = '客服001';
                            break;
                        case '回访客服' :
                            $v['adname'] = '回访客服02';
                            break;
                        case '渠道101' :
                            $v['adName'] = '渠道119';
                            break;
                        case '特美声售后' :
                            $v['adname'] = '大客户售后';
                            break;
                        case '余客服' :
                            $v['adname'] = '工单客服000';
                            break;
                    }
                    $v['admin_id'] = intval($admin_info[$v['adname']]['id']);
                    $v['create_time'] = $v['add_time'];
                    $v['id'] = intval($v['id']);
                    unset($v['adname']);
                    unset($v['add_time']);

                    $list[$k] = $v;
                }
                if (!empty($list)) {
                    BaseModel::getInstance('worker_order_audit_remark')->insertAll(array_values($list));
                    $where_id = end($recondinfo)['id'];
                }
                unset($list);
            }while($recondinfo);
            M()->commit();
//            sort($list);
//            $splitNum = 1000;
//            foreach(array_chunk($list, $splitNum) as $values){
//                D('Publics', 'Logic')->updateAll('worker_order_audit_remark', $values, 'id');
////                updateAll('worker_order_audit_remark', $values, 'id');
//            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function workerContact()
    {
        try{
            M()->startTrans();
            $backinfo = BaseModel::getInstance('worker_contact_record_backup')->getList([
                'field' => 'id,object_type,contact_method,contact_type,contact_result,contact_gu',
                'index' => 'id',
            ]);
            $data = [];
            foreach ($backinfo as $k => $v) {
                switch ($v['object_type'])
                {
                    case '维修商':
                        $v['contact_object'] = 1;
                        break;
                    case '零售商':
                        $v['contact_object'] = 2;
                        break;
                    case '零售商带维修商':
                        $v['contact_object'] = 3;
                        break;
                    case '商家':
                        $v['contact_object'] = 4;
                        break;
                    case '批发商':
                        $v['contact_object'] = 5;
                        break;
                    case '批发商带维修商':
                        $v['contact_object'] = 6;
                        break;
                    default :
                        $v['contact_object'] = 0;
                        $v['contact_object_other'] = $v['object_type'];
                        break;
                }
                switch ($v['contact_method'])
                {
                    case '电话':
                        $v['contact_method'] = 1;
                        break;
                    case '微信':
                        $v['contact_method'] = 2;
                        break;
                    case 'QQ':
                        $v['contact_method'] = 3;
                        break;
                    case '短信':
                        $v['contact_method'] = 4;
                        break;
                }
                switch ($v['contact_type'])
                {
                    case '派单咨询':
                        $v['contact_type'] = 1;
                        break;
                    case '例行联系':
                        $v['contact_type'] = 2;
                        break;
                    case '维修报价':
                        $v['contact_type'] = 3;
                        break;
                    case '技术咨询':
                        $v['contact_type'] = 4;
                        break;
                    case '代找网点':
                        $v['contact_type'] = 5;
                        break;
                    case '其他':
                        $v['contact_type'] = 6;
                        break;
                }
                switch ($v['contact_result'])
                {
                    case '可以':
                        $v['contact_result'] = 1;
                        break;
                    case '不可以':
                        $v['contact_result'] = 2;
                        break;
                    case '其他':
                        $v['contact_result'] = 3;
                        break;
                }
                switch ($v['contact_gu'])
                {
                    case '可以合作':
                        $v['contact_report'] = 1;
                        break;
                    case '考虑合作':
                        $v['contact_report'] = 2;
                        break;
                    case '不用再联系':
                        $v['contact_report'] = 3;
                        break;
                    default:
                        $v['contact_report'] = 4;
                        break;
                }
                $data[$v['id']]['contact_object'] = $v['contact_object'];
                $data[$v['id']]['contact_method'] = $v['contact_method'];
                $data[$v['id']]['contact_type'] = $v['contact_type'];
                $data[$v['id']]['contact_result'] = $v['contact_result'];
                $data[$v['id']]['contact_report'] = $v['contact_report'];
                $data[$v['id']]['id'] = $v['id'];
            }
            $splitNum = 2000;
            $res = 0;
            foreach(array_chunk($data, $splitNum) as $values){
                $res = D('Publics', 'Logic')->updateAll('worker_contact_record', $values, 'id');
            }
            if ($res < 1) {
                $this->fail(ErrorCode::SYS_DB_ERROR, '更新失败');
            }
            M()->commit();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function orderRevisit()
    {
        set_time_limit(0);
        $next_span_nums = 30000;
        $where_id = 0;
        try {
            M()->startTrans();
            $admin_info = BaseModel::getInstance('admin')->getList([
                'field'  => 'id,user_name',
                'index' => 'user_name'
            ]);
            $fields = new \SplFixedArray(10);
            $fields->offsetSet(0, 'id');
            $fields->offsetSet(1, 'worker_order_id');
            $fields->offsetSet(2, 'admin_id');
            $fields->offsetSet(3, 'is_visit_ontime');
            $fields->offsetSet(4, 'irregularities');
            $fields->offsetSet(5, 'is_user_satisfy');
            $fields->offsetSet(6, 'repair_quality_score');
            $fields->offsetSet(7, 'not_visit_reason');
            $fields->offsetSet(8, 'return_remark');
            $fields->offsetSet(9, 'create_time');
            do {
                $revisit_info = BaseModel::getInstance('worker_order_revisit_backup')->getList([
                    'where' => [
                        'id' => ['GT', $where_id],
                    ],
                    'order' => 'id asc',
                    'limit' => $next_span_nums,
                ]);
                $list = new IndexedArray($next_span_nums);
                foreach ($revisit_info as $k => $v) {
                    switch ($v['add_name']) {
                        case '黄彩云':
                            $v['add_name'] = '客服001';
                            break;
                        case '回访客服':
                            $v['add_name'] = '回访客服02';
                            break;
                        case '回访':
                            $v['add_name'] = '回访客服02';
                            break;
                        case '渠道101':
                            $v['add_name'] = '渠道119';
                            break;
                        case 'admin':
                            $v['add_name'] = '爱皮皮技术';
                            break;
                        case '特美声售后':
                            $v['add_name'] = '大客户售后';
                            break;
                        case '回访001':
                            $v['add_name'] = '回访客服01';
                            break;
                        case '客服A':
                            $v['add_name'] = '工单客服000';
                            break;
                        case '':
                            $v['add_name'] = '工单客服000';
                            break;
                    }
                    $vals = new \SplFixedArray(10);
                    $vals->offsetSet(0, intval($v['id']));
                    $vals->offsetSet(1, intval($v['worker_order_id']));
                    $vals->offsetSet(2, intval($admin_info[$v['add_name']]['id']));
                    $vals->offsetSet(3, $v['is_work_apptime']);
                    $vals->offsetSet(4, $v['behavior']);
                    $vals->offsetSet(5, $v['is_work_satisfy']);
                    $vals->offsetSet(6, $v['quality_fraction']);
                    $vals->offsetSet(7, $v['work_hs_reason']);
                    $vals->offsetSet(8, $v['work_remarks']);
                    $vals->offsetSet(9, $v['add_time']);

                    $list->offsetSet($k, $vals);
                }
                if ($list->getSize() > 0) {
                    BaseModel::getInstance('worker_order_revisit_record')->batchInsert($fields, $list);
                    $where_id = $list->offsetGet($list->getSize() - 1)->offsetGet(0);
                }
                unset($list);
            }while($revisit_info);
            M()->commit();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function orderMessage()
    {
//        $a = memory_get_usage();
        set_time_limit(0);
        $next_span_nums = 10000;
        $where_id = 0;
        $admin_info = BaseModel::getInstance('admin')->getList([
            'field'  => 'id,user_name',
            'index' => 'user_name'
        ]);
//        $b = memory_get_usage();
//        $neicun = $b - $a;
//        var_dump($admin_info['n']['id']);exit;
        M()->startTrans();
        do {
            $message_info = BaseModel::getInstance('worker_order_mess_backup')->getList([
                'alias' => 'M',
                'where' => [
                    'id' => ['GT', $where_id],
                    '_string' => 'W.order_id  IS NOT NULL',
                ],
                'field' => 'M.*,W.order_id,W.factory_id,W.add_member_id,W.add_member_role',
                'join' => [
                    'LEFT JOIN worker_order W ON W.order_id = M.worker_order_id',
                ],
                'order' => 'id asc',
                'limit' => $next_span_nums,
                'index' => 'id'
            ]);
            $order_id = $update_data = $add = $change =[];
            foreach ($message_info as $k => $v) {
                switch ($v['mess_role'])
                {
                    case 'A' :
                        if ($v['type'] == 'W') {
                            $v['add_type'] = 4;
                            $v['receive_type'] = 1;
                            $v['add_id'] = $v['worker_id'];
                        } else {
                            $v['add_type'] = 1;
                            $v['receive_type'] = 4;
                        }
                        break;
                    case 'B' :
                        if ($v['type'] == 'F') {
                            $v['add_type'] = 2;
                            $v['receive_type'] = 1;
                            $v['add_id'] = $v['factory_id'];
                        } else {
                            $v['add_type'] = 1;
                            $v['receive_type'] = 2;
                        }
                        break;
                }
                if ($v['type'] == 'S') {
                    $v['add_id'] = $admin_info[$v['name']]['id'];
                    if (empty($v['add_id'])) {
                        $order_id[$v['worker_order_id']] = $v['worker_order_id'];
                        $change[$v['worker_order_id']][] = $v['id'];
                    }
                }
                $add[$v['id']] = [
                    'id' => intval($v['id']),
                    'worker_order_id' => $v['worker_order_id'],
                    'add_id' => intval($v['add_id']),
                    'add_type' => $v['add_type'],
                    'content' => $v['content'],
                    'create_time' => $v['addtime'],
                    'receive_type' => $v['receive_type'],
                ];
                
                unset($message_info[$k]);
            }
            // $order_ids = array_filter(explode(',', $order_id));
            $order_ids = implode(',', array_filter($order_id));
            $list = $order_ids ? BaseModel::getInstance('worker_order_access')->getList([
                'where' => [
                    'role_id' => 5,
                    'link_order_id' => ['in', $order_ids]
                ],
                'index' => 'link_order_id',
            ]) : [];
            $add_id = [];
            foreach ($change as $k => $v) {
                foreach ($v as $value) {
                    $data = $list[$k];
                    $add_id[$value] = [
                        'add_id' => intval(),
                    ];
                }

            }
//            $str = 'INSERT INTO `worker_order_message` (`id`,`worker_order_id`,`add_id`,`add_type`,`content`,`create_time`,`receive_type`) VALUES ';
//            file_put_contents("./sql_backup/yima_v2.0/count.sql", $str, FILE_APPEND);

            foreach ($add_id as $kr => $k) {
                 $add[$kr]['add_id'] = $k['add_id'];
            }
//            $adds = array_splice($add, 0, 20000);
            try {
                BaseModel::getInstance('worker_order_message')->insertAll(array_values($add));
            } catch (\Exception $e) {
               throw_exception($e);
            }

        }while($message_info);
        M()->commit();

    }

    public function express_back()
    {
        set_time_limit(0);
        $next_span_nums = 1500;
        $where_id = 0;
        try {
            $fields = new \SplFixedArray(10);
            $fields->offsetSet(0, 'id');
            $fields->offsetSet(1, 'express_number');
            $fields->offsetSet(2, 'express_code');
            $fields->offsetSet(3, 'data_id');
            $fields->offsetSet(4, 'state');
            $fields->offsetSet(5, 'content');
            $fields->offsetSet(6, 'is_book');
            $fields->offsetSet(7, 'type');
            $fields->offsetSet(8, 'create_time');
            $fields->offsetSet(9, 'last_update_time');
            do {
                M()->startTrans();
                $express_info = BaseModel::getInstance('express_track_backup')->getList([
                    'where' => [
                        'id' => ['GT', $where_id],
                    ],
                    'order' => 'id asc',
                    'limit' => $next_span_nums,
                ]);
                $list = new IndexedArray($next_span_nums);
                foreach ($express_info as $k => $v) {
                    switch ($v['type']) {
                        case 'SO' :
                            $v['type'] = 1;
                            break;
                        case 'SB' :
                            $v['type'] = 2;
                            break;
                        case 'WSO' :
                            $v['type'] = 3;
                            break;
                    }

                    $vals = new \SplFixedArray(10);
                    $vals->offsetSet(0, intval($v['id']));
                    $vals->offsetSet(1, $v['number']);
                    $vals->offsetSet(2, $v['comcode']);
                    $vals->offsetSet(3, intval($v['acor_id']));
                    $vals->offsetSet(4, $v['state']);
                    $vals->offsetSet(5, $v['content']);
                    $vals->offsetSet(6, $v['is_book']);
                    $vals->offsetSet(7, $v['type']);
                    $vals->offsetSet(8, intval($v['addtime']));
                    $vals->offsetSet(9, intval($v['last_uptime']));

                    $list->offsetSet($k, $vals);
                }

                if ($list->getSize() > 0) {
                    BaseModel::getInstance('express_tracking')->batchInsert($fields, $list);
                    $where_id = $list->offsetGet($list->getSize() - 1)->offsetGet(0);
                }
                unset($list);

            }while ($express_info);
            M()->commit();
        }  catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function express()
    {
        set_time_limit(0);
        //利用旧表数据更新到新标
        try {
            M()->startTrans();
            $express_info = BaseModel::getInstance('express_track_backup')->getList([
                'field' => 'id,`type`',
                'order' => 'id asc',
                'index' => 'id'
            ]);
            foreach ($express_info as $k => $v) {
                switch ($v['type'])
                {
                    case 'SO' :
                        $v['type'] = 1;
                        break;
                    case 'SB' :
                        $v['type'] = 2;
                        break;
                    case 'WSO' :
                        $v['type'] = 3;
                        break;
                }
                $express_info[$k] = $v;
            }
            $splitNum = 1000;
            $res = 0;
            foreach(array_chunk($express_info, $splitNum) as $values){
                $res = D('Publics', 'Logic')->updateAll('express_tracking', $values, 'id');
            }
            if ($res<1) {
                $this->fail(ErrorCode::SYS_DB_ERROR, '更新失败');
            }
            M()->commit();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function complaint()
    {
        try {
            M()->startTrans();
            $complaint_info = BaseModel::getInstance('worker_order_complaint')->getList([
                'alias' => 'M',
                'field' => 'M.id,M.cp_complaint_type_name,M.cp_complaint_type_name_modify,M.complaint_from_type,M.complaint_to_type,W.id,W.complaint_from_type as complaint_from_type_back,W.complaint_to_type as complaint_to_type_back',
                'join' => [
                    'LEFT JOIN worker_order_complaint_backup W ON W.id = M.id',
                ],
                'index' => 'id'
            ]);
            $update_complaint = [];
            foreach ($complaint_info as $k => $v) {
                switch ($v['complaint_from_type_back'])
                {
                    case 'F':
                        $v['complaint_from_type'] = 1;
                        break;
                    case 'W':
                        $v['complaint_from_type'] = 2;
                        break;
                    case 'S':
                        $v['complaint_from_type'] = 3;
                        break;
                }
                switch ($v['complaint_to_type_back'])
                {
                    case 'F':
                        $v['complaint_to_type'] = 1;
                        break;
                    case 'W':
                        $v['complaint_to_type'] = 2;
                        break;
                    case 'S':
                        $v['complaint_to_type'] = 3;
                        break;
                }
                switch ($v['cp_complaint_type_name'])
                {
                    case '0' :
                        $v['complaint_type_id'] = 0;
                        break;
                    case '1' :
                        $v['complaint_type_id'] = 1;
                        break;
                    case '2' :
                        $v['complaint_type_id'] = 2;
                        break;
                    case '3' :
                        $v['complaint_type_id'] = 3;
                        break;
                    case '4' :
                        $v['complaint_type_id'] = 4;
                        break;
                    case '5' :
                        $v['complaint_type_id'] = 5;
                        break;
                    case '6' :
                        $v['complaint_type_id'] = 6;
                        break;
                    case '7' :
                        $v['complaint_type_id'] = 7;
                        break;
                    case '8' :
                        $v['complaint_type_id'] = 15;
                        break;
                    case '9' :
                        $v['complaint_type_id'] = 16;
                        break;
                    case '10' :
                        $v['complaint_type_id'] = 17;
                        break;
                    case '11' :
                        $v['complaint_type_id'] = 18;
                        break;
                    case '12' :
                        $v['complaint_type_id'] = 19;
                        break;
                }
                switch ($v['cp_complaint_type_name_modify'])
                {
                    case '0' :
                        $v['complaint_modify_type_id'] = 0;
                        break;
                    case '1' :
                        $v['complaint_modify_type_id'] = 8;
                        break;
                    case '2' :
                        $v['complaint_modify_type_id'] = 9;
                        break;
                    case '3' :
                        $v['complaint_modify_type_id'] = 2;
                        break;
                    case '4' :
                        $v['complaint_modify_type_id'] = 10;
                        break;
                    case '5' :
                        $v['complaint_modify_type_id'] = 5;
                        break;
                    case '6' :
                        $v['complaint_modify_type_id'] = 4;
                        break;
                    case '7' :
                        $v['complaint_modify_type_id'] = 11;
                        break;
                    case '8' :
                        $v['complaint_modify_type_id'] = 1;
                        break;
                    case '9' :
                        $v['complaint_modify_type_id'] = 12;
                        break;
                    case '10' :
                        $v['complaint_modify_type_id'] = 7;
                        break;
                    case '11' :
                        $v['complaint_modify_type_id'] = 13;
                        break;
                    case '12' :
                        $v['complaint_modify_type_id'] = 14;
                        break;
                    //客服
                    case 'S1' :
                        $v['complaint_modify_type_id'] = 16;
                        break;
                    case 'S2' :
                        $v['complaint_modify_type_id'] = 17;
                        break;
                    case 'S3' :
                        $v['complaint_modify_type_id'] = 18;
                        break;
                    case 'S4' :
                        $v['complaint_modify_type_id'] = 19;
                        break;
                }
                $update_complaint[] = [
                    'id' => intval($v['id']),
                    'complaint_from_type' => $v['complaint_from_type'],
                    'complaint_to_type' => $v['complaint_to_type'],
                    'complaint_type_id' => intval($v['complaint_type_id']),
                    'complaint_modify_type_id' => intval($v['complaint_modify_type_id']),
                ];
            }
            $splitNum = 3000;
            $res = 0;
            foreach(array_chunk($update_complaint, $splitNum) as $values){
                $res = D('Publics', 'Logic')->updateAll('worker_order_complaint', $values, 'id');
            }
            if ($res<1) {
                $this->fail(ErrorCode::SYS_DB_ERROR, '更新失败');
            }
            M()->commit();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }




}