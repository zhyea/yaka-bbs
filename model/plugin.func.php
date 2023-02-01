<?php

// 本地插件
$plugin_paths = array();
$plugins = array(); // 跟官方插件合并

// 官方插件列表
$official_plugins = array();

const PLUGIN_OFFICIAL_URL = DEBUG == 4 ? 'https://plugin.x.com/' : 'https://plugin.xiuno.com/';

$g_include_slot_kv = array();


function _include($src_file): string
{
    global $conf;
    // 合并插件，存入 tmp_path
    $len = strlen(APP_PATH);
    $tmp_file = $conf['tmp_path'] . substr(str_replace('/', '_', $src_file), $len);
    if (!is_file($tmp_file) || DEBUG > 1) {
        // 开始编译
        $s = plugin_compile_src_file($src_file);

        // 支持 <template> <slot>
        for ($i = 0; $i < 10; $i++) {
            $s = preg_replace_callback('#<template\sinclude="(.*?)">(.*?)</template>#is', '_include_callback_1', $s);
            if (strpos($s, '<template') === FALSE) break;
        }
        file_put_contents_try($tmp_file, $s);

        $s = plugin_compile_src_file($tmp_file);
        file_put_contents_try($tmp_file, $s);
    }
    return $tmp_file;
}


function _include_callback_1($m)
{
    global $g_include_slot_kv;
    $r = file_get_contents($m[1]);
    preg_match_all('#<slot\sname="(.*?)">(.*?)</slot>#is', $m[2], $m2);
    if (!empty($m2[1])) {
        $kv = array_combine($m2[1], $m2[2]);
        $g_include_slot_kv += $kv;
        foreach ($g_include_slot_kv as $slot => $content) {
            $r = preg_replace('#<slot\sname="' . $slot . '"\s*/>#is', $content, $r);
        }
    }
    return $r;
}

// 在安装、卸载插件的时候，需要先初始化
function plugin_init()
{
    global $plugin_src_files, $plugin_paths, $plugins, $official_plugins;

    $plugin_paths = glob(APP_PATH . 'plugin/*', GLOB_ONLYDIR);
    if (is_array($plugin_paths)) {
        foreach ($plugin_paths as $path) {
            $dir = file_name($path);
            $conf_file = $path . "/conf.json";
            if (!is_file($conf_file)) continue;
            $arr = y_json_decode(file_get_contents($conf_file));
            if (empty($arr)) continue;
            $plugins[$dir] = $arr;

            // 额外的信息
            $plugins[$dir]['hooks'] = array();
            $hook_paths = glob(APP_PATH . "plugin/$dir/hook/*.*"); // path
            if (is_array($hook_paths)) {
                foreach ($hook_paths as $hk_path) {
                    $hook_name = file_name($hk_path);
                    $plugins[$dir]['hooks'][$hook_name] = $hk_path;
                }
            }

            // 本地 + 线上数据
            $plugins[$dir] = plugin_read_by_dir($dir);
        }
    }
}


// 插件依赖检测，返回依赖的插件列表，如果返回为空则表示不依赖
function plugin_dependencies($dir): array
{
    global $plugin_src_files, $plugin_paths, $plugins;
    $plugin = $plugins[$dir];
    $dependencies = $plugin['dependencies'];

    // 检查插件依赖关系
    $arr = array();
    foreach ($dependencies as $_dir => $version) {
        if (!isset($plugins[$_dir]) || !$plugins[$_dir]['enable']) {
            $arr[$_dir] = $version;
        }
    }
    return $arr;
}


//返回被依赖的插件数组：
function plugin_by_dependencies($dir): array
{
    global $plugins;

    $arr = array();
    foreach ($plugins as $_dir => $plugin) {
        if (isset($plugin['dependencies'][$dir]) && $plugin['enable']) {
            $arr[$_dir] = $plugin['version'];
        }
    }
    return $arr;
}


//
function plugin_enable($dir)
{
    global $plugins;

    if (!isset($plugins[$dir])) {
        return FALSE;
    }

    $plugins[$dir]['enable'] = 1;

    file_replace_var(APP_PATH . "plugin/$dir/conf.json", array('enable' => 1), TRUE);

    plugin_clear_tmp_dir();

    return TRUE;
}


