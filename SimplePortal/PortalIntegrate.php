<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

namespace Addons\SimplePortal;

use BBC\Codes;
use ElkArte\Helper\Util;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use ElkArte\Menu\Menu;
use ElkArte\User;

/**
 * Class Addons\PortalIntegrate
 */
class PortalIntegrate
{
	/**
	 * Register SimplePortal hooks to the system
	 *
	 * This function is called only after the SimplePortal core feature is enabled. Enabling the
	 * core feature sets Addons\PortalIntegrate as an enabled integration (via enableIntegration())
	 *
	 * The Hooks class makes static calls to ::register and ::settingsRegister for each class that
	 * was saved with enableIntegration() (stored in $modSettings['autoload_integrate'])
	 *
	 * @return array
	 */
	public static function register()
	{
		return [
			['integrate_init_theme', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_init_theme'],
			['integrate_current_action', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_current_action'],
			['integrate_action_boardindex_after', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_boardindex'],
			['integrate_actions', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_actions'],
			['integrate_whos_online', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_whos_online'],
			['integrate_action_frontpage', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_frontpage'],
			['integrate_quickhelp', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_quickhelp'],
			['integrate_buffer', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_buffer'],
			['integrate_menu_buttons', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_menu_buttons'],
			['integrate_redirect', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_redirect'],
			['integrate_sa_xmlhttp', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_xmlhttp'],
			['integrate_pre_bbc_parser', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_pre_parsebbc'],
			['integrate_setup_allow', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_setup_allow'],
			['integrate_additional_bbc', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_additional_bbc'],
		];
	}

	/**
	 * Register ACP config hooks for setting values
	 *
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return [
			['integrate_admin_areas', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_admin_areas'],
			['integrate_load_permissions', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_load_permissions'],
			['integrate_load_illegal_guest_permissions', '\Addons\SimplePortal\PortalIntegrate::sp_integrate_load_illegal_guest_permissions'],
		];
	}

	/**
	 * Used to add the Portal entry to the Core Features list.  This static call is made from the
	 * coreFeatures class via method _discoverCoreFeatures.  The hook is discovered by naming conventions,
	 * here the file must be in Addons directory and be XYZIntegrate.php where XYZ is the name of the addon.
	 *
	 * @param array $core_features The core features array
	 */
	public static function addCoreFeature(&$core_features)
	{
		isAllowedTo('admin_forum');
		Txt::load('SimplePortalAdmin');

		$core_features['pt'] = [
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'portalconfig', '{session_data}']),
			'setting_callback' => function ($value) {
				// Enabling
				if ($value)
				{
					Hooks::instance()->enableIntegration('\Addons\SimplePortal\PortalIntegrate');
					return ['disable_sp' => ''];
				}

				// Disabling
				Hooks::instance()->disableIntegration('\Addons\SimplePortal\PortalIntegrate');
				return ['disable_sp' => 1];
			},
		];
	}

	/**
	 * Adds [spattach] BBC code tags for use with article images.  Mostly the same as ILA [attach]
	 *
	 * @param array $additional_bbc
	 */
	public static function sp_integrate_additional_bbc(&$additional_bbc)
	{
		global $scripturl, $modSettings, $txt;

		// Generally, we don't want to render inside these tags ...
		$disallow = [
			'quote' => 1,
			'code' => 1,
			'nobbc' => 1,
			'html' => 1,
			'php' => 1,
		];

		// Disabled tags?
		$disabledBBC = empty($modSettings['disabledBBC']) ? [] : explode(',', $modSettings['disabledBBC']);
		$disabled = in_array('spattach', $disabledBBC, true);

		// Add simplePortal ILA codes
		$additional_bbc = array_merge($additional_bbc, [
			// A simple spattach
			[
				Codes::ATTR_TAG => 'spattach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_CONTENT => '$1',
				Codes::ATTR_VALIDATE => $disabled ? null : self::validate_plain(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . $scripturl . '?action=portal;sa=spattach;attach=$1">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 8,
			],
			[
				Codes::ATTR_TAG => 'spattach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => [
					'type' => [
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_MATCH => '(thumb)',
					],
				],
				Codes::ATTR_DISABLED => $disabled,
				Codes::ATTR_CONTENT => '$1',
				Codes::ATTR_VALIDATE => $disabled ? null : self::validate_plain(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_DISABLED_CONTENT => '<a href="' . $scripturl . '?action=portal;sa=spattach;attach=$1">(' . $txt['link'] . '-$1)</a> ',
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 8,
			],
			// Require a width with optional height/align to allow use of full image and/or ;thumb
			[
				Codes::ATTR_TAG => 'spattach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => [
					'width' => [
						Codes::PARAM_ATTR_VALIDATE => self::validate_width(),
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					],
					'height' => [
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'max-height:$1px;',
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					],
					'align' => [
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'float$1',
						Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					],
				],
				Codes::ATTR_CONTENT => '<a id="link_$1" class="sp_attach" data-lightboximage="$1" data-lightboxmessage="0" href="' . $scripturl . '?action=portal;sa=spattach;article={article};attach=$1;image"><img src="' . $scripturl . '?action=portal;sa=spattach;article={article};attach=$1{width}{height}" alt="" class="bbc_img {align}" /></a>',
				Codes::ATTR_VALIDATE => self::validate_options(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 8,
			],
			// Require a height with optional width/align to allow removal of ;thumb
			[
				Codes::ATTR_TAG => 'spattach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => [
					'height' => [
						Codes::PARAM_ATTR_VALIDATE => self::validate_height(),
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					],
					'width' => [
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'width:100%;max-width:$1px;',
						Codes::PARAM_ATTR_MATCH => '(\d+)',
					],
					'align' => [
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => 'float$1',
						Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					],
				],
				Codes::ATTR_CONTENT => '<a id="link_$1" class="sp_attach" data-lightboximage="$1" data-lightboxmessage="{article}" href="' . $scripturl . '?action=portal;sa=spattach;article={article};attach=$1;image"><img src="' . $scripturl . '?action=portal;sa=spattach;article={article};attach=$1{height}{width}" alt="" class="bbc_img {align}" /></a>',
				Codes::ATTR_VALIDATE => self::validate_options(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 8,
			],
			// Just an align?
			[
				Codes::ATTR_TAG => 'spattach',
				Codes::ATTR_TYPE => Codes::TYPE_UNPARSED_CONTENT,
				Codes::ATTR_PARAM => [
					'align' => [
						Codes::PARAM_ATTR_VALUE => 'float$1',
						Codes::PARAM_ATTR_MATCH => '(right|left|center)',
					],
					'type' => [
						Codes::PARAM_ATTR_OPTIONAL => true,
						Codes::PARAM_ATTR_VALUE => ';$1',
						Codes::PARAM_ATTR_MATCH => '(thumb|image)',
					],
				],
				Codes::ATTR_CONTENT => '<a id="link_$1" class="sp_attach" data-lightboximage="$1" data-lightboxmessage="{article}" href="' . $scripturl . '?action=portal;sa=spattach;article={article};attach=$1;image"><img src="' . $scripturl . '?action=portal;sa=spattach;article={article};attach=$1{type}" alt="" class="bbc_img {align}" /></a>',
				Codes::ATTR_VALIDATE => self::validate_options(),
				Codes::ATTR_DISALLOW_PARENTS => $disallow,
				Codes::ATTR_BLOCK_LEVEL => false,
				Codes::ATTR_AUTOLINK => false,
				Codes::ATTR_LENGTH => 8,
			],
		]);
	}

	/**
	 * Used when the optional width parameter is set
	 *
	 * - Determines the best image, full or thumbnail, based on ILA width desired
	 * - Used as PARAM_ATTR_VALIDATE function
	 *
	 * @return \Closure
	 */
	public static function validate_width()
	{
		global $modSettings;

		return static function ($data) use ($modSettings) {
			if (!empty($modSettings['attachmentThumbWidth']) && $data <= $modSettings['attachmentThumbWidth'])
			{
				return ';thumb" style="width:100%;max-width:' . $data . 'px;';
			}

			return '" style="width:100%;max-width:' . $data . 'px;';
		};
	}

	/**
	 * Used when the optional height parameter is set and no width is set
	 *
	 * - Determines the best image, full or thumbnail, based on desired ILA height
	 * - Used as PARAM_ATTR_VALIDATE function
	 *
	 * @return \Closure
	 */
	public static function validate_height()
	{
		global $modSettings;

		return static function ($data) use ($modSettings) {
			if (!empty($modSettings['attachmentThumbHeight']) && $data <= $modSettings['attachmentThumbHeight'])
			{
				return ';thumb" style="max-height:' . $data . 'px;';
			}

			return '" style="max-height:' . $data . 'px;';
		};
	}

	/**
	 * This provides for some control for "plain" tags
	 *
	 * - Determines if the ILA is an image or not
	 * - Sets the lightbox attributes if an image is identified
	 * - Keeps track of attachment usage to prevent displaying below the post
	 *
	 * @return \Closure
	 */
	public static function validate_plain()
	{
		global $scripturl, $context, $modSettings;

		return static function (&$tag, &$data) use ($scripturl, &$context, $modSettings) {
			$num = $data;
			$is_image = [];
			$preview = strpos($data, 'post_tmp_' . User::$info->id . '_');
			$article = $context['article']['id'] ?? 0;

			// Not a preview, then sanitize the attach id and determine the actual type
			if ($preview === false)
			{
				$num = (int) $data;
				$is_image = isArticleAttachmentImage($num);
			}

			// An image will get the light box treatment
			if (!empty($is_image['is_image']) || $preview !== false)
			{
				$type = !empty($modSettings['attachmentThumbnails']) ? ';thumb' : '';
				$data = '<a id="link_' . $num . '" data-lightboximage="' . $num . '" data-lightboxmessage="{article}" href="' . $scripturl . '?action=portal;sa=spattach;article=' . $article . ';attach=' . $num . ';image' . '"><img src="' . $scripturl . '?action=portal;sa=spattach;article=' . $article . ';attach=' . $num . $type . '" alt="" class="bbc_img" /></a>';
			}
			else
			{
				// Not an image, determine a mime or use a default thumbnail
				require_once(SUBSDIR . '/Attachments.subs.php');
				$check = returnMimeThumb(($is_image['fileext'] ?? ''), true);

				if ($is_image === false)
				{
					$data = '<img src="' . $check . '" alt="" class="bbc_img" />';
				}
				else
				{
					$data = '<a class="sp_attach" href="' . $scripturl . '?action=portal;sa=spattach;article=' . $article . ';attach=' . $num . '"><img src="' . $check . '" alt="' . $is_image['filename'] . '" class="bbc_img" /></a>';
				}
			}

			$context['ila_dont_show_attach_below'][] = $num;
			$context['ila_dont_show_attach_below'] = array_unique($context['ila_dont_show_attach_below']);
		};
	}

	/**
	 * For tags with options (width / height / align)
	 *
	 * - Keeps track of attachment usage to prevent displaying below the post
	 *
	 * @return \Closure
	 */
	public static function validate_options()
	{
		global $context;

		return static function (&$tag, &$data) use (&$context) {
			$article = $context['article']['id'] ?? 0;

			// Not a preview, then sanitize the attach id
			if (!str_contains($data, 'post_tmp_'))
			{
				$data = (int) $data;
			}

			$tag[Codes::ATTR_CONTENT] = str_replace('{article}', $article, $tag[Codes::ATTR_CONTENT]);

			$context['ila_dont_show_attach_below'][] = $data;
			$context['ila_dont_show_attach_below'] = array_unique($context['ila_dont_show_attach_below']);
		};
	}

	/**
	 * Integration hook integrate_setup_allow
	 *
	 * Called from Theme.php setupMenuContext(), used to determine if the admin button is visible for a given
	 * member as It's needed to access certain submenus
	 */
	public static function sp_integrate_setup_allow()
	{
		global $context;

		$context['allow_admin'] = $context['allow_admin'] || allowedTo(['sp_admin', 'sp_manage_settings', 'sp_manage_blocks', 'sp_manage_articles', 'sp_manage_pages', 'sp_manage_shoutbox', 'sp_manage_profiles', 'sp_manage_categories']);
	}

	/**
	 * integration hook integrate_actions
	 * Called from dispatcher.class, used to add in custom actions
	 *
	 * @param array $actions
	 */
	public static function sp_integrate_actions(&$actions)
	{
		global $context;

		if (!empty($context['disable_sp']))
		{
			return;
		}

		$actions['portal'] = ['\Addons\SimplePortal\Controller\PortalMain', 'action_index'];
		$actions['shoutbox'] = ['\Addons\SimplePortal\Controller\PortalShoutbox', 'action_index'];
		$actions['portalrefresh'] = ['\Addons\SimplePortal\Controller\PortalRefresh', 'action_index'];
	}

	/**
	 * Admin Menu Hook, integrate_admin_areas, called from Menu.php via generic hook,
	 * adds the admin menu
	 *
	 * @param Menu $admin_areas
	 */
	public static function sp_integrate_admin_areas($admin_areas)
	{
		global $txt, $context;

		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		Txt::load(['SimplePortalAdmin', 'SimplePortal']);

		$new_section['portal'] = [
			'enabled' => in_array('pt', $context['admin_features'], true),
			'title' => $txt['sp-adminCatTitle'],
			'permission' => ['sp_admin', 'sp_manage_settings', 'sp_manage_blocks', 'sp_manage_articles', 'sp_manage_pages', 'sp_manage_shoutbox', 'sp_manage_profiles', 'sp_manage_categories'],
			'areas' => [
				'portalconfig' => [
					'label' => $txt['sp-adminConfiguration'],
					'controller' => 'ManagePortalMain',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-cog',
					'permission' => ['sp_admin', 'sp_manage_settings'],
					'subsections' => [
						'information' => [$txt['sp-info_title']],
						'generalsettings' => [$txt['sp-adminGeneralSettingsName']],
						'blocksettings' => [$txt['sp-adminBlockSettingsName']],
						'articlesettings' => [$txt['sp-adminArticleSettingsName']],
					],
				],
				'portalblocks' => [
					'label' => $txt['sp-blocksBlocks'],
					'controller' => 'ManagePortalBlocks',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-spblock',
					'permission' => ['sp_admin', 'sp_manage_blocks'],
					'subsections' => [
						'list' => [$txt['sp-adminBlockListName']],
						'add' => [$txt['sp-adminBlockAddName']],
						'header' => [$txt['sp-positionHeader']],
						'left' => [$txt['sp-positionLeft']],
						'top' => [$txt['sp-positionTop']],
						'bottom' => [$txt['sp-positionBottom']],
						'right' => [$txt['sp-positionRight']],
						'footer' => [$txt['sp-positionFooter']],
					],
				],
				'portalarticles' => [
					'label' => $txt['sp_admin_articles_title'],
					'controller' => 'ManagePortalArticles',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-directory',
					'permission' => ['sp_admin', 'sp_manage_articles'],
					'subsections' => [
						'list' => [$txt['sp_admin_articles_list']],
						'add' => [$txt['sp_admin_articles_add']],
					],
				],
				'portalcategories' => [
					'label' => $txt['sp_admin_categories_title'],
					'controller' => 'ManagePortalCategories',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-contact',
					'permission' => ['sp_admin', 'sp_manage_categories'],
					'subsections' => [
						'list' => [$txt['sp_admin_categories_list']],
						'add' => [$txt['sp_admin_categories_add']],
					],
				],
				'portalpages' => [
					'label' => $txt['sp_admin_pages_title'],
					'controller' => 'ManagePortalPages',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-post-text',
					'permission' => ['sp_admin', 'sp_manage_pages'],
					'subsections' => [
						'list' => [$txt['sp_admin_pages_list']],
						'add' => [$txt['sp_admin_pages_add']],
					],
				],
				'portalshoutbox' => [
					'label' => $txt['sp_admin_shoutbox_title'],
					'controller' => 'ManagePortalShoutbox',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-comments-blank',
					'permission' => ['sp_admin', 'sp_manage_shoutbox'],
					'subsections' => [
						'list' => [$txt['sp_admin_shoutbox_list']],
						'add' => [$txt['sp_admin_shoutbox_add']],
					],
				],
				'portalmenus' => [
					'label' => $txt['sp_admin_menus_title'],
					'controller' => 'ManagePortalMenus',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-menu',
					'permission' => ['sp_admin', 'sp_manage_menus'],
					'subsections' => [
						'listmainitem' => [$txt['sp_admin_menus_main_item_list']],
						'addmainitem' => [$txt['sp_admin_menus_main_item_add']],
						'listcustommenu' => [$txt['sp_admin_menus_custom_menu_list']],
						'addcustommenu' => [$txt['sp_admin_menus_custom_menu_add']],
						'addcustomitem' => [$txt['sp_admin_menus_custom_item_add'], 'enabled' => !empty($_REQUEST['sa']) && $_REQUEST['sa'] === 'listcustomitem'],
					],
				],
				'portalprofiles' => [
					'label' => $txt['sp_admin_profiles_title'],
					'controller' => 'ManagePortalProfile',
					'function' => 'action_index',
					'namespace' => 'Addons\SimplePortal\AdminController\\',
					'class' => 'i-admin i-menu-register',
					'permission' => ['sp_admin', 'sp_manage_profiles'],
					'subsections' => [
						'listpermission' => [$txt['sp_admin_permission_profiles_list']],
						'liststyle' => [$txt['sp_admin_style_profiles_list']],
						'listvisibility' => [$txt['sp_admin_visibility_profiles_list']],
					],
				],
			],
		];

		return $admin_areas->insertSection( $new_section, 'forum');
	}

	/**
	 * Permissions hook, integrate_load_permissions, called from ManagePermissions.php
	 * used to add new permissions
	 *
	 * @param array $permissionGroups
	 * @param array $permissionList
	 * @param array $leftPermissionGroups
	 * @param array $hiddenPermissions
	 * @param array $relabelPermissions
	 */
	public static function sp_integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
	{
		$permissionList['membergroup'] = array_merge($permissionList['membergroup'], [
			'sp_admin' => [false, 'sp', 'sp'],
			'sp_manage_settings' => [false, 'sp', 'sp'],
			'sp_manage_blocks' => [false, 'sp', 'sp'],
			'sp_manage_articles' => [false, 'sp', 'sp'],
			'sp_manage_pages' => [false, 'sp', 'sp'],
			'sp_manage_shoutbox' => [false, 'sp', 'sp'],
			'sp_manage_menus' => [false, 'sp', 'sp'],
			'sp_manage_profiles' => [false, 'sp', 'sp'],
		]);

		$permissionGroups['membergroup'][] = 'sp';

		$leftPermissionGroups[] = 'sp';
	}

	/**
	 * Whos online hook, integrate_whos_online, called from who.subs
	 * translates custom actions to allow us to show what area a user is in
	 *
	 * @param array $actions
	 *
	 * @return string
	 */
	public static function sp_integrate_whos_online($actions)
	{
		global $scripturl, $modSettings, $txt;

		$data = null;

		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		Txt::load('SimplePortal');

		// This may miss if needed for the first action ... need to improve the hook or
		// find another location
		if ((int) $modSettings['sp_portal_mode'] === 1)
		{
			$txt['who_index'] = sprintf($txt['sp_who_index'], $scripturl);
			$txt['whoall_forum'] = sprintf($txt['sp_who_forum'], $scripturl);
		}
		elseif ((int) $modSettings['sp_portal_mode'] === 3)
		{
			$txt['whoall_portal'] = sprintf($txt['sp_who_index'], $scripturl);
		}

		// If it's a portal action, let's check it out.
		if (isset($actions['page']))
		{
			$data = self::sp_whos_online_page($actions['page']);
		}
		elseif (isset($actions['article']))
		{
			$data = self::sp_whos_online_article($actions['article']);
		}

		return $data;
	}

	/**
	 * Page online hook, helper function to determine the page a user is viewing
	 *
	 * @param string $page_id
	 *
	 * @return string
	 */
	public static function sp_whos_online_page($page_id)
	{
		global $scripturl, $txt, $context;

		$db = database();

		$data = $txt['who_hidden'];
		$numeric_ids = '';
		$string_ids = '';
		$page_where = '';

		if (is_numeric($page_id))
		{
			$numeric_ids = (int) $page_id;
		}
		else
		{
			$string_ids = $page_id;
		}

		if (!empty($numeric_ids))
		{
			$page_where = 'id_page IN ({int:numeric_ids})';
		}

		if (!empty($string_ids))
		{
			$page_where = 'namespace IN ({string:string_ids})';
		}

		$query = sprintf($context['SPortal']['permissions']['query'], 'permissions');

		$result = $db->query('', '
		SELECT
			id_page, namespace, title, permissions
		FROM {db_prefix}sp_pages
		WHERE ' . $page_where . ' AND ' . $query . '
		LIMIT {int:limit}',
			[
				'numeric_ids' => $numeric_ids,
				'string_ids' => $string_ids,
				'limit' => 1,
			]
		);
		$page_data = '';
		while ($row = $result->fetch_assoc())
		{
			$page_data = [
				'id' => $row['id_page'],
				'namespace' => $row['namespace'],
				'title' => $row['title'],
			];
		}
		$result->free_result();

		if (!empty($page_data))
		{
			if (isset($page_data['id']))
			{
				$data = sprintf($txt['sp_who_page'], $page_data['id'], censor($page_data['title']), $scripturl);
			}

			if (isset($page_data['namespace']))
			{
				$data = sprintf($txt['sp_who_page'], $page_data['namespace'], censor($page_data['title']), $scripturl);
			}
		}

		return $data;
	}

	/**
	 * Article online hook, helper function to determine the article a user is viewing
	 *
	 * @param string $article_id
	 *
	 * @return string
	 */
	public static function sp_whos_online_article($article_id)
	{
		global $scripturl, $txt, $context;

		$db = database();

		$data = $txt['who_hidden'];
		$numeric_ids = '';
		$string_ids = '';
		$article_where = '';

		if (is_numeric($article_id))
		{
			$numeric_ids = (int) $article_id;
		}
		else
		{
			$string_ids = $article_id;
		}

		if (!empty($numeric_ids))
		{
			$article_where = 'id_article IN ({int:numeric_ids})';
		}

		if (!empty($string_ids))
		{
			$article_where = 'namespace IN ({string:string_ids})';
		}

		$query = sprintf($context['SPortal']['permissions']['query'], 'permissions');

		$result = $db->query('', '
		SELECT
			id_article, namespace, title, permissions
		FROM {db_prefix}sp_articles
		WHERE ' . $article_where . ' AND ' . $query . '
		LIMIT {int:limit}',
			[
				'numeric_ids' => $numeric_ids,
				'string_ids' => $string_ids,
				'limit' => 1,
			]
		);
		$article_data = '';
		while ($row = $result->fetch_assoc())
		{
			$article_data = [
				'id' => $row['id_article'],
				'namespace' => $row['namespace'],
				'title' => $row['title'],
			];
		}
		$result->free_result();

		if (!empty($article_data))
		{
			if (isset($article_data['id']))
			{
				$data = sprintf($txt['sp_who_article'], $article_data['id'], censor($article_data['title']), $scripturl);
			}

			if (isset($article_data['namespace']))
			{
				$data = sprintf($txt['sp_who_article'], $article_data['namespace'], censor($article_data['title']), $scripturl);
			}
		}

		return $data;
	}

	/**
	 * Theme hook, integrate_init_theme, called from load.php
	 * Used to initialize main portal functions as soon as the theme is started
	 */
	public static function sp_integrate_init_theme()
	{
		// Need to run init to determine if we are even active
		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		sportal_init();
	}

	/**
	 * Help hook, integrate_quickhelp, called from help.controller.php
	 * Used to add in additional help languages for use in the admin quickhelp
	 */
	public static function sp_integrate_quickhelp()
	{
		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');

		// Load the Simple Portal Help file.
		Txt::load('SimplePortalHelp+SimplePortalAdmin');
	}

	/**
	 * Integration hook integrate_buffer, called from ob_exit via call_integration_buffer
	 * Used to modify the output buffer before it's sent, here we add in our copyright
	 *
	 * @param string $tourniquet
	 *
	 * @return string
	 */
	public static function sp_integrate_buffer($tourniquet)
	{
		global $context, $modSettings, $forum_copyright;

		if ((ELK === 'SSI' && empty($context['standalone']))
			|| empty($modSettings['sp_portal_mode'])
			|| !theme()->getLayers()->hasLayers())
		{
			return $tourniquet;
		}

		// Don't display copyright for things like SSI.
		if (!defined('FORUM_VERSION'))
		{
			return $tourniquet;
		}

		$fix = str_replace('{version}', SPORTAL_VERSION, '<a href="https://github.com/SimplePortal" target="_blank" class="new_win">SimplePortal {version} &copy; 2008-' . Util::strftime('%Y', time()) . '</a>');

		if (str_contains($tourniquet, $fix))
		{
			return $tourniquet;
		}

		// Append our notice at the end of the line
		$finds = [
			$forum_copyright,
		];
		$replaces = [
			sprintf($forum_copyright, FORUM_VERSION) . ' | ' . $fix,
		];

		$tourniquet = str_replace($finds, $replaces, $tourniquet);

		// Can't find it for some reason, so we add it at the end
		if (!str_contains($tourniquet, $fix))
		{
			$fix = '<div style="text-align: center; width: 100%; font-size: x-small; margin-bottom: 5px;">' . $fix . '</div></body></html>';
			$tourniquet = preg_replace('~</body>\s*</html>~', $fix, $tourniquet);
		}

		return $tourniquet;
	}

	/**
	 * Menu Button hook, integrate_menu_buttons, called from subs.php
	 * used to add top menu buttons
	 *
	 * @param array $buttons
	 */
	public static function sp_integrate_menu_buttons(&$buttons)
	{
		global $txt, $scripturl, $modSettings, $context;

		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		Txt::load('SimplePortal');

		// A couple of SVG Icons we use in the menus
		theme()->css->addCSSRules("
	.i-spgroup::before {
		content: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23555555' viewBox='0 0 36 32'%3E%3Cpath d='M24 24v-1.6a9 9 0 0 0 4-7.4c0-5 0-9-6-9s-6 4-6 9a9 9 0 0 0 4 7.4v1.7C13.2 24.6 8 28 8 32h28c0-4-5.2-7.4-12-8z'/%3E%3Cpath d='M10.2 24.9a19 19 0 0 1 6.3-2.6 11.3 11.3 0 0 1-2.8-7.3c0-2.7 0-5.2 1-7.3 1-2 2.6-3.3 5-3.7-.5-2.4-2-4-5.7-4-6 0-6 4-6 9a9 9 0 0 0 4 7.4v1.7C5.2 18.6 0 22 0 26h8.7l1.5-1.1z'/%3E%3C/svg%3E\");
	}
	.i-spblock::before {
		content: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' stroke='%23111111' stroke-width='.3px' viewBox='5 4.5 15 15'%3E%3Cpath fill='%23111111' fill-rule='evenodd' d='M11.793 5.045a.5.5 0 0 1 .415 0l6 2.737a.5.5 0 0 1 .292.455v6.842a.5.5 0 0 1-.252.434l-6 3.421a.5.5 0 0 1-.496 0l-6-3.42a.5.5 0 0 1-.252-.435V8.237a.5.5 0 0 1 .293-.455l6-2.737ZM6.5 9.042l5 2.47v6.127l-5-2.85V9.042Zm6 8.597 5-2.85V9.042l-5 2.47v6.127Zm-.5-6.995 4.835-2.39L12 6.05 7.165 8.255 12 10.644Z' clip-rule='evenodd'/%3E%3Cg transform='matrix(.8,-.4,.1,.8,7.5,5)'%3E%3Ctext x='5' y='17' font-family='Arial, sans-serif' font-size='8' font-weight='bold' fill='%23444444'%3ES%3C/text%3E%3C/g%3E%3C/svg%3E\");
	}");

		// Set the right portalurl based on what integration mode the portal is using
		$modSettings['sp_portal_mode'] = (int) $modSettings['sp_portal_mode'];
		if ($modSettings['sp_portal_mode'] === 1 && empty($context['disable_sp']))
		{
			$sportal_url = $scripturl . '?action=forum';
		}
		elseif ($modSettings['sp_portal_mode'] === 3 && empty($context['disable_sp']))
		{
			$buttons['home']['href'] = $modSettings['sp_standalone_url'];
			$sportal_url = $modSettings['sp_standalone_url'];
		}
		else
		{
			return;
		}

		// Define the new menu item(s), show it for modes 1 and 3 only
		$buttons = elk_array_insert($buttons, 'home', [
			'forum' => [
				'title' => empty($txt['sp-forum']) ? 'Forum' : $txt['sp-forum'],
				'data-icon' => 'i-spgroup',
				'href' => $sportal_url,
				'show' => empty($context['disable_sp']),
				'sub_buttons' => [],
				'action_hook' => true,
			],
		], 'after');

		// Any custom main menu items from our menu module?
		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');

		if (empty($context['SPortal']['permissions']))
		{
			sportal_load_permissions();
		}

		$items = sportal_get_menu_items(null, 'id_item', 0);
		if (!empty($items))
		{
			foreach ($items as $item)
			{
				if (!in_array($item['id_profile'], $context['SPortal']['permissions']['profiles']))
				{
					continue;
				}

				$sub_buttons = [];
				if (preg_match('~\$scripturl\?menu=(\d+)$~', $item['url'], $match))
				{
					$sub_buttons = self::sp_load_menu_items((int) $match[1]);
				}

				$button = [
					'title' => $item['title'],
					'data-icon' => 'i-spgroup',
					'href' => str_replace('$scripturl', $scripturl, $item['url']),
					'show' => true,
					'target' => $item['target'] ? '_blank' : '',
					'sub_buttons' => $sub_buttons,
				];

				$placement = !empty($item['placement']) ? $item['placement'] : 'after';
				$after = !empty($item['placement_after']) ? $item['placement_after'] : 'forum';

				// ElkArte's elk_array_insert takes care of 'before' and 'after'
				$buttons = elk_array_insert($buttons, $after, [$item['namespace'] => $button], $placement);
			}
		}
	}

	/**
	 * Helper function to load menu items for a custom menu
	 *
	 * @param int $menu_id
	 * @return array
	 */
	public static function sp_load_menu_items($menu_id)
	{
		global $scripturl, $context;

		if (empty($context['SPortal']['permissions']))
		{
			sportal_load_permissions();
		}

		$items = sportal_get_menu_items(null, 'id_item', $menu_id);
		$sub_buttons = [];
		foreach ($items as $item)
		{
			if (!in_array($item['id_profile'], $context['SPortal']['permissions']['profiles']))
			{
				continue;
			}

			$nested_sub_buttons = [];
			if (preg_match('~\$scripturl\?menu=(\d+)$~', $item['url'], $match))
			{
				$nested_sub_buttons = self::sp_load_menu_items((int) $match[1]);
			}

			$sub_buttons[$item['namespace']] = [
				'title' => $item['title'],
				'href' => str_replace('$scripturl', $scripturl, $item['url']),
				'show' => true,
				'target' => $item['target'] ? '_blank' : '',
				'sub_buttons' => $nested_sub_buttons,
			];
		}

		return $sub_buttons;
	}

	/**
	 * Redirection hook, integrate_redirect, called from subs.php redirectexit()
	 *
	 * @param string $setLocation
	 *
	 * @uses redirectexit_callback in subs.php
	 */
	public static function sp_integrate_redirect(&$setLocation)
	{
		global $modSettings, $context, $scripturl;

		// Set the default redirect location as the forum or the portal.
		if ($scripturl === $setLocation
			&& ((int) $modSettings['sp_portal_mode'] === 1 || (int) $modSettings['sp_portal_mode'] === 3))
		{
			// Redirect the user to the forum.
			if (!empty($modSettings['sp_disableForumRedirect']))
			{
				$setLocation = '?action=forum';
			}
			// Redirect the user to the SSI.php standalone portal.
			elseif ((int) $modSettings['sp_portal_mode'] === 3)
			{
				$setLocation = $context['portal_url'];
			}
		}
	}

	/**
	 * A single check for the sake of remove yet another code edit. :P
	 * integrate_action_boardindex_after
	 */
	public static function sp_integrate_boardindex()
	{
		global $context, $modSettings, $scripturl;

		if (!empty($_GET) && $_GET !== ['action' => 'forum'])
		{
			$context['robot_no_index'] = true;
		}

		// Set the board index canonical URL correctly when portal mode is set to the front page
		if (!empty($modSettings['sp_portal_mode']) && (int) $modSettings['sp_portal_mode'] === 1 && empty($context['disable_sp']))
		{
			$context['canonical_url'] = $scripturl . '?action=forum';
		}
	}

	/**
	 * Dealing with the current action?
	 *
	 * @param string $current_action
	 */
	public static function sp_integrate_current_action(&$current_action)
	{
		global $modSettings, $context;

		// If it is home, it may be something else
		if ($current_action === 'home')
		{
			$current_action = (int) $modSettings['sp_portal_mode'] === 3 && empty($context['standalone']) && empty($context['disable_sp'])
				? 'forum' : 'home';
		}

		if (empty($context['disable_sp']) && ((isset($_GET['board']) || isset($_GET['topic']) || in_array($context['current_action'], ['unread', 'unreadreplies', 'collapse', 'recent', 'stats', 'who'])) && in_array((int) $modSettings['sp_portal_mode'], [1, 3], true)))
		{
			$current_action = 'forum';
		}
	}

	/**
	 * Add to the XML array our sortable actions for block arrangement.
	 * integrate_sa_xmlhttp
	 *
	 * @param array $subActions
	 */
	public static function sp_integrate_xmlhttp(&$subActions)
	{
		$subActions['blockorder'] = ['controller' => '\Addons\SimplePortal\AdminController\ManagePortalBlocks', 'function' => 'action_blockorder', 'permission' => 'admin_forum'];
		$subActions['userblockorder'] = ['controller' => '\Addons\SimplePortal\Controller\PortalMain', 'function' => 'action_userblockorder'];
	}

	/**
	 * Add permissions that guest should never be able to have
	 * integrate_load_illegal_guest_permissions called from Permission.subs.php
	 */
	public static function sp_integrate_load_illegal_guest_permissions()
	{
		global $context;

		// Guests shouldn't be able to have any portal-specific permissions.
		$context['non_guest_permissions'] = array_merge($context['non_guest_permissions'], [
			'sp_admin',
			'sp_manage_settings',
			'sp_manage_blocks',
			'sp_manage_articles',
			'sp_manage_pages',
			'sp_manage_shoutbox',
			'sp_manage_profiles',
			'sp_manage_menus',
			'sp_manage_categories'
		]);
	}

	/**
	 * Subs hook, integrate_pre_parsebbc
	 *
	 * - Allow addons access before entering the main parse_bbc loop
	 * - Prevents cutoff tag from bleeding into the message
	 *
	 * @param string $message
	 */
	public static function sp_integrate_pre_parsebbc(&$message)
	{
		if (str_contains($message, '[cutoff]'))
		{
			$message = str_replace('[cutoff]', '', $message);
		}
	}
}
