<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once( 'core.php' );

/* * *************************************************
 * Only these origins are allowed to upload images *
 * ************************************************* */
$accepted_origins = array("http://localhost", 'https://dev.gavdi.pl');

$f_bug_id = gpc_get_int('bug_id');
$t_user_id = auth_get_current_user_id();

if (isset($_SERVER['HTTP_ORIGIN'])) {
    error_log('Orgin: ' . $_SERVER['HTTP_ORIGIN'] . 'koniec');
    // same-origin requests won't set an origin. If the origin is set, it must be valid.
    if (in_array($_SERVER['HTTP_ORIGIN'], $accepted_origins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    } else {
        header("HTTP/1.1 403 Origin Denied");
        return;
    }
}

// Don't attempt to process the upload on an OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    return;
}

if (bug_is_readonly($f_bug_id)) {
    throw new ClientException(
                    sprintf("Issue '%d' is read-only.", $f_bug_id),
                    ERROR_BUG_READ_ONLY_ACTION_DENIED,
                    array($f_bug_id));
}
if (!file_allow_bug_upload($f_bug_id, $t_user_id)) {
    throw new ClientException('access denied for uploading files', ERROR_ACCESS_DENIED);
}
$t_file_infos = [];
foreach ($_FILES as $t_file) {
    if (!empty($t_file['name'])) {
        # $p_bug_id, array $p_file, $p_table = 'bug', $p_title = '', $p_desc = '', $p_user_id = null, $p_date_added = 0, $p_skip_bug_update = false, $p_bugnote_id = 0
        $t_file_infos[] = file_add(
                $f_bug_id,
                $t_file,
                'bug',
                IMG_PREFIX, /* title */
                '', /* desc */
                null, /* user_id */
                0, /* date_added */
                true, /* skip_bug_update */
                dummy_bugnote);
    }
}


if (count($t_file_infos)) {
    $t_file_id = $t_file_infos[0]['id'];
    echo json_encode(array('location' => 'file_download.php?type=bug&file_id=' . $t_file_id));
} else {
    // Notify editor that the upload failed
    header("HTTP/1.1 500 Server Error");
}
?>
