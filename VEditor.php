<?php
#******************************************************************************
# VEditor is plugin for MantisBT using TinyMCE extension 
# Copyright Ryszard Pydo
#
# Licensed under MIT licence
#******************************************************************************

require_once( 'html2text.php' );
require_api('mention_api.php');
require_once( config_get('plugin_path') . 'MantisCoreFormatting' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'MantisMarkdown.php' );
require_once('htmLawed/htmLawed.php');

define("IMG_PREFIX", 'Pasted by TinyMCE');

class VEditorPlugin extends MantisFormattingPlugin {

    function register() {
        $this->name = 'VEditor';
        $this->description = 'TinyMCE extension - wyswig editor for textarea (replace MantisCoreFormatting)';
        $this->version = '1.1.0';
        $this->requires = array('MantisCore' => '2.1.0',);
        $this->author = 'Ryszard Pydo';
        $this->contact = 'pysiek634 on github.com';
        $this->url = 'https://github.com/pysiek634/VEditor.git';
    }

    /**
     * Event hook declaration.
     * @return array
     */
    function hooks() {
        $my_hooks = array(
            'EVENT_LAYOUT_RESOURCES' => 'Load_Wysiwyg',
            'EVENT_LAYOUT_BODY_END' => 'StartEditor',
            'EVENT_BUGNOTE_ADD' => 'bugnote_edit',
            'EVENT_BUGNOTE_EDIT' => 'bugnote_edit',
            'EVENT_UPDATE_BUG' => 'update_bug',
            'EVENT_REPORT_BUG' => 'report_bug',
        );
        return array_merge(parent::hooks(), $my_hooks);
    }

    public function install() {
        if (plugin_is_installed('MantisCoreFormatting')) {
            error_parameters('MantisCoreFormatting');
            trigger_error(ERROR_PLUGIN_ALREADY_INSTALLED, ERROR);
            return false;
        }

        return true;
    }

    public function uninstall() {
        if (!plugin_is_installed('MantisCoreFormatting')) {
            error_parameters('MantisCoreFormatting');
            trigger_error(ERROR_PLUGIN_NOT_REGISTERED, ERROR);
            return false;
        }

        return true;
    }

    function report_bug($p_event, $p_bug, $p_bug_id) {
        $this->check_bug_after_update($p_bug);
    }

    function update_bug($p_event, $p_existing_bug, $p_bug) {
        $this->check_bug_after_update($p_bug);
    }

    private function check_bug_after_update($p_bug) {
        if (plugin_config_get('conv_img_to_file', 0) === 0) {
            return;
        }
        list ($t_update1, $t_description) = $this->parse_note_text($p_bug->id, $p_bug->description);
        list ($t_update2, $t_steps_to_reproduce) = $this->parse_note_text($p_bug->id, $p_bug->steps_to_reproduce);
        list ($t_update3, $t_additional_information) = $this->parse_note_text($p_bug->id, $p_bug->additional_information);

        if ($t_update1 or $t_update2 or $t_update3) {
            db_param_push();
            $t_bug_text_id = bug_get_field($p_bug->id, 'bug_text_id');
            $t_query = 'UPDATE {bug_text}
							SET description=' . db_param() . ',
								steps_to_reproduce=' . db_param() . ',
								additional_information=' . db_param() . '
							WHERE id=' . db_param();
            db_query($t_query, array(
                $t_description,
                $t_steps_to_reproduce,
                $t_additional_information,
                $t_bug_text_id));

            bug_text_clear_cache($p_bug->id);
        }
#test for custom fields
        $this->update_custom_notes($p_bug->id);
    }

