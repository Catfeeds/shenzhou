<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/2/27
 * Time: 14:57
 */

namespace Common\Common\CacheModel;


use Common\Common\Model\BaseModel;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AreaService;

class AreaCacheModel extends CacheModel
{
    public static function getTableName()
    {
        return 'area';
    }

    /**
     * @param $id
     * @return array
     */
    public static function getWorkerReceivesIdsByAreaId($id)
    {
        AreaService::areaLevel($id, $level);
        $chil_ids = [];
        $i = AreaService::DISTRICT_LEVEL - $level + 1;
        self::areaAllChildrenIds([$id], $result, $chil_ids, $i);

        $datas = RedisPool::getInstance()->hGetAll(C('S_KEY_PRE'));

        $reet_arr = [];
        foreach ($chil_ids as $v) {
            $key = sprintf(C('S_KEY_PRE_TIME'), $v);
            if (RedisPool::getInstance()->get($key) !== '1' && RedisPool::getInstance()->set($key, '1', 6*3600)) {
                $reet_arr[] = 'FIND_IN_SET('.$v.', worker_area_ids)';
            }
        }

        if ($reet_arr) {

            $where = [
//                '_string' => ' FIND_IN_SET('.$id.', worker_area_ids) ',
                '_string' => implode(' OR ', $reet_arr),
                'receivers_status' => ['neq', 0],
                'is_check' => 1,
            ];
            $workers = BaseModel::getInstance('worker')->getList([
                'where' => $where,
                'field' => 'worker_id,worker_area_ids',
                'index' => 'worker_id',
            ]);

            $arr = [];
            foreach ($workers as $k => $v) {
                $string = array_filter(explode(',', $v['worker_area_ids']))[2];
                $string && $arr[$string][] = $v['worker_id'];
            }

//            $arr = array_intersect_key($arr, array_flip($reet_arr));

            foreach ($arr as $k => $v) {
                $datas[$k] = implode(',', $v);
            }

            if ($arr) {
                RedisPool::getInstance()->hMset(C('S_KEY_PRE'), $datas);
                RedisPool::getInstance()->expire(C('S_KEY_PRE'), 8*3600);
            }
        }

        $worker_ids = [];
        foreach ($datas as $v) {
            $worker_ids[] = explode(',', $v);
        }

        return $worker_ids;
    }

    /**
     * @param $ids 该ids 下 所有 子级 ids
     * @param array $return (krr_arr)
     * @param array $all_last_id 全部的最后一级的id
     * @param int $i 获取n次
     */
    public static function areaAllChildrenIds($ids, &$return = [], &$all_last_id = [], $i = 10000)
    {
        if ($i > 0) {
            --$i;
        } else {
            return;
        }
        $ids = array_unique($ids);
        $return && $ids = array_diff($ids, array_keys($return));
        $arr = $children_area = [];
        foreach ($ids as $v) {
            $rel_area_worker = sprintf('shenzhou:area:%s:children', $v);
            $data = RedisPool::getInstance()->sGetMembers($rel_area_worker);
            if ($i <= 0) {
                $all_last_id[] = $v;
                continue;
            }
            if (!$data) {
                $arr[] = $v;
            } else {
                $return[$v] = $data;
                $children_area = array_merge((array)$children_area, (array)$data);
                if ($i < 1) {
                    $all_last_id = array_merge((array)$all_last_id, (array)$children_area);
                }
            }
        }

        if ($arr) {
            $list = BaseModel::getInstance(self::getTableName())->getList([
                'field' => 'id,parent_id',
                'where' => [
                    'parent_id' => ['in', implode(',', $arr)],
                ],
                'index' => 'id',
            ]);
            $return_childred = [];
            foreach ($list as $v) {
                $return_childred[$v['parent_id']][] = $return[$v['parent_id']][] = $v['id'];
            }

            $all_last_id = array_merge((array)$all_last_id, (array)array_diff($arr, array_keys($return_childred)));

            foreach ($return_childred as $k => $v) {
                $rel_area_worker = sprintf('shenzhou:area:%s:children', $k);
                RedisPool::getInstance()->sAddArray($rel_area_worker, $v);
                RedisPool::getInstance()->expire($rel_area_worker, 24*3600);
                $children_area = array_merge((array)$children_area, (array)$v);
            }
        }
        $children_area && self::areaAllChildrenIds($children_area, $return, $all_last_id, $i);
    }

}