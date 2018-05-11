<?php
/**
 * File: BaseModel.class.php
 * User: xieguoqiu
 * Date: 2017/4/7 9:39
 */

namespace Script\Model;

use Script\Common\ErrorCode;

class BaseModel extends \Common\Common\Model\BaseModel
{
	public $is_other_db = '';

	function __construct($new_name = '', $prev = '', $conf = []) {
     	if (!empty($new_name) && $conf) {
        $this->is_other_db = $conf;
     		return parent::__construct($new_name, $prev, $conf);
      } elseif (!empty($new_name)) {
        return parent::__construct($new_name); 
      } {
      	return parent::__construct();
      }
  }

  public function setOtherAtModel($table = '')
  {
  	// return M($table, '', $is_other_db);
  	return new \Script\Model\BaseModel($table, '', $this->is_other_db);
  }

  public function last_sql()
  {
  	return M('', '', $this->is_other_db)->_sql();
  }
}
