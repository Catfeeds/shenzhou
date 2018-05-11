<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/31
 * Time: 14:54
 */

namespace Common\Common\Service;

use Common\Common\Model\BaseModel;
use Common\Common\ResourcePool\RedisPool;
use Illuminate\Support\Arr;
use Overtrue\Pinyin\Pinyin;

class AreaService
{
    public static function group($parent_id)
    {
        $key = 'area:group';
        $city_group = unserialize(RedisPool::getInstance()->get($key));

        if (!$city_group) {
            $cities = BaseModel::getInstance('area')
                ->getList([
                    'where' => [
                        'parent_id' => $parent_id,
                    ],
                    'field' => 'id,name',
                ]);

            $pinyin = new Pinyin();
            $map = [];
            foreach ($cities as $city) {
                $chars = $pinyin->abbr($city['name']);
                $map[] = [
                    'id' => $city['id'],
                    'chars' => $chars,
                    'name' => $city['name'],
                ];
            }
            usort($map,  function($a, $b) {  //设置闭包中需要使用到的变量
                return ($a['chars'] == $b['chars']) ? 0 : (($a['chars'] > $b['chars']) ? 1 : -1);
            });

            foreach ($map as $item) {
                $char = $item['chars']{0};
                $data = [
                    'id' => $item['id'],
                    'name' => $item['name']
                ];
                if ($char >= 'a' && $char <= 'g') {
                    $city_group['A-G'][] = $data;
                } elseif ($char >= 'h' && $char <= 'k') {
                    $city_group['H-K'][] = $data;
                } elseif ($char >= 'l' && $char <= 's') {
                    $city_group['L-S'][] = $data;
                } else {
                    $city_group['T-Z'][] = $data;
                }
            }

            RedisPool::getInstance()->set($key, serialize($city_group), 15552000);
        }

        return $city_group;
    }

    public static function index($parent_id)
    {
        $cities = BaseModel::getInstance('area')
            ->getList([
                'where' => [
                    'parent_id' => $parent_id,
                ],
                'field' => 'id,name',
            ]);

        return $cities;
    }

    public static function keyIndex($parent_id)
    {
        $cities = BaseModel::getInstance('area')
            ->getList([
                'where' => [
                    'parent_id' => $parent_id,
                ],
                'field' => 'id,name,parent_id',
                'index' => 'id',
            ]);

        return $cities;
    }

    public static function getAreaNameMapByIds($area_ids)
    {
        $area_id_name_map = $area_ids ? BaseModel::getInstance('area')->getList([
            'where' => ['id' => ['IN', $area_ids]],
            'field' => 'id,name',
            'order' => 'id ASC',
            'index' => 'id'
        ]) : [];
        return $area_id_name_map;
    }

    public static function getChildrenByParentIds($parent_ids)
    {
        $list = $parent_ids ? BaseModel::getInstance('area')->getList([
            'where' => [
                'parent_id' => ['IN', $parent_ids]
            ],
            'field' => 'id,name,parent_id',
        ]) : [];

        return $list;
    }

    public static function getChildrenByParentIdsAtIndex($parent_ids)
    {
        $list = $parent_ids ? BaseModel::getInstance('area')->getList([
            'where' => [
                'parent_id' => ['IN', $parent_ids]
            ],
            'field' => 'id,name,parent_id',
            'index' => 'id',
        ]) : [];

        return $list;
    }

    public static function tree()
    {
        $key = 'area:tree';
        $area_tree = unserialize(RedisPool::getInstance()->get($key));
        if (!$area_tree) {
            $parent_ids = [0];
            $options = [
                'where' => [
                    'parent_id' => ['IN', &$parent_ids]
                ],
                'field' => 'id value,name label,parent_id',
                'order' => 'value ASC',
                'index' => 'value',
            ];
            $provinces = BaseModel::getInstance('area')->getList($options);
            $parent_ids = Arr::pluck($provinces, 'value');
            $cities = BaseModel::getInstance('area')->getList($options);
            $parent_ids = Arr::pluck($cities, 'value');
            $districts = BaseModel::getInstance('area')->getList($options);
            foreach ($districts as $district) {
                $cities[$district['parent_id']]['children'][] = Arr::only($district, ['value', 'label']);
            }
            $cities = array_values($cities);
            foreach ($cities as &$city) {
                $provinces[$city['parent_id']]['children'][] = Arr::only($city, ['value', 'label', 'children']);
            }

            $area_tree = array_values($provinces);
            RedisPool::getInstance()->set($key, serialize($area_tree), 15552000);
        }


        return $area_tree;
    }

