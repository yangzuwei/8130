<?php
/**
 * 是对Linux Sysv系统消息队列的封装，单台服务器推荐使用
 * @author vv
 */
class SysvQueue
{
        private $msgid;
        private $msg;
        
        function __construct()
        {
                $this->msgid = ftok(__FILE__,'a');
               /* 
                if(!empty($config['msgtype']))
                {
                        $this->msgtype = $config['msgtype'];
                }
                */
                $this->msg = msg_get_queue($this->msgid) ;
        }
        
        function pop($msgtype)
        {
                $ret = msg_receive($this->msg, $msgtype,$type, 65525, $data,true,MSG_IPC_NOWAIT);
                if($ret)
                {
                        return $data;
                }
                return false;
        }
        
        function push($data,$msgtype)
        {
                return msg_send($this->msg, $msgtype, $data);
        }

	function remove()
	{
		return msg_remove_queue($this->msg);
	}
}
