<?php

namespace Common\Common\Model;

use Common\Common\ErrorCode;
use Think\Model;

class BaseModel extends Model
{

    protected static $instance = array();

    /**
     * 当出现嵌套开启事务的情况下，计算开启事务的次数，
     * 以第一次开启事务和最后一次提交为准
     * @var int
     */
    protected static $transCount = 0;

    /**
     * 作为静态函数，再也不用担心在Controller调用的问题了，
     * TP3.2开始M('Api\Model\BaseModel:User')需要带上命名空间，否则无法创建，
     * 可使用new BaseModel('User')的方法代替，但这种无法链式操作，
     * 在Controller中调用：\Api\Model\BaseModel::getInstance('User'),
     * 使用命名空间引入BaseModel后更无需每次加上前面的命名空间限定，
     * 在Model中调用$this->getInstance('User')或self::getInstance('User')
     * @param $table_name
     * @return BaseModel
     */
    public static function getInstance($table_name)
    {
        if (!isset(self::$instance[$table_name])) {
            self::$instance[$table_name] = new self($table_name, null);
        }
        return self::$instance[$table_name];
    }

    /**
     * 调用Model的query方法
     * @param string $sql
     * @param mixed $parse  是否需要解析SQL
     * @return mixed
     */
    public function query($sql, $parse = false)
    {
        $result = parent::query($sql, $parse);
        $this->occurDbError($result);
        return $result;
    }

    /**
     * 调用Model的execute方法
     * @param string $sql
     * @param mixed $parse  是否需要解析SQL
     * @return false|int
     */
    public function execute($sql,$parse = false)
    {
        $result = parent::execute($sql, $parse);
        $this->occurDbError($result);
        return $result;
    }

    /**
     * 开启事务，嵌套使用事务时计算开启事务的次数，
     * 只有第一次调用时才开启事务，并且不会发生提交
     * （TP每次调用startTrans默认是会发生一次事务提交的）
     */
    public function startTrans()
    {
//        if (static::$transCount == 0) {
        $this->db->startTrans();
//        }

//        ++static::$transCount;
    }

    /**
     * 提交事务，保证嵌套使用事务时，
     * 确保最后一次提交才把数据修改入库
     */
    public function commit()
    {
//        --static::$transCount;
        
//        if (static::$transCount == 0) {
        $this->db->commit();
//        }
    }

    /**
     * 强制提交事务，在嵌套事务中慎用
     */
    public function forceCommit()
    {
        static::$transCount = 0;

        $this->db->commit();
    }

    /**
     * 回滚事务
     */
    public function rollback()
    {
        static::$transCount = 0;
        
        $this->db->rollback();
    }

    /**
     * 根据ID获取字段列表信息
     * @param array $ids                id列表
     * @param string $fields            字段列表
     * @param array $extra              额外条件，可添加order等条件
     * @param string $id_field
     * @return array|mixed
     */
    public function getFieldsByIds($ids, $fields = '*', $extra = [], $id_field = 'id')
    {
        if (!$ids) {
            return [];
        }

        if (!in_array($id_field, $fields)) {
            array_unshift($fields, $id_field);
        }

        $options = [
            'where' => [$id_field => ['IN', $ids]]
        ];
        $options = array_merge_recursive($options, $extra);

        $fields = implode(',', $fields);

        return $this->getFieldVal($options, $fields);
    }

