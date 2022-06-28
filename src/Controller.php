<?php
namespace eBaocd\AbsMvc;

use eBaocd\Common\xFun;
use eBaocd\Common\xTpl;
use eBaocd\Common\xUpload;

abstract class Controller
{
    public $cur_user = NULL;
    public $cur_userid = 0;
    public $ispost = FALSE;
    public $g_tpl_vars = array();
    public $appname = '';
    public $shared_arr = ['msg', 'msgnoheader'];

    public function __construct()
    {
        $this->ispost = xFun::is_post() || xFun::is_ajax();

        $this->init();
    }

    abstract protected function init();

    abstract protected function setAppName();

    public function redirect($url = '/')
    {
        header("Location:$url");
        exit;
    }

    public function assign($key, $val)
    {
        $this->g_tpl_vars[$key] = $val;
    }

    protected function getTplFile($forward)
    {
        //调用任意类下面的模板，比如任意类里面直接调用首页（index/index）
        if (stristr($forward, '/') !== FALSE)
        {
            $temp     = explode('/', $forward);
            $cls_name = $temp[0] ?? '';
            $forward  = $temp[1] ?? 'msg';
        }
        else
        {
            if (in_array($forward, $this->shared_arr))
            {
                $cls_name = 'shared';
            }
            else
            {
                $cls = get_class($this);
                if ($cls == '')
                {
                    throw new Exception('类名为空');
                }
                $cls_arr  = explode('\\', $cls);
                $cls_name = end($cls_arr);
            }
        }


        $cls_name = strtolower($cls_name);
        $tFile    = CUR_APP_DIR . 'Views' . DIRECTORY_SEPARATOR . $cls_name . DIRECTORY_SEPARATOR . $forward . '.html';
        $cFile    = LOG_PATH . 'Views_c' . DIRECTORY_SEPARATOR . $cls_name . DIRECTORY_SEPARATOR . $forward . '.php';

        $tplfile = xTpl::display($tFile, $cFile);

        return $tplfile;
    }

    public function display($forward)
    {

        $tplfile = $this->getTplFile($forward);

        foreach ($this->g_tpl_vars as $k => $v)
        {
            ${$k} = $v;
        }
        include $tplfile;
        exit;
    }

    public function fetch($forward)
    {
        $tplfile = $this->getTplFile($forward);

        foreach ($this->g_tpl_vars as $k => $v)
        {
            ${$k} = $v;
        }

        ob_start();
        include $tplfile;
        $this_my_f = ob_get_contents();
        ob_end_clean();

        return $this_my_f;
    }

    public function MsgShow($info, $rUrl = '', $title = '操作结果')
    {
        if (empty($rUrl))
        {
            $info .= '，<a href="' . $rUrl . '">点此返回！</a>';
        }

        $this->assign('msg', $info);

        xFun::reqnum('nh', 0) == '1' ? $this->display('msgnoheader') : $this->display('msg');
    }

    public function ajax($param)
    {
        echo json_encode($param);
        exit;
    }

    //$fpara_arr = ['str'=['cnname','content']]
    //支持str,num,money,array,date
    public function reqparam(&$data, $fpara_arr, $finit_arr = NULL)
    {
        if ($finit_arr == NULL)
        {
            $finit_arr = array('str' => '', 'num' => 0, 'money' => 0, 'array' => []);
        }
        foreach ($fpara_arr as $reqkey => $val22)
        {
            $initstr = array_key_exists($reqkey, $finit_arr) ? $finit_arr[$reqkey] : '';
            $req1    = 'req' . $reqkey;
            foreach ($val22 as $key1 => $val1)
            {
                $data[$val1] = xFun::$req1($val1, $initstr);
                if ($reqkey == 'array')
                {
                    $data[$val1] = implode(',', $data[$val1]);
                    $data[$val1] = $data[$val1] == '' ? '' : ',' . $data[$val1] . ',';
                }
            }
        }
    }

