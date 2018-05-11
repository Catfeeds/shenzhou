<?php
/**
 * @User fzy
 */
namespace Admin\Logic;

use Admin\Logic\BaseLogic;
use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Common\BaiDuLbsApi;
use Library\Crypt\AuthCode;
use Library\Common\Util;

class SupportPersonLogic extends BaseLogic
{
    //新增技术支持人
    public function addTechnicalSupportPerson($factory_id = '0', $help_list = '0')
    {
        $factory_ids = $factory_id;
        $data = $help_list;
        $condition = $insert_data = [];
        $count = 0;
        foreach ($data as $kv => $c) {
            if (empty($c['name']) || empty($c['telephone'])) {
                $this->throwException(ErrorCode::CHECK_SUPPORTPERSON_AND_PHONE_IS_NULL);
            }
            if ($c['is_default'] == 1) {
                $count ++;
            }
        }
        if ($count > 1) {
            $this->throwException(ErrorCode::CHECK_IS__SUPPORTPERSON);
            die('d');
        }
        foreach ($data as $k => $v) {
            if (empty($v['id'])) {
                $phone = $v['telephone'];

                $condition[] = array('name' => $v['name'], '_logic' => 'or', 'telephone' => $v['telephone']);
                $condition['factory_id'] = $factory_ids;
                $red = BaseModel::getInstance('factory_helper')->getNum($condition);
                unset($condition);
                unset($phone);
                //过滤不是需要修改的技术支持人
                if (!empty($red)) {
                    $this->throwException(ErrorCode::CHECK_IS__EXIST, '该技术支持人名称或者手机已存在');
                } else {
                    //由于前端无法判断是否真正修改的，所以重置默认支持人
                    if ($v['is_default'] == 1) {
                        $where_reset['is_default'] = 0;
                        $factory_id_reset['factory_id'] = $factory_ids;
                        BaseModel::getInstance('factory_helper')->update($factory_id_reset, $where_reset);
                    }
                    $insert_data[] = [
                        'factory_id' => $factory_ids,
                        'name' => $v['name'],
                        'telephone' => $v['telephone'],
                        'is_default' => $v['is_default']
                    ];

                }
            }
        }
        if (!empty($insert_data)) {
            BaseModel::getInstance('factory_helper')->insertAll($insert_data);
        }

    }

    //编辑技术支持人
    public function editTechnicalSupportPerson($factory_id = '0', $help_list = '0')
    {
        $factory_ids = $factory_id;
        $data = $help_list;

        $condition = $update_data = [];
        $count = 0;
        foreach ($data as $kv => $c) {
            if (empty($c['name']) || empty($c['telephone'])) {
                $this->throwException(ErrorCode::CHECK_SUPPORTPERSON_AND_PHONE_IS_NULL);
            }
            if ($c['is_default'] == 1) {
                $count ++;
            }
        }
        if ($count > 1) {
            $this->throwException(ErrorCode::CHECK_IS__SUPPORTPERSON);
            die('d');
        }
        foreach ($data as $k => $v) {
            if ($v['id']) {
                $phone = $v['telephone'];

                $condition_check[] = array([
                    'name' => $v['name'],
                    '_logic' => 'or',
                    'telephone' => $v['telephone'],
                    ],
                    'id' => ['NEQ',$v['id']],
                    'factory_id' => $factory_ids,
                );
                //检验手机和名称
                $red_check = BaseModel::getInstance('factory_helper')->getNum([$condition_check]);
//                var_dump(M()->_sql());
//                die($red_check);exit;
                if (!empty($red_check)) {
                    $this->throwException(ErrorCode::CHECK_IS__EXIST, '该技术支持人名称或者手机已存在');

//                    $condition['name'] = $v['name'];
//                    $condition['telephone'] = $v['telephone'];
//                    $condition['is_default'] = $v['is_default'];
//                    $condition['factory_id'] = $factory_ids;
//                    $red = BaseModel::getInstance('factory_help_person')->getOne($condition);
//
//                    //过滤不是需要修改的技术支持人
//                    if (!empty($red)) {
//                        //由于前端无法判断是否真正修改的，所以检测是否是真正有 修改的
//                        if ($red['id'] != $v['id']) {
//                            $this->throwException(ErrorCode::CHECK_IS__EXIST, '该技术支持人名称或者手机已存在');
//                        }
//                    } else {
//                        die('sd');
//                        $this->throwException(ErrorCode::CHECK_IS__EXIST, '该技术支持人名称或者手机已存在');
//                    }

                }
//                else {
//                    if ($red_check) {
//                        $this->throwException(ErrorCode::CHECK_IS__EXIST, '该技术支持人名称或者手机已存在');
//                    }
                    //由于前端无法判断是否真正修改的，所以重置默认支持人
                if ($v['is_default'] == 1) {
                    $where_reset['is_default'] = 0;
                    $factory_id_reset['factory_id'] = $factory_ids;
                    BaseModel::getInstance('factory_helper')->update($factory_id_reset, $where_reset);
                }


//                }
                $update_data[] = [
                    'id' => $v['id'],
                    'factory_id' => $factory_ids,
                    'name' => $v['name'],
                    'telephone' => $v['telephone'],
                    'is_default' => $v['is_default'] //新表
                ];
            }
            unset($condition);
            unset($phone);
            unset($condition_check);
        }
//        var_dump(M()->_sql());
//        var_dump($update_data);
//        EXIT;
        if (!empty($update_data)) {
            D('Publics', 'Logic')->updateAll('factory_helper', $update_data, 'id');
//                updateAll('factory_help_person', $update_data, 'id');
        }

    }

    //批量删除技术支持人
    public function delBatchTechnicalSupportPerson($delSupportPerson = 0)
    {
        $data = $delSupportPerson;
        $where = [];
        if (!empty($data)) {
            $where['id'] = array('in', $data);
            BaseModel::getInstance('factory_helper')->remove($where);
        }
    }

    public function resetFactoryDefaultHelper($factory_id)
    {
        BaseModel::getInstance('factory_helper')->update(['factory_id' => $factory_id, 'is_default' => 1], [
            'is_default' => 0,
        ]);
    }
}
