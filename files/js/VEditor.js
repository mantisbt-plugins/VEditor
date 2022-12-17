/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Other/javascript.js to edit this template
 */


var tinycfg = document.getElementById('configTinyMCE').dataset;
//    skin: (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oxide-dark' : 'oxide'),
//    content_css: (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'default'),

let tiny_skin = ( tinycfg.dark == 1 ? 'oxide-dark' : 'oxide');
let tiny_css = ( tinycfg.dark == 1 ? 'dark' : 'default');

tinymce.init({
    selector: 'textarea',
    min_height: parseInt(tinycfg.height),
    language: tinycfg.lang,
    menubar: tinycfg.menubar,
    plugins: tinycfg.plugins,
    link_default_target: '_blank',
    paste_data_images: tinycfg.pasteimages  == "true",
    paste_as_text: tinycfg.pastetext  == "true",
//    paste_enable_default_filters: false,
    branding: false,
    toolbar: tinycfg.toolbar,
    preformatted: false,
    forced_root_block: 'div',
    force_br_newlines: false,
    convert_newlines_to_brs: false,
    remove_linebreaks: true,
    browser_spellcheck: true,
    skin: tiny_skin,
    content_css: tiny_css,
    promotion: false,
    setup: function (editor) {
        editor.on('change', function () {
            tinymce.triggerSave();
        });
    },
    init_instance_callback: function (editor) {
        let editorH = editor.editorContainer.offsetHeight;
        $('#description')
                .css({
                    'position': 'absolute',
                    'height': editorH,
                    'width': '40px'
                })
                .show();
    }
});
