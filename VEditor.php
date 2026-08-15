<?php

/**
 * VEditor - TinyMCE WYSIWYG Editor Plugin for MantisBT
 *
 * This plugin replaces the default MantisCoreFormatting with TinyMCE editor,
 * providing rich text editing capabilities including image paste support,
 * automatic base64-to-file conversion, and enhanced formatting options.
 *
 * @copyright  Ryszard Pydo
 * @license    MIT License
 * @package    VEditor
 * @since      1.0.0
 */
require_once('html2text.php');
require_api('mention_api.php');
require_once(config_get('plugin_path') . 'MantisCoreFormatting' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'MantisMarkdown.php');
require_once('htmLawed/htmLawed.php');

/**
 * VEditor Plugin - Main plugin class
 *
 * Extends MantisFormattingPlugin to provide TinyMCE integration with:
 * - Rich text editing
 * - Image paste and auto-conversion to attachments
 * - Multi-language support
 * - Role-based toolbar configuration
 * - XSS protection via htmLawed
 */
class VEditorPlugin extends MantisFormattingPlugin
{

    const IMG_PREFIX = 'Pasted by TinyMCE';
    const MIN_TEXT_AREA_SIZE = 1048576; // 1 MB
    const DEFAULT_EDITOR_HEIGHT = 300;
    const IMAGE_SEARCH = '/\ssrc="data:[\w\/]+;base64,([\w\/\+\=]+)"/mi';

    private ?string $lastUrl = null;
    private bool $editorOk = false;

    /**
     * Register plugin metadata
     *
     * @return void
     */
    public function register(): void
    {
        $this->name = lang_get('plugin_Veditor_title');
        $this->description = lang_get('plugin_Veditor_description');
        $this->page = 'config';
        $this->version = '1.5.0';
        $this->requires = ['MantisCore' => '2.27.0'];
        $this->author = 'Ryszard Pydo';
        $this->contact = 'pysiek634 on github.com';
        $this->url = 'https://github.com/pysiek634/VEditor.git';
    }

    /**
     * Initialize plugin and extend textarea size limit for images
     *
     * The default MantisBT limit (64 KB) is too small for base64-encoded images.
     * This plugin extends it to 1 MB to support image paste functionality.
     *
     * @return bool True on success
     */
    public function init(): bool
    {
        global $g_max_textarea_length;

        #force increase of max textarea length to support base64 images, if set to lower value in config
        if (isset($g_max_textarea_length) && $g_max_textarea_length < self::MIN_TEXT_AREA_SIZE) {
            $g_max_textarea_length = self::MIN_TEXT_AREA_SIZE;
        }
        return true;
    }

    /**
     * Declare event hooks
     *
     * @return array Array of event hooks
     */
    function hooks()
    {
        $myHooks = [
            'EVENT_LAYOUT_RESOURCES' => 'loadWysiwyg',
            'EVENT_LAYOUT_BODY_END' => 'startEditor',
            'EVENT_BUGNOTE_ADD' => 'handleBugnoteEdit',
            'EVENT_BUGNOTE_EDIT' => 'handleBugnoteEdit',
            'EVENT_UPDATE_BUG' => 'handleBugUpdate',
            'EVENT_REPORT_BUG' => 'handleBugReport',
        ];
        return array_merge(parent::hooks(), $myHooks);
    }

