<?php

/**用法
 *
 * echo form_radio_yes_no('radio1', 0);
 * echo form_checkbox('aaa', array('无', '有'), 0);
 *
 * echo form_radio_yes_no('aaa', 0);
 * echo form_radio('aaa', array('无', '有'), 0);
 * echo form_radio('aaa', array('a'=>'aaa', 'b'=>'bbb', 'c'=>'ccc', ), 'b');
 *
 * echo form_select('aaa', array('a'=>'aaa', 'b'=>'bbb', 'c'=>'ccc', ), 'a');
 */


function form_radio_yes_no($name, $checked = 0): string
{
    $checked = intval($checked);
    return form_radio($name, array(1 => lang('yes'), 0 => lang('no')), $checked);
}


function form_radio($name, $arr, $checked = 0): string
{
    empty($arr) && $arr = array(lang('no'), lang('yes'));
    $s = '';

    foreach ((array)$arr as $k => $v) {
        $add = $k == $checked ? ' checked="checked"' : '';
        $s .= "<label class=\"custom-input custom-radio\"><input type=\"radio\" name=\"$name\" value=\"$k\"$add /> $v</label> &nbsp; \r\n";
    }
    return $s;
}


function form_checkbox($name, $checked = 0, $txt = '', $val = 1): string
{
    $add = $checked ? ' checked="checked"' : '';
    $s = "<label class=\"custom-input custom-checkbox mr-4\"><input type=\"checkbox\" name=\"$name\" value=\"$val\" $add /> $txt</label>";
    return $s;
}


/*
	form_multi_checkbox('cateid[]', array('value1'=>'text1', 'value2'=>'text2', 'value3'=>'text3'), array('value1', 'value2'));
*/
function form_multi_checkbox($name, $arr, $checked = array()): string
{
    $s = '';
    foreach ($arr as $value => $text) {
        $is_checked = in_array($value, $checked);
        $s .= form_checkbox($name, $is_checked, $text, $value);
    }
    return $s;
}


function form_select($name, $arr, $checked = 0, $id = TRUE): string
{
    if (empty($arr)) return '';
    $id_add = $id === TRUE ? "id=\"$name\"" : ($id ? "id=\"$id\"" : '');
    $s = "<select name=\"$name\" class=\"custom-select\" $id_add> \r\n";
    $s .= form_options($arr, $checked);
    $s .= "</select> \r\n";
    return $s;
}


function form_options($arr, $checked = 0): string
{
    $s = '';
    foreach ((array)$arr as $k => $v) {
        $add = $k == $checked ? ' selected="selected"' : '';
        $s .= "<option value=\"$k\"$add>$v</option> \r\n";
    }
    return $s;
}


function form_text($name, $value, $width = FALSE, $placeholder = ''): string
{
    $style = '';
    if ($width !== FALSE) {
        is_numeric($width) and $width .= 'px';
        $style = " style=\"width: $width\"";
    }
    return "<input type=\"text\" name=\"$name\" id=\"$name\" placeholder=\"$placeholder\" value=\"$value\" class=\"form-control\"$style />";
}


function form_hidden($name, $value): string
{
    return "<input type=\"hidden\" name=\"$name\" id=\"$name\" value=\"$value\" />";
}


function form_textarea($name, $value, $width = FALSE, $height = FALSE): string
{
    $style = '';
    if ($width !== FALSE) {
        is_numeric($width) and $width .= 'px';
        is_numeric($height) and $height .= 'px';
        $style = " style=\"width: $width; height: $height; \"";
    }
    return "<textarea name=\"$name\" id=\"$name\" class=\"form-control\" $style>$value</textarea>";
}



function form_password($name, $value, $width = FALSE): string
{
    $style = '';
    if ($width !== FALSE) {
        is_numeric($width) and $width .= 'px';
        $style = " style=\"width: $width\"";
    }
    return "<input type=\"password\" name=\"$name\" id=\"$name\" class=\"form-control\" value=\"$value\" $style />";
}



function form_time($name, $value, $width = FALSE): string
{
    $style = '';
    if ($width !== FALSE) {
        is_numeric($width) and $width .= 'px';
        $style = " style=\"width: $width\"";
    }
    return "<input type=\"text\" name=\"$name\" id=\"$name\" class=\"form-control\" value=\"$value\" $style />";
}
