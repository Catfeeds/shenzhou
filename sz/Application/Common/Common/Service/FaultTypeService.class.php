<?php
/**
 * Created by Sublime Text 3
 * User: zjz
 * Date: 2017/11/17
 * Time: 11:20
 */

namespace Common\Common\Service;

class FaultTypeService
{
    
    const REPAIR_PRODUCT				= 0;
	const INSTALLATION_PRODUCT			= 1;
	const MAINTENANCE_PRODUCT			= 2;

    // 服务类型
    const TYPE_WORKER_REPAIR            = 107;
    const TYPE_WORKER_INSTALLATION      = 106;
    const TYPE_PRE_RELEASE_INSTALLATION = 110;
    const TYPE_USER_SEND_FACTORY_REPAIR = 109;
    const TYPE_WORKER_MAINTENANCE       = 108;


	const FAULT_TYPE_NAME_ARR = [
		self::REPAIR_PRODUCT			=> '维修',
		self::INSTALLATION_PRODUCT		=> '安装',
		self::MAINTENANCE_PRODUCT		=> '维护',
	];

	public static function getFaultType($service_type)
    {
        if (in_array($service_type, [self::TYPE_WORKER_INSTALLATION, self::TYPE_PRE_RELEASE_INSTALLATION])) {
            return self::INSTALLATION_PRODUCT;
        } elseif (in_array($service_type, [self::TYPE_WORKER_MAINTENANCE])) {
            return self::MAINTENANCE_PRODUCT;
        } else {
            return self::REPAIR_PRODUCT;
        }
    }
}