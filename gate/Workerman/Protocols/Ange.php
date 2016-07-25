<?php
namespace  Workerman\Protocols;
//服务器接收到的命令含义
/*
1.	命令定义     150        局域网内可视对讲
2.	命令定义     152        局域网内监视
3.	命令定义     154        主机名解析（子网内广播）
4.	命令定义     155        主机名解析（NS服务器）
5.	命令定义     160        远程布撤防（小门口机->室内机）
6.	命令定义     220        传送状态信息（室内主机－＞室内副机）

命令：数据包命令
Bit0、Bit1：命令类型
          1――主叫
          2――应答

Bit4、Bit3、Bit2：门口支持的视频编码 格式类型
0 1 0 --- 640*480(MJPEG)
1 0 0 --- 320*240(MJPEG)
1 1 1 --- 不支持

*/
class Ange
{
	
	public static function input($buffer)
	{
		$arr = unpack("C*",$buffer);
		$len = count($arr);
		if($len<52||self::getHeader($buffer)!='XXXCID')
		{
			return false;
		}
		return $len;
	}

	public static function encode($buffer)
	{
		$gram       = '';
		$arr        = unpack("C*",$buffer);
		$step       = self::getConnectStep($buffer);
		$header_arr = array_slice($arr,0,57);
		switch ($step) {
			case 'muti_cast':
				$arr[8] = 2;
				//设置地址个数
				array_splice($arr, 32,0,[1]);
				$IP = gethostbyname(gethostname());
				$IP_arr = explode('.', $IP);
				$arr[53] = $IP_arr[0];
				$arr[54] = $IP_arr[1];
				$arr[55] = $IP_arr[2];
				$arr[56] = $IP_arr[3];
				break;
			case 'call':
				$arr[9] = 4;
				//此时服务端发数据到app征求判断同意是否接通
				break;
/*			case 'talk_begin':
				//此时属于服务器主动发起过程 截取call-answer的报文改下命令
				$arr    = $header_arr; 
				$arr[8] = 6;
				break;*/
			// case 'talk_data':
			// 	$arr = $header_arr;
			// 	break;
			case 'unlock':
			//此时应该去找手机端确认下 服务器主动向门口机发送
				$arr    = $header_arr; 
				$arr[7] = 1;
				$arr[8] = 10;
				//$this->bin_data_array[8] = 2;
				break;

			case 'over':
        	        	$arr    = $header_arr;
                		$arr[7] = 1;
                		$arr[8] = 30;			
				break;
			default:
				
				break;
		}
		foreach ($arr as $key => $value) {
			$gram .=pack('C',$value);
		}
		return $gram;
	}

	public static function decode($buffer)
	{
		return $buffer;
	}

	//是否单播
	public static function isUnicast($buffer)
	{
		return (int)(self::getOrder($buffer)!=154);
	}

	//获取通信步骤
	public static function getConnectStep($buffer)
	{
		if(self::getHeader($buffer)!='XXXCID')
		{
			return;
		}
		$connect_step = 
		[
			'muti_cast'            =>1541,
			'call'                 =>1501,
			'answer'               =>1504,
			'talk_begin'           =>1506,
			'talk_data'            =>1507,
			'online_confirm'       =>1509,
			'unlock'               =>15010,
			'over'                 =>15030,
		];
		$unicast = self::getOrder($buffer);
		$step = '';
		if($unicast==154){
			$step = 'muti_cast';
		}else{
			$receive_cate = self::getOrder($buffer).self::getOperation($buffer);
			foreach ($connect_step as $key=>$value) {
				if($receive_cate==$value){
					$step = $key;
					if($step=='talk_data')
					{
						return self::getDataType($buffer);
					}
				}
			}
		}
		return $step;
	}

	//协议头部6个字节
	public static function getHeader($buffer)
	{
		return substr($buffer, 0,6);
	}

	//获取命令
	public static function getOrder($buffer)
	{	
		$arr = unpack("C*", $buffer);
		return $arr[7];
	}

	//获取命令类型
	public static function getOrderCate($buffer)
	{
		$arr = unpack("C*", $buffer);
		return $arr[8];
	}