    private function update_custom_notes($p_bug_id) {
        $t_query = 'SELECT * FROM {custom_field_string} WHERE `bug_id` = ' . db_param() . ' and text is NOT NULL';
        $rs = db_query($t_query, array($p_bug_id));

        while ($row = db_fetch_array($rs)) {
            list ($t_update, $t_note) = $this->parse_note_text($p_bug_id, $row['text']);
            if ($t_update) {
                $t_query = 'UPDATE {custom_field_string}
							SET text=' . db_param() .
                        ' WHERE field_id=' . db_param() .
                        ' AND bug_id=' . db_param();
                db_query($t_query, array(
                    $t_note,
                    $row['field_id'],
                    $p_bug_id));
            }
        }
    }

    function bugnote_edit($p_event, $p_bug_id, $p_bugnote_id, $files = null) {
        if (plugin_config_get('conv_img_to_file', 0) === 0) {
            return;
        }
        $t_text = bugnote_get_text($p_bugnote_id);
        $this->update_img_bugnote($p_bug_id, $p_bugnote_id, $t_text);
    }

    private function parse_note_text($p_bug_id, $p_bugnote_text, $p_type = 'bug', $p_bugnote_id = 0) {
        $t_note = $p_bugnote_text;
        $t_note_updated = false;
        if (!empty($p_bugnote_text)) {
            $t_ids = [];
            preg_match_all('/\ssrc="data:[\w\/]+;base64,([\w\/\+\=]+)"/mi', $p_bugnote_text, $t_ids, PREG_PATTERN_ORDER);

            if (isset($t_ids[1])) {
                for ($t_num = 0; $t_num < count($t_ids[1]); $t_num++) {
                    $t_file_id = $this->save_as_bug_attachement($p_bug_id, $p_bugnote_id, $t_ids[1][$t_num]);
                    if ($t_file_id) {
                        $t_img = ' src="file_download.php?type=' . $p_type . '&file_id=' . $t_file_id . '"';
                        $t_note = str_replace($t_ids[0][$t_num], $t_img, $t_note);
                        $t_note_updated = true;
                    }
                }
            }
        }
        return [$t_note_updated, $t_note];
    }

    private function update_img_bugnote($p_bug_id, $p_bugnote_id, $p_bugnote_text) {
        list ($t_note_updated, $t_note) = $this->parse_note_text($p_bug_id, $p_bugnote_text, 'bug', $p_bugnote_id);
        if ($t_note_updated) {
            $t_bugnote_text_id = bugnote_get_field($p_bugnote_id, 'bugnote_text_id');
            db_param_push();
            $t_query = 'UPDATE {bugnote_text} SET note=' . db_param() . ' WHERE id=' . db_param();
            db_query($t_query, array($t_note, $t_bugnote_text_id));
        }
    }

    private function save_as_bug_attachement($p_bug_id, $p_bugnote_id, $p_base64_string) {
        $t_file = $this->files_base64_to_temp($p_base64_string);

        if (isset($t_file['tmp_name'])) {
            $t_file_info = file_add(
                    $p_bug_id,
                    $t_file,
                    'bug',
                    IMG_PREFIX, /* title */
                    '', /* desc */
                    null, /* user_id */
                    0, /* date_added */
                    true, /* skip_bug_update */
                    $p_bugnote_id);
            return $t_file_info['id'];
        } else {
            return false;
        }
    }

    private function files_base64_to_temp($p_base64_string) {
        $t_file = [];

        if (!empty($p_base64_string)) {
            $t_raw_content = base64_decode($p_base64_string);

            do {
                $t_tmp_file = realpath(sys_get_temp_dir()) . '/' . uniqid('mantisbt-file');
            } while (file_exists($t_tmp_file));

            file_put_contents($t_tmp_file, $t_raw_content);
            $t_file['tmp_name'] = $t_tmp_file;
            $t_file['size'] = filesize($t_tmp_file);
            $t_file['browser_upload'] = false;
            $t_file['name'] = IMG_PREFIX;
        }
        return $t_file;
    }

