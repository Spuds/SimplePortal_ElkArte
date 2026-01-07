<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use ElkArte\Cache\Cache;
use ElkArte\Database\QueryInterface;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Theme Selection Block, Displays themes available for user selection
 *
 * @param array $parameters not used in this block
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class ThemeSelectBlock extends SPAbstractBlock
{
	/**
	 * Constructor, used to define block parameters
	 *
	 * @param QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		parent::__construct($db);
	}

	/**
	 * Initializes a block for use.
	 *
	 * - Called from portal.subs as part of the sportal_load_blocks process
	 *
	 * @param array $parameters
	 * @param int $id
	 */
	public function setup($parameters, $id)
	{
		global $settings, $txt;

		// Going to need some help to pick themes
		Txt::load('Profile');
		Txt::load('ManageThemes');
		require_once(SUBSDIR . '/Themes.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');

		if (!empty($_SESSION['id_theme']) && (!empty($this->_modSettings['theme_allow']) || allowedTo('admin_forum')))
		{
			$current_theme = (int) $_SESSION['id_theme'];
		}
		else
		{
			$current_theme = (int) User::$info->theme;
		}

		// Load in all the themes in the system
		$current_theme = empty($current_theme) ? -1 : $current_theme;
		[$available_themes, $guest_theme] = availableThemes($current_theme, User::$info->id);

		if ($guest_theme !== 0)
		{
			$available_themes[-1] = $available_themes[$guest_theme];
		}

		$available_themes[-1]['id'] = -1;
		$available_themes[-1]['name'] = $txt['theme_forum_default'];
		$available_themes[-1]['selected'] = $current_theme === 0;
		$available_themes[-1]['description'] = $txt['theme_global_description'];

		$current_images_url = $settings['images_url'];

		foreach ($available_themes as $id_theme => $theme_data)
		{
			if ($id_theme === 0)
			{
				continue;
			}

			// Set the name, keep it short so it does not break our list
			$available_themes[$id_theme]['name'] = preg_replace('~\stheme$~i', '', $theme_data['name']);
			if (Util::strlen($available_themes[$id_theme]['name']) > 18)
			{
				$available_themes[$id_theme]['name'] = Util::substr($available_themes[$id_theme]['name'], 0, 18) . '&hellip;';
			}
		}

		$settings['images_url'] = $current_images_url;

		ksort($available_themes);

		// Validate the selected theme id.
		if (!array_key_exists($current_theme, $available_themes))
		{
			$current_theme = -1;
			$available_themes[-1]['selected'] = true;
		}

		if (!empty($_POST['sp_ts_submit'])
			&& !empty($_POST['theme'])
			&& isset($available_themes[$_POST['theme']])
			&& (!empty($this->_modSettings['theme_allow']) || allowedTo('admin_forum')))
		{
			if (!empty($_POST['sp_ts_permanent']))
			{
				checkSession();

				$theme_id = $_POST['theme'] == -1 ? 0 : (int) $_POST['theme'];
				updateMemberData(User::$info->id, ['id_theme' => $theme_id]);

				if (!empty($_POST['vrt']))
				{
					updateThemeOptions([$theme_id, User::$info->id, 'theme_variant', Util::htmlspecialchars($_POST['vrt'])]);
					Cache::instance()->remove('theme_settings-' . $theme_id . ':' . User::$info->id);
					$_SESSION['id_variant'] = 0;
				}
			}
			else
			{
				$variant = Util::htmlspecialchars($_POST['vrt']);
				$_SESSION['id_variant'] = $variant;
			}
		}

		$this->data['available_themes'] = $available_themes;
		$this->data['current_theme'] = $current_theme;
		$this->setTemplate('template_sp_theme_select');
	}
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_theme_select($data)
{
	global $txt, $scripturl, $context;
	//<a class="linkbutton" href="http://192.168.99.90/fresh20/index.php?action=profile;area=pick;u=1;theme=0;wwdzLl89C=YiZntlasfQvnQSHSmKmvuPw16qkNENOI;variant=dark"
	// id="theme_preview_0">Preview theme</a>

	echo '
		<form action="', $scripturl, '" method="post" accept-charset="UTF-8">
			<div class="centertext">
				<select name="theme" id="sp_ts_theme" onchange="sp_theme_select(this)">';

	foreach ($data['available_themes'] as $theme)
	{
		echo '
					<option value="', $theme['id'], '"', $theme['id'] == $data['current_theme'] ? ' selected="selected"' : '', '>', $theme['name'], '</option>';
	}

	echo '
				</select>
				<div id="sp_ts_variant_container"', empty($data['available_themes'][$data['current_theme']]['variants']) ? ' style="display: none;"' : '', '>
					<br />
					<select name="vrt" id="sp_ts_variant" onchange="sp_variant_select(this)">';

	if (!empty($data['available_themes'][$data['current_theme']]['variants']))
	{
		foreach ($data['available_themes'][$data['current_theme']]['variants'] as $v_id => $variant)
		{
			echo '
						<option value="', $v_id, '"', $v_id == $data['available_themes'][$data['current_theme']]['selected_variant'] ? ' selected="selected"' : '', '>', $variant['label'], '</option>';
		}
	}

	echo '
					</select>
				</div>
				<br />
				<img class="avatar" src="', $data['available_themes'][$data['current_theme']]['thumbnail_href'], '" alt="', $data['available_themes'][$data['current_theme']]['name'], '" id="sp_ts_thumb" />
				<br />
				<input type="checkbox" class="input_check" name="sp_ts_permanent" value="1" /> ', $txt['sp-theme_permanent'], '
				<br />
				<input type="submit" name="sp_ts_submit" value="', $txt['sp-theme_change'], '" class="button_submit" />
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />

		</form>';

	$javascript = '
		var sp_ts_themes = ' . json_encode($data['available_themes']) . ';

		function sp_theme_select(theme_select)
		{
			var theme_id = theme_select.value,
				variant_container = document.getElementById("sp_ts_variant_container"),
				variant_select = document.getElementById("sp_ts_variant"),
				thumb = document.getElementById("sp_ts_thumb");

			thumb.src = sp_ts_themes[theme_id].thumbnail_href;

			while (variant_select.options.length > 0)
			{
				variant_select.remove(0);
			}

			if (sp_ts_themes[theme_id].variants)
			{
				variant_container.style.display = "";
				for (var v_id in sp_ts_themes[theme_id].variants)
				{
					var option = document.createElement("option");
					option.value = v_id;
					option.text = sp_ts_themes[theme_id].variants[v_id].label;
					if (v_id === sp_ts_themes[theme_id].selected_variant)
					{
						option.selected = true;
					}
					variant_select.appendChild(option);
				}
			}
			else
			{
				variant_container.style.display = "none";
			}
		}

		function sp_variant_select(variant_select)
		{
			var theme_id = document.getElementById("sp_ts_theme").value,
				variant_id = variant_select.value,
				thumb = document.getElementById("sp_ts_thumb");

			thumb.src = sp_ts_themes[theme_id].variants[variant_id].thumbnail;
		}';

	theme()->addInlineJavascript($javascript, true);
}
