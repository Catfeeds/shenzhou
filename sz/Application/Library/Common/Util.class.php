<?php

namespace Library\Common;

/**
 * 通用工具类，uploadImg、generateQRCode方法基于tp框架，
 * 部分函数需求php版本大于5.3(需要支持闭包)
 * @author qiuqiu
 * 2014-8-4
 */
class Util {

    /**
     * 对数组中的对象或数组根据指定字段进行排序
     * @param array $data 排序的数组
     * @param string $field 排序的字段
     * @param int $order 排序方式，默认为升序
     */
    public static function sortByField(&$data, $field, $order = 0)
    {
        //使用闭包设置需要比较的字段
        $cmpArray = function($field, $order) {
            return $order ? function($a, $b) use($field, $order) {  //设置闭包中需要使用到的变量
                return ($a[$field] == $b[$field]) ? 0 : (($a[$field] > $b[$field]) ? -1 : 1);
            } : function($a, $b) use($field, $order) {
                return ($a[$field] == $b[$field]) ? 0 : (($a[$field] < $b[$field]) ? -1 : 1);
            };
        };
        $cmpObject = function ($field, $order) {
            return $order ? function($a, $b) use($field, $order) {  //设置闭包中需要使用到的变量
                return ($a->$field == $b->$field) ? 0 : (($a->$field > $b->$field) ? -1 : 1);
            } : function($a, $b) use($field, $order) {
                return ($a->$field == $b->$field) ? 0 : (($a->$field < $b->$field) ? -1 : 1);
            };
        };
        usort($data, is_array(current($data)) ? $cmpArray($field, $order) : $cmpObject($field, $order));
    }

    /**
     * @param double $lat1  纬度1
     * @param double $lng1  经度1
     * @param double $lat2  纬度2
     * @param double $lng2  经度2
     * @return float 两点间的距离
     */
    public static function distanceSimplify($lat1, $lng1, $lat2, $lng2)
    {
        $dx = $lng1 - $lng2;
        $dy = $lat1 - $lat2;
        $b = ($lat1 + $lat2) / 2;
        $lx = deg2rad($dx) * 6367000.0 * cos(deg2rad($b));
        $ly = 6367000.0 * deg2rad($dy);

        return sqrt($lx * $lx + $ly * $ly);
    }


    /**
     * 根据一个点的经纬度获取一个范围内的经纬度
     * @param $longitude    经度
     * @param $latitude 纬度
     * @param int $distance 距离
     * @return array 二维数组，$arr[0]=>array($jd_min, $jd_max)经度的范围，同理$arr[1]代表纬度的范围
     */
    public static function jwRange($longitude, $latitude, $distance=2000)
    {
        $rate = 111000;
        $wd_jl = $distance / $rate;
        $wd_min = $latitude - $wd_jl;
        $wd_max = $latitude + $wd_jl;
        $jd_jl = $distance / ($rate * cos(deg2rad($latitude)));
        $jd_min = $longitude - $jd_jl;
        $jd_max = $longitude + $jd_jl;
        $arr[0] = array($jd_min, $jd_max);
        $arr[1] = array($wd_min, $wd_max);
        return $arr;
    }

//    /**
//     * 根据大小对图片进行缩放，并直接输出图片
//     * @param string $img_path
//     * @param int $max_width
//     * @param int $max_height
//     * @param int $cascade
//     */
//    public static function getThumbImg($img_path, $max_width = 480, $max_height = 480, $cascade = 1)
//    {
//        if (!file_exists($img_path)) {
//            throw new Exception('文件不存在');
//        }
//        $img_info = getimagesize($img_path);
//        $img_ext = strtolower(substr(image_type_to_extension($img_info[2]),1));
//        $img_ext = $img_ext=='jpg'?'jpeg':$img_ext;
//        $create_func = 'imagecreatefrom' . $img_ext;
//        $image_func = 'image' . $img_ext;
//        $src_width = $img_info[0];
//        $src_height = $img_info[1];
//        $src_img = $create_func($img_path);
//        //尺寸是否级联修改
//        if ($cascade) {
//            $scale = min($max_width/$src_width, $max_height/$src_height); // 计算缩放比例
//            if($scale >= 1) {
//                // 超过原图大小不再缩略
//                $width   =  $src_width;
//                $height  =  $src_height;
//            } else {
//                // 缩略图尺寸
//                $width  = (int)($src_width*$scale);
//                $height = (int)($src_height*$scale);
//            }
//        } else {
//            $width = $max_width;
//            $height = $max_height;
//        }
//        //创建缩略图
//        if ($img_ext != 'gif' && function_exists('imagecreatetruecolor')) {
//            $thumb_img = imagecreatetruecolor($width, $height);
//        } else {
//            $thumb_img = imagecreate($width, $height);
//        }
//        // 复制图片
//        if (function_exists("ImageCopyResampled")) {
//            //imagecopyresampled生成的图片较平滑，但效率比imagecopyresized低
//            imagecopyresampled($thumb_img, $src_img, 0, 0, 0, 0, $width, $height, $src_width,$src_height);
//        } else {
//            imagecopyresized($thumb_img, $src_img, 0, 0, 0, 0, $width, $height,  $src_width,$src_height);
//        }
//        header('Content-type: image/' . $img_ext);
//        $image_func($thumb_img);
//        imagedestroy($thumb_img);
//        imagedestroy($src_img);
//    }