    private function tinyMCE_config() {
        $t_config = [];
        $t_lang = lang_get_current();
        $t_langs = plugin_config_get('language_mapping', []);
        $t_config['lang'] = 'en';
        if (isset($t_langs[$t_lang])) {
            $t_config['lang'] = $t_langs[$t_lang];
        }
#plugins and toolbars
        $t_config['menubar'] = plugin_config_get('menubar', '');
        $t_dev_level = plugin_config_get('dev_level', DEVELOPER);
        if (access_get_project_level() < $t_dev_level) {
            $t_config['plugins'] = plugin_config_get('reporter_plugins', '');
            $t_config['toolbar'] = plugin_config_get('reporter_toolbar', '');
        } else {
            $t_config['plugins'] = plugin_config_get('dev_plugins', '');
            $t_config['toolbar'] = plugin_config_get('dev_toolbar', '');
        }
        $t_config['height'] = plugin_config_get('height', 300);
        $t_config['pasteimages'] = plugin_config_get('pasteimages', 'true');
        $t_config['pastetext'] = plugin_config_get('pastetext', 'true');

        return $t_config;
    }

    function StartEditor($p_event) {
        if (!$this->EditorIsAllowed()) {
            return;
        }
        $t_config = $this->tinyMCE_config();
        echo '<wyswig  id="configTinyMCE" ';
        echo 'data-lang="' . $t_config['lang'] . '" ';
        echo 'data-plugins="' . $t_config['plugins'] . '" ';
        echo 'data-toolbar="' . $t_config['toolbar'] . '" ';
        echo 'data-menubar="' . $t_config['menubar'] . '" ';
        echo 'data-height="' . $t_config['height'] . '" ';
        echo 'data-pasteimages="' . $t_config['pasteimages'] . '" ';
        echo 'data-pastetext="' . $t_config['pastetext'] . '" ';
        echo 'data-dark="' . config_get('plugin_MantisBTModernDarkTheme_enabled', 0) . '" ';

        echo '</wyswig>>';
        echo '<script src="' . plugin_file('js/VEditor.js') . '&KEY=' . md5(filemtime(plugin_file_path('js/VEditor.js', plugin_get_current()))) . '" referrerpolicy="origin"></script>';
    }

    private function EditorIsAllowed() {
        if (auth_is_user_authenticated()) {
            if (!isset($this->last_url) or $this->last_url != $_SERVER['REQUEST_URI']) {
                $this->last_url = $_SERVER['REQUEST_URI'];
                $this->editor_ok = false;
                $t_pages = plugin_config_get('pages', []);
                foreach ($t_pages as $t_page) {
                    if (strpos($_SERVER['REQUEST_URI'], $t_page) !== false) {
                        $this->editor_ok = true;
                        break;
                    }
                }
                if ($this->editor_ok) {
                    $t_access_level = plugin_config_get('access_level', REPORTER);
                    if (access_get_project_level() < $t_access_level) {
                        $this->editor_ok = false;
                    }
                }
            }
        } else {
            $this->editor_ok = false;
        }
        return $this->editor_ok;
    }

    function Load_Wysiwyg($p_event) {
        if ($this->EditorIsAllowed()) {
            echo '<script src="' . plugin_file('js/tinymce/tinymce.min.js') . '" referrerpolicy="origin"></script>';
        }
    }

    private function nl2br($p_string) {
        if (preg_match('/^<\w+>.*/', $p_string) != 1) {
            return string_nl2br($p_string);
        } else {
            return $p_string;
        }
    }