    /**
     * Default plugin configuration
     *
     * @return array Configuration array
     */
    public function config()
    {
        return [
            'process_text' => ON,
            'process_urls' => ON,
            'process_buglinks' => ON,
            'process_markdown' => OFF,

            #TinyMCE language codes mapping - maps MantisBT language codes to TinyMCE language files            
            'language_mapping' => [
                'english' => 'en',
                'french' => 'fr-FR',
                'german' => 'de',
                'polish' => 'pl',
                'spanish' => 'es'
            ],
            'pages' => [
                'bugnote_edit_page.php',
                'view.php',
                'bug_update_page.php',
                'bug_report_page.php',
                'bug_change_status_page.php'
            ],
            'access_level' => REPORTER,
            'dev_level' => DEVELOPER,
            'dev_plugins' => 'table searchreplace lists code image',
            'reporter_plugins' => 'table searchreplace lists',
            'dev_toolbar' => 'undo redo | styles | bold italic | numlist bullist outdent indent | alignleft aligncenter alignright | paste pastetext | code',
            'reporter_toolbar' => 'undo redo | styles | bold italic | numlist bullist outdent indent | alignleft aligncenter alignright | paste pastetext',
            'menubar' => 'edit format table tools help',
            'height' => self::DEFAULT_EDITOR_HEIGHT,
            'pasteimages' => 'true',
            'pastetext' => 'true',
            'conv_img_to_file' => ON,
            'html_disable_str' => ''
        ];
    }


    /**
     * Plugin installation validation
     *
     * @return bool True if installation can proceed
     */
    public function install(): bool
    {
        if (plugin_is_installed('MantisCoreFormatting')) {
            error_parameters('MantisCoreFormatting');
            trigger_error(ERROR_PLUGIN_ALREADY_INSTALLED, ERROR);
            return false;
        }
        return true;
    }

    /**
     * Plugin uninstallation validation
     *
     * @return bool True if uninstallation can proceed
     */
    public function uninstall(): bool
    {
        if (!plugin_is_installed('MantisCoreFormatting')) {
            error_parameters('MantisCoreFormatting');
            trigger_error(ERROR_PLUGIN_NOT_REGISTERED, ERROR);
            return false;
        }
        return true;
    }

    /**
     * Handle bug report event - process base64 images
     *
     * @param string $event Event name
     * @param object $bug Bug data object
     * @param int $bugId Bug ID
     * @return void
     */
    public function handleBugReport(string $event, object $bug, int $bugId): void
    {
        $this->processBugTextFields($bug);
    }

    /**
     * Handle bug update event - process base64 images
     *
     * @param string $event Event name
     * @param object $existingBug Existing bug data
     * @param object $bug New bug data
     * @return void
     */
    public function handleBugUpdate(string $event, object $existingBug, object $bug): void
    {
        $this->processBugTextFields($bug);
    }

    /**
     * Process bug text fields (description, steps, additional info)
     * Convert base64 images to file attachments
     *
     * @param object $bug Bug object with id, description, steps_to_reproduce, additional_information
     * @return void
     */
    private function processBugTextFields(object $bug): void
    {
        if (plugin_config_get('conv_img_to_file', 0) === 0) {
            return;
        }

        [$descUpdated, $description] = $this->parseNoteText($bug->id, $bug->description);
        [$stepsUpdated, $stepsToReproduce] = $this->parseNoteText($bug->id, $bug->steps_to_reproduce);
        [$infoUpdated, $additionalInfo] = $this->parseNoteText($bug->id, $bug->additional_information);

        if ($descUpdated || $stepsUpdated || $infoUpdated) {
            db_param_push();
            $bugTextId = bug_get_field($bug->id, 'bug_text_id');
            $query = 'UPDATE {bug_text}
                SET description = ' . db_param() . ',
                    steps_to_reproduce = ' . db_param() . ',
                    additional_information = ' . db_param() . '
                WHERE id = ' . db_param();
            db_query($query, [$description, $stepsToReproduce, $additionalInfo, $bugTextId]);
            bug_text_clear_cache($bug->id);
        }

        $this->processCustomFields($bug->id);
    }

    /**
     * Process custom fields for base64 images
     *
     * @param int $bugId Bug ID
     * @return void
     */
    private function processCustomFields(int $bugId): void
    {
        $query = 'SELECT * FROM {custom_field_string} WHERE bug_id = ' . db_param() . ' AND text IS NOT NULL';
        $result = db_query($query, [$bugId]);

        while ($row = db_fetch_array($result)) {
            [$updated, $note] = $this->parseNoteText($bugId, $row['text']);
            if ($updated) {
                $updateQuery = 'UPDATE {custom_field_string}
                    SET text = ' . db_param() . '
                    WHERE field_id = ' . db_param() . ' AND bug_id = ' . db_param();
                db_query($updateQuery, [$note, $row['field_id'], $bugId]);
            }
        }
    }

