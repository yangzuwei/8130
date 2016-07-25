<?php
use Workerman\Worker;
use Workerman\Protocols\Ange;
require_once './Workerman/Autoloader.php';
require_once './Channel/Client.php';
require_once './Config/config.php';
//配置文件内容
$server_door = new Worker("Ange://0.0.0.0:8302");
$server_door->count = 1;
$server_door->name = 'udp-door';
$server_door->transport = 'udp';
$server_door->uidConnections = array();

$server_door->onMessage = function($connection,$data)use($server_door)
{
		//判断门口机发来的报文 属于什么类别（什么通信步骤）
		$step = Ange::getConnectStep($data);
	    //如果是组播直接回复
	    if($step=='muti_cast')
	    {
	        $connection->send(Ange::encode($data));
	        //如果是多门口机 可以通过主叫地址来判断连接【M100……】
			$connection->uid ='door';
	        $server_door->uidConnections[$connection->uid] = $connection;
	    }
		/*-----------------------------------------------命令逻辑模块--------------------------------------------------------------*/
		//Channel\Client::connect('127.0.0.1', 2206);
		//收tcp客户端发来的消息 订阅
	    Channel\Client::connect('127.0.0.1', 2206);
	    Channel\Client::subscribe('audio');
	    Channel\Client::subscribe('video');
		Channel\Client::subscribe('tcp_udp');

		//发布消息的时候应该要带上链接标示是发给哪个app的 （多机器状态下要补充）????????????????????????????


		//接收来自各个服务器的主题信息 并且转化成为报文 回复给门口机--------------------------------------------------------
		Channel\Client::$onMessage = function($subject,$message)use($server_door,$data)
		{
			switch ($subject) {
				case 'tcp_udp':
					//echo 'this is from tcp_app:'.$message;
					//这个Message是json形式的 需要解析成数组
					//这个json数据应该还要包含 主叫地址M1001000主叫地址信息用来标示  这个app的消息是用于和哪个门口机通信的
					$order = '';
					$arr = json_decode($message,true);
					if(isset($arr['order']))
					{
						$order = $arr['order'];
					}

					switch($order)
					{
						case 'talk':
							//var_dump($data);
							$gram_talk = Ange::setOperation(6,$data);
							//var_dump($gram_talk); 
							$server_door->uidConnections['door']->send($gram_talk,true);
						break;
						case 'unlock':
							$server_door->uidConnections['door']->send(Ange::setOperation(10,$data),true);
						break;
						case 'over':
							$server_door->uidConnections['door']->send(Ange::setOperation(30,$data),true);
						break;
					}
					break;
				case 'audio':
					//echo 'this is a msg from 8303'.$message;
					//这个Message里面应该要带有主叫地址的信息  从而可以指定连接发给对应门口机
					//如果是多门口机 可以通过主叫地址来判断连接【M100……】
					if(Ange::getConnectStep($data)=="audio")
	        		{	
	        			$GLOBALS['header57'] = Ange::getHeader57($data);
	        			$GLOBALS['middle_part'] = Ange::getMiddlePart($data);
					}
	                $GLOBALS['audio_time_stamp'] += 8;
	                $GLOBALS['frame_num']++;
	                $data_type = pack('v',1);
	                if(isset($GLOBALS['header57']))
	                {
	                	//组包之后发给对应的门口机
	                	$audio_gram = $GLOBALS['header57'].pack("V",$GLOBALS['audio_time_stamp']).$data_type.pack("v",$GLOBALS['frame_num']).$GLOBALS['middle_part'].base64_decode($message);
					}
	                $server_door->uidConnections['door']->send($audio_gram,true);
					break;
				case 'video':
					//视频信息不用发给门口机只是门口机发给app 所以此处不用写
					break;
				case 'busy':
					echo 'busy send to door';
					$gram_busy = Ange::setOperation(2,$data);
					$server_door->uidConnections['door']->send($gram_busy,true);
					break;
				default:
					# code...
					//onnection->send($data);
					break;
			}
		};
	//根据目前的报文类别 相应的转发给app——————————————————————————————————————————————————————————————————————————————
	//在线信息判断 可以通过心跳包来判断
	$isOnline=true;

	$relation = ["00010108090"=>"359608060142756","00010108110"=>"A00000554A3FAA"];
	$room_address         = Ange::getCalledAddress($data);
	$room_address  = substr($room_address,1,11);
	$door_address = substr(Ange::getCallAddress($data),0,8);
	//var_dump($room_address);
	switch($step)
	{
		case 'call':
			$connection->send(Ange::encode($data));
			$arr['order']	      = 'call';
			$arr['doorAddress']   = $door_address;
			$arr['community']     = COMMUNITY;
			$arr['roomAddress']   = Ange::getCalledAddress($data);
			$arr['uid']           = $relation[$room_address];

			if($isOnline)
			{
				echo 'call is sending to .......'; 
				var_dump($arr);
				$to_tcp_json = json_encode($arr);
				//r_dump($to_tcp_json);
				Channel\Client::publish('udp_tcp',$to_tcp_json);
			}
			break;
		case 'talk_begin':
	        $connection->send(Ange::encode($data));
	        $arr['order']         = 'talk begin';
			$arr['doorAddress']   = $door_address;
			$arr['community']     = COMMUNITY;
			$arr['roomAddress']   = Ange::getCalledAddress($data);
			$arr['uid']           = $relation[$room_address];      
	        if($isOnline)
	        {
                echo 'talk is sending to .......';
                $to_tcp_json = json_encode($arr);
                //r_dump($to_tcp_json);
                Channel\Client::publish('udp_tcp',$to_tcp_json);
	        }
			break;
		case 'over':
			$arr['order']         = 'over';	
			$arr['doorAddress']   = $door_address;
			$arr['community']     = COMMUNITY;
			$arr['roomAddress']   = Ange::getCalledAddress($data);
			$arr['uid']           = $relation[$room_address];  		
    		$to_tcp_json = json_encode($arr);
    		Channel\Client::publish('udp_tcp',$to_tcp_json);
    		//此处可以进行全局变量的销毁
			unset($GLOBALS);
			//可以对指定的门口机连接进行销毁
            unset($server_door->uidConnections['door']);
			break;
		case 'unlock':
	    	$arr['order'] = 'unlock success';
			$arr['doorAddress']   = $door_address;
			$arr['community']     = COMMUNITY;
			$arr['roomAddress']   = Ange::getCalledAddress($data);
			$arr['uid']           = $relation[$room_address];           
            $to_tcp_json = json_encode($arr);
            Channel\Client::publish('udp_tcp',$to_tcp_json);
            break;
		case 'audio':
			$audio_to_app = Ange::getAudioData($data);
			echo 'publish audio len is ('.strlen($audio_to_app).')';
            Channel\Client::publish('audio_to_app',base64_encode($audio_to_app));
			break;
		case 'video':
			//ho 'video gram len is'.strlen($data);
			static $Frame = [];
			static $flag = 0;
			static $total_pack_num = 0;
			$current_frame_num = Ange::getFrameNum($data);//获取当前包的帧数
			if($flag != $current_frame_num)
			{
				$flag = $current_frame_num;
			}
			//将收到的数据存在一个数组中
			//echo 'flag is '.$flag.' current_frame_num is '.$current_frame_num;
			if($flag === $current_frame_num)
			{
				$total_pack_num = Ange::getTotalPackNum($data);				
				$current_pack_num = Ange::getPackNum($data);
				//获取之后存在frame数组中
				$Frame[$current_pack_num] = Ange::getVideoData($data);
				//最后一个包了 该发了
				if($current_pack_num == $total_pack_num)
				{
					//先组起来
					$frame_contents = '';
					foreach($Frame as $k=>$v)
					{
						$frame_contents .=$v;
					}
					$frame_temp_arr = unpack("C*",$frame_contents);	
					$final_frame_arr = array_merge(JpegHeadEnd::$head_jpeg_arr,$frame_temp_arr,JpegHeadEnd::$end_jpeg_arr);
					//打包成字符串
					$final_frame_data = '';
					foreach ($final_frame_arr as $key => $value) {
						$final_frame_data .= pack("C",$value);
					}
					//发出去 
					file_put_contents('1.jpeg',$final_frame_data);
					echo 'publish video len is ('.strlen($final_frame_data).')';
					Channel\Client::publish('video_to_app',base64_encode($final_frame_data));
					unset($Frame);
                    $total_pack_num = 0;
				}
			}
			break;
		default:
			//var_dump($data);
			$connection->send(Ange::encode($data));
			break;
	}	
};

// 运行worker
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