    /**
     * Default plugin configuration.
     * @return array
     */
    function config() {
        return ['process_text' => ON,
            'process_urls' => ON,
            'process_buglinks' => ON,
            'process_markdown' => OFF,
            'language_mapping' => ['english' => 'en', 'french' => 'fr_FR', 'german' => 'de', 'polish' => 'pl', 'spanish' => 'es_419'],
            'pages' => ['bugnote_edit_page.php', 'view.php', 'bug_update_page.php', 'bug_report_page.php', 'bug_change_status_page.php'],
            'access_level' => REPORTER,
            'dev_level' => DEVELOPER,
            'dev_plugins' => 'table searchreplace lists code image',
            'reporter_plugins' => 'table searchreplace lists',
            'dev_toolbar' => 'undo redo | styles | bold italic | numlist bullist outdent indent | alignleft aligncenter alignright | paste pastetext | code',
            'reporter_toolbar' => 'undo redo | styles | bold italic | numlist bullist outdent indent | alignleft aligncenter alignright | paste pastetext ',
            'menubar' => 'edit format table tools help',
            'height' => 300,
            'pasteimages' => 'true',
            'pastetext' => 'true',
            'conv_img_to_file' => 1,
            'html_disable_str' => ''
        ];
    }

    /**
     * Process Text, make sure to block any possible xss attacks
     *
     * @param string  $p_string    Raw text to process.
     * @param boolean $p_multiline True for multiline text (default), false for single-line.
     *                             Determines which html tags are used.
     *
     * @return string valid formatted text
     */
    private function processText($p_string, $p_multiline = true) {

        $t_string = string_strip_hrefs($p_string);
        if ($p_multiline) {
            $config = array('safe' => 1, 'schemes' => '*:*; src:http, https, data');
            $out_str = htmLawed($p_string, $config);
            $out_str = $p_string;
            return $out_str;
        }
        $t_string = string_html_specialchars($t_string, ENT_NOQUOTES);
        return string_restore_valid_html_tags($t_string, $p_multiline);
    }

    /**
     * Process Bug and Note links
     * @param string  $p_string    Raw text to process.
     *
     * @return string Formatted text
     */
    private function processBugAndNoteLinks($p_string) {

        $t_string = string_process_bug_link($p_string);
        return string_process_bugnote_link($t_string);
    }

    /**
     * Plain text processing.
     *
     * @param string  $p_event     Event name.
     * @param string  $p_string    Raw text to process.
     * @param boolean $p_multiline True for multiline text (default), false for single-line.
     *                             Determines which html tags are used.
     *
     * @return string Formatted text
     *
     * @see $g_html_valid_tags
     * @see $g_html_valid_tags_single_line
     */
    function text($p_event, $p_string, $p_multiline = true) {
        static $s_text;

        if (null === $s_text) {
            $s_text = plugin_config_get('process_text');
        }

        if (ON == $s_text) {

            $t_string = $this->processText($p_string, $p_multiline);

            if ($p_multiline) {
                $t_string = string_preserve_spaces_at_bol($t_string);
            }
            return $t_string;
        } else {
            return $p_string;
        }
    }

    /**
     * Formatted text processing.
     *
     * Performs plain text, URLs, bug links, markdown processing
     *
     * @param string  $p_event     Event name.
     * @param string  $p_string    Raw text to process.
     * @param boolean $p_multiline True for multiline text (default), false for single-line.
     *                             Determines which html tags are used.
     *
     * @return string Formatted text
     */
    function formatted($p_event, $p_string, $p_multiline = true) {
        static $s_text, $s_urls, $s_buglinks, $s_markdown;

        $t_string = $p_string;

        if (null === $s_text) {
            $s_text = plugin_config_get('process_text');
        }

        if (null === $s_urls) {
            $s_urls = plugin_config_get('process_urls');
            $s_buglinks = plugin_config_get('process_buglinks');
        }

        if (null === $s_markdown) {
            $s_markdown = plugin_config_get('process_markdown');
        }

        if (ON == $s_text) {
            if ($p_multiline && OFF == $s_markdown) {
                $t_string = string_preserve_spaces_at_bol($t_string);
            }
            $t_string = $this->nl2br($t_string);
            $t_string = $this->processText($t_string);
        }

        # Process Markdown
        if (ON == $s_markdown) {
            if ($p_multiline) {
                $t_string = MantisMarkdown::convert_text($t_string);
            } else {
                $t_string = MantisMarkdown::convert_line($t_string);
            }
        }

        if (ON == $s_urls && OFF == $s_markdown) {
            $t_string = string_insert_hrefs($t_string);
        }

        if (ON == $s_buglinks) {
            $t_string = $this->processBugAndNoteLinks($t_string);
        }

        $t_string = mention_format_text($t_string, /* html */ true);

        return $t_string;
    }

