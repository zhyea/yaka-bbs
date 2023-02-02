<?php


// ------------> 最原生的 CURD，无关联其他数据。

function _attach_create($arr)
{
    return db_create('attach', $arr);
}


function _attach_update($aid, $arr)
{
    return db_update('attach', array('aid' => $aid), $arr);
}


function _attach_read($aid)
{
    return db_find_one('attach', array('aid' => $aid));
}


function _attach_delete($aid)
{
    return db_delete('attach', array('aid' => $aid));
}


function _attach_find($cond = array(), $order_by = array(), $page = 1, $page_size = 20): array
{
    return db_find('attach', $cond, $order_by, $page, $page_size);
}


function attach_create($arr)
{
    return _attach_create($arr);
}


function attach_update($aid, $arr)
{
    return _attach_update($aid, $arr);
}


function attach_read($aid)
{
    $attach = _attach_read($aid);
    attach_format($attach);
    return $attach;
}


function attach_delete($aid)
{
    global $conf;
    $attach = attach_read($aid);
    $path = $conf['upload_path'] . 'attach/' . $attach['filename'];
    file_exists($path) and unlink($path);

    return _attach_delete($aid);
}


function attach_delete_by_pid($pid): int
{
    global $conf;
    list($attach_list, $image_list, $file_list) = attach_find_by_pid($pid);
    foreach ($attach_list as $attach) {
        $path = $conf['upload_path'] . 'attach/' . $attach['filename'];
        file_exists($path) and unlink($path);
        _attach_delete($attach['aid']);
    }
    return count($attach_list);
}


function attach_delete_by_uid($uid)
{
    global $conf;
    $attach_list = db_find('attach', array('uid' => $uid), array(), 1, 9000);
    foreach ($attach_list as $attach) {
        $path = $conf['upload_path'] . 'attach/' . $attach['filename'];
        file_exists($path) and unlink($path);
        _attach_delete($attach['aid']);
    }
}


function attach_find($cond = array(), $order_by = array(), $page = 1, $page_size = 20): array
{
    $attach_list = _attach_find($cond, $order_by, $page, $page_size);
    if ($attach_list) {
        foreach ($attach_list as &$attach) {
            attach_format($attach);
        }
    }
    return $attach_list;
}


// 获取 $file_list $image_list
function attach_find_by_pid($pid): array
{
    $attach_list = $image_list = $file_list = array();
    $attach_list = _attach_find(array('pid' => $pid), array(), 1, 1000);
    if ($attach_list) {
        foreach ($attach_list as $attach) {
            attach_format($attach);
            $attach['is_image'] ? ($image_list[] = $attach) : ($file_list[] = $attach);
        }
    }
    return array($attach_list, $image_list, $file_list);
}


function attach_format(&$attach)
{
    global $conf;
    if (empty($attach)) return;
    $attach['create_date_fmt'] = date('Y-n-j', $attach['create_date']);
    $attach['url'] = $conf['upload_url'] . 'attach/' . $attach['filename'];
}


function attach_count($cond = array()): int
{
    $cond = db_cond_to_sql_add($cond);
    return db_count('attach', $cond);
}


function attach_type($name, $types)
{
    $ext = file_ext($name);
    foreach ($types as $type => $arr_ext) {
        if ($type == 'all') continue;
        if (in_array($ext, $arr_ext)) {
            return $type;
        }
    }
    return 'other';
}


// 扫描垃圾的附件，每日清理一次
function attach_gc()
{
    global $time, $conf;
    $tmp_files = glob($conf['upload_path'] . 'tmp/*.*');
    if (is_array($tmp_files)) {
        foreach ($tmp_files as $file) {
            // 清理超过一天还没处理的临时文件
            if ($time - filemtime($file) > 86400) {
                unlink($file);
            }
        }
    }
}


// 关联 session 中的临时文件，并不会重新统计 images, files
function attach_assoc_post($pid): bool
{
    global $uid, $time, $conf;
    $sess_tmp_files = _SESSION('tmp_files');
    if (!$sess_tmp_files && preg_match('/tmp\+files\|(a\:1\:\{.*\})/', _SESSION('data'), $arr)) {
        $sess_tmp_files = unserialize(str_replace(array('+', '='), array('_', '.'), $arr['1']));
    }

    $post = post__read($pid);
    if (empty($post)) {
        return TRUE;
    }

    // hook attach_assoc_post_start.php

    $tid = $post['tid'];
    $post['message_old'] = $post['message_fmt'];

    // 把临时文件 upload/tmp/xxx.xxx 也处理了
    //preg_match_all('#src="upload/tmp/(\w+\.\w+)"#', $post['message_old'], $m);
    //$use_tmp_files = $m[1]; // 实际使用的临时文件，不用的全部删除？如果是两个帖子一起编辑？

    // 将 session 中的数据和 message 中的数据合并。
    //$tmp_files = array_unique(array_merge($sess_tmp_files, $use_tmp_files));

    $attach_dir_save_rule = array_value($conf, 'attach_dir_save_rule', 'Ym');

    $tmp_files = $sess_tmp_files;
    if ($tmp_files) {
        foreach ($tmp_files as $file) {

            // 将文件移动到 upload/attach 目录
            $filename = file_name($file['url']);

            $day = date($attach_dir_save_rule, $time);
            $path = $conf['upload_path'] . 'attach/' . $day;
            $url = $conf['upload_url'] . 'attach/' . $day;
            !is_dir($path) and mkdir($path, 0777, TRUE);

            $dest_file = $path . '/' . $filename;
            $dest_url = $url . '/' . $filename;
            $r = y_copy($file['path'], $dest_file);
            !$r and y_log("xn_copy($file[path]), $dest_file) failed, pid:$pid, tid:$tid", 'php_error');
            if (is_file($dest_file) && filesize($dest_file) == filesize($file['path'])) {
                @unlink($file['path']);
            }
            $arr = array(
                'tid' => $tid,
                'pid' => $pid,
                'uid' => $uid,
                'filesize' => $file['filesize'],
                'width' => $file['width'],
                'height' => $file['height'],
                'filename' => "$day/$filename",
                'org_file_name' => $file['org_file_name'],
                'filetype' => $file['filetype'],
                'create_date' => $time,
                'comment' => '',
                'downloads' => 0,
                'is_image' => $file['is_image']
            );

            // 插入后，进行关联
            $aid = attach_create($arr);
            $post['message'] = str_replace($file['url'], $dest_url, $post['message']);
            $post['message_fmt'] = str_replace($file['url'], $dest_url, $post['message_fmt']);

        }
    }

    // 清空 session
    $_SESSION['tmp_files'] = array();

    $post['message_old'] != $post['message_fmt'] and post__update($pid, array('message' => $post['message'], 'message_fmt' => $post['message_fmt']));


    // 更新 images files
    list($attach_list, $image_list, $file_list) = attach_find_by_pid($pid);
    $images = count($image_list);
    $files = count($file_list);
    $post['is_first'] and thread__update($tid, array('images' => $images, 'files' => $files));
    post__update($pid, array('images' => $images, 'files' => $files));

    return TRUE;
}
