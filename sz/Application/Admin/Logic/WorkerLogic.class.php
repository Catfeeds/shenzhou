<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/27
 * Time: 18:11
 */

namespace Admin\Logic;

use Admin\Model\BaseModel;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\ComplaintService;
use Library\Common\Util;
use Admin\Common\ErrorCode;

class WorkerLogic extends BaseLogic
{
    // 逻辑分析处理分数缓存
    public function getCashDataReceivers($order = [], $sort_type = 0, $workers = [], $worker_ids = '', $area_id = '')
    {
        $s_key = $sort_type.'_'.C('S_KEY_PRE').$order['id'].'_'.$area_id;
        $s_score_key = $sort_type.'_'.C('S_SCORE_KEY_PRE').$order['id'];
        $guoqi = orderReceiversDataWorkerSortTime();

        $cash_data = ($s_key);
        $scores = $cash_data['scores'];

        $return = [];

        $worker_id_key_arr = array_flip(explode(',', $worker_ids));

        $delete_ids = array_keys(array_diff_key($scores, $worker_id_key_arr));
        $search_ids = implode(',', array_keys(array_diff_key($worker_id_key_arr, $scores)));

        if ($order['lat'] != $cash_data['lat'] || $order['lon'] != $cash_data['lon'] || !count($scores)) {
            S($s_score_key, null); // 清空分数

            $return = $this->getWorkerOrderByWorkerScoresAndOther($order, $sort_type, $workers, $worker_ids);
        } elseif ($search_ids) {
            $result = $this->getWorkerOrderByWorkerScoresAndOther($order, $sort_type, $workers, $search_ids);
            // $return[0] = array_merge((array)$scores, (array)$result[0]);
            $return[0] = (array)$result[0] + (array)$scores;
        } else {
            $return[0] = $scores;
        }

        foreach ($delete_ids as $key => $value) {
            unset($return[0][$key]);
        }

        // krsort($return[0]);
        // arsort($return[0]);

        $cash_data = [
            'scores' => $return[0],
            'lat'    => $order['lat'],
            'lon'    => $order['lon'],
        ];

        // S($s_key, null);
        S($s_key, $cash_data, ['expire' => $guoqi]);

        return $return;
    }

    // 计算分数
    public function getWorkerOrderByWorkerScoresAndOther($order = [], $sort_type = 0, $workers = [], $worker_ids = '')
    {

        $worker_statistics_field = '';
        $DISTANCE = '';
        switch ($sort_type) {
            case 1: // 签约优先
                $worker_statistics_field = ',contract_qualification_a as contract_qualification';
                $DISTANCE = 'DISTANCE_A';
                break;

            case 2: // 里程优先
                $worker_statistics_field = ',contract_qualification_b as contract_qualification';
                $DISTANCE = 'DISTANCE_B';
                break;

            default:
                // $worker_statistics_field = ',paid_order,no_complaint,no_cancel,on_work_time,appoint_time,return_time';
                break;
        }
        $statistics = $worker_ids ? BaseModel::getInstance('worker_statistics')
            ->getList([
                'field' => 'worker_id,paid_order,no_complaint,no_cancel,on_work_time,return_time,appoint_time' . $worker_statistics_field,
                'where' => [
                    'worker_id' => ['in', $worker_ids],
                ],
                'index' => 'worker_id',
            ]) : [];

        $scores_arr = $this->orderReceivesByPuoduct($order, $worker_ids);

        $scores = $other = [];
        foreach ($workers as $key => $value) {
            $k = $value['worker_id'];
            $lat_lon = 0; //  上门里程分数
            $mi = 0;
            $li = 0.0;

            if ($order['lat'] != null && $order['lon'] != null && $value['lat'] != null && $value['lon'] != null) {
                $mi = Util::distanceSimplify($order['lat'], $order['lon'], $value['lat'], $value['lon']) * 1.5;
                $li = $mi / 1000;

                if ($DISTANCE) {

                    if ($li <= 5) {
                        $lat_lon = C($DISTANCE)[0];
                    } elseif ($li <= 10) {
                        $lat_lon = C($DISTANCE)[1];
                    } elseif ($li <= 20) {
                        $lat_lon = C($DISTANCE)[2];
                    } elseif ($li <= 30) {
                        $lat_lon = C($DISTANCE)[3];
                    } elseif ($li <= 50) {
                        $lat_lon = C($DISTANCE)[4];
                    } elseif ($li < 60) {
                        $lat_lon = C($DISTANCE)[5];
                    } else {
                        $lat_lon = max(-intval(($li - 50) / 10), -20) * 100;
                    }
                }

            }
            $st = $statistics[$k];
            $total_scores = $st['contract_qualification'] + $st['paid_order'] + $st['no_complaint'] + $st['no_cancel'] + $st['on_work_time'] + $st['appoint_time'] + $st['return_time'];
            $scores[$k] = $total_scores + $scores_arr[0][$k] + $scores_arr[1][$k] + $lat_lon;

            $statistics[$k]['is_complete_pros'] = (string)$scores_arr[0][$k];
            $statistics[$k]['first_distribute_pros'] = (string)$scores_arr[1][$k];
            $statistics[$k]['lat_lon'] = (string)$lat_lon;
            unset($statistics[$k]['worker_id']);

            foreach ($statistics[$k] as $sk => $sv) {
                $statistics[$k][$sk] = number_format($sv / 100, 2, '.', '');
            }

            $scores[$k] = $scores[$k];
        }

        // 缓存分数 
        if (count($statistics)) {
            $s_score_key = $sort_type.'_'.C('S_SCORE_KEY_PRE').$order['id'];
            $cash_sccore = S($s_score_key);

            $guoqi = orderReceiversDataWorkerSortTime();
            S($s_score_key, (array)$statistics + (array)$cash_sccore, ['expire' => $guoqi]);
        }

        return [$scores, $other];
    }

