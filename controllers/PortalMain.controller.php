<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015 SimplePortal Team
 * @license BSD 3-clause
 * @version 1.0.0 Beta 2
 */

use ElkArte\sources\Frontpage_Interface;

if (!defined('ELK'))
{
	die('No access...');
}

/**
 * Portal controller.
 *
 * - This class handles requests that allow viewing the main portal or the portal credits
 */
class Sportal_Controller extends Action_Controller implements Frontpage_Interface
{
	/**
	 * Default method, just forwards
	 */
	public function action_index()
	{
		require_once(SUBSDIR . '/Action.class.php');

		// Where do you want to go today?
		$subActions = array(
			'index' => array($this, 'action_sportal_index'),
			'credits' => array($this, 'action_sportal_credits'),
			'resetlayout' => array($this, 'action_sportal_resetLayout'),
			'userorder' => array($this, 'action_userblockorder'),
			'spattach' => array('controller' => 'Articles_Controller', 'dir' => CONTROLLERDIR, 'file' => 'PortalArticles.controller.php', 'function' => 'action_index'),
		);

		// We like action, so lets get ready for some
		$action = new Action('');

		// Get the subAction, or just go to action_sportal_index
		$subAction = $action->initialize($subActions, 'index');

		// Finally go to where we want to go
		$action->dispatch($subAction);
	}