// 清空插件的临时目录
function plugin_clear_tmp_dir()
{
    global $conf;
    rmdir_recursive($conf['tmp_path'], TRUE);
    y_unlink($conf['tmp_path'] . 'model.min.php');
}


function plugin_disable($dir)
{
    global $plugins;

    if (!isset($plugins[$dir])) {
        return FALSE;
    }

    $plugins[$dir]['enable'] = 0;

    file_replace_var(APP_PATH . "plugin/$dir/conf.json", array('enable' => 0), TRUE);

    plugin_clear_tmp_dir();

    return TRUE;
}

// 安装所有的本地插件
function plugin_install_all()
{
    global $plugins;

    // 检查文件更新
    foreach ($plugins as $dir => $plugin) {
        plugin_install($dir);
    }
}

// 卸载所有的本地插件
function plugin_uninstall_all()
{
    global $plugins;

    // 检查文件更新
    foreach ($plugins as $dir => $plugin) {
        plugin_uninstall($dir);
    }
}

/*
	插件安装：
		把所有的插件点合并，重新写入文件。如果没有备份文件，则备份一份。
		插件名可以为源文件名：view/header.htm
*/
function plugin_install($dir)
{
    global $plugins, $conf;

    if (!isset($plugins[$dir])) {
        return FALSE;
    }

    $plugins[$dir]['installed'] = 1;
    $plugins[$dir]['enable'] = 1;

    // 写入配置文件
    file_replace_var(APP_PATH . "plugin/$dir/conf.json", array('installed' => 1, 'enable' => 1), TRUE);

    plugin_clear_tmp_dir();

    return TRUE;
}

// copy from plugin_install 修改
function plugin_uninstall($dir): bool
{
    global $plugins;

    if (!isset($plugins[$dir])) {
        return TRUE;
    }

    $plugins[$dir]['installed'] = 0;
    $plugins[$dir]['enable'] = 0;

    // 写入配置文件
    file_replace_var(APP_PATH . "plugin/$dir/conf.json", array('installed' => 0, 'enable' => 0), TRUE);

    plugin_clear_tmp_dir();

    return TRUE;
}


function plugin_paths_enabled()
{
    static $return_paths;
    if (empty($return_paths)) {
        $return_paths = array();
        $plugin_paths = glob(APP_PATH . 'plugin/*', GLOB_ONLYDIR);
        if (empty($plugin_paths)) return array();
        foreach ($plugin_paths as $path) {
            $conf_file = $path . "/conf.json";
            if (!is_file($conf_file)) continue;
            $p_conf = y_json_decode(file_get_contents($conf_file));
            if (empty($p_conf)) {
                continue;
            }
            if (empty($p_conf['enable']) || empty($p_conf['installed'])) {
                continue;
            }
            $return_paths[$path] = $p_conf;
        }
    }
    return $return_paths;
}


// 编译源文件，把插件合并到该文件，不需要递归，执行的过程中 include _include() 自动会递归。
function plugin_compile_src_file($src_file)
{
    global $conf;
    // 判断是否开启插件
    if (!empty($conf['disabled_plugin'])) {
        return file_get_contents($src_file);
    }

    // 如果有 overwrite，则用 overwrite 替换掉
    $src_file = plugin_find_overwrite($src_file);
    $s = file_get_contents($src_file);

    // 最多支持 10 层
    for ($i = 0; $i < 10; $i++) {
        if (strpos($s, '<!--{hook') !== FALSE || strpos($s, '// hook') !== FALSE) {
            $s = preg_replace('#<!--{hook\s+(.*?)}-->#', '// hook \\1', $s);
            $s = preg_replace_callback('#//\s*hook\s+(\S+)#is', 'plugin_compile_src_file_callback', $s);
        } else {
            break;
        }
    }
    return $s;
}


// 只返回一个权重最高的文件名
function plugin_find_overwrite($src_file)
{
    $plugin_paths = plugin_paths_enabled();

    $len = strlen(APP_PATH);

    $return_file = $src_file;
    $max_rank = 0;
    foreach ($plugin_paths as $path => $pconf) {

        // 文件路径后半部分
        $dir = file_name($path);
        $filepath_half = substr($src_file, $len);
        $overwrite_file = APP_PATH . "plugin/$dir/overwrite/$filepath_half";
        if (is_file($overwrite_file)) {
            $rank = $pconf['overwrites_rank'][$filepath_half] ?? 0;
            if ($rank >= $max_rank) {
                $return_file = $overwrite_file;
                $max_rank = $rank;
            }
        }
    }
    return $return_file;
}