    // 实时计算 产品匹配  同类型产品完成率
    public function orderReceivesByPuoduct($order = [], $worker_ids = '')
    {
        $return = $cate_id = [];
        foreach ($order['details'] as $k => $v) {
            $cate_id[$v['product_category_id']] = $v['product_category_id'];
        }

        $cm_list = $cate_id ? BaseModel::getInstance('product_category')
            ->getList([
                'id' => ['in', implode(',', $cate_id)],
            ]) : [];

        // TODO 只满足两级分类
        $parent = [];
        foreach ($cm_list as $k => $v) {
            $parent[$v['id']] = $v['parent_id'] > 0 ? $v['parent_id'] : $v['id'];
        }
        // SAME_PRODUCT 同类型产品完成率
        $return[0] = $this->orderIscompleteProsScores($parent[reset($cate_id)], $worker_ids, $order['service_type']);

        $cate_id = array_unique(array_filter(array_merge($parent, $cate_id)));
        // 产品匹配
        $return[1] = $this->coopFirstDistributeProsScores($cate_id, $worker_ids);

        return $return;
    }

    // 产品匹配得分
    public function coopFirstDistributeProsScores($cate_id = [], $worker_ids = '')
    {
        $model = BaseModel::getInstance('worker_coop_busine');
        $coop_list = $worker_ids ? $model
            ->field('worker_id,first_distribute_pros')
            ->where(['worker_id' => ['in', $worker_ids]])
            ->index('worker_id')
            ->select() : [];

        $return = [];
        foreach (explode(',', $worker_ids) as $k => $v) {
            if ($coop_list[$v]['first_distribute_pros']) {
                $arr = explode(',', $coop_list[$v]['first_distribute_pros']);
                if (array_filter(array_intersect($arr, $cate_id))) { // 交集有数据
                    $return[$v] = reset(C('PRODUCT_MATCH'));
                }
            } else {
                $return[$v] = 0;
            }
        }

        return $return;
    }