	//如果无操作命令则不需要获取 区分组播和单播 默认单播
	public static function getOperation($buffer)
	{
		$unicast = self::isUnicast($buffer);
		
		$arr = unpack("C*", $buffer);
		if($unicast)
		{
			return $arr[8+$unicast];	
		}else{
			return '';
		}
		
	}

	//有操作命令才会被执行
	public static function setOperation($operation,$buffer)
	{
		$unicast = (int)(self::isUnicast($buffer));
		$arr = unpack("C*", $buffer);
		$header_arr = array_slice($arr,0,57);
		$gram= '';
		if($unicast)
		{
			$header_arr[8] = $operation;
			foreach ($header_arr as $key => $value) {
				$gram .= pack('C',$value);
			}
			return $gram;	
		}else{
			return false;
		}
	}
	//获取主叫地址
	public static function getCallAddress($buffer)
	{	
		$unicast = self::isUnicast($buffer);
		$arr = unpack("C*", $buffer);
		$address = '';
		
		$address_array = array_slice($arr, 8+$unicast,20);
		foreach ($address_array as $key => $value) {
			$address .= pack('C',$value);
		}
		return $address;
	}

	//获取主叫IP 192.168.0.1格式
	public static function getCallIp($buffer)
	{
		$unicast = self::isUnicast($buffer);
		$arr = unpack("C*", $buffer);
		$ip = '';
		$IP_array = array_slice($arr, 28+$unicast,4);
		foreach($IP_array as $value)
		{
			$ip .= $value.'.';
		}
		return rtrim($ip,'.');
	}

	public static function getCalledAddress($buffer)
	{
		$unicast = self::isUnicast($buffer);
		$arr = unpack("C*", $buffer);
		$address = '';
		$address_array = array_slice($arr, 32+$unicast,20);
		foreach ($address_array as $key => $value) {
			$address .= pack('C',$value);
		}
		return $address;
	}

	public static function getCalledIp($buffer)
	{
		$unicast = self::isUnicast($buffer);;
		$arr = unpack("C*", $buffer);
		$ip = '';
		$IP_array = array_slice($arr, 52+$unicast,4);
		foreach($IP_array as $value)
		{
			$ip .= $value.'.';
		}
		return rtrim($ip,'.');
	}
	
	//数据类型 仅仅对 视频包和音频包做区分
	public static function getDataType($buffer)
	{
		$arr = unpack("C*",$buffer);
		if($arr[62]>1)
		{
			return 'video';
		}else{
			return 'audio';
		}
	}
	
	//此处可以用来 区分不同的udp连接
	public static function getHeader57($buffer)
	{
		$header = '';
		$arr = unpack("C*",$buffer);
		$header_arr57 = array_slice($arr,0,57);
		foreach($header_arr57 as $key => $value)
		{
			$header .= pack("C*",$value); 
		}
		return $header;
	}
	
	public static function getMiddlePart($buffer)
	{
		$middle_part = '';
		$arr = unpack("C*",$buffer);
		$middle_arr = array_slice($arr,65,21);
		foreach($middle_arr as $k => $v)
		{
			$middle_part .= pack("C",$v);
		}
		return $middle_part;
	}
	
	public static function getAudioData($buffer)
	{
		$audio_data = '';
                $arr = unpack("C*",$buffer);
                $audio_arr = array_slice($arr,86,64);
                foreach($audio_arr as $k => $v)
                {
                        $audio_data .= pack("C",$v);
                }
                return $audio_data;
	}
	
	public static function getFrameNum($buffer)
	{
		$str = substr($buffer,63,2);
		$arr = unpack("v",$str);
		return $arr[1];	
	}
	
	public static function getPackNum($buffer)
	{
		$str = substr($buffer,71,2);
		$arr = unpack('v',$str);
                return $arr[1]; 
	}	
	public static function getTotalPackNum($buffer)
	{
		$str = substr($buffer,69,2);
		$arr = unpack('v',$str);
                return $arr[1]; 
	}
	
	public static function getVideoData($buffer)
	{
		$str = substr($buffer,86);
		return $str;
	}	
}


?>
