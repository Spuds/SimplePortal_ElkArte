<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */


function template_categories_edit()
{
	global $context, $scripturl, $txt;

	template_show_error('category_errors');

	echo '
	<div id="sp_edit_category">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=portalcategories;sa=edit" method="post" accept-charset="UTF-8" onsubmit="submitonce(this);">
			<h3 class="category_header">
				', $context['page_title'], '
			</h3>
				<div class="sp_content_padding">
					<dl class="sp_form">
						<dt>
							<label for="category_name">', $txt['sp_admin_categories_col_name'], ':</label>
						</dt>
						<dd>
							<input type="text" name="category_name" id="category_name" value="', $context['category']['name'], '" class="input_text" />
						</dd>
						<dt>
							<label for="category_namespace">', $txt['sp_admin_categories_col_namespace'], ':</label>
						</dt>
						<dd>
							<input type="text" name="category_namespace" id="category_namespace" value="', $context['category']['namespace'], '" class="input_text" />
							<span class="smalltext">', $txt['sp_admin_namespace_requirements'], '</span>
						</dd>
						<dt>
							<label for="category_description">', $txt['sp_admin_categories_col_description'], ':</label>
						</dt>
						<dd>
							<textarea class="sp_content" name="category_description" id="category_description" rows="5" cols="45">', $context['category']['description'], '</textarea>
						</dd>
						<dt>
							<label for="category_permissions">', $txt['sp_admin_categories_col_permissions'], ':</label>
						</dt>
						<dd>
							<select name="category_permissions" id="category_permissions">';

	foreach ($context['category']['permission_profiles'] as $profile)
		echo '
								<option value="', $profile['id'], '"', $profile['id'] == $context['category']['permissions'] ? ' selected="selected"' : '', '>', $profile['label'], '</option>';

	echo '
							</select>
						</dd>
						<dt>
							<label for="category_status">', $txt['sp_admin_categories_col_status'], ':</label>
						</dt>
						<dd>
							<input type="checkbox" name="category_status" id="category_status" value="1"', $context['category']['status'] ? ' checked="checked"' : '', ' class="input_check" />
						</dd>
					</dl>
					<div class="submitbutton">
						<input type="submit" name="submit" value="', $context['page_title'], '" />
						<input type="hidden" name="category_id" value="', $context['category']['id'], '" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
			</div>
		</form>
	</div>';
}