    // 同类型产品 完成率
    public function orderIscompleteProsScores($cate_id = 0, $worker_ids = '', $servicetype = '')
    {
        $cm_list = $cate_id ? BaseModel::getInstance('product_category')
            ->getList(['parent_id' => $cate_id]) : [];
        $cate_ids = [];
        foreach ($cm_list as $k => $v) {
            $cate_ids[$v['id']] = $v['id'];
        }
        $cate_ids = array_unique(array_filter(array_merge($cate_ids, [$cate_id])));

        $cate_field = '';
        if ($cate_ids) {
            $cate_field = ',SUM(IF(product_category_id IN(' . implode(',', $cate_ids) . '),1,0)) AS detail_cate_nums';
        }

        // GROUP_CONCAT(distinct servicepro ORDER BY order_detail_id DESC SEPARATOR ","
        $model = BaseModel::getInstance('worker_order');
        $pros = $worker_ids ? $model
            ->alias('WO')
            ->join('LEFT JOIN worker_order_product WOD ON WO.id = WOD.worker_order_id')
            ->field('WO.worker_id,COUNT(WOD.id) AS detail_nums' . $cate_field)
            ->where([
                'WO.worker_id'           => ['in', $worker_ids],
                'WO.service_type'        => $servicetype,
                'WO.worker_order_status' => 18,
            ])
            ->index('worker_id')
            ->group('worker_id')
            ->select() : [];
        $worker_id_arr = array_flip(explode(',', $worker_ids));
        $return = [];
        foreach ($pros as $k => $v) {
            // 同类型产品完成率=同服务类型产品完成量/技工已结算工单产品量*100%
            $bfb = ($v['detail_cate_nums'] / $v['detail_nums']) * 100;
            $score = 0;
            if ($bfb >= 90) {
                $score = C('SAME_PRODUCT')[0];
            } elseif ($bfb >= 80) {
                $score = C('SAME_PRODUCT')[1];
            } elseif ($bfb >= 70) {
                $score = C('SAME_PRODUCT')[2];
            } elseif ($bfb >= 60) {
                $score = C('SAME_PRODUCT')[3];
            } elseif ($bfb >= 50) {
                $score = C('SAME_PRODUCT')[4];
            } elseif ($bfb >= 40) {
                $score = C('SAME_PRODUCT')[5];
            } elseif ($bfb >= 30) {
                $score = C('SAME_PRODUCT')[6];
            } elseif ($bfb >= 20) {
                $score = C('SAME_PRODUCT')[7];
            } elseif ($bfb >= 10) {
                $score = C('SAME_PRODUCT')[8];
            } elseif ($bfb >= 0) {
                $score = C('SAME_PRODUCT')[9];
            }

            // 分数*指数A  指数A=同服务类型产品完成量/10（如果指数＜1，则取同服务类型产品完成量/10；如果指数≥1，则按照1计算）
            $zhishu = $v['detail_cate_nums'] / C('SAME_PRODUCT_ZHISHU_A');
            $zhishu = $zhishu >= 1 ? 1 : $zhishu;
            $score = $score * $zhishu;
            $return[$k] = (int)$score;
            unset($worker_id_arr[$k]);
        }
        foreach ($worker_id_arr as $k => $v) {
            $return[$k] = 0;
        }

        return $return;
    }

    // 时效评分
    public function getWorkerEffectivenessScore($worker_id)
    {
        $total_score = BaseModel::getInstance('worker_order_reputation')
            ->getOne([
                'worker_id'   => $worker_id,
                'is_complete' => 1,
            ], '(sum(appiont_fraction)+sum(arrive_fraction)+sum(ontime_fraction)+sum(return_fraction))/count(*) as sum');

        return round($total_score['sum'] / 4);
    }

    // 用户满意度评分
    public function getUserSatisfyScore($worker_id)
    {
        $total_score = BaseModel::getInstance('worker_order_reputation')
            ->getOne([
                'worker_id'   => $worker_id,
                'is_complete' => 1,
            ], '(sum(sercode_fraction)+sum(revcode_fraction))/count(*) as sum');

        return round($total_score['sum'] / 2);
    }

    // 维修质量评分
    public function getRepairQualityScore($worker_id)
    {
        $quality = $this->getWorkerServiceQualityScore($worker_id);

        $detect = BaseModel::getInstance('worker_order_quality')->getNum([
            'worker_id' => $worker_id,
            'is_detect' => 1,
        ]);
        $detect *= 30;

        $fault = BaseModel::getInstance('worker_order_quality')->getNum([
            'worker_id' => $worker_id,
            'is_fault'  => 1,
        ]);
        $fault *= 30;

        $worker_reputation_num = BaseModel::getInstance('worker_order_reputation')
            ->getNum([
                'worker_id'   => $worker_id,
                'is_complete' => 1,
            ]);

        return $worker_reputation_num ? round(($quality - $detect - $fault) / $worker_reputation_num) : 0;
    }