    public static function areaRuleResult($area_data)
    {
        $provinces = AreaService::keyIndex(0);
        $province_ids = Arr::pluck($provinces, 'id');
        $province_name_id_map = Arr::pluck($provinces, 'id', 'name');
        $city_list = AreaService::getChildrenByParentIdsAtIndex($province_ids);
        $city_id_name_map = [];
        $city_ids = [];
        foreach ($city_list as $item) {
            $city_id_name_map[$item['parent_id'] . $item['name']] = $item['id'];
            $city_ids[] = $item['id'];
        }
        $district_list = AreaService::getChildrenByParentIdsAtIndex($city_ids);
        $district_id_name_map = [];
        $city_id_district_map = [];
        foreach ($district_list as $item) {
            $district_id_name_map[$item['parent_id'] . $item['name']] = $item['id'];
            $city_id_district_map[$item['parent_id']][] = [
                'id' => $item['id'],
                'name' => $item['name'],
            ];
        }
        $result = [];
        foreach ($area_data as $key => $area_des) {

            // 5个自治区、4个直辖市特殊匹配
            if (preg_match('/^(河北|山西|辽宁|吉林|黑龙江|江苏|浙江|安徽|福建|江西|山东|河南|湖北|湖南|广东|海南|四川|贵州|云南|陕西|甘肃|青海|台湾)[^省]/u', $area_des, $area_match)) {
                $area_des = $area_match[1] . '省' . mb_substr($area_des, mb_strlen($area_match[1]));
            } elseif (mb_substr($area_des, 0, 8) == '新疆维吾尔自治区') {
                $area_des = '新疆' . mb_substr($area_des, 8);
            } elseif (mb_substr($area_des, 0, 7) == '宁夏回族自治区') {
                $area_des = '宁夏' . mb_substr($area_des, 7);
            } elseif (mb_substr($area_des, 0, 5) == '西藏自治区') {
                $area_des = '西藏' . mb_substr($area_des, 5);
            } elseif (mb_substr($area_des, 0, 7) == '广西壮族自治区') {
                $area_des = '广西' . mb_substr($area_des, 7);
            } elseif (mb_substr($area_des, 0, 6) == '内蒙古自治区') {
                $area_des = '内蒙古' . mb_substr($area_des, 6);
            } elseif (preg_match('/^(北京|重庆|上海|天津)/u', $area_des, $area_match)) {
                $area_len = mb_strlen($area_match[1]);
                if (preg_match('/^(北京|重庆|上海|天津)市/u', $area_des, $area_match_1)) {
                    // 匹配北京市海淀区 转为 北京市北京市海淀区
                    if (mb_substr($area_des, $area_len + 1, $area_len) != $area_match[1]) {
                        $area_des = $area_match[1] . '市' . $area_match[1] . '市' . mb_substr($area_des, $area_len + 1);
                    }
                } else {
                    // 匹配北京海淀区 转为 北京市北京市海淀区
                    if (mb_substr($area_des, $area_len, $area_len) != $area_match[1]) {
                        $area_des = $area_match[1] . '市' . $area_match[1] . '市' . mb_substr($area_des, $area_len);
                    } else {    // 匹配北京北京市海淀区 转为 北京市北京市海淀区
                        $area_des = $area_match[1] . '市' . mb_substr($area_des, $area_len);
                    }
                }
            }

            // 匹配城市
            preg_match('/^(?:(内蒙古|西藏|新疆|广西|宁夏)|(.+?)(?:省|市))?(?:(.+?)(市|自治州|自治县|地区|县|盟))?(.+)$/u', $area_des, $match);

            /**
             * XXX省或XX市（直辖市）匹配成功则在2中，
             * 如果是特殊省份则在1中，
             */
            $province = trim($match[2]) ? : trim($match[1]);
            $city = trim($match[3]) . $match[4];
            $district = trim($match[5]);

            $province_id = intval($province_name_id_map[$province]);
            $city_id = $province_id ? (isset($city_id_name_map[$province_id . $city]) ? intval($city_id_name_map[$province_id . $city]) : 0) : 0;

            $district_id = 0;
            $real_district = '';
            foreach ($city_id_district_map[$city_id] as $dt) {
                if (mb_substr($district, 0, mb_strlen($dt['name'])) == $dt['name']) {
                    $real_district = $dt['name'];
                    $district_id = intval($dt['id']);
                    break;
                }
            }

            $result[$key]['province']  = $provinces[$province_id];
            $result[$key]['city']      = $city_list[$city_id];
            $result[$key]['district']  = $district_list[$district_id];
            $result[$key]['names']  = [$province, $city, $real_district];
        }

        return $result;
    }
}