    /**
     * Handle bugnote add/edit events - process base64 images
     *
     * @param string $event Event name
     * @param int $bugId Bug ID
     * @param int $bugnoteId Bugnote ID
     * @param mixed $files Files (optional)
     * @return void
     */
    public function handleBugnoteEdit(string $event, int $bugId, int $bugnoteId, $files = null): void
    {
        if (plugin_config_get('conv_img_to_file', 0) === 0) {
            return;
        }
        $text = bugnote_get_text($bugnoteId);
        $this->updateBugnoteImages($bugId, $bugnoteId, $text);
    }

    /**
     * Parse note text and convert base64 images to file attachments
     *
     * @param int $bugId Bug ID
     * @param string $noteText Note text to process
     * @param int $bugnoteId Bugnote ID (0 for bug description)
     * @return array [bool updated, string processedText]
     */
    private function parseNoteText(int $bugId, string $noteText, int $bugnoteId = 0): array
    {
        $updated = false;

        if (empty($noteText)) {
            return [$updated, $noteText];
        }

        $note = preg_replace_callback(
            self::IMAGE_SEARCH,
            function (array $matches) use ($bugId, $bugnoteId, &$updated): string {
                $base64Data = $matches[1];
                $fileId = $this->saveAsBugAttachment($bugId, $bugnoteId, $base64Data);
                if ($fileId) {
                    $updated = true;
                    return ' src="file_download.php?type=bug&file_id=' . $fileId . '"';
                }
                return $matches[0];
            },
            $noteText
        );

        return [$updated, $note];
    }

    /**
     * Update bugnote with converted image links
     *
     * @param int $bugId Bug ID
     * @param int $bugnoteId Bugnote ID
     * @param string $bugnoteText Bugnote text
     * @return void
     */
    private function updateBugnoteImages(int $bugId, int $bugnoteId, string $bugnoteText): void
    {
        [$updated, $note] = $this->parseNoteText($bugId, $bugnoteText, $bugnoteId);
        if ($updated) {
            $bugnoteTextId = bugnote_get_field($bugnoteId, 'bugnote_text_id');
            db_param_push();
            $query = 'UPDATE {bugnote_text} SET note = ' . db_param() . ' WHERE id = ' . db_param();
            db_query($query, [$note, $bugnoteTextId]);
        }
    }

    /**
     * Save base64 image as bug attachment
     *
     * @param int $bugId Bug ID
     * @param int $bugnoteId Bugnote ID
     * @param string $base64String Base64 encoded image data
     * @return int|false File ID on success, false on failure
     */
    private function saveAsBugAttachment(int $bugId, int $bugnoteId, string $base64String): int|false
    {
        $file = $this->convertBase64ToTempFile($base64String);

        if (isset($file['tmp_name'])) {
            $fileInfo = file_add(
                $bugId,
                $file,
                'bug',
                self::IMG_PREFIX,
                '',
                null,
                0,
                true,
                $bugnoteId
            );
            return $fileInfo['id'];
        }
        return false;
    }

