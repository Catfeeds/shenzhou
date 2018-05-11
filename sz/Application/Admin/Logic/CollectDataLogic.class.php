<?php
/**
 * File: CollectDataLogic.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/21
 */

namespace Admin\Logic;


use Admin\Model\BaseModel;
use Think\Exception;

class CollectDataLogic extends BaseLogic
{

    protected $param = [];

    protected $result = [];

    const ONE_TO_ONE  = 1;
    const ONE_TO_MANY = 2;

    /**
     * 注册
     *
     * @param string $tag_table 目标表名
     * @param string $field     字段名
     * @param string $fk        外键名,用于搜索
     *
     * @return string 返回注册键名,用于写入
     * @throws Exception
     */
    public function register($tag_table, $field = '*', $fk = 'id')
    {
        if (strlen($tag_table) <= 0) {
            throw new Exception('请设置查询表名');
        }
        $fk = strlen($fk) <= 0 ? 'id' : $fk;
        $field = strlen($field) <= 0 ? '*' : $field;

        $register_key = $this->generateRegisterKey();
        $this->param[$register_key] = [
            'ids'        => [],
            'field'      => $field,
            'table_name' => $tag_table,
            'fk'         => $fk,
            'relation'   => self::ONE_TO_ONE, // 默认一对一
        ];

        return $register_key;
    }

    /**
     * 设置返回结果为一对一
     *
     * @param string $register_key 注册键名
     */
    public function one2One($register_key)
    {
        $this->param[$register_key]['relation'] = self::ONE_TO_ONE;
    }

    /**
     * 设置返回结果是一对多
     *
     * @param string $register_key 注册键名
     */
    public function one2Many($register_key)
    {
        $this->param[$register_key]['relation'] = self::ONE_TO_MANY;
    }

    /**
     * 生成注册键名
     * @return string
     */
    protected function generateRegisterKey()
    {
        return uniqid();
    }

    /**
     * 收集id
     *
     * @param string       $register_key 注册键名
     * @param string|array $ids          外键值
     */
    public function collect($register_key, $ids)
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $cur_ids = $this->param[$register_key]['ids'];
        $ids = array_merge($cur_ids, $ids);
        $this->param[$register_key]['ids'] = array_unique($ids);
    }

    /**
     * 获取数据
     *
     * @param string $execute_key 执行获取数据的注册键名,空为全部
     */
    public function execute($execute_key = '')
    {
        $execute_collect = [];

        if (strlen($execute_key) > 0) {
            $execute_collect[] = $execute_key;
        } else {
            $execute_collect = array_keys($this->param);
        }

        foreach ($execute_collect as $register_key) {
            $param = $this->param[$register_key];

            $table_name = $param['table_name'];
            $fk = $param['fk'];
            $field = $param['field'];
            $ids = $param['ids'];
            $relation = $param['relation'];
            $field = preg_match("#\b{$fk}\b#", $field) ? $field : $field . ',' . $fk;

            if (!empty($ids)) {
                $model = BaseModel::getInstance($table_name);
                $opts = [
                    'field' => $field,
                    'where' => [$fk => ['in', $ids]],
                ];
                $list = $model->getList($opts);

                $data = [];

                foreach ($list as $val) {
                    $search_key = $val[$fk];

                    if (self::ONE_TO_MANY == $relation) {
                        $data[$search_key][] = $val;
                    } else {
                        $data[$search_key] = $val;
                    }
                }

                $this->result[$register_key] = $data;
            }

        }

    }

    /**
     * 获取结果集
     *
     * @param string $register_key 注册键名
     * @param string $fk           外键值
     *
     * @return array
     */
    public function getResultInfo($register_key, $fk)
    {
        return $this->result[$register_key][$fk]?? [];
    }


}