<?php
/**
 * File: ExpressTrackingService.class.phpss.php
 * Function:
 * User: sakura
 * Date: 2018/4/23
 */

namespace Common\Common\Service;


class ExpressTrackingService
{

    //物流对应业务类型 1-配件单发件 2配件单返件 3-工单预发件安装单发件 4-师傅码邮寄
    const TYPE_ACCESSORY_SEND      = 1;
    const TYPE_ACCESSORY_SEND_BACK = 2;
    const TYPE_ORDER_PRE_INSTALL   = 3;
    const TYPE_MASTER_CODE_SEND    = 4;

    const TYPE_VALID_ARRAY
        = [
            self::TYPE_ACCESSORY_SEND,
            self::TYPE_ACCESSORY_SEND_BACK,
            self::TYPE_ORDER_PRE_INSTALL,
            self::TYPE_MASTER_CODE_SEND,
        ];

    //运单状态：默认：-1，0在途中、1已揽收、2疑难、3已签收
    const STATE_ON_THE_WAY = 0;
    const STATE_PICK_UP    = 1;
    const STATE_DIFFICULT  = 2;
    const STATE_RECEIVE    = 3;

    //是否订阅成功，0否，1是
    const IS_BOOK_NO  = 0;
    const IS_BOOK_YES = 1;

}