function plugin_compile_src_file_callback($m): string
{
    static $hooks;
    if (empty($hooks)) {
        $hooks = array();
        $plugin_paths = plugin_paths_enabled();

        foreach ($plugin_paths as $path => $p_conf) {
            $dir = file_name($path);
            $hook_paths = glob(APP_PATH . "plugin/$dir/hook/*.*"); // path
            if (is_array($hook_paths)) {
                foreach ($hook_paths as $hk_path) {
                    $hook_name = file_name($hk_path);
                    $rank = $p_conf['hooks_rank']["$hook_name"] ?? 0;
                    $hooks[$hook_name][] = array('hook_path' => $hk_path, 'rank' => $rank);
                }
            }
        }
        foreach ($hooks as $hook_name => $arr_list) {
            $arr_list = array_list_multi_sort($arr_list, 'rank', FALSE);
            $hooks[$hook_name] = array_list_values($arr_list, 'hook_path');
        }
    }

    $s = '';
    $hook_name = $m[1];
    if (!empty($hooks[$hook_name])) {
        $file_ext = file_ext($hook_name);
        foreach ($hooks[$hook_name] as $path) {
            $t = file_get_contents($path);
            if ($file_ext == 'php' && preg_match('#^\s*<\?php\s+exit;#is', $t)) {
                // 正则表达式去除兼容性比较好。
                $t = preg_replace('#^\s*<\?php\s*exit;(.*?)(?:\?>)?\s*$#is', '\\1', $t);
            }
            $s .= $t;
        }
    }
    return $s;
}

function plugin_online_install($dir)
{

}


// -------------------> 官方插件列表缓存到本地。

// 条件满足的总数
function plugin_official_total($cond = array()): int
{
    global $official_plugins;
    $off_list = $official_plugins;
    $off_list = array_list_cond_order_by($off_list, $cond, array(), 1, 1000);
    return count($off_list);
}


// 远程插件列表，从官方服务器获取插件列表，全部缓存到本地，定期更新
function plugin_official_list($cond = array(), $order_by = array('plugin_id' => -1), $page = 1, $page_size = 20)
{
    global $official_plugins;
    // 服务端插件信息，缓存起来
    $off_list = $official_plugins;
    $off_list = array_list_cond_order_by($off_list, $cond, $order_by, $page, $page_size);
    foreach ($off_list as &$plugin) $plugin = plugin_read_by_dir($plugin['dir'], FALSE);
    return $off_list;
}


function plugin_official_list_cache()
{
    $s = DEBUG == 3 ? NULL : cache_get('plugin_official_list');
    if ($s === NULL) {
        $url = PLUGIN_OFFICIAL_URL . "plugin-all-4.htm"; // 获取所有的插件，匹配到3.0以上的。
        $s = http_get($url);

        // 检查返回值是否正确
        if (empty($s)) return y_error(-1, '从官方获取插件数据失败。');
        $r = y_json_decode($s);
        if (empty($r)) return y_error(-1, '从官方获取插件数据格式不对。');

        $s = $r;
        cache_set('plugin_official_list', $s, 3600); // 缓存时间 1 小时。
    }
    return $s;
}


function plugin_official_read($dir)
{
    global $official_plugins;
    $off_list = $official_plugins;
    return $off_list[$dir] ?? array();
}


