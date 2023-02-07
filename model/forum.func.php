<?php


function _forum_create($arr)
{
    return db_create('forum', $arr);
}


function _forum_update($fid, $arr)
{
    return db_update('forum', array('fid' => $fid), $arr);
}


function _forum_read($fid)
{
    return db_find_one('forum', array('fid' => $fid));
}


function _forum_delete($fid)
{
    return db_delete('forum', array('fid' => $fid));
}


function _forum_find($cond = array(), $order_by = array(), $page = 1, $page_size = 1000): array
{
    return db_find('forum', $cond, $order_by, $page, $page_size, 'fid');
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function forum_create($arr)
{
    $r = _forum_create($arr);
    forum_list_cache_delete();
    return $r;
}


function forum_update($fid, $arr)
{
    $r = _forum_update($fid, $arr);
    forum_list_cache_delete();
    return $r;
}


function forum_read($fid)
{
    global $conf, $forum_list;
    if ($conf['cache']['enable']) {
        return empty($forum_list[$fid]) ? array() : $forum_list[$fid];
    } else {
        $forum = _forum_read($fid);
        forum_format($forum);
        return $forum;
    }
}


// 关联数据删除
function forum_delete($fid)
{
    //  把板块下所有的帖子都查找出来，此处数据量大可能会超时，所以不要删除帖子特别多的板块
    $cond = array('fid' => $fid);
    $thread_list = db_find('thread', $cond, array(), 1, 1000000, '', array('tid', 'uid'));

    foreach ($thread_list as $thread) {
        thread_delete(0, $thread['tid']);
    }

    $r = _forum_delete($fid);

    forum_access_delete_by_fid($fid);

    forum_list_cache_delete();

    return $r;
}


function forum_find($cond = array(), $order_by = array('rank' => -1), $page = 1, $page_size = 1000): array
{
    $forum_list = _forum_find($cond, $order_by, $page, $page_size);
    if ($forum_list) foreach ($forum_list as &$forum) forum_format($forum);
    return $forum_list;
}

// ------------> 其他方法

function forum_format(&$forum)
{
    global $conf;
    if (empty($forum)) return;

    // hook model_forum_format_start.php

    $forum['create_date_fmt'] = date('Y-n-j', $forum['create_date']);
    $forum['icon_url'] = $forum['icon'] ? $conf['upload_url'] . "forum/$forum[fid].png" : 'view/img/forum.png';
    $forum['accesslist'] = $forum['accesson'] ? forum_access_find_by_fid($forum['fid']) : array();
    $forum['modlist'] = array();
    if ($forum['moduids']) {
        $mod_list = user_find_by_uids($forum['moduids']);
        foreach ($mod_list as &$mod) $mod = user_safe_info($mod);
        $forum['modlist'] = $mod_list;
    }
    // hook model_forum_format_end.php
}


function forum_count($cond = array()): int
{
    // hook model_forum_count_start.php
    $n = db_count('forum', $cond);
    // hook model_forum_count_end.php
    return $n;
}


function forum_max_id(): int
{
    // hook model_forum_maxid_start.php
    $n = db_max_id('forum', 'fid');
    // hook model_forum_maxid_end.php
    return $n;
}


// 从缓存中读取 forum_list 数据x
function forum_list_cache()
{
    global $conf, $forum_list;
    $forum_list = cache_get('forumlist');

    // hook model_forum_list_cache_start.php

    if ($forum_list === NULL) {
        $forum_list = forum_find();
        cache_set('forumlist', $forum_list, 60);
    }
    // hook model_forum_list_cache_end.php
    return $forum_list;
}

// 更新 forumlist 缓存
function forum_list_cache_delete()
{
    global $conf;
    static $deleted = FALSE;
    if ($deleted) return;

    // hook model_forum_list_cache_delete_start.php

    cache_delete('forumlist');
    $deleted = TRUE;
    // hook model_forum_list_cache_delete_end.php
}


// 对 $forumlist 权限过滤，查看权限没有，则隐藏
function forum_list_access_filter($forum_list, $gid, $allow = 'allowread')
{
    global $conf, $group_list;
    if (empty($forum_list)) return array();
    if ($gid == 1) return $forum_list;
    $forum_list_filter = $forum_list;
    $group = $group_list[$gid];

    // hook model_forum_list_access_filter_start.php

    foreach ($forum_list_filter as $fid => $forum) {
        if (empty($forum['accesson']) && empty($group[$allow]) || !empty($forum['accesson']) && empty($forum['accesslist'][$gid][$allow])) {
            unset($forum_list_filter[$fid]);
            unset($forum_list_filter[$fid]['modlist']);
        }
        unset($forum_list_filter[$fid]['accesslist']);
    }
    // hook model_forum_list_access_filter_end.php
    return $forum_list_filter;
}


function forum_filter_module_id($module_ids)
{
    $module_ids = trim($module_ids);
    if (empty($module_ids)) return '';
    $arr = explode(',', $module_ids);
    $r = array();
    foreach ($arr as $_uid) {
        $_uid = intval($_uid);
        $_user = user_read($_uid);
        if (empty($_user)) continue;
        if ($_user['gid'] > 4) continue;
        $r[] = $_uid;
    }
    return implode(',', $r);
}


function forum_safe_info($forum)
{
    // hook model_forum_safe_info_start.php
    //unset($forum['moduids']);
    // hook model_forum_safe_info_end.php
    return $forum;
}

// hook model_forum_end.php
