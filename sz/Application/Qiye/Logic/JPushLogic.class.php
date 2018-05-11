<?php
/**
 * File: JPushLogic.class.php
 * User: sakura
 * Date: 2017/11/2
 */

namespace Qiye\Logic;

use Qiye\Common\ErrorCode;
use Qiye\Logic\BaseLogic;
use Qiye\Model\BaseModel;

class JPushLogic extends BaseLogic
{

    public function edit()
    {
        $user_id = $this->getParam('user_id');
        $jpush_id = $this->getParam('jpush_id');

        if (empty($jpush_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '极光绑定id为空');
        }

        $worker_db = BaseModel::getInstance('worker');

        //检查是否有其他用户使用了这个极光id
        $worker_ids = $worker_db->getFieldVal([
            'where' => [
                'jpush_alias' => $jpush_id
            ]
        ], 'worker_id', true);
        if (!empty($worker_ids)) {
            $worker_db->update([
                'worker_id' => ['in', implode(',', $worker_ids)]
            ], [
                'jpush_alias' => null
            ]);
        }

        $update_data = [
            'jpush_alias' => $jpush_id,
        ];
        $worker_db->update($user_id, $update_data); // 更新登录信息

    }

}