// -------------------> 本地插件列表缓存到本地。
// 安装，卸载，禁用，更新
function plugin_read_by_dir($dir, $local_first = TRUE)
{
    global $plugins;

    $local = array_value($plugins, $dir, array());
    $official = plugin_official_read($dir);
    if (empty($local) && empty($official)) return array();
    if (empty($local)) $local_first = FALSE;

    // 本地插件信息
    //!isset($plugin['dir']) && $plugin['dir'] = '';
    !isset($local['name']) && $local['name'] = '';
    !isset($local['price']) && $local['price'] = 0;
    !isset($local['brief']) && $local['brief'] = '';
    !isset($local['version']) && $local['version'] = '1.0';
    !isset($local['bbs_version']) && $local['bbs_version'] = '4.0';
    !isset($local['installed']) && $local['installed'] = 0;
    !isset($local['enable']) && $local['enable'] = 0;
    !isset($local['hooks']) && $local['hooks'] = array();
    !isset($local['hooks_rank']) && $local['hooks_rank'] = array();
    !isset($local['dependencies']) && $local['dependencies'] = array();
    !isset($local['icon_url']) && $local['icon_url'] = '';
    !isset($local['have_setting']) && $local['have_setting'] = 0;
    !isset($local['setting_url']) && $local['setting_url'] = 0;

    // 加上官方插件的信息
    !isset($official['plugin_id']) && $official['plugin_id'] = 0;
    !isset($official['name']) && $official['name'] = '';
    !isset($official['price']) && $official['price'] = 0;
    !isset($official['brief']) && $official['brief'] = '';
    !isset($official['bbs_version']) && $official['bbs_version'] = '4.0';
    !isset($official['version']) && $official['version'] = '1.0';
    !isset($official['cateid']) && $official['cateid'] = 0;
    !isset($official['lastupdate']) && $official['lastupdate'] = 0;
    !isset($official['stars']) && $official['stars'] = 0;
    !isset($official['user_stars']) && $official['user_stars'] = 0;
    !isset($official['installs']) && $official['installs'] = 0;
    !isset($official['sells']) && $official['sells'] = 0;
    !isset($official['file_md5']) && $official['file_md5'] = '';
    !isset($official['filename']) && $official['filename'] = '';
    !isset($official['is_cert']) && $official['is_cert'] = 0;
    !isset($official['is_show']) && $official['is_show'] = 0;
    !isset($official['img1']) && $official['img1'] = 0;
    !isset($official['img2']) && $official['img2'] = 0;
    !isset($official['img3']) && $official['img3'] = 0;
    !isset($official['img4']) && $official['img4'] = 0;
    !isset($official['brief_url']) && $official['brief_url'] = '';
    !isset($official['qq']) && $official['qq'] = '';

    $local['official'] = $official;

    if ($local_first) {
        $plugin = $local + $official;
    } else {
        $plugin = $official + $local;
    }
    // 额外的判断
    $plugin['icon_url'] = $plugin['plugin_id'] ? PLUGIN_OFFICIAL_URL . "upload/plugin/$plugin[plugin_id]/icon.png" : "../plugin/$dir/icon.png";
    $plugin['setting_url'] = $plugin['installed'] && is_file("../plugin/$dir/setting.php") ? "plugin-setting-$dir.htm" : "";
    $plugin['downloaded'] = isset($plugins[$dir]);
    $plugin['stars_fmt'] = $plugin['plugin_id'] ? str_repeat('<span class="icon star"></span>', $plugin['stars']) : '';
    $plugin['user_stars_fmt'] = $plugin['plugin_id'] ? str_repeat('<span class="icon star"></span>', $plugin['user_stars']) : '';
    $plugin['is_cert_fmt'] = empty($plugin['is_cert']) ? '<span class="text-danger">' . lang('no') . '</span>' : '<span class="text-success">' . lang('yes') . '</span>';
    $plugin['have_upgrade'] = $plugin['installed'] && version_compare($official['version'], $local['version']) > 0;
    $plugin['official_version'] = $official['version']; // 官方版本
    $plugin['img1_url'] = $official['img1'] ? PLUGIN_OFFICIAL_URL . 'upload/plugin/' . $plugin['plugin_id'] . '/img1.jpg' : ''; // 官方版本
    $plugin['img2_url'] = $official['img2'] ? PLUGIN_OFFICIAL_URL . 'upload/plugin/' . $plugin['plugin_id'] . '/img2.jpg' : ''; // 官方版本
    $plugin['img3_url'] = $official['img3'] ? PLUGIN_OFFICIAL_URL . 'upload/plugin/' . $plugin['plugin_id'] . '/img3.jpg' : ''; // 官方版本
    $plugin['img4_url'] = $official['img4'] ? PLUGIN_OFFICIAL_URL . 'upload/plugin/' . $plugin['plugin_id'] . '/img4.jpg' : ''; // 官方版本
    return $plugin;
}


function plugin_site_id(): string
{
    global $conf;
    $auth_key = $conf['auth_key'];
    $site_ip = _SERVER('SERVER_ADDR');
    return md5($auth_key . $site_ip);
}

