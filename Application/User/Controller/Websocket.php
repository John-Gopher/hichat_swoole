<?php
use  \Common\Controller\HiController;
use \ Application\User\Model\MemberModel;
use \ Framework\Util\Input;
use \Framework\Model\BaseModel;

class Websocket extends HiController
{
    private $storeSocketMap = array(),$ws,$rd;

    function __construct()
    {
        parent::__construct();
    }

    function Websocket()
    {
        parent::__construct();

    }

    function index()
    {

        //创建websocket服务器对象，监听0.0.0.0:9502端口
        $ws =  $this->ws =  new swoole_websocket_server("0.0.0.0", 8888);
        $ws->user_c = [];   //给ws对象添加属性user_c，值为空数组；
        $ws->storeSocketMap = [];
        //监听WebSocket连接打开事件
        $ws->on('open', function ($ws, $request) {
            $this->rd = $redis = new Redis();
            $this->rd->connect('localhost');
            $this->rd->hSet('connect:fd2userid:map',$request->fd,$request->fd);
            $this->_OutputJSON($request,0, '已经打开连接');
        });
        //监听WebSocket消息事件
        $ws->on('message', function ($ws, $frame) {
            $msg = $frame->data;
            //$scurity = load('Safety');
            if (!$msg || !($msg) || !($msg = json_decode($msg, true))) {
                $ws->push($frame->fd, $frame->fd.'msg empty');
                return;
            }
            $this->rd = $redis = new Redis();
            $this->rd->connect('localhost');
            if (isset($msg['init'])) {
                $ret = $this->rd->hGet('connect:fd2userid:map',$frame->fd);
                if (!empty($ret) && !empty($msg['from'])) {
                    $this->rd->hSet('socket:userid2fd:map','from'.$msg['from'],$frame->fd) ;
                    $this->rd->hSet('connect:fd2userid:map',$frame->fd,$msg['from']);
                    $this->_OutputJSON($frame,0, '初始化完成');
                }
                return;
            }

            //验证是否是好友
            if (!empty($msg['from']) && !empty($msg['to']) && !empty($msg['content'])) {
                $model = new BaseModel();
                $db = $model->db;
                $res = $db->query("INSERT INTO hichat.tbprivatemsg SET  sContent='{$msg['content']}',iFromUserId='{$msg['from']}',iToUserId='{$msg['to']}'");
                $retMsg = array('msg' => $msg['content']);
                if ($res && $insertId = $db->insert_id()) {
                    $retMsg['save'] = 'ok';
                    //如果好友还在线，则主动推送
                    if (($toFd =  $this->rd->hGet('socket:userid2fd:map','from'.$msg['to'])) && $this->rd->hGet('connect:fd2userid:map',$toFd)) {
                        $res = $db->getRow("SELECT * FROM hichat.tbprivatemsg where iPrivateMsgId='$insertId'");
                        $retMsg['data'] = $res;
                        $db->query("UPDATE hichat.tbprivatemsg SET iHasSended=1 WHERE iPrivateMsgId='$insertId'");
                        $retMsg['tome'] =true;
                        $retMsg['res'] = 0;
                        $ws->push($toFd, json_encode($retMsg));
                        $retMsg['tome'] =false;
                        $ws->push($frame->fd, json_encode($retMsg));
                    }else{
                        $retMsg['msg'] = '对方已离线';
                        $retMsg['res'] = -1;
                        $ws->push($frame->fd, json_encode($retMsg));
                    }
                } else {
                    $retMsg['save'] = 'err';
                    $retMsg['res'] = -1;
                    $ws->push($frame->fd, json_encode($retMsg));
                }

            }else{
                $this->_OutputJSON($frame,-11, '参数异常');
            }

        });

        //监听WebSocket连接关闭事件
        $ws->on('close', function ($ws, $fd) {
            $this->rd = $redis = new Redis();
            $this->rd->connect('localhost');
            //删除已断开的客户端
            $userId = $this->rd->hGet('connect:fd2userid:map',$fd);
            $this->rd->hDel('connect:fd2userid:map',$fd);
            $this->rd->hDel('socket:userid2fd:map',$userId);
        });

        $ws->start();

    }
    function group()
    {
        $ws =  $this->ws =  new swoole_websocket_server("0.0.0.0", 8889);
        $ws->on('open', function ($ws, $request) {
            $this->rd = $redis = new Redis();
            $this->rd->connect('localhost');
            $this->rd->hSet('connect:fd2userid:map:group',$request->fd,$request->fd);
            var_dump($this->rd->hGetAll('connect:fd2userid:map:group'));
            $this->_OutputJSON($request,0, '已经打开连接');
        });
        //监听WebSocket消息事件
        $ws->on('message', function ($ws, $frame) {
            $msg = $frame->data;
            //$scurity = load('Safety');
            if (!$msg || !($msg) || !($msg = json_decode($msg, true))) {
                $ws->push($frame->fd, $frame->fd.'msg empty');
                return;
            }
            $this->rd = $redis = new Redis();
            $this->rd->connect('localhost');
            if (isset($msg['init'])) {
                $ret = $this->rd->hGet('connect:fd2userid:map',$frame->fd);
                if (!empty($ret) && !empty($msg['from'])) {
                    $this->rd->hSet('socket:userid2fd:map','from'.$msg['from'],$frame->fd) ;
                    $this->rd->hSet('connect:fd2userid:map',$frame->fd,$msg['from']);
                    $this->_OutputJSON($frame,0, '初始化完成');
                }
                return;
            }

            //验证是否是好友
            if (!empty($msg['from']) && !empty($msg['to']) && !empty($msg['content'])) {
                $model = new BaseModel();
                $db = $model->db;
                $res = $db->query("INSERT INTO hichat.tbprivatemsg SET  sContent='{$msg['content']}',iFromUserId='{$msg['from']}',iToUserId='{$msg['to']}'");
                $retMsg = array('msg' => $msg['content']);
                if ($res && $insertId = $db->insert_id()) {
                    $retMsg['save'] = 'ok';
                    //如果好友还在线，则主动推送
                    $res = $db->getRow("SELECT * FROM hichat.tbprivatemsg where iPrivateMsgId='$insertId'");
                    $retMsg['data'] = $res;
                    $db->query("UPDATE hichat.tbprivatemsg SET iHasSended=1 WHERE iPrivateMsgId='$insertId'");
                    if ($toFds =  $this->rd->hGetAll('socket:userid2fd:map')){
                        foreach($toFds as $toFd){
                            if($this->rd->hGet('connect:fd2userid:map',$toFd)) {
                                $retMsg['res'] = 0;
                                $ws->push($toFd, json_encode($retMsg));
                            }
                        }

                    }
                } else {
                    $retMsg['save'] = 'err';
                    $retMsg['res'] = -1;
                    $ws->push($frame->fd, json_encode($retMsg));
                }

            }else{
                $this->_OutputJSON($frame,-11, '参数异常');
            }

        });

        //监听WebSocket连接关闭事件
        $ws->on('close', function ($ws, $fd) {
            $this->rd = $redis = new Redis();
            $this->rd->connect('localhost');
            //删除已断开的客户端
            $userId = $this->rd->hGet('connect:fd2userid:map',$fd);
            $this->rd->hDel('connect:fd2userid:map',$fd);
            $this->rd->hDel('socket:userid2fd:map',$userId);
        });

        $ws->start();

    }
    protected function _OutputJSON($frame,$res_code, $res_msg, $res_data = array())
    {
        $this->ws->push($frame->fd,json_encode(array(
            'res' => $res_code,
            'msg' => $res_msg,
            'data' => $res_data
        )));

    }

}