    /**
     * RSS text processing.
     * @param string $p_event  Event name.
     * @param string $p_string Unformatted text.
     * @return string Formatted text
     */
    function rss($p_event, $p_string) {
        static $s_text, $s_urls, $s_buglinks;

        $t_string = $p_string;

        if (null === $s_text) {
            $s_text = plugin_config_get('process_text');
            $s_urls = plugin_config_get('process_urls');
            $s_buglinks = plugin_config_get('process_buglinks');
        }

        if (ON == $s_text) {
            $t_string = str_replace(">\r\n", '>', $t_string);
            $t_string = str_replace("> \r\n", '>', $t_string);
            $t_string = str_replace("\n", '\+', $t_string);

            $t_string = string_strip_hrefs($t_string);
            $t_string = convert_html_to_text($t_string, true);
            $t_string = str_replace('\+', "\n", $t_string);
        }

        if (ON == $s_urls) {
            $t_string = string_insert_hrefs($t_string);
        }

        if (ON == $s_buglinks) {
            $t_string = string_process_bug_link($t_string, true, false, true);
            $t_string = string_process_bugnote_link($t_string, true, false, true);
        }

        $t_string = mention_format_text($t_string, /* html */ true);

        return $t_string;
    }

    /**
     * Email text processing.
     * @param string $p_event  Event name.
     * @param string $p_string Unformatted text.
     * @return string Formatted text
     */
    function email($p_event, $p_string) {
        static $s_text, $s_buglinks;
        static $s_html_disable;

        $t_string = $p_string;

        if (null === $s_text) {
            $s_text = plugin_config_get('process_text');
            $s_buglinks = plugin_config_get('process_buglinks');
        }

        if (null === $s_html_disable) {
            $s_html_disable = plugin_config_get('html_disable_str', '', false, NO_USER, ALL_PROJECTS);
        }
        if (ON == $s_text) {
            if (empty($s_html_disable) or strpos($t_string, $s_html_disable) === false) { //magis string to disable HTML formatting
                $t_string = str_replace(">\r\n", '>', $t_string);
                $t_string = str_replace("> \r\n", '>', $t_string);
                $t_string = str_replace("\n", '\+', $t_string);

                $t_string = string_strip_hrefs($t_string);
                $t_string = convert_html_to_text($t_string, true);
                $t_string = str_replace('\+', "\n", $t_string);
            }
        }

        if (ON == $s_buglinks) {
            $t_string = string_process_bug_link($t_string, false);
            $t_string = string_process_bugnote_link($t_string, false);
        }

        $t_string = mention_format_text($t_string, /* html */ false);

        return $t_string;
    }

}

/*
 * This is a copy of function bug_get_attachments with disabling attachaments pasted by TinyMCE
 */

function veditor_bug_get_attachments($p_bug_id) {
    db_param_push();
    $t_query = 'SELECT id, title, diskfile, filename, filesize, file_type, date_added, user_id, bugnote_id
		                FROM {bug_file}
		                WHERE bug_id=' . db_param();

#if bug is not moving to another project, disable TinyMCE attachments    
    if (strpos($_SERVER['PHP_SELF'], 'bug_actiongroup.php') === false) {
        $t_query .= " AND title <> '" . IMG_PREFIX . "' ";
    }
    $t_query .= ' ORDER BY date_added';
    $t_db_result = db_query($t_query, array($p_bug_id));

    $t_result = array();

    while ($t_row = db_fetch_array($t_db_result)) {
        $t_result[] = $t_row;
    }

    return $t_result;
}