    /**
     * Convert base64 string to temporary file
     *
     * @param string $base64String Base64 encoded data
     * @return array File array with tmp_name, size, browser_upload, name
     */
    private function convertBase64ToTempFile(string $base64String): array
    {
        $file = [];

        if (empty($base64String)) {
            return $file;
        }

        $rawContent = base64_decode($base64String, true);
        if ($rawContent === false) {
            return $file;
        }

        // Use tempnam() for atomic temp file creation (avoids TOCTOU race condition)
        $tempFile = tempnam(sys_get_temp_dir(), 'mantisbt-file');
        file_put_contents($tempFile, $rawContent);

        // Detect mime type to assign the correct extension
        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $tempFile);
            if ($detected !== false) {
                $mimeType = $detected;
            }
            // finfo_close($finfo); only for php version <= 7.4
        } elseif (function_exists('mime_content_type')) {
            $detected = mime_content_type($tempFile);
            if ($detected !== false) {
                $mimeType = $detected;
            }
        }

        $extensions = [
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'image/svg+xml' => '.svg',
        ];
        $ext = $extensions[$mimeType] ?? '';

        $file['tmp_name'] = $tempFile;
        $file['size'] = filesize($tempFile);
        $file['browser_upload'] = false;
        $file['name'] = self::IMG_PREFIX . $ext;

        return $file;
    }

    /**
     * Get TinyMCE configuration based on user access level
     *
     * @return array TinyMCE configuration array
     */
    private function getTinyMCEConfig(): array
    {
        $config = [];
        $currentLang = lang_get_current();
        $langMapping = plugin_config_get('language_mapping', []);
        $config['lang'] = $langMapping[$currentLang] ?? 'en';

        $config['menubar'] = plugin_config_get('menubar', '');
        $devLevel = plugin_config_get('dev_level');

        if (access_get_project_level() < $devLevel) {
            $config['plugins'] = plugin_config_get('reporter_plugins', '');
            $config['toolbar'] = plugin_config_get('reporter_toolbar', '');
        } else {
            $config['plugins'] = plugin_config_get('dev_plugins', '');
            $config['toolbar'] = plugin_config_get('dev_toolbar', '');
        }

        $config['height'] = plugin_config_get('height', self::DEFAULT_EDITOR_HEIGHT);
        $config['pasteimages'] = plugin_config_get('pasteimages', 'true');
        $config['pastetext'] = plugin_config_get('pastetext', 'true');

        return $config;
    }

    /**
     * Start editor - inject configuration and JavaScript
     *
     * @param string $event Event name
     * @return void
     */
    public function startEditor(string $event): void
    {
        if (!$this->isEditorAllowed()) {
            return;
        }

        $config = $this->getTinyMCEConfig();
        $darkMode = config_get('plugin_MantisBTModernDarkTheme_enabled', 0);

        echo '<wysiwyg id="configTinyMCE" ';
        echo 'data-lang="' . $config['lang'] . '" ';
        echo 'data-plugins="' . htmlspecialchars($config['plugins']) . '" ';
        echo 'data-toolbar="' . htmlspecialchars($config['toolbar']) . '" ';
        echo 'data-menubar="' . htmlspecialchars($config['menubar']) . '" ';
        echo 'data-height="' . $config['height'] . '" ';
        echo 'data-pasteimages="' . $config['pasteimages'] . '" ';
        echo 'data-pastetext="' . $config['pastetext'] . '" ';
        echo 'data-dark="' . $darkMode . '"';
        echo '</wysiwyg>';

        $jsPath = plugin_file('js/VEditor.js');
        $jsFilePath = plugin_file_path('js/VEditor.js', plugin_get_current());
        $cacheKey = md5(filemtime($jsFilePath));
        echo '<script src="' . $jsPath . '&KEY=' . $cacheKey . '" referrerpolicy="origin"></script>';
    }

    /**
     * Check if editor is allowed for current user and page
     *
     * @return bool True if editor should be loaded
     */
    private function isEditorAllowed(): bool
    {
        if (!auth_is_user_authenticated()) {
            return false;
        }

        $currentUrl = $this->getCurrentUrl();

        if (!isset($this->lastUrl) || $this->lastUrl !== $currentUrl) {
            $this->lastUrl = $currentUrl;
            $this->editorOk = false;

            $allowedPages = plugin_config_get('pages', []);
            foreach ($allowedPages as $page) {
                if (strpos($currentUrl, $page) !== false) {
                    $this->editorOk = true;
                    break;
                }
            }

            if ($this->editorOk) {
                $accessLevel = plugin_config_get('access_level', REPORTER);
                if (access_get_project_level() < $accessLevel) {
                    $this->editorOk = false;
                }
            }
        }

        return $this->editorOk;
    }

    /**
     * Get current request URI safely
     *
     * @return string Current request URI or empty string
     */
    private function getCurrentUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '';
    }

    /**
     * Load TinyMCE library
     *
     * @param string $event Event name
     * @return void
     */
    public function loadWysiwyg(string $event): void
    {
        if ($this->isEditorAllowed()) {
            $expectedHash = md5(filemtime(plugin_file_path('js/tinymce/tinymce.min.js')));
            echo '<script src="' . plugin_file('js/tinymce/tinymce.min.js') . '&KEY=' . $expectedHash . '" referrerpolicy="origin"></script>';
        }
    }

    /**
     * Convert newlines to <br> tags for non-HTML content
     *
     * @param string $string Input string
     * @return string Processed string
     */
    private function convertNewlinesToBr(string $string): string
    {
        if (preg_match('/^<\w+>.*/', $string) !== 1) {
            return string_nl2br($string);
        }
        return $string;
    }

    /**
     * Normalize HTML line breaks for text processing
     *
     * @param string $text Input text
     * @return string Normalized text
     */
    private function normalizeLineBreaks(string $text): string
    {
        return str_replace([">\r\n", "> \r\n", "\n"], ['>', '>', '\+'], $text);
    }


    /**
     * Process text and sanitize to block XSS attacks
     *
     * @param string $string Raw text to process
     * @param bool $multiline True for multiline text (default), false for single-line
     * @return string Sanitized formatted text
     */
    private function processText(string $string, bool $multiline = true): string
    {
        if ($multiline) {
            $config = ['safe' => 1, 'schemes' => '*:*; src:http, https, data'];
            return htmLawed($string, $config);
        }

        $processed = string_html_specialchars($string);
        return string_restore_valid_html_tags($processed, $multiline);
    }

    /**
     * Process bug and note links in text
     *
     * @param string $string Raw text to process
     * @return string Formatted text with links
     */
    private function processBugAndNoteLinks(string $string): string
    {
        $processed = string_process_bug_link($string);
        return string_process_bugnote_link($processed);
    }

    /**
     * Plain text processing
     *
     * @param string $event Event name
     * @param string $string Raw text to process
     * @param bool $multiline True for multiline text (default), false for single-line
     * @return string Formatted text
     */
    #[\Override]
    public function text($event, $string, $multiline = true): string
    {
        static $processText = null;

        if ($processText === null) {
            $processText = plugin_config_get('process_text');
        }

        if ($processText === ON) {
            $result = $this->processText($string, $multiline);

            if ($multiline) {
                $result = string_preserve_spaces_at_bol($result);
            }
            return $result;
        }

        return $string;
    }

    /**
     * Formatted text processing
     *
     * Performs plain text, URLs, bug links, and markdown processing
     *
     * @param string $p_event Event name
     * @param string $p_string Raw text to process
     * @param bool $p_multiline True for multiline text (default), false for single-line
     * @return string Fully formatted text
     */
    #[\Override]
    function formatted($p_event, $p_string, $p_multiline = true)
    {
        static $s_text, $s_urls, $s_buglinks, $s_markdown;

        $t_string = $p_string;

        if (null === $s_urls) {
            $s_urls = plugin_config_get('process_urls');
            $s_buglinks = plugin_config_get('process_buglinks');
        }

        if (null === $s_markdown) {
            $s_markdown = plugin_config_get('process_markdown');
        }

        # Parse input and return finished HTML markup, no further processing.
        if (ON == $s_markdown) {
            return MantisMarkdown::getInstance($s_urls, $s_buglinks)->convert($t_string, $p_multiline);
        }

        if (null === $s_text) {
            $s_text = plugin_config_get('process_text');
        }

        if (ON == $s_text) {
            if ($p_multiline) {
                $t_string = string_preserve_spaces_at_bol($t_string);
            }
            $t_string = $this->convertNewlinesToBr($t_string); //replace string_nl2br with custom method to avoid converting newlines in HTML tags            
            $t_string = $this->processText($t_string, true);
        }

        if (ON == $s_urls) {
            $t_string = string_insert_hrefs($t_string);
        }

        if (ON == $s_buglinks) {
            $t_string = $this->processBugAndNoteLinks($t_string);
        }

        return mention_format_text($t_string, /* html */ true);
    }

    /**
     * RSS text processing
     *
     * Converts HTML to plain text for RSS feeds
     *
     * @param string $event Event name
     * @param string $string Unformatted text
     * @return string Formatted text
     */
    public function rss($event, $string): string
    {
        static $processText = null;
        static $processUrls = null;
        static $processBuglinks = null;

        $result = $string;

        if ($processText === null) {
            $processText = plugin_config_get('process_text');
            $processUrls = plugin_config_get('process_urls');
            $processBuglinks = plugin_config_get('process_buglinks');
        }

        if ($processText === ON) {
            $result = $this->normalizeLineBreaks($result);
            $result = string_strip_hrefs($result);
            $result = convert_html_to_text($result, true);
            $result = str_replace('\+', "\n", $result);
        }

        if ($processUrls === ON) {
            $result = string_insert_hrefs($result);
        }

        if ($processBuglinks === ON) {
            $result = string_process_bug_link($result, true, false, true);
            $result = string_process_bugnote_link($result, true, false, true);
        }

        $result = mention_format_text($result, true);

        return $result;
    }

    /**
     * Email text processing
     *
     * Converts HTML to plain text for email notifications
     *
     * @param string $event Event name
     * @param string $string Unformatted text
     * @return string Formatted text
     */
    public function email($event, $string): string
    {
        static $processText = null;
        static $processBuglinks = null;
        static $htmlDisableStr = null;

        $result = $string;

        if ($processText === null) {
            $processText = plugin_config_get('process_text');
            $processBuglinks = plugin_config_get('process_buglinks');
        }

        if ($htmlDisableStr === null) {
            $htmlDisableStr = plugin_config_get('html_disable_str', '', false, NO_USER, ALL_PROJECTS);
        }

        if ($processText === ON) {
            if (empty($htmlDisableStr) || strpos($result, $htmlDisableStr) === false) {
                $result = $this->normalizeLineBreaks($result);
                $result = string_strip_hrefs($result);
                $result = convert_html_to_text($result, true);
                $result = str_replace('\+', "\n", $result);
            }
        }

        if ($processBuglinks === ON) {
            $result = string_process_bug_link($result, false);
            $result = string_process_bugnote_link($result, false);
        }

        $result = mention_format_text($result, false);

        return $result;
    }
}

/**
 * Get bug attachments excluding TinyMCE pasted images
 *
 * This is a modified version of bug_get_attachments() that excludes
 * images pasted via TinyMCE (except during project move operations)
 *
 * @param int $bugId Bug ID
 * @return array Array of attachment data
 */
function veditor_bug_get_attachments(int $bugId): array
{
    db_param_push();
    $query = 'SELECT id, title, diskfile, filename, filesize, file_type, date_added, user_id, bugnote_id
        FROM {bug_file}
        WHERE bug_id = ' . db_param();

    $params = [$bugId];

    // Exclude TinyMCE pasted attachments unless moving to another project
    if (strpos($_SERVER['PHP_SELF'], 'bug_actiongroup.php') === false) {
        $query .= ' AND title <> ' . db_param();
        $params[] = VEditorPlugin::IMG_PREFIX;
    }
    $query .= ' ORDER BY date_added';

    $dbResult = db_query($query, $params);

    $result = [];
    while ($row = db_fetch_array($dbResult)) {
        $result[] = $row;
    }

    return $result;
}
