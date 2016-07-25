<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

use \GatewayWorker\Lib\Gateway;
use Workerman\Protocols\Ange;
require_once("/var/www/html/gate/Channel/Client.php");
require_once('/var/www/html/gate/Workerman/Protocols/Ange.php');
require_once('/var/www/html/gate/Config/config.php');
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 *
 * GatewayWorker开发参见手册：
 * @link http://gatewayworker-doc.workerman.net/
 */

class Event
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     * @link http://gatewayworker-doc.workerman.net/gateway-worker-development/onconnect.html
     */
    public static function onConnect($client_id)
    {
	       echo $client_id."on Connect";
        // 向当前client_id发送数据 @see http://gatewayworker-doc.workerman.net/gateway-worker-development/send-to-client.html
       // Gateway::sendToClient($client_id, "talk");
        // 向所有人发送 @see http://gatewayworker-doc.workerman.net/gateway-worker-development/send-to-all.html
        //Gateway::sendToAll("$client_id login");
    }
    
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param string $message 具体消息
    * @link http://gatewayworker-doc.workerman.net/gateway-worker-development/onmessage.html
    */
   public static function onMessage($client_id, $message)
   {
  	$arr = json_decode($message,true);
    if(isset($arr['heartBeat']))
    {
	echo 'heart beat uid is:'.$arr['uid'];
      if(count(Gateway::getClientIdByUid($arr['uid']))==0)
      {
	echo 'bind uid';
        Gateway::bindUid($client_id,$arr['uid']);
      }
    }
  	if(!array_key_exists('heartBeat',$arr))
  	echo "$message";


  	Channel\Client::connect('127.0.0.1',2206);
  	//订阅来自udp_door的消息 把它发给 app客户端
  	Channel\Client::subscribe('udp_tcp');
  	Channel\Client::$onMessage = function($subject,$msg)use($client_id)
  	{
  		echo ' This is a message from udp_door server:'.$msg;
      $msg_arr = json_decode($msg,true);
      $order       = $msg_arr['order'];
      $uid         = $msg_arr['uid'];
      $doorAddress = $msg_arr['doorAddress'];

      if($order=='over')
      {
        unset($GLOBALS[$uid]);
      }


      //首先判断一下 是不是占线  doorAddress+uid 如果存在 是本线路 否则 判断新建或者占线
 
      if(isset($GLOBALS[$uid])&&$GLOBALS[$uid] == $doorAddress)
      {
        //本线路操作:发送信息给app
        Gateway::sendToUid($uid,$msg);
      }else
      {
        if(isset($GLOBALS[$uid])&&$GLOBALS[$uid] != $doorAddress)
        {
          //占线
          $msg_arr['order'] = 'busy';
          Channel\Client::publish('tcp_udp',json_encode($msg_arr));
        }else
        {
          //新标记线路
          $GLOBALS[$uid] = $doorAddress;
        }
      }  		
  	};
	 
   
  	//发布app客户端来的消息 给门口机服务器
  	Channel\Client::publish('tcp_udp',$message);

   }
   
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id)
   {
       // 向所有人发送 @see http://gatewayworker-doc.workerman.net/gateway-worker-development/send-to-all.html
       Gateway::sendToClient($client_id,'talk over');
   }
}