    public function FmtTzFiles(&$one, $obj, $tzfiles = array('tzfiles'))
    {
        global $APP_G;
        $md5str = isset($APP_G['md5str']) ? $APP_G['md5str'] : 'x5k2w9';
        foreach ($tzfiles as $k => $v)
        {
            $one[$v . '-list'] = array();
            if ($one && isset($one[$v]) && $one[$v] != '')
            {
                $tmp  = explode(',', trim($one[$v], ','));
                $w    = array('id_IN' => $tmp);
                $list = $obj->GetList($w, 'id ASC', -1, -1, '*', 'tzfiles');
                foreach ($list['allrow'] as $kk7 => $vv7)
                {
                    $time                = mt_rand();
                    $one[$v . '-list'][] = array(
                        'id'   => $vv7['id'],
                        'full' => $vv7['url'],
                        'name' => $vv7['cnname'],
                        'time' => $time,
                        'sign' => md5($vv7['id'] . $md5str . $time)
                    );
                }
            }
        }
    }

    public function MultFileUpload(&$data, $obj, $adderuid)
    {
        //'ufileids','supurls','supnames'
        $ufileids = xFun::reqarray('ufileids');
        $supurls  = xFun::reqarray('supurls');
        $supnames = xFun::reqarray('supnames');

        //var_dump($supurls);
        if (count($supurls) < 1)
        {
            return;
        }
        $ip = xFun::real_ip();

        $tzids = [];
        for ($iijj = 0; $iijj < count($supurls); $iijj++)
        {
            $ext   = strtolower(substr($supurls[$iijj], -4));
            $ftype = '2';
            if (in_array($ext, array('.jpg', '.bmp', 'jpeg', '.gif', '.png')))
            {
                $ftype = '1';
            }
            else if (in_array($ext, array('.mp4', '.flv')))
            {
                $ftype = '3';
            }
            else if (in_array($ext, array('.ppt', '.pptx', '.ppts', '.pps')))
            {
                $ftype = '4';
            }
            $ud       = [
                'url'     => $supurls[$iijj],
                'cnname'  => $supnames[$iijj],
                'flag'    => 1,
                'addtime' => date('Y-m-d H:i:s'),
                'adderid' => $adderuid,
                'adderip' => $ip,
                'ftype'   => $ftype
            ];
            $tzfileid = $obj->AddOne($ud, 'tzfiles');
            $tzids[]  = $tzfileid;
        }
        //var_dump($tzids);

        if (count($ufileids) > 0)
        {
            $tzids = array_merge($tzids, $ufileids);
        }
        //var_dump($ufileids);
        //exit;

        if (count($tzids) > 0)
        {
            $tzids = array_unique($tzids);
            //var_dump($tzids);
            $data['tzfiles'] = ',' . implode(',', $tzids) . ',';
        }
        else
        {
            $data['tzfiles'] = '';
        }
    }

    //MultFileUpload(2,'cgwj')
    public function MultFileUpload2($upnum, $inputname, $obj)
    {
        //附件
        $ip     = xFun::real_ip();
        $dtime  = date('Y-m-d H:i:s');
        $zfwjs  = array();
        $upnum1 = xFun::reqabsnum('filenum', $upnum);

        $uppath = RUNTIME_PATH . 'upload' . DS . $this->siteid . DS;
        for ($iijj = 1; $iijj <= $upnum1; $iijj++)
        {
            $img = xUpload::uploadfile($inputname . $iijj, 2, $uppath);
            //print_r($img);
            //exit;
            $name = xFun::reqstr($inputname . 'name' . $iijj, '');
            if ($img['err'] == '')
            {
                if ($name == '')
                {
                    $name = \basename($img['msg']['name']);
                }
                if ($name == '')
                {
                    $name = \basename($img['msg']['url']);
                }
                $ext   = strtolower(substr($img['msg']['url'], -4));
                $ftype = '2';
                if (in_array($ext, array('.jpg', '.bmp', 'jpeg', '.gif', '.png')))
                {
                    $ftype = '1';
                }
                $data    = array(
                    'url'     => $img['msg']['url'],
                    'cnname'  => $name,
                    'ftype'   => $ftype,
                    'siteid'  => $this->siteid,
                    'addtime' => $dtime,
                    'adderid' => $this->cur_userid,
                    'adderip' => $ip
                );
                $zfwjs[] = $obj->AddOne($data, 'tzfiles', TRUE);
            }
        }
        $zfwjold = xFun::reqarray($inputname);
        foreach ($zfwjold as $kk7 => $vv7)
        {
            if (\is_numeric($vv7) && !in_array($vv7, $zfwjs))
            {
                $zfwjs[] = $vv7;
            }
        }
        $tzfiles = implode(',', $zfwjs);

        return $tzfiles;
    }

