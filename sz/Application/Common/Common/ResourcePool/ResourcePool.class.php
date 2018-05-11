<?php
/**
 * File: ResourcePool.class.php
 * Function:
 * User: sakura
 * Date: 2018/2/23
 */

namespace Common\Common\ResourcePool;


abstract class ResourcePool
{

    public abstract static function getInstance($is_new_instance = false);

}