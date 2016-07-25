<?php
use Workerman\Worker;
use Workerman\Lib\Timer;
require_once './Workerman/Autoloader.php';
require_once './Channel/Server.php';

$channel_server = new Channel\Server('0.0.0.0', 2206);

if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
