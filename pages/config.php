<?php

/**
 * VEditor Configuration Page
 *
 * @package VEditor
 * @copyright Ryszard Pydo
 * @license MIT License
 */

auth_reauthenticate();
access_ensure_global_level(config_get('manage_plugin_threshold'));

layout_page_header(lang_get('plugin_Veditor_title'));

layout_page_begin('manage_overview_page.php');

print_manage_menu('manage_plugin_page.php');

// Get current configuration values
$t_process_text = plugin_config_get('process_text');
$t_process_urls = plugin_config_get('process_urls');
$t_process_buglinks = plugin_config_get('process_buglinks');
$t_process_markdown = plugin_config_get('process_markdown');
$t_language_mapping = plugin_config_get('language_mapping');
$t_pages = plugin_config_get('pages');
$t_access_level = plugin_config_get('access_level');
$t_dev_level = plugin_config_get('dev_level');
$t_dev_plugins = plugin_config_get('dev_plugins');
$t_reporter_plugins = plugin_config_get('reporter_plugins');
$t_dev_toolbar = plugin_config_get('dev_toolbar');
$t_reporter_toolbar = plugin_config_get('reporter_toolbar');
$t_menubar = plugin_config_get('menubar');
$t_height = plugin_config_get('height');
$t_pasteimages = plugin_config_get('pasteimages');
$t_pastetext = plugin_config_get('pastetext');
$t_conv_img_to_file = plugin_config_get('conv_img_to_file');
$t_html_disable_str = plugin_config_get('html_disable_str');
?>

