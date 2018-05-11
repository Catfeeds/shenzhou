<?php
/**
 * File: PasswordLogic.class.php
 * User: sakura
 * Date: 2017/11/2
 */

namespace Qiye\Logic;

use Qiye\Logic\BaseLogic;

class PasswordLogic extends BaseLogic
{

    public function checkSafe($password)
    {
        $pattern = "#^[a-z0-9]{6,18}$#i";
        return preg_match($pattern, $password);
    }

}