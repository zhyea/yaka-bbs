<?php

// ------------> 关联的 CURD，无关联其他数据。


function thread_create($uid, $tid)
{
    if ($uid == 0) return TRUE; // 匿名发帖
    $thread = thread_read($uid, $tid);
    if (empty($thread)) {
        return db_create('y-thread', array('uid' => $uid, 'tid' => $tid));
    } else {
        return TRUE;
    }
}


function thread_read($uid, $tid)
{
    return db_find_one('y-thread', array('uid' => $uid, 'tid' => $tid));
}


function thread_delete($uid, $tid)
{
    return db_delete('y-thread', array('uid' => $uid, 'tid' => $tid));
}


function thread_delete_by_uid($uid)
{
    return db_delete('y-thread', array('uid' => $uid));
}


function thread_delete_by_fid($fid)
{
    return db_delete('y-thread', array('fid' => $fid));
}


function thread_delete_by_tid($tid)
{
    return db_delete('y-thread', array('tid' => $tid));
}


function thread_find($cond = array(), $order_by = array(), $page = 1, $page_size = 20): array
{
    return db_find('y-thread', $cond, $order_by, $page, $page_size);
}


function thread_find_by_uid($uid, $page = 1, $page_size = 20): array
{
    $thread_list = thread_find(array('uid' => $uid), array('tid' => -1), $page, $page_size);
    if (empty($thread_list)) {
        return array();
    }

    $result = array();
    foreach ($thread_list as &$thread) {
        $result[$thread['tid']] = thread_read($uid, $thread['tid']);
    }
    return $result;
}