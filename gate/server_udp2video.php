<?php
use Workerman\Worker;
use Workerman\Protocols\Ange;
require_once './Workerman/Autoloader.php';
require_once './Channel/Client.php';
require_once './Config/config.php';
//配置文件内容


$server_video = new Worker("Ange://0.0.0.0:8304");
$server_video->count = 1;
$server_video->name = "udp-app-video";
$server_video->transport = 'udp';
$server_video->uidConnections = [];

$server_video->onMessage = function($connection,$message)
{	
	//单门口机情况 如果是多门口机 应该要在Message里面带上门口机标志 此时此连接应该也要被设置$server_video->uidConnections['门口机标示']
	Channel\Client::connect('127.0.0.1', 2206);
	Channel\Client::subscribe('video_to_app');
	Channel\Client::$onMessage = function($subject,$msg)use($connection)
	{	
		//echo '8304 send a video to app';
		$decode_msg = base64_decode($msg);
		$connection->send($decode_msg,true);
	};
	//echo 'this is msg_app'.$msg_app;
	Channel\Client::publish('video',base64_encode($message));

};

if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
