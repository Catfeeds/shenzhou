<?php

define('API_SECRET_PARAM', 'api_url_secret');
define('API_SECRET_CODE',  '0A1B0c2D0e3F0G');

return [
    'URL_ROUTE_RULES' => [
        // 分类列表
        ['factory/categories', 'factoryProduct/getCategory', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 规格列表
        ['factory/standards', 'factoryProduct/getStandard', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 品牌列表
        ['factory/brands', 'factoryProduct/getBrand', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 产品列表
        ['factory/products', 'factoryProduct/getProducte', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'get']],
        // 创建工单 zjz
        ['orders', 'order/createOrder', API_SECRET_PARAM.'='.API_SECRET_CODE, ['method' => 'post']],
        
    ]
];