	/**
	 * {@inheritdoc }
	 */
	public static function frontPageHook(&$default_action)
	{
		global $modSettings, $context;

		// Need to run init to determine if we are even active
		require_once(SUBSDIR . '/Portal.subs.php');
		sportal_init();

		// Portal is active
		if (empty($context['disable_sp']))
		{
			$file = null;
			$function = null;

			// Any actions we need to handle with the portal, set up the action here.
			if (empty($_GET['page']) && empty($_GET['article']) && empty($_GET['category']) && $modSettings['sp_portal_mode'] == 1)
			{
				// View the portal front page
				$file = CONTROLLERDIR . '/PortalMain.controller.php';
				$controller = 'Sportal_Controller';
				$function = 'action_sportal_index';
			}
			elseif (!empty($_GET['page']))
			{
				// View a specific page
				$file = CONTROLLERDIR . '/PortalPages.controller.php';
				$controller = 'Pages_Controller';
				$function = 'action_sportal_page';
			}
			elseif (!empty($_GET['article']))
			{
				// View a specific  article
				$file = CONTROLLERDIR . '/PortalArticles.controller.php';
				$controller = 'Articles_Controller';
				$function = 'action_sportal_article';
			}
			elseif (!empty($_GET['category']))
			{
				// View a specific category
				$file = CONTROLLERDIR . '/PortalCategories.controller.php';
				$controller = 'Categories_Controller';
				$function = 'action_sportal_category';
			}

			// Something portal-ish, then set the new action
			if (isset($file, $function))
			{
				$default_action = array(
					'file' => $file,
					'controller' => isset($controller) ? $controller : null,
					'function' => $function
				);
			}
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public static function canFrontPage()
	{
		return true;
	}

	/**
	 * {@inheritdoc }
	 */
	public static function frontPageOptions()
	{
		global $txt;
		parent::frontPageOptions();

		loadLanguage('SPortalAdmin');
		addInlineJavascript('
			$(\'#front_page\').on(\'change\', function() {
				var $base = $(\'#sp_portal_mode\').parent();
				if ($(this).val() == \'PortalMain_Controller\')
				{
					$base.fadeIn();
					$base.prev().fadeIn();
				}
				else
				{
					$base.fadeOut();
					$base.prev().fadeOut();
				}
			}).change();', true);

		return array(array('select', 'sp_portal_mode', explode('|', $txt['sp_portal_mode_options'])));
	}

	/**
	 * Common actions for all methods in the class
	 */
	public function pre_dispatch()
	{
		global $context;

		$context['page_title'] = $context['forum_name'];

		if (isset($context['page_title_html_safe']))
		{
			$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title']));
		}

		if (!empty($context['standalone']))
		{
			setupMenuContext();
		}
	}

	/**
	 * Loads article previews for display with the portal index template
	 */
	public function action_sportal_index()
	{
		global $context, $modSettings;

		$context['sub_template'] = 'portal_index';
		loadTemplate('Portal');

		// Showing articles on the index page?
		if (!empty($modSettings['sp_articles_index']))
		{
			require_once(SUBSDIR . '/PortalArticle.subs.php');

			// Set up the pages
			$total_articles = sportal_get_articles_count();
			$total = min($total_articles, !empty($modSettings['sp_articles_index_total']) ? $modSettings['sp_articles_index_total'] : 20);
			$per_page = min($total, !empty($modSettings['sp_articles_index_per_page']) ? $modSettings['sp_articles_index_per_page'] : 5);
			$start = !empty($_REQUEST['articles']) ? (int) $_REQUEST['articles'] : 0;

			if ($total > $per_page)
			{
				$context['article_page_index'] = constructPageIndex($context['portal_url'] . '?articles=%1$d', $start, $total, $per_page, true);
			}

			// If we have some articles
			require_once(SUBSDIR . '/PortalArticle.subs.php');
			$context['articles'] = sportal_get_articles(0, true, true, 'spa.id_article DESC', 0, $per_page, $start);

			foreach ($context['articles'] as $article)
			{
				$context['articles'][$article['id']]['preview'] = censorText($article['body']);
				$context['articles'][$article['id']]['date'] = htmlTime($article['date']);
				$context['articles'][$article['id']]['time'] = $article['date'];

				// Parse / shorten as required
				$context['articles'][$article['id']]['cut'] = sportal_parse_cutoff_content($context['articles'][$article['id']]['preview'], $article['type'], $modSettings['sp_articles_length'], $context['articles'][$article['id']]['article_id']);
			}
		}
	}

	/**
	 * Displays the credit page outside of the admin area,
	 *
	 * - Forwards to admin controller to display credits outside the admin area
	 */
	public function action_sportal_credits()
	{
		loadLanguage('SPortalAdmin');

		require_once(ADMINDIR . '/PortalAdminMain.controller.php');
		$admin_main = new ManagePortalConfig_Controller();
		$admin_main->action_information(false);
	}

	/**
	 * Reset a users custom portal block arrangement
	 */
	public function action_sportal_resetLayout()
	{
		checkSession('request');

		// Remove the block layout settings
		require_once(SUBSDIR . '/Portal.subs.php');
		resetMemberLayout();

		// Redirect to the main page
		redirectexit();
	}

	/**
	 * Reorders the front page blocks in response to a D&D ajax request
	 */
	public function action_userblockorder()
	{
		global $context, $txt, $user_info, $settings, $modSettings;

		// Should not happen, but no guest processing
		if ($user_info['is_guest'] || $user_info['id'] == 0)
		{
			return;
		}

		// Start off with nothing
		$context['xml_data'] = array();
		$errors = array();
		$order = array();

		// Chances are
		loadLanguage('SPortal');

		// You have to be allowed to do this
		$validation_session = checkSession();
		if (empty($validation_session))
		{
			$block_tree = array();

			// No questions that we are rearranging the blocks
			if (isset($_POST['order'], $_POST['received'], $_POST['moved']))
			{
				$column_numbers = array(
					'sp_left_div' => 1,
					'sp_top_div' => 2,
					'sp_bottom_div' => 3,
					'sp_right_div' => 4,
					'sp_header' => 5,
					'sp_footer' => 6
				);

				// What block was drag and dropped? e.g. block_2,4
				list ($block_moved,) = explode(',', $_POST['moved']);
				$block_moved = (int) str_replace('block_', '', $block_moved);

				// Where is it going
				$target_column = $column_numbers[$_POST['received']];

				// The block ids arrive in 1-n view order ... block,column
				foreach ($_POST['block'] as $id)
				{
					list ($block, $column) = explode(',', $id);

					// Update the moved blocks column
					if ($block == $block_moved)
					{
						$column = $target_column;
					}

					$block_tree[$column][] = $block;
				}
			}

			// Update the option so its remembered
			require_once(SUBSDIR . '/Themes.subs.php');
			updateThemeOptions(array($settings['theme_id'], $user_info['id'], 'sp_block_layout', serialize($block_tree)));

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			{
				cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 60);
			}

			$order[] = array(
				'value' => $txt['sp-blocks_success_arrange'],
			);
		}
		// Failed validation, tough day for you
		else
		{
			$errors[] = array('value' => $txt['sp-blocks_fail_arrange']);
		}

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'orders' => array(
				'identifier' => 'order',
				'children' => $order,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}
}

class PortalMain_Controller extends Sportal_Controller
{
}