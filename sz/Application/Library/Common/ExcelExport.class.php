<?php
/**
 * File: ExcelExport.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/1
 */

namespace Library\Common;


class ExcelExport
{

    protected $cur_sheet_index  = null;
    protected $cur_start_col    = null;
    protected $cur_start_row    = null;
    protected $php_excel        = null;
    protected $php_active_sheet = null;

    public function __construct()
    {
        $this->php_excel = new \PHPExcel();
        \PHPExcel_Settings::setCacheStorageMethod(\PHPExcel_CachedObjectStorageFactory::cache_in_memory_serialized);
    }

    /**
     * 设置当前工作簿sheet
     *
     * @param int $sheet_index sheet序号
     *
     * @return $this
     */
    public function setSheet($sheet_index)
    {
        $set_sheet = $this->php_excel->getSheet($sheet_index);
        if (is_null($set_sheet)) {
            //不存在,则新建工作簿
            $my_work_sheet = new \PHPExcel_Worksheet($this->php_excel);
            $this->php_excel->addSheet($my_work_sheet, $sheet_index);
        }
        $this->cur_sheet_index = $sheet_index;
        $this->php_excel->setActiveSheetIndex($sheet_index);
        $this->php_active_sheet = $this->php_excel->getActiveSheet();

        return $this;
    }

    /**
     * 设置数据左上角起始输出单元格位置
     *
     * @param string $col_no 列号,如A,B
     * @param int    $row_no 行号
     *
     * @return $this
     */
    public function setStartPlace($col_no, $row_no)
    {
        $this->cur_start_col = $col_no;
        $this->cur_start_row = $row_no;

        return $this;
    }

    public function setCellValue($col_no, $row_no, $value)
    {
        if (is_null($this->cur_sheet_index)) {
            throw new \Exception('请设置sheet');
        }
        $this->php_active_sheet->setCellValue($col_no.$row_no, $value);
    }

    /**
     * 添加行数据
     *
     * @param array    $data          行数据
     * @param callable $filter_method 过滤方法
     *
     * @throws \Exception
     * @return $this
     */
    public function setRowData($data, $filter_method = null)
    {
        if (is_null($this->cur_sheet_index)) {
            throw new \Exception('请设置sheet');
        }
        if (is_null($this->cur_start_col) || is_null($this->cur_start_row)) {
            throw new \Exception('请设置start place');
        }
        $col = $this->cur_start_col;
        $row = $this->cur_start_row;
        foreach ($data as $cell) {
            if (is_callable($filter_method)) {
                $cell = call_user_func_array($filter_method, [$cell]);
            }
            $this->php_active_sheet->setCellValue($col.$row, $cell);
            $col++;
        }
        $this->cur_start_row++;

        return $this;
    }

    /**
     * 设置加载模板路径
     *
     * @param string $path        模板路径,绝对/相对都可以
     * @param  int   $sheet_index 当前工作簿索引
     *
     * @return $this
     * @throws
     */
    public function setTplPath($path, $sheet_index = 0)
    {
        $path = realpath($path);
        if (!file_exists($path)) {
            throw new \Exception('模板文件不存在');
        }

        $set_sheet = $this->php_excel->getSheet($sheet_index);
        if (is_null($set_sheet)) {
            //不存在,则新建工作簿
            $my_work_sheet = new \PHPExcel_Worksheet($this->php_excel);
            $this->php_excel->addSheet($my_work_sheet, $sheet_index);
        }

        $this->php_excel = \PHPExcel_IOFactory::load($path);
        $this->php_excel->setActiveSheetIndex($sheet_index);
        $this->php_active_sheet = $this->php_excel->getActiveSheet();

        return $this;
    }

    /**
     * 导出文件
     *
     * @param string $file_name 文件名,如果设置为空,则默认使用当前时间(格式:ymdHis)作为文件名
     * @param string $ext       导出文件后缀
     *
     * @throws \Exception
     */
    public function download($file_name, $ext = 'xls')
    {
        if (is_null($this->php_excel)) {
            throw new \Exception('异常导出对象');
        }

        //如果没有设置名字,使用日期代替
        if (strlen($file_name) <= 0) {
            $file_name = date('ymdHis');
        }
        $file_name .= '.' . $ext;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file_name . '"');
        $objWriter = new \PHPExcel_Writer_Excel2007($this->php_excel);
        $objWriter->save('php://output');
    }

    public function saveFile($dir_path, $file_name, $ext = 'xls')
    {
        if (is_null($this->php_excel)) {
            throw new \Exception('异常导出对象');
        }

        //如果没有设置名字,使用日期代替
        if (strlen($file_name) <= 0) {
            $file_name = date('ymdHis');
        }
        $file_name .= '.' . $ext;
        $path = $dir_path . '/' . $file_name;

        $objWriter = \PHPExcel_IOFactory::createWriter($this->php_excel, 'Excel5');
        $objWriter->setPreCalculateFormulas(false); //防止遇到"="开头当做算式计算
        $objWriter->save($path);
    }


}