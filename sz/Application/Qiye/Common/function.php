<?php

function get_client_ip_diy($type = 0) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if($_SERVER['HTTP_X_REAL_IP']){//nginx 代理模式下，获取客户端真实IP
        $ip=$_SERVER['HTTP_X_REAL_IP'];     
    }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {//客户端的ip
        $ip     =   $_SERVER['HTTP_CLIENT_IP'];
    }elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {//浏览当前页面的用户计算机的网关
        $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $pos    =   array_search('unknown',$arr);
        if(false !== $pos) unset($arr[$pos]);
        $ip     =   trim($arr[0]);
    }elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];//浏览当前页面的用户计算机的ip地址
    }else{
        $ip=$_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 一天的结束时间 日期日期转换 时间戳
 *  User zjz
 */
function stratDateStrToString($date = '')
{
    return strtotime(date('Ymd', strtotime($date)));
}

function stratDateStrToTime($int = NOW_TIME)
{
    return strtotime(date('Ymd', $int));
}

/**
 * 一天的结束时间 日期日期转换 时间戳
 *  User zjz
 */
function endDateStrToString($date = '')
{
    return strtotime(date('Ymd', strtotime($date)))+3600*24-1;
}

function endDateStrToTime($int = NOW_TIME)
{
    return strtotime(date('Ymd', $int))+3600*24-1;
}

// 某日的整周开始时间戳 zjz
function thisTimeWeekFristDayForTime($time = null)
{
    $time = ($time == null) ? NOW_TIME : $time;
    if(date('w', $time) === '0')
        return stratDateStrToTime(strtotime('this week', $time)-3600*24*7);
    else
        return stratDateStrToTime(strtotime('this week', $time));
}

// 某日的整周开始结束戳 zjz 
function thisTimeWeekLastDayForTime($time = null)
{
    $time = ($time == null) ? NOW_TIME : $time;
    if(date('w', $time) === '0')
        return stratDateStrToTime(strtotime('this week', $time)-3600*24*7);
    else
        return endDateStrToTime(strtotime('last day next week', $time));
}

// 某日的整月开始时间戳 zjz
function thisTimeMothFristDayForTime($time = null)
{
    $time = ($time == null) ? NOW_TIME : $time;
    return stratDateStrToString(date('Y-m-01', $time));
}

// 某日的整月开始结束戳 zjz 
function thisTimeMothLastDayForTime($time = null)
{   
    $time = ($time == null) ? NOW_TIME : $time;
    return endDateStrToString(date('Y-m-'.date('t', $time), $time));
}
