<?php
/**
 * 数据库工具类
 */
namespace Framework\Model;
use \Framework\Base\Object;

class DbForge extends Object
{
    var $db = null;

    function __construct($params = '', $db = '')
    {
        $this->DbForge($params, $db);
    }

    /**
     *  构造函数
     *
     * @author LorenLei
     * @param  array $params
     * @param  object $db
     * @return void
     */
    function DbForge($db)
    {
        if (!$db) {
            $this->db = db();
        }
    }

    function createModel($table, $prefix,$prik, $modelName, $mod)
    {
        //字符验证长度,数字验证大小，email，手机号码，ip地址验证格式
        $this->fields = $this->db->table_fields_more($prefix . $table);
        $mod = ucfirst($mod);
        $modelName = $modelName ? ucfirst($modelName) : ucfirst($table);
        $modelName = $modelName.'Model';
        $autov = '';
        foreach ($this->fields as $fieldName => $val) {
            if (strtolower($val['column_default']) == 'current_timestamp') {
                continue;
            }
            $autov .= "
             '$fieldName' => array(
                'required'  => true,";
            $this->Number($autov, $val);
            $this->String($autov, $val);
            $this->Tel($autov, $val);
            $this->Email($autov, $val);
            $this->ip($autov, $val);
            $autov .= "
             ),\r\n";

        }
        $str =
"<?php
namespace  Application\\$mod\\Model;
use \Framework\Model\BaseModel;
class $modelName extends BaseModel
{
    var \$table  = '$prefix$table';
    var \$prikey = '{$prik}';
    var \$_name  = '$table';

    var \$_autov = array(
        $autov
    );
}";
        //echo $str;
        file_put_contents(APP_PATH.$mod.'/Model/'.$modelName.'.php',$str);

    }

    function Number(&$str, $colInfo)
    {
        $num = trim($colInfo['column_type']);
        $ma = array();
        preg_match_all('/(\w*int)\(\d+\)\s*(unsigned)*/', $num, $ma);
        $arr = array('tinyint' => 127, 'smallint' => 32767, 'mediumint' => 8388607, 'int' => 2147483647, '' => 9223372036854775807);
        foreach ($arr as $name => $val) {
            if (isset($ma[1][0]) && $ma[1][0] == $name) {
                if (isset($ma[2][0]) && $ma[2][0] = trim($ma[2][0])) {
                    $min = 1;
                    $max = $val * 2 + 1;
                } else {
                    $min = -($val + 1);
                    $max = $val;
                }
                $str .= "
                'min' => $min,
                'max' => $max,
                'type' => 'Number',";
                break;
            }
        }

    }

    function String(&$str, $colInfo)
    {
        //char(2) varchar(2) *text(232)
        $num = trim($colInfo['column_type']);
        $ma = array();
        $max = 0;
        preg_match_all('/(\w*char)\((\d+)\)|(\w*text)/i', $num, $ma);
        if (isset($ma[2][0]) || isset($ma[3][0])) {
            if (isset($ma[2][0])) {
                $max = $ma[2][0];
            } else {
                $ma[3][0] = strtolower($ma[3][0]);
                switch ($ma[3][0]) {
                    case 'tinytext':
                        $max = 255;
                        break;
                    case 'text':
                        $max = 65535;
                        break;
                    case 'mediumtext':
                        $max = 16777215;
                        break;
                    case 'nediumtext':
                        $max = 16777215;
                        break;
                    case 'longtext':
                        $max = 4294967295;
                        break;
                }
            }
            $str .= "'min' => 1,";
            $str .=  "'type' => 'String',";
            $max && $str .= "'max' => $max,";
        }


    }


    function Tel(&$str, $colInfo)
    {
        $num = trim($colInfo['column_type']);
        if (preg_match('/手机|电话|tel|phone/', $num) || preg_match('/tel|phone|handset/', $num)) {
            $str .= " 'valid' => 'is_tel',";
            $str .=  "'type' => 'String',";
        }
    }

    function Email(&$str, $colInfo)
    {
        $num = trim($colInfo['column_type']);
        if (preg_match('/邮箱|email|邮件/', $num) || preg_match('/email|mail|post|dak/', $num)) {
            $str .= " 'valid' => 'is_email',";
            $str .=  "'type' => 'String',";
        }
    }

    function Ip(&$str, $colInfo)
    {
        $num = trim($colInfo['column_type']);
        if (preg_match('/ip/', $num) || preg_match('/ip/', $num)) {
            $str .= " 'valid' => 'is_email',";
            $str .=  "'type' => 'String',";
        }
    }

    /**
     * 创建控制器文件
     * @param $table
     * @param $prefix
     * @param $modelName
     * @param $contollerName
     * @param $mod
     */
    function createController($table, $prefix, $modelName, $contollerName, $mod)
    {
        //字符验证长度,数字验证大小，email，手机号码，ip地址验证格式
        $this->fields = $this->db->table_fields_more($prefix . $table);
        $mod = ucfirst($mod);
        $modelName = $modelName ? ucfirst($modelName) : ucfirst($table);
        $saveData = '';
        foreach ($this->fields as $fieldName => $val) {
            if (strtolower($val['column_default']) == 'current_timestamp') {
                continue;
            }
            $saveData .= "'$fieldName' => \$input->post('$fieldName'),";

        }
        $modelObjName = lcfirst($modelName).'M';
        $contollerName = ucfirst($contollerName);
        $str =
"<?php
use \ Application\\$mod\Model\\{$modelName}Model;
use \ Framework\Controller\BaseController;
use \ Framework\Util\Input;
class $contollerName extends BaseController
{
    function $contollerName(){
        parent::__construct();
    }
    function add(){
        \$input = new Input();
        $$modelObjName= new {$modelName}Model();

        \$data = array(
            $saveData
        );

        \$res =  \${$modelObjName}->save(\$data);
        if(!\$res){
            \$this->jsonResult(-1,'保存失败~');
        }else{
            \$this->jsonResult(0,'保存成功~',\$res);
        }
    }
    function find(){
       \$input = new Input();
       $$modelObjName= new {$modelName}Model();

       \$conditions = array('conditions' => array($saveData));

        \$res =  \${$modelObjName}->find(\$conditions);
        if(!\$res){
            \$this->jsonResult(-1,'查找失败~');
        }else{
            \$this->jsonResult(0,'查找成功~',\$res);
        }
    }
    function delete(){
        \$input = new Input();
        $$modelObjName= new {$modelName}Model();
        \$conditions = array('conditions' => array($saveData));
        \$res =  \${$modelObjName}->delete(\$conditions);
        if(!\$res){
            \$this->jsonResult(-1,'删除失败~');
        }else{
            \$this->jsonResult(0,'删除成功~',\$res);
        }
    }

}";
        //echo $str;
        file_put_contents(APP_PATH.$mod.'/Controller/'.$contollerName.'.php',$str);

    }

}


?>