    /**
     * @param array $setting
     * @return mixed
     * @throws \Exception
     */
    public static function upload($setting = array())
    {
        $conf = [
            'rootPath' => './Uploads/',
        ];
        $setting = array_merge($conf, $setting);
        if (!is_dir($setting['rootPath'])) {
            mkdir($setting['rootPath'], 0777);
        }
        $upload = new \Think\Upload($setting);
        $info = $upload->upload();
        if (!$info) {
            throw new \Exception($upload->getError());
        } else {
            // 返回成功上传的文件信息
            $info = array_values($info)[0];
            $info['file_path'] = $setting['rootPath'] . $info['savepath'] . $info['savename'];
            return $info;
        }
    }


    /**
     * @param $url
     * @param array $params
     * @param string $method
     * @param bool $multi
     * @param array $header
     * @return mixed
     * @throws \Exception
     */
    public static function requestRemoteData($url, $params = array(), $method = 'GET', $multi = false, $header = array())
    {
        $opts = array(
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $header
        );
        switch(strtoupper($method)) {
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                throw new \Exception('请求方式错误!');
        }
        // 初始化并执行curl请求
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new \Exception('请求发生错误：' . $error);
        }
        return $data;
    }

    /**
     * 获取访问设备
     * @return string
     */
    public static function getDevice()
    {
        $user_agent = $_SERVER["HTTP_USER_AGENT"];
        if (strripos($user_agent, 'iphone')) {
            return 'iphone';
        }
        if (strripos($user_agent, 'android')) {
            return 'android';
        }
        if (strripos($user_agent, 'ipad')) {
            return 'ipad';
        }
        if (strripos($user_agent, 'ipod')) {
            return 'ipod';
        }
        if (strripos($user_agent, 'windows nt')) {
            return 'pc';
        }
        return 'other';
    }

    /**
     * 根据url生成二维码
     * @param string $url url地址
     * @param string $level 纠错级别：L、M、Q、H，L最低H最高
     * @param int $size 二维码尺寸，手机一般设为4就足够
     * @param int $margin 边距
     */
    public static function generateQRCode($url, $level = 'Q', $size = 4, $margin = 2)
    {
        vendor('phpqrcode.qrlib');
        // 如果要保存图片,用$fileName替换第二个参数false
        \QRcode::png($url, false, $level, $size, $margin);
    }

    /**
     * @param $time
     * @return string
     */
    public static function getTimeDesc($time)
    {
        $diff = NOW_TIME - $time;
        if ($diff < 60) {
            return '刚刚';
        } else if ($diff < 3600) {
            return intval($diff / 60) . '分钟前';
        } else if ($diff < 86400) {
            return intval($diff / 3600) . '小时前';
        } else if ($diff < 86400 * 30) {
            return intval($diff / 86400) . '天前';
        } else if ($diff < 86400 * 30 * 365) {
            return intval($diff / (86400 * 30)) . '月前';
        } else {
            return intval($diff / (86400 * 30 * 365)) . '年前';
        }
    }

    public static function checkUrl($url)
    {
        return preg_match('/^https?:\/\/.+/i', $url);
    }

    /**
     * 获取当前服务器Url
     * @return string
     */
    public static function getServerUrl()
    {
        return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    }

    public static function getServerFileUrl($path)
    {
        if (self::checkUrl($path)) {
            return $path;
        }

        $path = ltrim($path, '.');
        return C('RESOURCE_URL') . $path;
    }

    public static function getServer()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $server_name = $_SERVER['HTTP_X_FORWARDED_HOST'];
        } else {
            $server_name = $_SERVER['HTTP_HOST'];
        }
        return (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $server_name;
    }

    /**
     * 获取当前uri
     * @return string
     */
    public static function getRequestUri()
    {
        return self::getServerUrl() . $_SERVER['REQUEST_URI'];
    }

    /**
     * 检查是否正确的手机号码
     * @param $phone
     * @return int
     */
    public static function isPhone($phone)
    {
        return preg_match("/^1[3456789][0-9]{9}$/", $phone);
    }

    /**
     * 检查是否正确的身份证号
     * @param $idcard
     * @return bool
     */
    public static function isIdCardNo($idcard)
    {
        if (strlen($idcard) != 18) {
            return false;
        }
        $aCity = array(11 => "北京", 12 => "天津", 13 => "河北", 14 => "山西", 15 => "内蒙古",
            21 => "辽宁", 22 => "吉林", 23 => "黑龙江",
            31 => "上海", 32 => "江苏", 33 => "浙江", 34 => "安徽", 35 => "福建", 36 => "江西", 37 => "山东",
            41 => "河南", 42 => "湖北", 43 => "湖南", 44 => "广东", 45 => "广西", 46 => "海南",
            50 => "重庆", 51 => "四川", 52 => "贵州", 53 => "云南", 54 => "西藏",
            61 => "陕西", 62 => "甘肃", 63 => "青海", 64 => "宁夏", 65 => "新疆",
            71 => "台湾", 81 => "香港", 82 => "澳门",
            91 => "国外");
        //非法地区
        if (!array_key_exists(substr($idcard, 0, 2), $aCity)) {
            return false;
        }
        //验证生日
        if (!checkdate(substr($idcard, 10, 2), substr($idcard, 12, 2), substr($idcard, 6, 4))) {
            return false;
        }
        $idcard_base = substr($idcard, 0, 17);

        // 加权因子
        $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);

        // 校验码对应值
        $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');

        $checksum = 0;
        for ($i = 0; $i < strlen($idcard_base); ++$i) {
            $checksum += intval(substr($idcard_base, $i, 1)) * $factor[$i];
        }

        $mod = strtoupper($checksum % 11);
        $verify_number = $verify_number_list[$mod];

        if ($verify_number != strtoupper(substr($idcard, 17, 1))) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 对首字符是ASCII码如果是字母则返回该字母，否则返回#
     * 对中文(UTF-8编码)字符返回该字符拼音的首字母，
     * 对其他特殊字符返回#
     * @param $str
     * @param string $from_charset
     * @param string $to_charset
     * @return string
     */
    public static function getFirstCharter($str, $from_charset = 'UTF-8', $to_charset='gbk')
    {
        $first_char = ord($str{0});
        // 如果是字母则返回该字母
        if ($first_char >= ord('A') && $first_char <= ord('Z') || $first_char >= ord('a') && $first_char <= ord('z')) {
            return strtoupper($str{0});
        } else if ($first_char >= 0 && $first_char <= 127) {    // 除字母外的asc码字符返回#
            return '#';
        }
        // 中文字符返回拼音的首字母
        $s1=iconv($from_charset, $to_charset, $str);
        $s2=iconv($to_charset, $from_charset, $s1);
        $s=$s2==$str?$s1:$str;
        $asc=ord($s{0})*256+ord($s{1})-65536;
        if($asc>=-20319&&$asc<=-20284) return 'A';
        if($asc>=-20283&&$asc<=-19776) return 'B';
        if($asc>=-19775&&$asc<=-19219) return 'C';
        if($asc>=-19218&&$asc<=-18711) return 'D';
        if($asc>=-18710&&$asc<=-18527) return 'E';
        if($asc>=-18526&&$asc<=-18240) return 'F';
        if($asc>=-18239&&$asc<=-17923) return 'G';
        if($asc>=-17922&&$asc<=-17418) return 'H';
        if($asc>=-17417&&$asc<=-16475) return 'J';
        if($asc>=-16474&&$asc<=-16213) return 'K';
        if($asc>=-16212&&$asc<=-15641) return 'L';
        if($asc>=-15640&&$asc<=-15166) return 'M';
        if($asc>=-15165&&$asc<=-14923) return 'N';
        if($asc>=-14922&&$asc<=-14915) return 'O';
        if($asc>=-14914&&$asc<=-14631) return 'P';
        if($asc>=-14630&&$asc<=-14150) return 'Q';
        if($asc>=-14149&&$asc<=-14091) return 'R';
        if($asc>=-14090&&$asc<=-13319) return 'S';
        if($asc>=-13318&&$asc<=-12839) return 'T';
        if($asc>=-12838&&$asc<=-12557) return 'W';
        if($asc>=-12556&&$asc<=-11848) return 'X';
        if($asc>=-11847&&$asc<=-11056) return 'Y';
        if($asc>=-11055&&$asc<=-10247) return 'Z';
        // 其他特殊字符返回#
        return '#';
    }

    /**
     * 根据数组中的$field字段来分组，并按分组的索引返回索引数组，
     * 分组对字母开头及中文字符拼音首字母按字母分组，其他字符分为#组，
     * 如果$special_last为true则把特殊字符组放到排序最后，否则在最前
     * @param $arr
     * @param $field
     * @param $special_last
     * @return array
     */
    public static function groupByFirstCharacter($arr, $field, $special_last = true)
    {
        $group = array();
        foreach ($arr as $item) {
            $group[self::getFirstCharter($item[$field])][] = $item;
        }
        ksort($group);
        if (!empty($group['#']) && $special_last) {
            $special_char_arr = $group['#'];
            unset($group['#']);
            $group['#'] = $special_char_arr;
        }
        return $group;
    }

    public static function downloadFile($file_path)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
    }

    public static function getFileLines($file_path, $only_valid = true)
    {
        $file = new \SplFileObject($file_path);
        $lines = 0;
        $file->current();
        while(!$file->eof())  {
            $content = $file->current();
            if ($only_valid) {
                trim($content) && ++$lines;
            } else {
                ++$lines;
            }
            $file->next();
        }
        return $lines;
    }


    /**
     * 过滤形如1,2,3这种形式已逗号分隔ID的字段
     * @param $ids
     * @param bool $to_string
     * @return array|string
     */
    public static function filterIdList($ids, $to_string = false)
    {
        $ids = trim($ids); // 去除前后空字符
        $ids = trim($ids, ','); // 去除逗号
        $ids = explode(',', $ids);
        $ids = array_unique($ids);
        $filter_ids = [];
        foreach ($ids as $id) {
            $str = trim($id);
            if (!empty($str)) {
                $filter_ids[] = $str;
            }
        }
        return $to_string ? implode(',', $filter_ids) : $filter_ids;
    }

    public static function buildImgTagSource($html)
    {
        $dom = new \DOMDocument();
        $html = '<meta http-equiv="Content-Type" content="text/html;charset=utf-8">' . $html;
        $dom->loadHTML($html);
        $imgs = $dom->getElementsByTagName('img');
        foreach ($imgs as $img) {
            $img->setAttribute('src', static::getServerFileUrl($img->getAttribute('src')));
        }

        return $dom->saveHTML();
    }

}