    // 服务规范
    public function workerServiceSpecificationScore($worker_id)
    {
        $total_score = BaseModel::getInstance('worker_order_reputation')
            ->getOne([
                'worker_id'   => $worker_id,
                'is_complete' => 1,
            ], 'sum(quality_standard_fraction)/count(*) as sum');

        return round($total_score['sum'] / 3);
    }

    // 服务质量评分
    public function getWorkerServiceQualityScore($worker_id)
    {
        $total_score = BaseModel::getInstance('worker_order_reputation')
            ->getSum([
                'worker_id'   => $worker_id,
                'is_complete' => 1,
            ], 'repair_nums_fraction');

        return round($total_score);
    }

    // 技工信誉可以查的数据
    public function loadWorkerOrderReputationByWorkers($worker_ids)
    {
        $model = BaseModel::getInstance('worker_order_reputation');
        // return_fraction 返件得分; appiont_fraction 快速预约得分; arrive_fraction 快速上门得分; all_order_nums 总共单量;cancel_order_nums 取消工单总数; complete_order_nums 已结算工单量
        $field = 'worker_id,SUM(IF(is_complete = 1,return_fraction,0)) AS total_return_fraction_score,SUM(IF(is_complete = 1,appiont_fraction,0)) AS total_appiont_fraction_score,SUM(IF(is_complete = 1,arrive_fraction,0)) as total_arrive_fraction_score,COUNT(worker_id) AS all_order_nums,SUM(IF(is_complete = 1,1,0)) AS complete_order_nums,SUM(IF(is_complete = 0 AND is_return = 1,1,0)) AS cancel_order_nums';

        $list = $worker_ids ? $model
            ->field($field)
            ->where(['worker_id' => ['in', implode(',', $worker_ids)]])
            ->group('worker_id')
            ->index('worker_id')
            ->select() : [];

        $worker_id_arr = array_flip($worker_ids);

        $worker_complete_order_nums_arr = RedisPool::getInstance()->hGetAll(C('SKEY_COMPLETE_ORDER_NUMS'));
//        $worker_complete_order_nums_arr = (array)RedisPool::getInstance()->get(C('SKEY_COMPLETE_ORDER_NUMS'));
        foreach ($list as $key => $item) {
            if ($item['complete_order_nums'] > 0) {
                $worker_complete_order_nums_arr[$key] = (int)$item['complete_order_nums'];
            } else {
                unset($worker_complete_order_nums_arr[$key]);
            }

            // 返件得分
            $list[$key]['return_fraction_score'] = intval($item['total_return_fraction_score'] / $item['complete_order_nums'] * 100) / 2;
            // 快速预约得分
            $list[$key]['appiont_fraction_score'] = intval($item['total_appiont_fraction_score'] / $item['complete_order_nums'] * 100) / 2;
            // 快速上门得分
            $list[$key]['arrive_fraction_score'] = intval($item['total_arrive_fraction_score'] / $item['complete_order_nums'] * 100);
            if ($item['all_order_nums'] == 0) {
                $cancel_score = C('NO_CANCEL')[10];
            } else {
                $cancel_rate = $item['cancel_order_nums'] / $item['all_order_nums'];
                if ($cancel_rate <= 0.01) {
                    $cancel_score = C('NO_CANCEL')[0];
                } elseif ($cancel_rate <= 0.02) {
                    $cancel_score = C('NO_CANCEL')[1];
                } elseif ($cancel_rate <= 0.03) {
                    $cancel_score = C('NO_CANCEL')[2];
                } elseif ($cancel_rate <= 0.04) {
                    $cancel_score = C('NO_CANCEL')[3];
                } elseif ($cancel_rate <= 0.05) {
                    $cancel_score = C('NO_CANCEL')[4];
                } elseif ($cancel_rate <= 0.06) {
                    $cancel_score = C('NO_CANCEL')[5];
                } elseif ($cancel_rate <= 0.07) {
                    $cancel_score = C('NO_CANCEL')[6];
                } elseif ($cancel_rate <= 0.08) {
                    $cancel_score = C('NO_CANCEL')[7];
                } elseif ($cancel_rate <= 0.09) {
                    $cancel_score = C('NO_CANCEL')[8];
                } elseif ($cancel_rate <= 0.1) {
                    $cancel_score = C('NO_CANCEL')[9];
                } else {
                    $cancel_score = C('NO_CANCEL')[10];
                }
            }
            // 无取消单分数
            $list[$key]['cancel_score'] = $cancel_score;
            unset($worker_id_arr[$key]);
            unset(
                $list[$key]['total_return_fraction_score'],
                $list[$key]['total_arrive_fraction_score'],
                $list[$key]['total_appiont_fraction_score']
            );
        }

        foreach ($worker_id_arr as $k => $v) {
            $list[$k] = [
                'worker_id'              => $k,
                'return_fraction_score'  => 0,
                'appiont_fraction_score' => 0,
                'arrive_fraction_score'  => 0,
                'all_order_nums'         => 0,
                'cancel_order_nums'      => 0,
                'complete_order_nums'    => 0,
                'cancel_score'           => 0,
            ];
//            $worker_complete_order_nums_arr[$k] = 0;
        }

        RedisPool::getInstance()->hMset(C('SKEY_COMPLETE_ORDER_NUMS'), $worker_complete_order_nums_arr);
        RedisPool::getInstance()->expire(C('SKEY_COMPLETE_ORDER_NUMS'), 100800);
//        RedisPool::getInstance()->set(C('SKEY_COMPLETE_ORDER_NUMS'), $worker_complete_order_nums_arr, 100800);

        return $list;
    }

