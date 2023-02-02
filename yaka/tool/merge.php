﻿<?php

function_exists('set_magic_quotes_runtime') AND set_magic_quotes_runtime(0);

$dir = '../';

$s = php_strip_whitespace($dir.'db.func.php');
$s .= php_strip_whitespace($dir.'db_abstract.class.php');
$s .= php_strip_whitespace($dir.'db_abstract_mysql.class.php');
$s .= php_strip_whitespace($dir.'db_mysql.class.php');
$s .= php_strip_whitespace($dir.'db_pdo_mysql.class.php');
$s .= php_strip_whitespace($dir.'db_pdo_sqlite.class.php');

$s .= php_strip_whitespace($dir.'cache.func.php');
$s .= php_strip_whitespace($dir.'cache_abstract.class.php');
$s .= php_strip_whitespace($dir.'cache_apc.class.php');
$s .= php_strip_whitespace($dir.'cache_memcached.class.php');
$s .= php_strip_whitespace($dir.'cache_mysql.class.php');
$s .= php_strip_whitespace($dir.'cache_redis.class.php');
$s .= php_strip_whitespace($dir.'cache_xcache.class.php');
$s .= php_strip_whitespace($dir.'cache_yac.class.php');

$s .= php_strip_whitespace($dir.'array.func.php');
$s .= php_strip_whitespace($dir.'encrypt.func.php');
$s .= php_strip_whitespace($dir.'html_safe.func.php');
$s .= php_strip_whitespace($dir.'http.func.php');
$s .= php_strip_whitespace($dir.'image.func.php');
$s .= php_strip_whitespace($dir.'io.func.php');
$s .= php_strip_whitespace($dir.'mail.func.php');
$s .= php_strip_whitespace($dir.'misc.func.php');
$s .= php_strip_whitespace($dir.'pinyin.func.php');
$s .= php_strip_whitespace($dir.'str.func.php');
$s .= php_strip_whitespace($dir.'zip.func.php');

$s = substr($s, 8, -2);

$yaka = file_get_contents($dir.'yaka.php');
$before = '// hook yaka_include_before.php';
$after = '// hook yaka_include_after.php';
$pre = substr($yaka, 0, strpos($yaka, $before) + 1 + mb_strlen($before));
$suffix = substr($yaka, strpos($yaka, $after));
$yaka_min = trim($pre)."\r\n\r\n".trim($s)."\r\n\r\n".trim($suffix);


file_put_contents($dir.'yaka.min.php', $yaka_min);

echo 'ok';