<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>
	<div class="form-container">

		<form id="veditor-config-form" action="<?php echo plugin_page('config_edit') ?>" method="post">
			<?php echo form_security_field('plugin_Veditor_config_edit') ?>

			<div class="widget-box widget-color-blue2">
				<div class="widget-header widget-header-small">
					<h4 class="widget-title lighter">
						<?php print_icon('fa-text-width', 'ace-icon'); ?>
						<?php echo sprintf(
							'%s: %s',
							lang_get('plugin_Veditor_title'),
							lang_get('plugin_Veditor_config')
						) ?>
					</h4>
				</div>
				<div class="widget-body">
					<div class="widget-main no-padding">
						<div class="table-responsive">
							<table class="table table-bordered table-condensed table-striped">

								<!-- Text Processing Options -->
								<tr>
									<th class="category width-40" colspan="3">
										<strong><?php echo lang_get('plugin_Veditor_section_text_processing') ?></strong>
									</th>
								</tr>

								<tr>
									<th class="category width-40">
										<?php echo lang_get('plugin_Veditor_process_text') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_process_text_warning_notice') ?>
										</span>
									</th>
									<td class="center width-20">
										<label>
											<input
												type="radio"
												name="process_text"
												value="1"
												class="ace"
												<?php check_checked($t_process_text, ON) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
									</td>
									<td class="center width-20">
										<label>
											<input
												type="radio"
												name="process_text"
												value="0"
												class="ace"
												<?php check_checked($t_process_text, OFF) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_process_urls') ?>
									</th>
									<td class="center">
										<label>
											<input
												type="radio"
												name="process_urls"
												value="1"
												class="ace"
												<?php check_checked($t_process_urls, ON) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
									</td>
									<td class="center">
										<label>
											<input
												type="radio"
												name="process_urls"
												value="0"
												class="ace"
												<?php check_checked($t_process_urls, OFF) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_process_buglinks') ?>
										<br>
										<span class="small"><?php
															printf(
																lang_get('plugin_Veditor_process_buglinks_info'),
																config_get('bug_link_tag'),
																config_get('bugnote_link_tag')
															);
															?>
										</span>
									</th>
									<td class="center">
										<label>
											<input
												type="radio"
												name="process_buglinks"
												value="1"
												class="ace"
												<?php check_checked($t_process_buglinks, ON) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
									</td>
									<td class="center">
										<label>
											<input
												type="radio"
												name="process_buglinks"
												value="0"
												class="ace"
												<?php check_checked($t_process_buglinks, OFF) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_process_markdown') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_process_markdown_info') ?>
										</span>
									</th>
									<td class="center">
										<label>
											<input
												type="radio"
												name="process_markdown"
												value="1"
												class="ace"
												<?php check_checked($t_process_markdown, ON) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
									</td>
									<td class="center">
										<label>
											<input
												type="radio"
												name="process_markdown"
												value="0"
												class="ace"
												<?php check_checked($t_process_markdown, OFF) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<!-- Editor Configuration -->
								<tr>
									<th class="category" colspan="3">
										<strong><?php echo lang_get('plugin_Veditor_section_editor_config') ?></strong>
									</th>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_menubar') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_menubar_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="text"
											name="menubar"
											value="<?php echo htmlspecialchars($t_menubar) ?>"
											class="input-sm"
											size="60">
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_height') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_height_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="number"
											name="height"
											value="<?php echo htmlspecialchars($t_height) ?>"
											class="input-sm"
											min="100"
											max="2000">
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_pasteimages') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_pasteimages_info') ?>
										</span>
									</th>
									<td colspan="2">
										<label>
											<input
												type="radio"
												name="pasteimages"
												value="true"
												class="ace"
												<?php check_checked($t_pasteimages, 'true') ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
										<label>
											<input
												type="radio"
												name="pasteimages"
												value="false"
												class="ace"
												<?php check_checked($t_pasteimages, 'false') ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_pastetext') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_pastetext_info') ?>
										</span>
									</th>
									<td colspan="2">
										<label>
											<input
												type="radio"
												name="pastetext"
												value="true"
												class="ace"
												<?php check_checked($t_pastetext, 'true') ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
										<label>
											<input
												type="radio"
												name="pastetext"
												value="false"
												class="ace"
												<?php check_checked($t_pastetext, 'false') ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_conv_img_to_file') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_conv_img_to_file_info') ?>
										</span>
									</th>
									<td colspan="2">
										<label>
											<input
												type="radio"
												name="conv_img_to_file"
												value="1"
												class="ace"
												<?php check_checked($t_conv_img_to_file, 1) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_enabled') ?>
											</span>
										</label>
										<label>
											<input
												type="radio"
												name="conv_img_to_file"
												value="0"
												class="ace"
												<?php check_checked($t_conv_img_to_file, 0) ?>>
											<span class="lbl padding-6">
												<?php echo lang_get('plugin_Veditor_disabled') ?>
											</span>
										</label>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_html_disable_str') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_html_disable_str_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="text"
											name="html_disable_str"
											value="<?php echo htmlspecialchars($t_html_disable_str) ?>"
											class="input-sm"
											size="60">
									</td>
								</tr>

								<!-- Access Control -->
								<tr>
									<th class="category" colspan="3">
										<strong><?php echo lang_get('plugin_Veditor_section_access_control') ?></strong>
									</th>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_access_level') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_access_level_info') ?>
										</span>
									</th>
									<td colspan="2">
										<select name="access_level" class="input-sm">
											<?php print_enum_string_option_list('access_levels', (int)$t_access_level) ?>
										</select>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_dev_level') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_dev_level_info') ?>
										</span>
									</th>
									<td colspan="2">
										<select name="dev_level" class="input-sm">
											<?php print_enum_string_option_list('access_levels', (int)$t_dev_level) ?>
										</select>
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_pages') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_pages_info') ?>
										</span>
									</th>
									<td colspan="2">
										<textarea
											name="pages"
											class="input-sm"
											rows="5"
											cols="60"><?php echo htmlspecialchars(implode("\n", $t_pages)) ?></textarea>
									</td>
								</tr>

								<!-- Role-Based Configuration -->
								<tr>
									<th class="category" colspan="3">
										<strong><?php echo lang_get('plugin_Veditor_section_role_config') ?></strong>
									</th>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_dev_plugins') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_dev_plugins_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="text"
											name="dev_plugins"
											value="<?php echo htmlspecialchars($t_dev_plugins) ?>"
											class="input-sm"
											size="60">
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_reporter_plugins') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_reporter_plugins_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="text"
											name="reporter_plugins"
											value="<?php echo htmlspecialchars($t_reporter_plugins) ?>"
											class="input-sm"
											size="60">
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_dev_toolbar') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_dev_toolbar_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="text"
											name="dev_toolbar"
											value="<?php echo htmlspecialchars($t_dev_toolbar) ?>"
											class="input-sm"
											size="80">
									</td>
								</tr>

								<tr>
									<th class="category">
										<?php echo lang_get('plugin_Veditor_reporter_toolbar') ?>
										<br>
										<span class="small">
											<?php echo lang_get('plugin_Veditor_reporter_toolbar_info') ?>
										</span>
									</th>
									<td colspan="2">
										<input
											type="text"
											name="reporter_toolbar"
											value="<?php echo htmlspecialchars($t_reporter_toolbar) ?>"
											class="input-sm"
											size="80">
									</td>
								</tr>

							</table>
						</div>
					</div>
					<div class="widget-toolbox padding-8 clearfix">
						<input
							type="submit" class="btn btn-primary btn-white btn-round"
							value="<?php echo lang_get('change_configuration') ?>">
					</div>
				</div>
			</div>
		</form>
	</div>
</div>

<?php
layout_page_end();
