<?php

/*
	Yaka 只是定义了一些函数和全局变量，方便使用，并没有要求如何组织代码。
	采用静态语言编程风格，有利于 Zend 引擎的编译和 OPCache 缓存，支持 PHP7
	1. 禁止使用 eval(), 正则表达式 e 修饰符
	2. 尽量避免 autoload
	3. 尽量避免 $$var 多重变量
	4. 尽量避免 PHP 高级特性 __call __set __get 等魔术方法，不利于错误排查
	5. 尽量采用函数封装功能，通过前缀区分模块
*/

!defined('DEBUG') and define('DEBUG', 1); // 1: 开发模式， 2: 线上调试：日志记录，0: 关闭
!defined('APP_PATH') and define('APP_PATH', './');
!defined('YAKA_PATH') and define('YAKA_PATH', dirname(__FILE__) . '/');

function_exists('ini_set') and ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(DEBUG ? E_ALL : 0);
version_compare(PHP_VERSION, '5.3.0', '<') and set_magic_quotes_runtime(0);
$get_magic_quotes_gpc = version_compare(PHP_VERSION, '5.4.0', '<') ? get_magic_quotes_gpc() : FALSE;
$start_time = microtime(1);
$time = time();

// 头部，判断是否运行在命令行下
define('IN_CMD', !empty($_SERVER['SHELL']) || empty($_SERVER['REMOTE_ADDR']));
if (IN_CMD) {
    !isset($_SERVER['REMOTE_ADDR']) and $_SERVER['REMOTE_ADDR'] = '';
    !isset($_SERVER['REQUEST_URI']) and $_SERVER['REQUEST_URI'] = '';
    !isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] = 'GET';
} else {
    header("Content-type: text/html; charset=utf-8");
}

// ----------------------------------------------------------> db cache class

include YAKA_PATH . 'db_mysql.class.php';
include YAKA_PATH . 'db_pdo_mysql.class.php';
include YAKA_PATH . 'db_pdo_sqlite.class.php';
include YAKA_PATH . 'cache_apc.class.php';
include YAKA_PATH . 'cache_memcached.class.php';
include YAKA_PATH . 'cache_mysql.class.php';
include YAKA_PATH . 'cache_redis.class.php';
include YAKA_PATH . 'cache_xcache.class.php';
include YAKA_PATH . 'cache_yac.class.php';

// ----------------------------------------------------------> 全局函数

include YAKA_PATH . 'db.func.php';
include YAKA_PATH . 'cache.func.php';
include YAKA_PATH . 'image.func.php';
include YAKA_PATH . 'array.func.php';
include YAKA_PATH . 'encrypt.func.php';
include YAKA_PATH . 'misc.func.php';

// hook yaka_include_after.php

empty($conf) and $conf = array('db' => array(), 'cache' => array(), 'tmp_path' => './', 'log_path' => './', 'timezone' => 'Asia/Shanghai');
empty($conf['tmp_path']) and $conf['tmp_path'] = ini_get('upload_tmp_dir');
empty($conf['log_path']) and $conf['log_path'] = './';

$ip = ip();
$long_ip = ip2long($ip);
$long_ip < 0 and $long_ip = sprintf("%u", $long_ip); // fix 32 位 OS 下溢出的问题
$useragent = _SERVER('HTTP_USER_AGENT');

// 语言包变量
!isset($lang) and $lang = array();

// 全局的错误，非多线程下很方便。
$err_no = 0;
$err_str = '';

// error_handle
DEBUG and set_error_handler('error_handle', -1);
empty($conf['timezone']) and $conf['timezone'] = 'Asia/Shanghai';
date_default_timezone_set($conf['timezone']);

// 超级全局变量
!empty($_SERVER['HTTP_X_REWRITE_URL']) and $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
!isset($_SERVER['REQUEST_URI']) and $_SERVER['REQUEST_URI'] = '';
$_SERVER['REQUEST_URI'] = str_replace('/index.php?', '/', $_SERVER['REQUEST_URI']); // 兼容 iis6
$_REQUEST = array_merge($_COOKIE, $_POST, $_GET, y_url_parse($_SERVER['REQUEST_URI']));

// IP 地址
!isset($_SERVER['REMOTE_ADDR']) and $_SERVER['REMOTE_ADDR'] = '';
!isset($_SERVER['SERVER_ADDR']) and $_SERVER['SERVER_ADDR'] = '';

// $_SERVER['REQUEST_METHOD'] === 'PUT' ? @parse_str(file_get_contents('php://input', false , null, -1 , $_SERVER['CONTENT_LENGTH']), $_PUT) : $_PUT = array(); // 不需要支持 PUT
$ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(trim($_SERVER['HTTP_X_REQUESTED_WITH'])) == 'xmlhttprequest') || param('ajax');
$method = $_SERVER['REQUEST_METHOD'];


// 保存到超级全局变量，防止冲突被覆盖。
$_SERVER['starttime'] = $start_time;
$_SERVER['time'] = $time;
$_SERVER['ip'] = $ip;
$_SERVER['longip'] = $long_ip;
$_SERVER['useragent'] = $useragent;
$_SERVER['conf'] = $conf;
$_SERVER['lang'] = $lang;
$_SERVER['errno'] = $err_no;
$_SERVER['errstr'] = $err_str;
$_SERVER['method'] = $method;
$_SERVER['ajax'] = $ajax;
$_SERVER['get_magic_quotes_gpc'] = $get_magic_quotes_gpc;


// 初始化 db cache，这里并没有连接，在获取数据的时候会自动连接。
$db = !empty($conf['db']) ? db_new($conf['db']) : NULL;

$conf['cache']['mysql']['db'] = $db; // 这里直接传 $db，复用 $db；如果传配置文件，会产生新链接。
$cache = !empty($conf['cache']) ? cache_new($conf['cache']) : NULL;
unset($conf['cache']['mysql']['db']); // 用完清除，防止保存到配置文件

// 对 key 进行安全保护，Yaka 专用扩展
!empty($conf) and (function_exists('y_key') ? ($conf['auth_key'] = y_key()) : NULL);

$_SERVER['db'] = $db;
$_SERVER['cache'] = $cache;
