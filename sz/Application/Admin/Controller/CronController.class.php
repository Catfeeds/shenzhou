<?php
/**
 * File: CronController.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/16
 */

namespace Admin\Controller;


class CronController extends BaseController
{

    public function settle()
    {
        try {
            D('Cron', 'Logic')->orderSettle();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function clear()
    {
        try {

            D('Cron', 'Logic')->clearFatalErrorLog();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function promptWorkerUploadAccessoryReport()
    {
        try {

            D('Cron', 'Logic')->promptWorkerUploadAccessoryReport();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function promptWorkerAppointTomorrow()
    {
        try {

            D('Cron', 'Logic')->promptWorkerAppointTomorrow();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function promptFactoryAccessoryConfirmSend()
    {
        try {
            D('Cron', 'Logic')->promptFactoryAccessoryConfirmSend();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function promptWorkerAccessorySendBack()
    {
        try {
            D('Cron', 'Logic')->promptWorkerAccessorySendBack();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    public function promptWorkerUploadReport()
    {
        try {
            M()->startTrans();
            D('Cron', 'Logic')->promptWorkerUploadReport();
            M()->commit();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}