    /**
     * 添加额外数据到列表中，
     * 适用于一对一关系
     * @param array $list                   数据列表
     * @param array $field                  需要添加的字段列表
     * @param array $extra                  额外的where信息
     * @param string $foreign_id_field      外键字段
     * @param string $list_key              列表中显示的key，留空则默认使用表名
     * @throws \Exception
     */
    public function attachField2List(
        &$list,
        $field = [],
        $extra = [],
        $foreign_id_field = '',
        $list_key = ''
    ) {
        if (!$field) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'Attach field invalid');
        }

        if (!$foreign_id_field) {
            $foreign_id_field = $this->getTableName() . '_id';
        }

        $ids = [];
        foreach ($list as $item) {
            if (!array_key_exists($foreign_id_field, $item)) {
                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'Attach foreign key not valid in list.');
            }
            $item[$foreign_id_field] && $ids[] = $item[$foreign_id_field];
        }

        if (!$ids) {
            return ;
        }

        if (!is_array($field)) {
            $field = explode(',', $field);
        }

        $require_pk = true;
        if (!in_array($this->getPk(), $field)) {
            $require_pk = false;
            array_unshift($field, $this->getPk());
        }

        $where = array_merge([$this->getPk() => ['IN', $ids]], $extra);
        $list_info = $this->getList(
            [
                'field' => $field,
                'where' => $where,
            ]
        );
        $id_map = [];
        foreach ((array)$list_info as $item) {
            $pk = $item[$this->getPk()];

            if (!$require_pk) {
                unset($item[$this->getPk()]);
            }

            $id_map[$pk] = $item;
        }

        $list_key = $list_key ? : $this->getTableName();

        foreach ($list as $key => $val) {
            $list[$key][$list_key] = $id_map[$val[$foreign_id_field]];
        }
    }

    /**
     * 添加额外数据到列表中，
     * 适用于一对多关系
     * @param array $list                   列表数据
     * @param string $foreign_key           当前model的外键
     * @param string $field                  字段列表
     * @param array $extra                  额外的where条件
     * @param string $list_primary_key      列表数据在中间表中的外键
     * @param string $list_key              列表中显示的key，留空则默认使用表名
     * @throws \Exception
     */
    public function attachMany2List(
        &$list,
        $foreign_key,
        $field = '*',
        $extra = [],
        $list_primary_key = 'id',
        $list_key = ''
    ) {
        $ids = [];
        foreach ($list as $item) {
            if (!isset($item[$list_primary_key])) {
                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'Attach foreign key not valid in list.');
            }
            $ids[] = $item[$list_primary_key];
        }

        if (!$ids) {
            return ;
        }

        $require_fk = true;
        !is_array($field) && $field = explode(',', $field);
        if (!in_array($foreign_key, $field)) {
            $require_fk = false;
            $field[] = $foreign_key;
        }

        $where = array_merge([$foreign_key => ['IN', $ids]], $extra);
        $list_info = $this->getList(
            [
                'field' => $field,
                'where' => $where,
            ]
        );

        $id_map = [];
        foreach ($list_info as $item) {
            $fk = $item[$foreign_key];

            if (!$require_fk) {
                unset($item[$foreign_key]);
            }

            $id_map[$fk][] = $item;
        }

        $list_key = $list_key ? : $this->getTableName();

        foreach ($list as $key => $val) {
            $list[$key][$list_key] = $id_map[$val[$list_primary_key]];
        }
    }

    /**
     * 添加额外数据到列表中，
     * 适用于多对多关系
     * @param $list
     * @param $relational_table
     * @param $list_table_foreign_key
     * @param array $field
     * @param array $extra
     * @param string $list_key
     * @param string $current_table_foreign_key
     * @param $list_table_primary_key
     * @param string $table_prefix
     * @throws \Exception
     */
    public function attachManyThrough(
        &$list,
        $relational_table,
        $list_table_foreign_key,
        $field = ['*'],
        $extra = [],
        $list_key = '',
        $current_table_foreign_key = '',
        $list_table_primary_key = 'id',
        $table_prefix = 'tb_'
    ) {
        if (!$field) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'Attach field invalid');
        }

        $list_ids = [];
        foreach ($list as $item) {
            if (!isset($item[$list_table_primary_key])) {
                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'Attach primary key not valid in list.');
            }
            $list_ids[] = $item[$list_table_primary_key];
        }

        if (!$list_ids) {
            return ;
        }

        if (!$current_table_foreign_key) {
            $current_table_foreign_key = $this->getTableName() . '_id';
        }

        $require_fk = true;
        if (!in_array($list_table_foreign_key, $field)) {
            $require_fk = false;
            array_push($field, $list_table_foreign_key);
        }

        $pk = $this->getPk();
        $options = [
            'alias' => 'cur',
            'field' => $field,
            'join' => "LEFT JOIN {$relational_table} rel ON rel.{$current_table_foreign_key}=cur.{$pk}",
            'where' => [$list_table_foreign_key => ['IN', $list_ids]]
        ];
        $options = array_merge($options, $extra);
        $relational_data = $this->getList($options);

        $list_table_id_map = [];
        foreach ($relational_data as $item) {
            $list_table_foreign_key_val = $item[$list_table_foreign_key];

            if (!$require_fk) {
                unset($item[$list_table_foreign_key]);
            }
            $list_table_id_map[$list_table_foreign_key_val][] = $item;
        }

        $list_key = $list_key ? : $this->getTableName();

        foreach ($list as &$val) {
            $val[$list_key] = $list_table_id_map[$val[$list_table_primary_key]];
        }
    }

    /**
     * 重置多对多关系
     * @param int $id 当前model的ID
     * @param string $rel_table 关系表名称
     * @param string $other_foreign_key 另一个表的外键字段
     * @param array $other_rel_ids 另一个表的外键值列表
     * @param bool $is_update 是否为更新操作
     * @param string $foreign_key 当前表的外键字段，可不指定
     * @throws \Exception
     */
    public function resetRelation(
        $id,
        $rel_table,
        $other_foreign_key,
        $other_rel_ids,
        $is_update = false,
        $foreign_key = ''
    ) {
        if (!$other_foreign_key) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'Other foreign key invalid.');
        }

        if (!$foreign_key) {
            $foreign_key = $this->getTableName() . '_id';
        }

        $rel_data = [];
        foreach ($other_rel_ids as $rel_id) {
            $rel_data[] = [
                $foreign_key => $id,
                $other_foreign_key => $rel_id
            ];
        }

        $rel_model = BaseModel::getInstance($rel_table);

        if ($is_update) {
            $rel_model->remove([$foreign_key => $id]);
        }
        $rel_model->insertAll($rel_data);
    }

    /**
     * 重置多对多关系,与resetRelation不同在于可自定义重新插入的值
     * @param $id
     * @param $rel_table
     * @param $key_map
     * @param bool $is_update
     * @param string $foreign_key
     * @throws \Exception
     */
    public function resetRelationByMap(
        $id,
        $rel_table,
        $key_map,
        $is_update = false,
        $foreign_key = ''
    ) {
        if (!$key_map) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, 'foreign key map invalid.');
        }

        if (!$foreign_key) {
            $foreign_key = $this->getTableName() . '_id';
        }

        $rel_data = [];
        foreach ($key_map as $rel) {
            $rel_data[] = array_merge([$foreign_key => $id], $rel);
        }

        $rel_model = BaseModel::getInstance($rel_table);

        if ($is_update) {
            $rel_model->remove([$foreign_key => $id]);
        }
        $rel_model->insertAll($rel_data);
    }

    /**
     * 获取一条数据
     * @param array|int $opts
     * @param string $field	查询的字段,只有$opts参数为id或where单选项时才会使用该字段
     * @return array|boolean 成功返回对应关联数组数据，失败返回false
     */
    public function getOne($opts, $field = '*')
    {
        if (!is_array($opts)) { // 根据id查询
            $data = $this->field($field)->find($opts);
        } else if ($this->isMultiQuery($opts)) {  // 多选项查询
            $data = $this->setOptions($opts)->find();
        } else {    // 单独where语句选项查询
            $data = $this->field($field)->where($opts)->find();
        }
        $this->occurDbError($data);
        return $data;
    }

    /**
     * 获取一条数据，如果该数据不存在则抛出异常
     * @param $opts
     * @param string $field
     * @return array|bool
     * @throws \Exception
     */
    public function getOneOrFail($opts, $field = '*')
    {
        $item = $this->getOne($opts, $field);
        if ($item === null) {
            $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
        }
        return $item;
    }

    /**
     * 根据参数项获取数据列表
     * @param array $opts 参数项
     * @param $field
     * @return array|boolean 成功返回对应关联数组数据，失败返回false
     */
    public function getList($opts = array(), $field = '*')
    {
        if ($this->isMultiQuery($opts)) {
            $data = $this->setOptions($opts)->select();
        } else {
            $data = $this->field($field)->where($opts)->select();
        }
        $this->occurDbError($data);
        return $data;
    }

    /**
     * 获取一条/多条记录的某个字段值,
     * 当$separator为true时将结果按索引数组返回,否则按$separator指定的分隔符按字符串返回
     * @param $opts
     * @param $field
     * @param null $separator
     * @return mixed
     */
    public function getFieldVal($opts, $field, $separator = null)
    {
        if (!is_array($opts)) { // 根据id查询
            $rows = $this->where(array($this->getPk() => $opts))->getField($field, $separator);
        } else if ($this->isMultiQuery($opts)) {  // 多选项查询
            $rows = $this->setOptions($opts)->getField($field, $separator);
        } else {    // 单独where语句选项查询
            $rows = $this->where($opts)->getField($field, $separator);
        }
        $this->occurDbError($rows);
        return $rows;
    }

    /**
     * 添加数据
     * @param array $data
     * @return string|boolean 成功返回添加后的id，失败返回false
     */
    public function insert($data)
    {
        $id = $this->add($data);
        $this->occurDbError($id);
        return $id;
    }

    /**
     * 批量插入
     * @param $data
     * @return bool|string
     */
    public function insertAll($data)
    {
        $rows = $this->addAll($data);
        $this->occurDbError($rows);
        return $rows;
    }

    /**
     * 批量插入数据，由于使用TP自定的addAll必须传关联数组，
     * 需要插入的字段（即KEY）也出现N次导致的浪费，
     * 因此将插入字段与分数分成单独的参数，
     * 并且对于SplFixedArray可以有更快的效率及内存使用率
     * @param \SplFixedArray $fields
     * @param \SplFixedArray $data
     * @return false|int
     */
    public function batchInsert(\SplFixedArray $fields, $data)
    {
        $table_name = $this->getTableName();
        $field_num = count($fields);
        $field_list = '';
        foreach ($fields as $field) {
            $field_list .= "`{$field}`,";
        }
        $field_list = substr($field_list, 0, -1);
        $sql = "INSERT INTO {$table_name}({$field_list}) VALUES";
        foreach ($data as $item) {
            $tmp_val = '';
            for ($i = 0; $i < $field_num; ++$i) {
                $tmp_val .= '\'' .$item[$i] . '\',';
            }
            $sql .= '(' . substr($tmp_val, 0, -1) . '),';
        }
        $sql = substr($sql, 0, -1);
        return $this->execute($sql);
    }

    /**
     * 删除数据
     * @param $opts
     * @return mixed
     */
    public function remove($opts)
    {
        if (is_array($opts)) {
            $rows = $this->where($opts)->delete();
        } else {
            $rows = $this->delete($opts);
        }
        $this->occurDbError($rows);
        return $rows;
    }

    /**
     * 更新数据
     * @param $opts
     * @param array $fields
     * @return bool
     */
    public function update($opts, $fields = array())
    {
        if (!is_array($opts)) { // 根据id查询
            $rows = $this->where(array($this->getPk() => $opts))->save($fields);
        } else if ($this->isMultiQuery($opts)) {
            $rows = $this->setOptions($opts)->save($fields);
        } else {
            $rows = $this->where($opts)->save($fields);
        }
        $this->occurDbError($rows);
        return $rows;
    }

    /**
     * 获取数量
     * @param $opts
     * @param $field
     * @return int|boolean 成功返回数量，失败返回false
     */
    public function getNum($opts = [], $field = '*')
    {
        if ($this->isMultiQuery($opts)) {
            $num = $this->setOptions($opts)->count($field);
        } else if (is_array($opts)) {
            $num = $this->where($opts)->count($field);
        } else {
            $num = $this->where(array($this->getPk() => $opts))->count($field);
        }
        $this->occurDbError($num);
        return $num;
    }

    public function getSum($opts, $field)
    {
        if ($this->isMultiQuery($opts)) {
            $num = $this->setOptions($opts)->sum($field);
        } else if (is_array($opts)) {
            $num = $this->where($opts)->sum($field);
        } else {
            $num = $this->where(array($this->getPk() => $opts))->sum($field);
        }
        $this->occurDbError($num);
        return $num;
    }

    /**
     * 增加字段值
     * @param $opts
     * @param $field
     * @param int $step
     * @return bool
     */
    public function setNumInc($opts, $field, $step = 1)
    {
        if (!is_array($opts)) {
            $rows = $this->where(array($this->getPk() => $opts))->setInc($field, $step);
        } else if ($this->isMultiQuery($opts)) {
            $rows = $this->setOptions($opts)->setInc($field, $step);
        } else {
            $rows = $this->where($opts)->setInc($field, $step);
        }
        $this->occurDbError($rows);
        return $rows;
    }

    /**
     * 减少字段值
     * @param $opts
     * @param $field
     * @param int $step
     * @return bool
     */
    public function setNumDec($opts, $field, $step = 1)
    {
        if (!is_array($opts)) {
            $rows = $this->where(array($this->getPk() => $opts))->setDec($field, $step);
        } else if ($this->isMultiQuery($opts)) {
            $rows = $this->setOptions($opts)->setDec($field, $step);
        } else {
            $rows = $this->where($opts)->setDec($field, $step);
        }
        $this->occurDbError($rows);
        return $rows;
    }

    /**
     * 判断指定数据是否存在
     * @param array $opts
     * @return boolean 存在返回true，不存在返回false
     */
    public function dataExist($opts)
    {
        return (bool)($this->getNum($opts));
    }

    public function getSql($opts, $field = '*')
    {
        if ($this->isMultiQuery($opts)) {
            $this->setOptions($opts);
        } else if (is_array($opts)) {
            $this->where($opts);
        } else {
            $this->where(array($this->getPk() => $opts))->count($field);
        }
        return $this->buildSql();
    }

    /**
     * 是否是为多选项查询
     * @param $opts
     * @return bool
     */
    protected function isMultiQuery($opts)
    {
        // ||判断常用的放在可减少判断次数
        return is_array($opts) && (isset($opts['where']) || isset($opts['field']) || isset($opts['join']) ||
            isset($opts['order']) || isset($opts['limit']) || isset($opts['group']) || isset($opts['union']));
    }

    /**
     * 设置数据库选项
     * @param array $opts
     * @return BaseModel
     */
    protected function setOptions($opts = array())
    {
        foreach ($opts as $k=>$v) {
            $this->$k($v);
        }
        return $this;
    }

    /**
     * 如果$data为false，则抛出数据库错误异常
     * @param $data
     * @throws \Exception
     */
    protected function occurDbError(&$data)
    {
        if ($data === false) {
            APP_DEBUG ? $this->throwException(ErrorCode::SYS_DB_ERROR, $this->getDbError()) : $this->throwException(ErrorCode::SYS_DB_ERROR);
        }
    }

    /**
     * @param $error_code
     * @param string $error_msg
     * @throws \Exception
     */
    protected function throwException($error_code, $error_msg = '')
    {
        empty($error_msg) && $error_msg = ErrorCode::getMessage($error_code);
        throw new \Exception($error_msg, $error_code);
    }

    /**
     * @param $field_arr
     * @param string $field
     */
    public function fieldArrToFieldStr($field_arr = [])
    {
        $field = '*';
        if ($field_arr) {
            $field_v = [];
            foreach ($field_arr as $k => $v) {
                $arr = array_unique(array_filter(explode(',', $v)));
                if (!$arr) {
                    continue;
                }
                $field_v[] =  $k.'.'.implode(','.$k.'.', $arr) ;
            }
            if ($field_v) {
                $field = implode(',', $field_v);
            }
        }
        return $field;
    }

}