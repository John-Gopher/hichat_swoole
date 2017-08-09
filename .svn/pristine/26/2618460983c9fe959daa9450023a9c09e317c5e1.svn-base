<?php
/**
 *    Boot框架核心文件，包含最基础的类与函数
*    @author  LorenLei
 */
//require_once ROOT_PATH . '/Framework/Controller/ZmApp.php';
//temp
class Boot
{

	/* 启动 */
	static  function run()
	{

        //模型基础类
        require(ROOT_PATH . '/Framework/Base/Object.php');
        require(ROOT_PATH . '/Framework/Model/BaseModel.php');
		/* 请求转发 */
		$default_app = 'welcome';
		$default_act =  'index';
        $default_mod =  'user';
        if(!is_cli()){
            $m = $_REQUEST['m'];
            $c = $_REQUEST['c'];
            $a = $_REQUEST['a'];
        }else{
            //cli模式
            $param = getopt('m:c:a');
            $m = $param['m'];
            $c = $param['c'];
            $a = $param['a'];
        }

        //匹配任何非单词字符。等价于“[^A-Za-z0-9_]”。
        $mod    = ucfirst(!empty($m) ? preg_replace('/(\W+)/', '', $m) : $default_mod);
        $app    = ucfirst(!empty($c) ? preg_replace('/(\W+)/', '', $c) : $default_app);
        $act    = ucfirst(!empty($a) ? preg_replace('/(\W+)/', '', $a) : $default_act);

        $app_file = APP_PATH. "{$mod}/Controller/{$app}.php";
		if (!is_file($app_file))
		{
			exit('The controller is not found!');
		}
		require($app_file);
        define('MOD', $mod);
		define('APP', $app);
		define('ACT', $act);
		/* 实例化控制器 */
		$app     = new $app();
		c($app);
        //转发至对应的Action
		$app->do_action($act);

	}
}




?>