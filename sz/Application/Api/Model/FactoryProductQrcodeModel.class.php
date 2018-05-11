<?php
/**
 * File: FactoryProductQrcodeModel.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 17:52
 */

namespace Api\Model;

use QiuQiuX\BaseConvert\BaseConvert;

class FactoryProductQrcodeModel extends BaseModel
{
    
    protected $trueTableName = 'factory_product_qrcode';

    public function getInfoByCode($code)
    {
        $factory_code = substr($code,1,3);
        $factory_id = BaseConvert::convert($factory_code, '36', 10);
        $product_code = substr($code,4);

        $info = $this->getOneOrFail(
            [
                'factory_id' => $factory_id,
                'qr_first_int' => ['ELT', $product_code],
                'qr_last_int' => ['EGT', $product_code]
            ]
        );
        
        return $info;
    }
    
}
