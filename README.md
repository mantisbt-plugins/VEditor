
# VEditor

VEditor is plugin for MantisBT using TinyMCE as  bugnote editor.
This allows you to paste screensots and use html code, e.g. bold text, colors or tables. It replace default textarea in bug reporting.  This plugin replace standard MantisCoreFormatting.


## Features

- Enables you to paste a sceneshot directly from the clipboard
- Converts pasted images as issue attachments 
- Allows rich formatting of text and tables (HTML), including MantisBT css classes
- Enables TinyMCE in comments, issue descriptions and custom fields (memo)
- Support for TinyMCE plugins (separate set for developer and reporter)
- Support for Light/dark mode
- Multi-language support

This plugin requires MantisBT 2.1.0. It was tested on 2.26.1, PHP 8.3.X 

## Installation

1. Download and unzip the plugin files to your computer
2. Upload the plugin directory and the files into <yourMantisRoot>/plugins. Directory should be named VEditor (remove version number) and contains VEditor.php file
3. In MantisBT go to page Manage > Manage Plugins. You will see a list of installed and currently not installed plugins
4. Click the **Uninstall** button to uninstall standard MantisBT Formatting  plugin
5. Click the Install button to install VEditor plugin.

### Update config_inc.php
If you want paste images from clipboard you should enable blob(base64) images in your content security policy  
In your config_inc.php add line


```php
$g_custom_headers = array( 'Content-Security-Policy: ' . "default-src *; img-src 'self' blob: data:; script-src 'self'; style-src  'self' 'unsafe-inline' *"  );
```

### Update bug_api.php
If you want to save images as bug attachments (recommended) you should patch MantisBT code.
It is required for hide files generated from TinyMCE.
Open core/bug_api.php file and find bug_get_attachments function.  
Add marked lines at the beginning code (around 1900 line code)

```php
function bug_get_attachments( $p_bug_id ) {
	$p_bug_id = (int)$p_bug_id;

	global $g_cache_bug_attachments;
	if( isset( $g_cache_bug_attachments[$p_bug_id] ) ) {
		return $g_cache_bug_attachments[$p_bug_id];
	}

#VEditor begin
        if (function_exists('veditor_bug_get_attachments')) {
           return veditor_bug_get_attachments($p_bug_id);
        }
#VEditor end
    
	db_param_push();
```    
## Configuration

See config() method for plugin default configuration.


## Authors

- [Ryszard Pydo](https://www.github.com/pysiek634)


## License

This plugin is licenced under [MIT](https://choosealicense.com/licenses/mit/)

