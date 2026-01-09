<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

namespace Addons\SimplePortal\Controller;

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Attachments\TemporaryAttachmentsList;
use ElkArte\Controller\Attachment;
use ElkArte\Exceptions\Exception;
use ElkArte\Graphics\TextImage;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Article controller.
 *
 * - This class handles requests for Article Functionality
 */
class PortalArticles extends AbstractController
{
	/**
	 * This method is executed before any action handler.
	 * Loads common things for all methods
	 */
	public function pre_dispatch()
	{
		theme()->getTemplates()->load('PortalArticles');
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalArticle.subs.php');
	}

	/**
	 * Default method
	 */
	public function action_index()
	{
		// add subaction array to act accordingly
		$subActions = [
			'article' => [$this, 'action_sportal_article'],
			'articles' => [$this, 'action_sportal_articles'],
			'spattach' => [$this, 'action_sportal_attach'],
			'rmattach' => [$this, 'action_sportal_rmattach'],
		];

		// Set up the action handler
		$action = new Action();
		$subAction = $action->initialize($subActions, 'article');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 * Handles the display of articles in the portal, including pagination, article processing, and
	 * setting up the page context.
	 *
	 * @return void
	 */
	public function action_sportal_articles()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Set up for pagination
		$total_articles = sportal_get_articles_count();
		$per_page = min($total_articles, !empty($modSettings['sp_articles_per_page']) ? $modSettings['sp_articles_per_page'] : 10);
		$start = !empty($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		if ($total_articles > $per_page)
		{
			$context['page_index'] = constructPageIndex($scripturl . '?action=portal;sa=articles;start=%1$d', $start, $total_articles, $per_page, true);
		}

		// Fetch the article page
		$context['articles'] = sportal_get_articles(null, true, true, 'spa.id_article DESC', null, $per_page, $start);
		foreach ($context['articles'] as $article)
		{
			$context['articles'][$article['id']]['preview'] = censor($article['body']);
			$context['articles'][$article['id']]['date'] = htmlTime($article['date']);
			$context['articles'][$article['id']]['time'] = $article['date'];

			// Parse and cut as needed
			$context['articles'][$article['id']]['cut'] = sportal_parse_cutoff_content($context['articles'][$article['id']]['preview'], $article['type'], $modSettings['sp_articles_length'], $context['articles'][$article['id']]['article_id']);
		}

		// Account for videos, pretty print, spoilers and more
		theme()->addInlineJavascript('sp_prep_articles();', true);

		$context['linktree'][] = [
			'url' => $scripturl . '?action=portal;sa=articles',
			'name' => $txt['sp-articles'],
		];

		$context['page_title'] = $txt['sp-articles'];
		$context['sub_template'] = 'view_articles';
	}

	/**
	 * Display a chosen article, called from frontpage hook
	 *
	 * - Update the stats, like #views etc.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function action_sportal_article()
	{
		global $context, $scripturl, $modSettings;

		$article_id = !empty($_REQUEST['article']) ? $_REQUEST['article'] : 0;

		if (!is_int($article_id))
		{
			$article_id = Util::htmlspecialchars($article_id, ENT_QUOTES);
		}

		// Fetch and render the article
		$context['article'] = sportal_get_articles($article_id, true, true);
		if (empty($context['article']['id']))
		{
			throw new Exception('error_sp_article_not_found', false);
		}

		$context['article']['style'] = sportal_select_style($context['article']['styles']);
		$context['article']['body'] = censor($context['article']['body']);
		$context['article']['body'] = sportal_parse_content($context['article']['body'], $context['article']['type'], 'return');

		// Fetch attachments if there are any
		if (!empty($modSettings['attachmentEnable']) && !empty($context['article']['has_attachments']))
		{
			loadJavascriptFile('topic.js');
			$context['article']['attachment'] = sportal_load_attachment_context($context['article']['id']);
		}

		// Set up for the comment pagination
		$total_comments = sportal_get_article_comment_count($context['article']['id']);
		$per_page = min($total_comments, !empty($modSettings['sp_articles_comments_per_page']) ? $modSettings['sp_articles_comments_per_page'] : 20);
		$start = !empty($_REQUEST['comments']) ? (int) $_REQUEST['comments'] : 0;

		if ($total_comments > $per_page)
		{
			$context['page_index'] = constructPageIndex($scripturl . '?article=' . $context['article']['article_id'] . ';comments=%1$d', $start, $total_comments, $per_page, true);
		}

		// Load in all the comments for the article
		$context['article']['comments'] = sportal_get_comments($context['article']['id'], $per_page, $start);

		// Prepare the final template details
		$context['article']['time'] = $context['article']['date'];
		$context['article']['date'] = htmlTime($context['article']['date']);
		$context['article']['can_comment'] = $context['user']['is_logged'];
		$context['article']['can_moderate'] = allowedTo('sp_admin') || allowedTo('sp_manage_articles');

		// Commenting, new or an update perhaps
		if ($context['article']['can_comment'] && !empty($_POST['body']))
		{
			checkSession();
			sp_prevent_flood('spacp', false);

			require_once(SUBSDIR . '/Post.subs.php');

			// Prep the body / comment
			$body = Util::htmlspecialchars(trim($_POST['body']));
			$preparse = PreparseCode::instance('');
			$preparse->preparsecode($body);

			// Update or add a new comment
			$parser = ParserWrapper::instance();
			if (!empty($body) && trim(strip_tags($parser->parseMessage($body, false), '<img>')) !== '')
			{
				if (!empty($_POST['comment']))
				{
					[$comment_id, $author_id,] = sportal_fetch_article_comment((int) $_POST['comment']);
					if (empty($comment_id) || (!$context['article']['can_moderate'] && User::$info['id'] !== (int) $author_id))
					{
						throw new Exception('error_sp_cannot_comment_modify', false);
					}

					sportal_modify_article_comment($comment_id, $body);
				}
				else
				{
					sportal_create_article_comment($context['article']['id'], $body);
				}
			}

			// Set an anchor
			$anchor = '#comment' . (!empty($comment_id) ? $comment_id : ($total_comments > 0 ? $total_comments - 1 : 1));
			redirectexit('article=' . $context['article']['article_id'] . $anchor);
		}

		// Prepare to edit an existing comment
		if ($context['article']['can_comment'] && !empty($_GET['modify']))
		{
			checkSession('get');

			[$comment_id, $author_id, $body] = sportal_fetch_article_comment((int) $_GET['modify']);
			if (empty($comment_id) || (!$context['article']['can_moderate'] && User::$info['id'] != (int) $author_id))
			{
				throw new Exception('error_sp_cannot_comment_modify', false);
			}

			require_once(SUBSDIR . '/Post.subs.php');

			$context['article']['comment'] = [
				'id' => $comment_id,
				'body' => str_replace(['"', '<', '>', '&nbsp;'], ['&quot;', '&lt;', '&gt;', ' '], un_preparsecode($body)),
			];
		}

		// Want to delete a comment?
		if ($context['article']['can_comment'] && !empty($_GET['delete']))
		{
			checkSession('get');

			if (sportal_delete_article_comment((int) $_GET['delete']) === false)
			{
				throw new Exception('error_sp_cannot_comment_delete', false);
			}

			redirectexit('article=' . $context['article']['article_id']);
		}

		// Increase the article view counter
		if (empty($_SESSION['last_viewed_article']) || $_SESSION['last_viewed_article'] != $context['article']['id'])
		{
			sportal_increase_viewcount('article', $context['article']['id']);
			$_SESSION['last_viewed_article'] = $context['article']['id'];
		}

		// Build the breadcrumbs
		$context['linktree'] = array_merge($context['linktree'], [
			[
				'url' => $scripturl . '?category=' . $context['article']['category']['category_id'],
				'name' => $context['article']['category']['name'],
			],
			[
				'url' => $scripturl . '?article=' . $context['article']['article_id'],
				'name' => $context['article']['title'],
			]
		]);

		// Auto video embedding enabled?
		if (!empty($modSettings['enableVideoEmbeding']))
		{
			theme()->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", () => {
				if ($.isFunction($.fn.linkifyvideo))
				{
					$().linkifyvideo(oEmbedtext);
				}
			});', true);
		}

		// Needed for basic Lightbox functionality
		loadJavascriptFile('topic.js', ['defer' => false]);

		$context['description'] = trim(preg_replace('~<[^>]+>~', ' ', $context['article']['body']));
		$context['description'] = Util::shorten_text(preg_replace('~\s\s+|&nbsp;|&quot;|&#039;~', ' ', $context['description']), 384, true);

		// Off to the template we go
		$context['page_title'] = $context['article']['title'];
		$context['sub_template'] = 'view_article';
	}

	/**
	 * Download / show an article attachment
	 *
	 * It is accessed via the query string ?action=portal;sa=spattach.
	 */
	public function action_sportal_attach()
	{
		global $modSettings, $context;

		// Some defaults that we need.
		$context['no_last_modified'] = true;

		// Make sure some attachment was requested, and they can view them
		if (!isset($_GET['article'], $_GET['attach']))
		{
			throw new Exception('no_access', false);
		}

		// No funny business, you need to have access to the article to see its attachments
		if (sportal_article_access($_GET['article']) === false)
		{
			throw new Exception('no_access', false);
		}

		// Temporary attachment, special case...
		if (str_contains($_GET['attach'], 'post_tmp_' . User::$info['id'] . '_'))
		{
			$modSettings['automanage_attachments'] = 0;
			$modSettings['attachmentUploadDir'] = [1 => $modSettings['sp_articles_attachment_dir']];

			return (new Attachment())->action_tmpattach();
		}

		$id_article = (int) $_GET['article'];
		$id_attach = (int) $_GET['attach'];

		if (isset($_GET['thumb']))
		{
			$attachment = sportal_get_attachment_thumb_from_article($id_article, $id_attach);
		}
		else
		{
			$attachment = sportal_get_attachment_from_article($id_article, $id_attach);
		}

		if (empty($attachment))
		{
			throw new Exception('no_access', false);
		}

		[$real_filename, $file_hash, $file_ext, $id_attach, $attachment_type, $mime_type, $width, $height] = $attachment;
		$filename = $modSettings['sp_articles_attachment_dir'] . '/' . $id_attach . '_' . $file_hash . '.elk';

		// No file, generate a bland its missing image
		if (!file_exists($filename))
		{
			$this->sp_no_attach();
			obExit(false);
		}

		require_once(SUBSDIR . '/Attachments.subs.php');
		$eTag = '"' . substr($id_attach . $real_filename . filemtime($filename), 0, 64) . '"';
		$disposition = !isset($_GET['image']) ? 'attachment' : 'inline';
		$do_cache = (!isset($_GET['image']) && getValidMimeImageType($file_ext) !== '') === false;

		// Make sure the mime type warrants an inline display.
		if (isset($_GET['image']) && !empty($mime_type) && !str_starts_with($mime_type, 'image/'))
		{
			unset($_GET['image']);
			$mime_type = '';
		}
		// Does this have a mime type?
		elseif (empty($mime_type) || !(isset($_GET['image']) || getValidMimeImageType($file_ext) === ''))
		{
			$mime_type = '';
			unset($_GET['image']);
		}

		$this->send_headers($filename, $eTag, $mime_type, $disposition, $real_filename, $do_cache);
		$this->send_file($filename, $mime_type);

		obExit(false);
	}

	/**
	 * Sends the requested file to the user. If the file is compressible e.g.,
	 * has a mine type of text/??? May compress the file before sending.
	 *
	 * @param string $filename
	 * @param string $mime_type
	 */
	public function send_file($filename, $mime_type)
	{
		$body = file_get_contents($filename);
		$use_compression = $this->useCompression($mime_type);
		$length = filesize($filename);

		$headers = Headers::instance();

		// If we can/should compress this file
		if ($use_compression && strlen($body) > 250)
		{
			$body = gzencode($body, 2);
			$length = strlen($body);
			$headers
				->header('Content-Encoding','gzip')
				->header('Vary', 'Accept-Encoding');
		}

		// Someone is getting a present
		if (!empty($length))
		{
			$headers->header('Content-Length', $length);
		}

		// Forcibly end any output buffering going on.
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		$headers->sendHeaders();
		echo $body;
	}

	/**
	 * If the mime type benefits from compression e.g., text/xyz and gzencode is
	 * available and the user agent accepts gzip, then return true, else false
	 *
	 * @param string $mime_type
	 * @return bool if we should compress the file
	 */
	public function useCompression($mime_type)
	{
		global $modSettings;

		// Support is available on the server
		if (!function_exists('gzencode') || empty($modSettings['enableCompressedOutput']))
		{
			return false;
		}

		// Not compressible or not supported / requested by a client
		if (!preg_match('~^(?:text/|application/(?:json|xml|rss\+xml)$)~i', $mime_type)
			|| (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) || !str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')))
		{
			return false;
		}

		return true;
	}

	/**
	 * Takes care of sending out the most common headers.
	 *
	 * @param string $filename Full path+file name of the file in the filesystem
	 * @param string $eTag ETag cache validator
	 * @param string $mime_type The mime-type of the file
	 * @param string $disposition The value of the Content-Disposition header
	 * @param string $real_filename The original name of the file
	 * @param bool $do_cache If to send a max-age header or not
	 * @param bool $check_filename When false, any check on $filename is skipped
	 */
	public function send_headers($filename, $eTag, $mime_type, $disposition, $real_filename, $do_cache, $check_filename = true)
	{
		global $txt;

		$headers = Headers::instance();

		// No point in a nicer message, because this is supposed to be an attachment anyway...
		if ($check_filename && !FileFunctions::instance()->fileExists($filename))
		{
			Txt::load('Errors');

			$headers
				->removeHeader('all')
				->httpCode(404)
				->sendHeaders();

			die('404 - ' . $txt['attachment_not_found']);
		}

		// If it hasn't been modified since the last time this attachment was retrieved, there's no need to display it again.
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
		{
			[$modified_since] = explode(';', $this->_req->server->HTTP_IF_MODIFIED_SINCE);
			if (!$check_filename || strtotime($modified_since) >= filemtime($filename))
			{
				@ob_end_clean();

				// Answer the question - no, it hasn't been modified ;).
				$headers
					->removeHeader('all')
					->httpCode(304)
					->sendHeaders();
				exit;
			}
		}

		// Check whether the ETag was sent back, and cache based on that...
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && str_contains($_SERVER['HTTP_IF_NONE_MATCH'], $eTag))
		{
			@ob_end_clean();

			$headers
				->removeHeader('all')
				->httpCode(304)
				->sendHeaders();
			exit;
		}

		// Send the attachment headers.
		$headers
			->header('Expires', gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT')
			->header('Last-Modified', gmdate('D, d M Y H:i:s', $check_filename ? filemtime($filename) : time() - 525600 * 60) . ' GMT')
			->header('Accept-Ranges', 'bytes')
			->header('Connection', 'close')
			->header('ETag', $eTag);

		// Different browsers like different standards...
		$headers->setAttachmentFileParams($mime_type, $real_filename, $disposition);

		// If this has an "image extension" - but isn't an image - then ensure it isn't cached cause of silly IE.
		if ($do_cache)
		{
			$headers
				->header('Cache-Control', 'max-age=' . (525600 * 60) . ', private');
		}
		else
		{
			$headers
				->header('Pragma', 'no-cache')
				->header('Cache-Control', 'no-cache');
		}

		// Try to buy some time...
		detectServer()->setTimeLimit(600);
	}

	/**
	 * Function to remove attachments via ajax calls
	 */
	public function action_sportal_rmattach()
	{
		global $context, $txt, $modSettings;

		// Prepare the template so we can respond with JSON
		setJsonTemplate();

		// Make sure the session is valid
		if (checkSession('post', '', false) !== '')
		{
			Txt::load('Errors');
			$context['json_data'] = ['result' => false, 'data' => $txt['session_timeout']];

			return false;
		}

		// Temp attachment or one that was already saved?
		if (isset($this->_req->post->attachid))
		{
			$result = false;
			$tmp_attachments = new TemporaryAttachmentsList();
			if ($tmp_attachments->hasAttachments())
			{
				$attachId = $tmp_attachments->getIdFromPublic($this->_req->post->attachid);

				try
				{
					$tmp_attachments->removeById($attachId);
					$context['json_data'] = ['result' => true];
					$result = true;
				}
				catch (\Exception $e)
				{
					$result = $e->getMessage();
				}
			}

			// Not a temporary attachment, but a previously uploaded one?
			if ($result !== true)
			{
				$attachId = $this->_req->getPost('attachid', 'intval');
				$articleId = $this->_req->getPost('articleid', 'intval');
				sportal_load_permissions();
				if (sportal_article_access($articleId))
				{
					$keep_ids = [];
					$attach_ids = sportal_get_articles_attachments($articleId);
					$attach_ids = !empty($attach_ids[$articleId]) ? $attach_ids[$articleId] : [];
					foreach ($attach_ids as $id => $value)
					{
						if ($id !== $attachId)
						{
							$keep_ids[] = $id;
						}
					}

					$attachmentQuery = [
						'id_article' => $articleId,
						'not_id_attach' => $keep_ids,
						'id_folder' => $modSettings['sp_articles_attachment_dir'],
					];

					$result_tmp = removeArticleAttachments($attachmentQuery);
					if (!empty($result_tmp))
					{
						$context['json_data'] = ['result' => true];
						$result = true;
					}
					else
					{
						$result = $result_tmp;
					}
				}
			}

			if ($result !== true)
			{
				Txt::load('Errors');
				$context['json_data'] = ['result' => false, 'data' => $txt[!empty($result) ? $result : 'attachment_not_found']];
			}
		}
		else
		{
			Txt::load('Errors');
			$context['json_data'] = ['result' => false, 'data' => $txt['attachment_not_found']];
		}
	}

	/**
	 * Generates a language image based on a text for display.
	 *
	 * @param null|string $text
	 * @throws Exception
	 */
	public function sp_no_attach($text = null)
	{
		global $txt;

		if ($text === null)
		{
			Txt::load('Errors');
			$text = $txt['attachment_not_found'];
		}

		$headers = Headers::instance();
		$this->send_headers('no_image', 'no_image', 'image/png', 'inline', 'no_image.png', true, false);
		$headers->sendHeaders();

		$img = new TextImage($text);
		$img = $img->generate();

		if ($img === false)
		{
			throw new Exception('no_access', false);
		}

		echo $img;
	}
}