    /**
     * 根据技工列表回去对应的签约资质分数
     *
     * @param $workers
     * @param $worker_ids
     *
     * @return array
     */
    public function loadContractQualification(&$workers, &$worker_ids)
    {
        $worker_id_coop_map = BaseModel::getInstance('worker_coop_busine')
            ->getFieldVal([
                'worker_id' => ['IN', $worker_ids],
            ], 'worker_id,coop_level');

        $worker_id_coop_score_map = [];
        foreach ($workers as $worker) {
            $score_a = C('CONTRACT_QUALIFICATION_A')[3];
            $score_b = C('CONTRACT_QUALIFICATION_B')[3];
            if (isset($worker_id_coop_map[$worker['worker_id']]) && ($worker_id_coop_map[$worker['worker_id']] == 3 || $worker_id_coop_map[$worker['worker_id']] == 4)) {
                if ($worker['quality_money'] > 0) {
                    if ($worker['quality_money'] == $worker['quality_money_need']) {
                        $score_a = C('CONTRACT_QUALIFICATION_A')[0];
                        $score_b = C('CONTRACT_QUALIFICATION_B')[0];
                    } else {
                        $score_a = C('CONTRACT_QUALIFICATION_A')[1];
                        $score_b = C('CONTRACT_QUALIFICATION_B')[1];
                    }
                } else {
                    $score_a = C('CONTRACT_QUALIFICATION_A')[2];
                    $score_b = C('CONTRACT_QUALIFICATION_B')[2];
                }
            }
            $worker_id_coop_score_map[$worker['worker_id']] = [
                $score_a,
                $score_b,
            ];
        }

        return $worker_id_coop_score_map;
    }

