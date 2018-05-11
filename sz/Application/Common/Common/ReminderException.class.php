<?php
/**
 * File: ReminderException.class.php
 * User: huangyingjian
 * Date: 2017/11/1
 */

namespace Common\Common;

class ReminderException extends \Exception
{

    public $data;

    /**
     * ReminderException constructor.
     *
     * @param string $message
     * @param int    $code
     * @param object $data
     */
    public function __construct($message, $code, $data)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }
}