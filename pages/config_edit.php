<?php

/**
 * VEditor Configuration Edit Handler
 *
 * @package VEditor
 * @copyright Ryszard Pydo
 * @license MIT License
 */

form_security_validate('plugin_veditor_config_edit');

auth_reauthenticate();
access_ensure_global_level(config_get('manage_plugin_threshold'));

// Get form values
$f_process_text = gpc_get_int('process_text');
$f_process_urls = gpc_get_int('process_urls');
$f_process_buglinks = gpc_get_int('process_buglinks');
$f_process_markdown = gpc_get_int('process_markdown');
$f_menubar = gpc_get_string('menubar');
$f_height = gpc_get_int('height');
$f_pasteimages = gpc_get_string('pasteimages');
$f_pastetext = gpc_get_string('pastetext');
$f_conv_img_to_file = gpc_get_int('conv_img_to_file');
$f_html_disable_str = gpc_get_string('html_disable_str');
$f_access_level = gpc_get_int('access_level');
$f_dev_level = gpc_get_int('dev_level');
$f_pages = gpc_get_string('pages');
$f_dev_plugins = gpc_get_string('dev_plugins');
$f_reporter_plugins = gpc_get_string('reporter_plugins');
$f_dev_toolbar = gpc_get_string('dev_toolbar');
$f_reporter_toolbar = gpc_get_string('reporter_toolbar');

// Process pages array
$f_pages_array = array_filter(array_map('trim', explode("\n", $f_pages)));

// Save configuration if values have changed
if (plugin_config_get('process_text') != $f_process_text) {
    plugin_config_set('process_text', $f_process_text);
}

if (plugin_config_get('process_urls') != $f_process_urls) {
    plugin_config_set('process_urls', $f_process_urls);
}

if (plugin_config_get('process_buglinks') != $f_process_buglinks) {
    plugin_config_set('process_buglinks', $f_process_buglinks);
}

if (plugin_config_get('process_markdown') != $f_process_markdown) {
    plugin_config_set('process_markdown', $f_process_markdown);
}

if (plugin_config_get('menubar') != $f_menubar) {
    plugin_config_set('menubar', $f_menubar);
}

if (plugin_config_get('height') != $f_height) {
    plugin_config_set('height', $f_height);
}

if (plugin_config_get('pasteimages') != $f_pasteimages) {
    plugin_config_set('pasteimages', $f_pasteimages);
}

if (plugin_config_get('pastetext') != $f_pastetext) {
    plugin_config_set('pastetext', $f_pastetext);
}

if (plugin_config_get('conv_img_to_file') != $f_conv_img_to_file) {
    plugin_config_set('conv_img_to_file', $f_conv_img_to_file);
}

if (plugin_config_get('html_disable_str') != $f_html_disable_str) {
    plugin_config_set('html_disable_str', $f_html_disable_str);
}

if (plugin_config_get('access_level') != $f_access_level) {
    plugin_config_set('access_level', $f_access_level);
}

if (plugin_config_get('dev_level') != $f_dev_level) {
    plugin_config_set('dev_level', $f_dev_level);
}

if (plugin_config_get('pages') != $f_pages_array) {
    plugin_config_set('pages', $f_pages_array);
}

if (plugin_config_get('dev_plugins') != $f_dev_plugins) {
    plugin_config_set('dev_plugins', $f_dev_plugins);
}

if (plugin_config_get('reporter_plugins') != $f_reporter_plugins) {
    plugin_config_set('reporter_plugins', $f_reporter_plugins);
}

if (plugin_config_get('dev_toolbar') != $f_dev_toolbar) {
    plugin_config_set('dev_toolbar', $f_dev_toolbar);
}

if (plugin_config_get('reporter_toolbar') != $f_reporter_toolbar) {
    plugin_config_set('reporter_toolbar', $f_reporter_toolbar);
}

form_security_purge('plugin_veditor_config_edit');

print_header_redirect(plugin_page('config', true));
