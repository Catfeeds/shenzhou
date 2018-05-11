<?php
/**
 * File: CommonController.class.php
 * User: xieguoqiu
 * Date: 2017/2/5 16:16
 */

namespace Qiye\Controller;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Library\Common\Util;
use Common\Common\Logic\ExpressTrackLogic;
use Common\Common\Service\AreaService;

class CommonController extends BaseController
{
    const CM_LIST_ITEM_TABLE_NAME = 'cm_list_item';
    const AREA_TABLE_NAME = 'area';

    public function areasThree()
    {
        $where = ['parent_id' => 0];
        $opt = [
            'where' => $where,
            // 'limit' => 1,
            'field' => 'id as value,name as label,parent_id,null as children', // ,item_sort,lat,lng
            'index' => 'value',
        ];
        $model = BaseModel::getInstance(self::AREA_TABLE_NAME);
        $count = $model->getNum($where);
        $list = $count ? 
                $model->getList($opt):
                [];

        $ids = arrFieldForStr($list, 'value');
        $list_2 = $ids ? $model->getList([
                        'where' => ['parent_id' => ['in', $ids]],
                        'index' => 'value',
                        'field' => $opt['field']
                    ]) : [];

        $ids = arrFieldForStr($list_2, 'value');
        $list_3 = $ids ? $model->getList([
                        'where' => ['parent_id' => ['in', $ids]],
                        'field' => $opt['field']
                    ]) : [];

        foreach ($list_3 as $k => $v) {
            $parent_id = $v['parent_id'];
            unset($v['parent_id']);
            $list_2[$parent_id]['children'][] = $v;
        }

        foreach ($list_2 as $k => $v) {
            $parent_id = $v['parent_id'];
            unset($v['parent_id'], $list[$parent_id]['parent_id']);
            $list[$parent_id]['children'][] = $v;
        }
        // die(json_encode(array_values($list), JSON_UNESCAPED_UNICODE));
        $this->response(array_values($list));
    }

    public function areaInfo()
    {
        $model = BaseModel::getInstance(self::AREA_TABLE_NAME);
        try {
            $id = I('get.id', 0, 'intval');
            $this->response($model->getOneOrFail($id));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function areaList()
    {
        try {
            $get = I('get.');
            isset($get['pid']) && $where['parent_id'] = I('get.pid', 0, 'intval');
            $model = BaseModel::getInstance(self::AREA_TABLE_NAME);
            $opt = [
                'where' => $where,
                'limit' => $this->page(),
                'field' => 'id,name,parent_id'
            ];
            $count = $model->getNum($where);
            $list = $count ? 
                    $model->getList($opt):
                    [];

            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getBanks()
    {
        $list = BaseModel::getInstance('cm_list_item')->getList([
                'where' => ['list_id' => 42],
                'field' => 'list_item_id as bank_id,item_desc as bank_name,item_parent as parent,item_thumb as bank_thumb',
            ]);

        foreach ($list as $k => $v) {
            $v['bank_thumb_full'] = $v['bank_thumb'] ? Util::getServerFileUrl($v['bank_thumb']) : '';
            $list[$k] = $v;
        }

        $this->responseList($list);
    }

    public function checkCreditCard()
    {
        try {
            $number = I('get.number', '');
            $update = [];
            $card_info = D('Worker', 'Logic')->checkCreditCard($number);
            $number = str_replace(' ', '', $number);
            if($card_info['status'] != 1){
                if(preg_match('/^[1-9]\d{15,19}$/', $number)){
                    $update['type'] = '借记卡';
                } elseif (preg_match('/^\d{11,}$/', $number)) {
                    $update['type'] = '其他';
                } else {
                    $this->throwException(ErrorCode::CREDIT_CARD_GS_IS_WRONG);
                }
            }else{
                $update['type'] = $card_info['data']['cardtype'];
            }
            $this->response($update);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