    public function complaintScore(&$workers, &$worker_ids)
    {
        $worker_id_num_map = BaseModel::getInstance('worker_order_complaint')
            ->getList([
                'where' => [
                    'response_type_id' => ['IN', $worker_ids],
                    'response_type'    => ComplaintService::RESPONSE_TYPE_WORKER,
                ],
                'field' => 'response_type_id,count(*) num,(SELECT count(*) FROM worker_order WHERE worker_id=response_type_id) order_num',
                'group' => 'response_type_id',
                'order' => null,
                'index' => 'response_type_id',
            ]);

        //        $worker_id_num_map = [];
        //        foreach ($worker_complaint as $item) {
        //            $worker_id_num_map[$item['response_type_id']] = [
        //                'complaint_num' => $item['num'],
        //                'order_num' => $item['order_num'],
        //            ];
        //        }

        $worker_id_complaint_score_map = [];
        foreach ($workers as $worker) {
            if (isset($worker_id_num_map[$worker['worker_id']])) {
                if ($worker_id_num_map[$worker['worker_id']]['order_num'] == 0) {
                    $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[10];
                } else {
                    $rate = $worker_id_num_map[$worker['worker_id']]['complaint_num'] / $worker_id_num_map[$worker['worker_id']]['order_num'];
                    if ($rate <= 0.01) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[0];
                    } elseif ($rate <= 0.02) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[1];
                    } elseif ($rate <= 0.03) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[2];
                    } elseif ($rate <= 0.04) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[3];
                    } elseif ($rate <= 0.05) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[4];
                    } elseif ($rate <= 0.06) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[5];
                    } elseif ($rate <= 0.07) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[6];
                    } elseif ($rate <= 0.08) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[7];
                    } elseif ($rate <= 0.09) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[8];
                    } elseif ($rate <= 0.1) {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[9];
                    } else {
                        $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[10];
                    }
                }
            } else {
                $worker_id_complaint_score_map[$worker['worker_id']] = C('NO_COMPLAINT')[0];
            }
        }

        return $worker_id_complaint_score_map;
    }

    public function getSearchWorkerOpts()
    {
        $condition = [];

        $name = I('name');
        $worker_area_id = I('worker_area_id');
        $phone = I('worker_telephone');
        $super_login = I('super_login');
        $money_from = I('money_from');
        $money_to = I('money_to');
        $order_count_from = I('order_count_from');
        $order_count_to = I('order_count_to');
        $add_from = I('add_from');
        $add_to = I('add_to');
        $is_unchecked = I('is_unchecked');

        $label_ids = I('label_ids');
        $label_ids = Util::filterIdList($label_ids);

        $label_nums = I('label_nums');

        //姓名
        if (strlen($name) > 0) {
            $condition['nickname'] = ['like', '%' . $name . '%'];
        }

        //所属地区
        if (strlen($worker_area_id) > 0) {
            $condition['_string'] = 'find_in_set ( ' . $worker_area_id . ' , worker_area_ids )';
        }

        //手机
        if (strlen($phone) > 0) {
            $condition['worker_telephone'] = ['like', '%' . $phone . '%'];
        }

        //登录方式
        if (strlen($super_login) > 0) {
            $condition['super_login'] = $super_login;
        }

        //维修金
        if (strlen($money_from) > 0) {
            $condition['money'][] = ['egt', $money_from];
        }
        if (strlen($money_to) > 0) {
            $condition['money'][] = ['elt', $money_to];
        }

        //工单量
        $sql = "(SELECT COUNT(*) FROM `worker_order_reputation` AS `wor` WHERE wor.`worker_id` = w.`worker_id` )";
        $sub_query = [];
        if (strlen($order_count_from) > 0) {
            $sub_query[] = $sql . '>=' . $order_count_from;
        }
        if (strlen($order_count_to) > 0) {
            $sub_query[] = $sql . '<=' . $order_count_to;
        }
        if (!empty($sub_query)) {
            $condition['_string'] = implode(' and ', $sub_query);
        }

        //创建日期
        if (strlen($add_from) > 0) {
            $condition['add_time'][] = ['egt', $add_from];
        }
        if (strlen($add_to) > 0) {
            $condition['add_time'][] = ['lt', $add_to];
        }

        //是否已经完善信息
        if (1 == $is_unchecked) {
            $condition['is_complete_info'] = 2;
        }

        //标签
        if (!empty($label_ids)) {
            $condition['_string'] = empty($condition['_string']) ? '' : $condition['_string'] .= ' AND ';
            $last = count($label_ids) - 1;
            foreach ($label_ids as $key => $one_label) {
                $condition['_string'] .= "   (SELECT COUNT(id) FROM worker_label AS c WHERE w.worker_id= c.worker_id  AND c.label_id =$one_label ) >0  ";
                if ($key != $last) {
                    $condition['_string'] .= " AND ";
                }
            }
        }

        //标签数量
        if (strlen($label_nums) > 0) {
            $condition['_string'] = empty($condition['_string']) ? '' : $condition['_string'] .= ' AND ';

            $condition['_string'] .= "  (SELECT count(*)  FROM  worker_label where worker_label.worker_id = w.worker_id ) >={$label_nums}";
        }

        return [
            'alias' => 'w',
            'where' => $condition,
        ];
    }

    public function export()
    {
        $opt = $this->getSearchWorkerOpts();

        $alias = $opt['alias']?? null;
        $where = $opt['where']?? null;

        (new ExportLogic())->masterCode(['where' => $where, 'alias' => $alias]);

    }


}