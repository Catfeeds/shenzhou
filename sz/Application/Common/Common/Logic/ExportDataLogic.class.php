<?php
/**
 * create 2016/04/05 12:06
 * user zjz
 */
namespace Common\Common\Logic;

class ExportDataLogic
{
    public $objPHPExcel;
    public $sheetIndex;
    public $sheetTitle;
    public $error;
    protected $cellName = array();
    protected $sheetCellName = array();
    protected $cellKeys = array();

    function __construct($filename = '')
    {
        Vendor('PHPExcel.PHPExcel');
        $this->objPHPExcel = file_exists($filename) ? \PHPExcel_IOFactory::load($filename) : new \PHPExcel();
        $this->sheetIndex  = 0;
        $this->initExcel();
        $this->error = ['state' => 0, 'mess' => ''];

        $this->cellName = array(
            'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
            'AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU',
            'AV','AW','AX','AY','AZ'
        );

        $this->cellKeys = array();

    }

    protected function initExcel(){
        $this->objPHPExcel
             ->getProperties()
             ->setCreator("Maarten Balliauw")
             ->setLastModifiedBy("Maarten Balliauw")
             ->setTitle("Office 2007 XLSX Test Document")
             ->setSubject("Office 2007 XLSX Test Document")
             ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
             ->setKeywords("office 2007 openxml php")
             ->setCategory("Test result file");
    }

    public function setExcelForDatas($sets_arr = [], $key_arr = [], $datas, $in_arr = [], $num = 2)
    {
        if($this->objPHPExcel){
            $this->objPHPExcel->createSheet();
        }
        if(!$this->sheetTitle) $this->setSheetTitle();
        $this->error = [];
        foreach ($sets_arr as $k => $v) {
            switch ($k) {
                case 'setWidth':
                    foreach ($v as $key => $value) 
                        $this->objPHPExcel->getActiveSheet($this->sheetIndex)->getColumnDimension($key)->setWidth($value);
                    break;
                case 'setCellValue':
                    foreach ($v as $key => $value)
                        foreach ($value as $ks => $vs)
                            $this->objPHPExcel->setActiveSheetIndex($this->sheetIndex)->setCellValue($ks.''.$key, $vs);
                    break;
                case 'mergeCells':
                    foreach ($v as $value)
                        $this->objPHPExcel->setActiveSheetIndex($this->sheetIndex)->mergeCells($value);
                    break;
                default:
                    $this->error['mess'][$k] = $v;
                    break;
            }
        }

        foreach ($datas as $v) {
            foreach ($key_arr as $key => $value) {
                $set = $v[$value];
                if(isset($in_arr[$value])) $set = $in_arr[$value][$set];
                $this->objPHPExcel->setActiveSheetIndex($this->sheetIndex)->setCellValue($key.''.$num, $set);   
            }
            $num++;
        }
        $this->objPHPExcel->getActiveSheet($this->sheetIndex)->setTitle($this->sheetTitle);
    }


    /**
     * 设置表格的相关属性
     *
     * @param array $title 表格的表头 ['setWidth' => 10, 'setCellValue' => '学生编号', 'cellKey' => 'aaa']
     * @param array $lines 表格数据 [['key' => 'val', 'key1' => 'val1'], ['key' => 'val', 'key1' => 'val1']]
     * @param array $lineCallback 行的回调函数
     * @param int $startLine 开始行数
     * @return null
     * @throws \PHPExcel_Exception
     */
    public function setSheetData($title, $lines, $lineCallback=null, $startLine=1) {
        $titleLen = count($title);
        if (count($title) < 0) {
            return null;
        }
        $this->sheetCellName = array_slice($this->cellName, 0, $titleLen);

        if($this->objPHPExcel){
            $this->objPHPExcel->createSheet();
        }

        if(!$this->sheetTitle)
            $this->setSheetTitle();

        // 遍历Title的行
        $firstLine = $this->objPHPExcel->setActiveSheetIndex($this->sheetIndex);
        foreach ($title as $index => $cellSet) {
            $this->cellKeys[] = $cellSet['cellKey'];
            // 遍历Title的列
            foreach ($cellSet as $cellSetName => $cellSetVal) {
                // 属性名
                switch ($cellSetName) {
                    case 'setWidth':
                        $firstLine->getColumnDimension($this->sheetCellName[$index])->setWidth($cellSetVal);
                        break;
                    case 'setCellValue':
                        $firstLine->setCellValue($this->sheetCellName[$index] . $startLine, $cellSetVal);
                        // 高亮加粗
                        $firstLine->getStyle($this->sheetCellName[$index] . $startLine)->getFont()->setBold(true);
                        $firstLine->getStyle($this->sheetCellName[$index] . $startLine)
                            ->getFill()->setFillType('solid');
                        $firstLine->getStyle($this->sheetCellName[$index] . $startLine)
                            ->getFill()->getStartColor()->setARGB('D0D0D0');

                        break;
                }
            }
        }
        $startLine++;

        // 遍历数据行
        foreach ($lines as $item) {
            if (!is_null($lineCallback)) {
                $item = call_user_func($lineCallback, $item);
            }
            foreach ($this->sheetCellName as $index => $cellName) {
                $cellKey = $this->cellKeys[$index];
                $cellVal = '';
                if(isset($item[$cellKey])) {
                    $cellVal = $item[$cellKey];
                }
                // A . 行1 用户设置第1行A列的数据
                $this->objPHPExcel->setActiveSheetIndex($this->sheetIndex)->setCellValue($cellName.''.$startLine, $cellVal);
            }
            $startLine++;
        }
        $this->objPHPExcel->getActiveSheet($this->sheetIndex)->setTitle($this->sheetTitle);
    }

    public function setSheetIndex($sheetIndex = 0)
    {
        $this->sheetIndex = $sheetIndex;
    }

    public function setSheetTitle($title = null)
    {
        $this->sheetTitle = ($title === null) ? '工作表'.($this->sheetIndex+1) : $title;
    }

    public function getError()
    {
        return $this->error;
    }

    public function putOut($fileName)
    {
        $objWriter = new \PHPExcel_Writer_Excel5($this->objPHPExcel);
        if(!stripos($_SERVER["HTTP_USER_AGENT"],'firefox')){//特别兼容火狐。
            $fileName = urlencode($fileName);
        }
        ob_end_clean();
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET, POST, PUT, DELETE");
        header('Pragma:public');
        header('Content-Type:application/x-msexecl;name="' . $fileName . '.xls"');
        header('Content-Disposition:inline;filename="' . $fileName . '.xls"');
        $objWriter->save("php://output");
    }

    /**
     * 下载Excel
     * @author: luzg (2016-04-06 19:22:52)
     *
     * @param string $fileName 文件名字
     * @param array $title 没列的名字
     * @param array $data 数组，N行N列
     * @param array $callback 提供一个函数，用于过滤
     */
    public function downloadExcel($fileName, $title, $data, $callback=null) {
        // array(
        //     array('key' => 'val', 'key1' => 'val1'),
        //     array('key1' => 'val', 'key2' => 'val1'),
        // )
        $fileName = $fileName . date('Ymd-Hi');
        $titleSet = array();
        foreach ($title as $cellKey => $cellVal) {
            $titleSet[] = array('cellKey' => $cellKey, 'setCellValue' => $cellVal);
        }
        $this->setSheetData($titleSet, $data, $callback);
        $this->putOut($fileName);
        exit(0);
    }


}
?>