    public function ExtHtmlDoc($html, $filename)
    {
        header('Content-Type:application/msword');
        header('Content-Disposition: attachment;filename=' . $filename);
        header('Cache-Control: max-age=0');
        ob_clean();//关键
        flush();
        echo $html;
        exit;
    }

    /**
     * 导出数据为excel表格
     *
     * @param $data     一个二维数组,结构如同从数据库查出来的数组
     * @param $title    excel的第一行标题,一个数组,如果为空则没有标题
     * @param $filename 下载的文件名
     *
     * @examlpe
     * $stu = M ('User');
     * $arr = $stu -> select();
     * exportexcel($arr,array('id','账户','密码','昵称'),'文件名!');
     */
    public function ExtXls($data = array(), $title = array(), $f_arr = array(), $filename = 'report')
    {

        require_once APPLIB_DIR . 'Excel' . DS . 'PHPExcel.php';
        require_once APPLIB_DIR . 'Excel' . DS . 'PHPExcel' . DS . 'IOFactory.php';

        $objPHPExcel = new \PHPExcel();
        // Set document properties
        $objPHPExcel->getProperties()->setCreator("Xiuluo");
        //->setLastModifiedBy("Maarten Balliauw")
        //->setTitle("Office 2007 XLSX Test Document")
        //->setSubject("Office 2007 XLSX Test Document")
        //->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
        //->setKeywords("office 2007 openxml php")
        //->setCategory("Test result file");
        foreach ($title as $k1 => $v1)
        {
            $objPHPExcel->getActiveSheet()->getStyleByColumnAndRow($k1, 1)->getFont()->setBold(TRUE);//字体加粗
            $objPHPExcel->getActiveSheet()->getStyleByColumnAndRow($k1, 1)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//文字居中
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($k1, 1, $v1);
        }

        $i = 2;
        foreach ($data as $k => $v)
        {
            for ($iijj = 0; $iijj < count($f_arr); $iijj++)
            {//$v[$f_arr[$iijj]] √ font:217346 background:c6efce
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($iijj, $i, $v[$f_arr[$iijj]]);
                if ($f_arr[$iijj] == 'edate1' && strtotime($v[$f_arr[$iijj]]) < time())
                {
                    $dyg = chr(65 + $iijj) . $i;
                    $objPHPExcel->getActiveSheet()->getStyle($dyg)->getFont()->getColor()->setRGB('9c0006');
                    $objPHPExcel->getActiveSheet()->getStyle($dyg)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('ffc7ce');
                }
            }
            $i++;
        }
        $endstr   = chr(65 + count($f_arr) - 1) . (count($data) + 1);
        $styleArr = array(
            'allborders' => array(
                'style' => \PHPExcel_Style_Border::BORDER_THIN
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A0:' . $endstr)->getBorders()->applyFromArray($styleArr);


        // Rename worksheet
        $objPHPExcel->getActiveSheet()->setTitle($filename);


        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);
        // Redirect output to a client’s web browser (Excel5)

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=' . \rawurlencode($filename) . '.xls');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        //header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        //header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        //header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        //header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        //header ('Pragma: public'); // HTTP/1.0
        ob_clean();//关键
        flush();//关键
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    public function DownPdf($tmpFile, $fileContent, $filename)
    {
        if (!\is_dir($tmpFile))
        {
            @\mkdir($tmpFile, 0777, TRUE);
        }
        $htmFile  = $tmpFile . '.html';
        $pdfFile  = $tmpFile . '.pdf';
        $filename .= '.pdf';
        if (!file_put_contents($htmFile, $fileContent))
        {
            return FALSE;
        }
        global $APP_G;
        $cmd = $APP_G['html2pdf'];
        if ($cmd == '')
        {
            throw new Exception('html2pdf未设置');

            return;
        }
        $cmd .= ' ' . $htmFile . ' ' . $pdfFile;

        \shell_exec($cmd);

        header('Content-type: application/pdf');
        header('Content-Disposition: inline; filename="' . \urlencode($filename) . '"');
        readfile($pdfFile);

        exit;
    }
}


?>
