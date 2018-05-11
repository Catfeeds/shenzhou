<?php

function getExcelKeyStr($key = 1, $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', $data = [])
{
    $re = getExcelKeys($key, 0, $string, $data);
    $result = '';
    foreach ($re as $k => $v) {
        $result .= $string[$v-1];
    }
    return $result;
}

function getExcelKeys($key = 1, $i = 0, $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', $data = [])
{
    $length = strlen($string);
    $data[$i] = $data[$i] ? $data[$i] : 0;
    if ($data[$i] == ($length + 1)) {
        $data[$i] = 1;
        ++$i;
        $data[$i] = $data[$i] ? $data[$i] : 1;
    }
    if ($key > $length) {
        ++$data[$i];
        $data =  getExcelKeys($key - $length, $i, $string, $data);
    } else if ($data[$i]) {
        $data[$i+1] = $key;
    } else {
        $data[$i] = $key;
    }
    return $data;
}

// 工单派发 技工排序 的县官数据缓存过期时间 zjz
function orderReceiversDataWorkerSortTime()
{
	return 24*3600 - (NOW_TIME - strtotime(date('Y-m-d', NOW_TIME))) + 4*3600;
}

//获取权限递归树
function getNodeTree($data, $pid=0)
{
    $tree = [];
    foreach ($data as $key => $val) {
        if ($val['pid'] == $pid) {
            $value = [];
            $value = $val;
            $value['space']  = str_repeat("&nbsp;",($val['level']-1) * 4);

            $tree[] = $value;
            unset($data[$key]);
            getNodeTree($data, $val['id']);
        }
    }
    return $tree;
}

function checkFactoryOrderPermission($worker_order_id, $factory_id = null)
{
    (new \Admin\Logic\OrderLogic())->checkFactoryOrderPermission($worker_order_id, $factory_id);
}

function checkAdminOrderPermission($worker_order_id, $admin = null)
{
    (new \Admin\Logic\OrderLogic())->checkAdminOrderPermission($worker_order_id, $admin);
}

// B端迁移过来的函数
/**
 * 计算时间差
 *
 * @param int    $begin_time 起始时间,时间戳,单位:秒
 * @param int    $end_time   结束时间,时间戳,单位:秒
 * @param string $type       时间差单位, day-日 hour-时 min-分 sec-秒, $type为空返回秒
 *
 * @return int
 */
function timediff($begin_time, $end_time, $type = 'min')
{
    $diff = $end_time - $begin_time;

    if ('day' == $type) {
        return floor($diff/86400);
    } elseif ('hour' == $type) {
        return floor($diff/3600);
    } elseif ('min' == $type) {
        return floor($diff/60);
    } else {
        return $diff;
    }
}

/**
 * 客服kpi 时效的时间界定 并转换 最后的时间
 * @param int/string $time 转换时间,时间戳,单位:秒
 *
 * @reutrn objest  new \Carbon\Carbon(date('Y-m-d H:i:s, $time));
 */
function kpiValidTimeBetween($time)
{
    $time = (int)$time;
    $time_obj = new \Carbon\Carbon(date('Y-m-d H:i:s', $time));
    $hour_min_se = ($time_obj->hour).'.'.str_pad($time_obj->minute, 2, '0', STR_PAD_LEFT).str_pad($time_obj->second, 2, '0', STR_PAD_LEFT);
    if ($time_obj->hour >= C('VALID_TIME_BETWEEN.EGT_HOUR')) {
        $add_date = ($time_obj->addDay()->toDateString()).' '.C('VALID_TIME_BETWEEN.SET_HOUR_MINUTE_SECOND');
        $time_obj = new \Carbon\Carbon($add_date);
    } elseif ($hour_min_se < C('VALID_TIME_BETWEEN.LT_HOUR_MINUTE_SECOND')) {
        $add_date = ($time_obj->toDateString()).' '.C('VALID_TIME_BETWEEN.SET_HOUR_MINUTE_SECOND');
        $time_obj = new \Carbon\Carbon($add_date);
    }
    return $time_obj;
}

/**
 * 客服kpi 开始时间与结束时间对比
 */
function kpiTimeDiff ($begin_time, $end_time, $type = '', $is_day_second = true)
{
    $be_obj = kpiValidTimeBetween($begin_time);
    $ed_obj = kpiValidTimeBetween($end_time);

    $diff = $ed_obj->timestamp - $be_obj->timestamp;
    if ($is_day_second) { // 扣除 下午 18点到明天8点30分
        $day_second =  3600*24;
        $diff_second = (24-18+8)*3600 + 30*60;
        if (date('ymd', $ed_obj->timestamp) != date('ymd', $be_obj->timestamp)) {
            $second = round($diff/$day_second) * $diff_second;
            $diff -= $second;
        }
    }

    if ('day' == $type) {
        return floor($diff/86400);
    } elseif ('hour' == $type) {
        return floor($diff/3600);
    } elseif ('min' == $type) {
        return floor($diff/60);
    } else {
        return $diff;
    }
}