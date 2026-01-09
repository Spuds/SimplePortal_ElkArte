<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use BBC\ParserWrapper;
use ElkArte\Graphics\Image;
use ElkArte\User;

/**
 * Returns the number of views and comments for a given article
 *
 * @param int $id the id of the article
 *
 * @return array
 */
function sportal_get_article_views_comments($id)
{
	$db = database();

	if (empty($id))
	{
		return [0, 0];
	}

	// Make the request
	$result = [0, 0];
	$db->query('', '
	SELECT
		views, comments
	FROM {db_prefix}sp_articles
	WHERE id_article = {int:article_id}
	LIMIT {int:limit}',
		[
			'article_id' => $id,
			'limit' => 1,
		]
	)->fetch_callback(function($row) use (&$result) {
		$result[0] = $row['views'];
		$result[1] = $row['comments'];
	});

	return $result;
}

/**
 * Loads an article by id, or articles by namespace
 *
 * @param int|string|null $article_id id of an article or string for the articles in a namespace
 * @param bool $active true to only return items that are active
 * @param bool $allowed true to check permission access to the item
 * @param string $sort string passed to the order by parameter
 * @param int|null $category_id id of the category
 * @param int|null $limit limit the number of results
 * @param int|null $start start number for pages
 *
 * @return array
 */
function sportal_get_articles($article_id = null, $active = false, $allowed = false, $sort = 'spa.title', $category_id = null, $limit = null, $start = null)
{
	global $scripturl, $context, $color_profile;

	$db = database();

	$query = [];
	$parameters = ['sort' => $sort];

	// Load up an article or namespace
	if (!empty($article_id) && is_int($article_id))
	{
		$query[] = 'spa.id_article = {int:article_id}';
		$parameters['article_id'] = (int) $article_id;
	}
	elseif (!empty($article_id))
	{
		$query[] = 'spa.namespace = {string:namespace}';
		$parameters['namespace'] = $article_id;
	}

	// Want articles only from a specific category
	if ($category_id !== null)
	{
		$query[] = 'spa.id_category = {int:category_id}';
		$parameters['category_id'] = (int) $category_id;
	}

	// Checking access?
	if (!empty($allowed))
	{
		$query[] = sprintf($context['SPortal']['permissions']['query'], 'spa.permissions');
		$query[] = sprintf($context['SPortal']['permissions']['query'], 'spc.permissions');
	}

	// We have an active article and category?
	if (!empty($active))
	{
		$query[] = 'spa.status = {int:article_status}';
		$parameters['article_status'] = 1;
		$query[] = 'spc.status = {int:category_status}';
		$parameters['category_status'] = 1;
	}

	// Limits?
	if ($limit !== null)
	{
		$parameters['limit'] = $limit;
		$parameters['start'] = $start ?? 0;
	}

	// Make the request
	$member_ids = [];
	$return = [];
	$db->query('', '
	SELECT
		spa.id_article, spa.id_category, spa.namespace AS article_namespace, spa.title, spa.body,
		spa.type, spa.date, spa.status, spa.permissions AS article_permissions, spa.views, spa.comments, spa.styles,
		spc.permissions AS category_permissions, spc.name, spc.namespace AS category_namespace,
		m.avatar, IFNULL(m.id_member, 0) AS id_author, IFNULL(m.real_name, spa.member_name) AS author_name, m.email_address,
		a.id_attach, a.attachment_type, a.filename
	FROM {db_prefix}sp_articles AS spa
		INNER JOIN {db_prefix}sp_categories AS spc ON (spc.id_category = spa.id_category)
		LEFT JOIN {db_prefix}members AS m ON (m.id_member = spa.id_member)
		LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)' . (!empty($query) ? '
	WHERE ' . implode(' AND ', $query) : '') . '
	ORDER BY {raw:sort}' . ($limit !== null ? '
	LIMIT {int:start}, {int:limit}' : ''), $parameters
	)->fetch_callback(
		function($row) use (&$return, &$member_ids, $scripturl) {
			if (!empty($row['id_author'])) {
				$member_ids[$row['id_author']] = $row['id_author'];
			}

			$return[$row['id_article']] = [
				'id' => (int) $row['id_article'],
				'category' => [
					'id' => (int) $row['id_category'],
					'category_id' => $row['category_namespace'],
					'name' => $row['name'],
					'href' => $scripturl . '?category=' . $row['category_namespace'],
					'link' => '<a class="sp_cat_link" href="' . $scripturl . '?category=' . $row['category_namespace'] . '">' . $row['name'] . '</a>',
					'permissions' => $row['category_permissions'],
				],
				'author' => [
					'id' => (int) $row['id_author'],
					'name' => $row['author_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
					'link' => $row['id_author']
						? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>')
						: $row['author_name'],
					'avatar' => determineAvatar([
						'avatar' => $row['avatar'],
						'filename' => $row['filename'],
						'id_attach' => $row['id_attach'],
						'email_address' => $row['email_address'],
						'attachment_type' => $row['attachment_type'],
					]),
				],
				'article_id' => $row['article_namespace'],
				'title' => $row['title'],
				'href' => $scripturl . '?article=' . $row['article_namespace'],
				'link' => '<a href="' . $scripturl . '?article=' . $row['article_namespace'] . '">' . $row['title'] . '</a>',
				'body' => $row['body'],
				'type' => $row['type'],
				'date' => $row['date'],
				'permissions' => $row['article_permissions'],
				'styles' => $row['styles'],
				'view_count' => (int) $row['views'],
				'comment_count' => (int) $row['comments'],
				'status' => $row['status'],
				'has_attachments' => false
			];
		}
	);

	// No results, nothing else to do
	if (empty($return))
	{
		return [];
	}

	// Flag which ones have attachments
	$id_articles = array_keys($return);
	$has_attachments = sportal_article_has_attachments($id_articles);
	foreach ($has_attachments as $id_article => $article)
	{
		$return[$id_article]['has_attachments'] = $article;
	}

	// Use color profiles?
	if (!empty($member_ids) && sp_loadColors($member_ids) !== false)
	{
		foreach ($return as $key => $value)
		{
			if (!empty($color_profile[$value['author']['id']]['link']))
			{
				$return[$key]['author']['link'] = $color_profile[$value['author']['id']]['link'];
			}
		}
	}

	// Return one or all
	return !empty($article_id) ? current($return) : $return;
}

/**
 * Returns the count of articles in a given category
 *
 * @param int $catid category identifier
 *
 * @return int
 */
function sportal_get_articles_in_cat_count($catid)
{
	global $context;

	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}sp_articles
		WHERE status = {int:article_status}
			AND {raw:article_permissions}
			AND id_category = {int:current_category}
		LIMIT {int:limit}',
		[
			'article_status' => 1,
			'article_permissions' => sprintf($context['SPortal']['permissions']['query'], 'permissions'),
			'current_category' => $catid,
			'limit' => 1,
		]
	);
	list ($total_articles) = $request->fetch_row();
	$request->free_result();

	return $total_articles;
}

/**
 * Checks if a member has access to an article
 *
 * Checks permissions and that the article and category are active
 *
 * @param int $id_article article to check access
 * @return bool
 */
function sportal_article_access($id_article)
{
	global $context;

	if (empty($id_article))
	{
		return false;
	}

	$db = database();

	$request = $db->query('', '
		SELECT 
			spa.id_article
		FROM {db_prefix}sp_articles AS spa
			INNER JOIN {db_prefix}sp_categories AS spc ON (spc.id_category = spa.id_category)
		WHERE spa.id_article = {int:id_article}
		 	AND spa.status = {int:article_status}
			AND spc.status = {int:category_status}
			AND {raw:article_permissions}
			AND {raw:category_permissions}
		LIMIT {int:limit}',
		[
			'id_article' => $id_article,
			'article_status' => 1,
			'category_status' => 1,
			'article_permissions' => sprintf($context['SPortal']['permissions']['query'], 'spa.permissions'),
			'category_permissions' => sprintf($context['SPortal']['permissions']['query'], 'spc.permissions'),
			'limit' => 1,
		]
	);
	list ($total_articles) = $request->fetch_row();
	$request->free_result();

	return !empty($total_articles);
}

/**
 * Returns the number of articles in the system that a user can see
 *
 * @return int
 */
function sportal_get_articles_count()
{
	global $context;

	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}sp_articles AS spa
			INNER JOIN {db_prefix}sp_categories AS spc ON (spc.id_category = spa.id_category)
		WHERE spa.status = {int:article_status}
			AND spc.status = {int:category_status}
			AND {raw:article_permissions}
			AND {raw:category_permissions}
		LIMIT {int:limit}',
		[
			'article_status' => 1,
			'category_status' => 1,
			'article_permissions' => sprintf($context['SPortal']['permissions']['query'], 'spa.permissions'),
			'category_permissions' => sprintf($context['SPortal']['permissions']['query'], 'spc.permissions'),
			'limit' => 1,
		]
	);
	list ($total_articles) = $request->fetch_row();
	$request->free_result();

	return $total_articles;
}

/**
 * Fetches the number of comments a given article has received
 *
 * @param int $id article id
 *
 * @return int
 */
function sportal_get_article_comment_count($id)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}sp_comments
		WHERE id_article = {int:current_article}
		LIMIT {int:limit}',
		[
			'current_article' => $id,
			'limit' => 1,
		]
	);
	list ($total_comments) = $request->fetch_row();
	$request->free_result();

	return $total_comments;
}

/**
 * Given an article comment ID, fetch the comment and comment author
 *
 * @param int $id
 *
 * @return array
 */
function sportal_fetch_article_comment($id)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			id_comment, id_member, body
		FROM {db_prefix}sp_comments
		WHERE id_comment = {int:comment_id}
		LIMIT {int:limit}',
		[
			'comment_id' => $id,
			'limit' => 1,
		]
	);
	list ($comment_id, $author_id, $body) = $request->fetch_row();
	$request->free_result();

	return [$comment_id, $author_id, $body];
}

/**
 * Loads all the comments that an article has generated
 *
 * @param int|null $article_id
 * @param int|null $limit limit the number of results
 * @param int|null $start start number for pages
 *
 * @return array
 */
function sportal_get_comments($article_id = null, $limit = null, $start = null)
{
	global $scripturl, $color_profile;

	$db = database();

	$return = [];
	$member_ids = [];
	$parser = ParserWrapper::instance();

	$db->query('', '
	SELECT
		spc.id_comment, IFNULL(spc.id_member, 0) AS id_author,
		IFNULL(m.real_name, spc.member_name) AS author_name,
		spc.body, spc.log_time,
		m.avatar, m.email_address,
		a.id_attach, a.attachment_type, a.filename
	FROM {db_prefix}sp_comments AS spc
		LEFT JOIN {db_prefix}members AS m ON (m.id_member = spc.id_member)
		LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
	WHERE spc.id_article = {int:article_id}
	ORDER BY spc.id_comment' . ($limit !== null ? '
	LIMIT {int:start}, {int:limit}' : ''),
		[
			'article_id' => (int) $article_id,
			'limit' => (int) $limit,
			'start' => (int) $start,
		]
	)->fetch_callback(
		function($row) use (&$return, &$member_ids, $scripturl, $parser) {
			if (!empty($row['id_author']))
			{
				$member_ids[$row['id_author']] = $row['id_author'];
			}

			$return[$row['id_comment']] = [
				'id' => $row['id_comment'],
				'body' => censor($parser->parseMessage($row['body'], true)),
				'time' => htmlTime($row['log_time']),
				'author' => [
					'id' => $row['id_author'],
					'name' => $row['author_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_author'],
					'link' => $row['id_author']
						? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>')
						: $row['author_name'],
					'avatar' => determineAvatar([
						'avatar' => $row['avatar'],
						'filename' => $row['filename'],
						'id_attach' => $row['id_attach'],
						'email_address' => $row['email_address'],
						'attachment_type' => $row['attachment_type'],
					]),
				],
				'can_moderate' => allowedTo('sp_admin') || allowedTo('sp_manage_articles') || (!User::$info->is_guest && User::$info->id == $row['id_author']),
			];
		}
	);

	// Colorization
	if (!empty($member_ids) && sp_loadColors($member_ids) !== false)
	{
		foreach ($return as $key => $value)
		{
			if (!empty($color_profile[$value['author']['id']]['link']))
			{
				$return[$key]['author']['link'] = $color_profile[$value['author']['id']]['link'];
			}
		}
	}

	return $return;
}

/**
 * Creates a new comment response to a published article
 *
 * @param int $article_id id of the article commented on
 * @param string $body text of the comment
 *
 * @return null
 */
function sportal_create_article_comment($article_id, $body)
{
	$db = database();

	$db->insert('',
		'{db_prefix}sp_comments',
		[
			'id_article' => 'int',
			'id_member' => 'int',
			'member_name' => 'string',
			'log_time' => 'int',
			'body' => 'string',
		],
		[
			$article_id,
			User::$info->id,
			User::$info->name,
			time(),
			$body,
		],
		['id_comment']
	);

	// Increase the comment count
	sportal_recount_comments($article_id);
}

/**
 * Edits an article comment that has already been made
 *
 * @param int $comment_id comment id to edit
 * @param string $body replacement text
 */
function sportal_modify_article_comment($comment_id, $body)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}sp_comments
		SET body = {text:body}
		WHERE id_comment = {int:comment_id}',
		[
			'comment_id' => $comment_id,
			'body' => $body,
		]
	);
}

/**
 * Removes a comment from an article
 *
 * @param int $comment_id comment it
 *
 * @return boolean
 */
function sportal_delete_article_comment($comment_id)
{
	global $context;

	$db = database();

	$request = $db->query('', '
		SELECT 
			id_comment, id_article, id_member
		FROM {db_prefix}sp_comments
		WHERE id_comment = {int:comment_id}
		LIMIT {int:limit}',
		[
			'comment_id' => $comment_id,
			'limit' => 1,
		]
	);
	list ($comment_id, $article_id, $author_id) = $request->fetch_row();
	$request->free_result();

	// You do have a right to remove the comment?
	if (empty($comment_id) || (!$context['article']['can_moderate'] && User::$info->id !== (int) $author_id))
	{
		return false;
	}

	// Poof ... gone
	$db->query('', '
		DELETE FROM {db_prefix}sp_comments
		WHERE id_comment = {int:comment_id}',
		[
			'comment_id' => $comment_id,
		]
	);

	// One less comment
	sportal_recount_comments($article_id);

	return true;
}

/**
 * Recounts all comments made on an article and updates the DB with the new value
 *
 * @param int $article_id
 */
function sportal_recount_comments($article_id)
{
	$db = database();

	// How many comments were made for this article?
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}sp_comments
		WHERE id_article = {int:article_id}
		LIMIT {int:limit}',
		[
			'article_id' => $article_id,
			'limit' => 1,
		]
	);
	list ($comments) = $request->fetch_row();
	$request->free_result();

	// Update the article comment count
	$db->query('', '
		UPDATE {db_prefix}sp_articles
		SET comments = {int:comments}
		WHERE id_article = {int:article_id}',
		[
			'article_id' => $article_id,
			'comments' => $comments,
		]
	);
}

/**
 * Removes attachments associated with an article
 *
 * - It does no permissions check.
 *
 * @param array $attachmentQuery
 */
function removeArticleAttachments($attachmentQuery)
{
	$db = database();

	$aid = $attachmentQuery['id_article'];
	$restriction = !empty($attachmentQuery['not_id_attach']) ? $attachmentQuery['not_id_attach'] : [];

	// Get all the attachments
	$attach = [];
	$db->query('', '
	SELECT
		a.filename, a.file_hash, a.attachment_type, a.id_attach, a.id_member, a.id_article,
		IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.filename AS thumb_filename, thumb.file_hash AS thumb_file_hash
	FROM {db_prefix}sp_attachments AS a
		LEFT JOIN {db_prefix}sp_attachments AS thumb ON (thumb.id_attach = a.id_thumb)
	WHERE 
		a.attachment_type = {int:attachment_type}
		AND a.id_article = {int:aid}' . (!empty($restriction) ? ' AND a.id_attach NOT IN ({array_int:restriction})' : ''),
		[
			'attachment_type' => 0,
			'aid' => $aid,
			'restriction' => $restriction,
		]
	)->fetch_callback(
		function($row) use (&$attach, $attachmentQuery) {
			$filename = $attachmentQuery['id_folder'] . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.elk';
			@unlink($filename);

			// If this attachment has a thumb, remove it as well.
			if (!empty($row['id_thumb']))
			{
				$thumb_filename = $attachmentQuery['id_folder'] . '/' . $row['id_thumb'] . '_' . $row['thumb_file_hash'] . '.elk';
				@unlink($thumb_filename);
				$attach[] = $row['id_thumb'];
			}

			$attach[] = $row['id_attach'];
		}
	);

	if (!empty($attach))
	{
		$db->query('', '
			DELETE FROM {db_prefix}sp_attachments
			WHERE id_attach IN ({array_int:attachment_list})',
			[
				'attachment_list' => $attach,
			]
		);

		return true;
	}

	return false;
}

/**
 * Compute and return the total size of attachments for a single article.
 *
 * @param int $id_article
 * @param bool $include_count = true if true, it also returns the attachments count
 */
function attachmentsSizeForArticle($id_article, $include_count = true)
{
	$db = database();

	if (empty($id_article))
	{
		return $include_count ? [0, 0] : [0];
	}

	if ($include_count)
	{
		$request = $db->query('', '
			SELECT 
				COUNT(*), SUM(size)
			FROM {db_prefix}sp_attachments
			WHERE id_article = {int:id_article}
				AND attachment_type = {int:attachment_type}',
			[
				'id_article' => $id_article,
				'attachment_type' => 0,
			]
		);
	}
	else
	{
		$request = $db->query('', '
			SELECT 
				COUNT(*)
			FROM {db_prefix}sp_attachments
			WHERE id_article = {int:id_article}
				AND attachment_type = {int:attachment_type}',
			[
				'id_article' => $id_article,
				'attachment_type' => 0,
			]
		);
	}
	$size = $request->fetch_row();
	$request->free_result();

	return $size;
}

/**
 * Create an article attachment with the given array of parameters.
 *
 * What it does:
 * - Adds any additional or missing parameters to $attachmentOptions.
 * - Renames the temporary file.
 * - Creates a thumbnail if the file is an image and the option is enabled.
 *
 * @param array $attachmentOptions associative array of options
 * @return bool
 */
function createArticleAttachment(&$attachmentOptions)
{
	global $modSettings;

	$db = database();

	// Going to need some help
	require_once(SUBSDIR . '/Attachments.subs.php');

	$image = new Image($attachmentOptions['tmp_name']);

	// If this is an image, we need to set a few additional parameters.
	$is_image = $image->isImageLoaded();
	$size = $is_image ? $image->getImageDimensions() : [0, 0, 0];
	list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;
	$attachmentOptions['width'] = max(0, $attachmentOptions['width']);
	$attachmentOptions['height'] = max(0, $attachmentOptions['height']);

	// If it's an image, get the mime type right.
	if ($is_image)
	{
		$attachmentOptions['mime_type'] = getValidMimeImageType($size[2]);

		// Want to correct for phonetographer photos?
		if (!empty($modSettings['attachment_autorotate']))
		{
			$image->autoRotate();
		}
	}

	// Get the hash if no hash has been given yet.
	if (empty($attachmentOptions['file_hash']))
	{
		$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], 0, null, true);
	}

	// Assuming no-one set the extension, let's take a look at it.
	if (empty($attachmentOptions['fileext']))
	{
		$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
		if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] === $attachmentOptions['name'])
		{
			$attachmentOptions['fileext'] = '';
		}
	}

	$db->insert('',
		'{db_prefix}sp_attachments',
		[
			'id_article' => 'int', 'id_member' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40',
			'fileext' => 'string-8', 'size' => 'int', 'width' => 'int', 'height' => 'int', 	'mime_type' => 'string-20'
		],
		[
			(int) $attachmentOptions['article'],
			(int) $attachmentOptions['poster'],
			$attachmentOptions['name'],
			$attachmentOptions['file_hash'],
			$attachmentOptions['fileext'],
			(int) $attachmentOptions['size'],
			(empty($attachmentOptions['width']) ? 0 : (int) $attachmentOptions['width']),
			(empty($attachmentOptions['height']) ? 0 : (int) $attachmentOptions['height']),
			(!empty($attachmentOptions['mime_type']) ? $attachmentOptions['mime_type'] : ''),
		],
		['id_article']
	);
	$attachmentOptions['id'] = $db->insert_id('{db_prefix}sp_attachments');

	// @todo Add an error here maybe?
	if (empty($attachmentOptions['id']))
	{
		return false;
	}

	// Now that we have the attached id, let's rename this and finish up.
	$attachmentOptions['destination'] = $attachmentOptions['id_folder'] . '/' . $attachmentOptions['id'] . '_' . $attachmentOptions['file_hash'] . '.elk';
	if (rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']) && $is_image)
	{
		// Let the manipulator the (loaded) file new location
		$image->setFileName($attachmentOptions['destination']);
	}

	if (empty($modSettings['attachmentThumbnails']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
	{
		return true;
	}

	// Like thumbnails, do we?
	if (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachmentOptions['width'] > $modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > $modSettings['attachmentThumbHeight']))
	{
		$thumb_filename = $attachmentOptions['name'] . '_thumb';
		$thumb_path = $attachmentOptions['destination'] . '_thumb';
		$thumb_image = $image->createThumbnail($modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight'], $thumb_path);
		if ($thumb_image !== false)
		{
			// Figure out how big we actually made it.
			$size = $thumb_image->getImageDimensions();
			list ($thumb_width, $thumb_height) = $size;

			$thumb_mime = getValidMimeImageType($size[2]);
			$thumb_size = $thumb_image->getFilesize();
			$thumb_file_hash = getAttachmentFilename($thumb_filename, 0, null, true);

			// To the database we go!
			$db->insert('',
				'{db_prefix}sp_attachments',
				[
					'id_article' => 'int', 'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20',
				],
				[
					(int) $attachmentOptions['article'],
					(int) $attachmentOptions['poster'],
					3,
					$thumb_filename,
					$thumb_file_hash,
					$attachmentOptions['fileext'],
					$thumb_size,
					$thumb_width,
					$thumb_height,
					$thumb_mime,
				],
				['id_article']
			);
			$attachmentOptions['thumb'] = $db->insert_id('{db_prefix}sp_attachments');

			if (!empty($attachmentOptions['thumb']))
			{
				$db->query('', '
					UPDATE {db_prefix}sp_attachments
					SET id_thumb = {int:id_thumb}
					WHERE id_attach = {int:id_attach}',
					[
						'id_thumb' => $attachmentOptions['thumb'],
						'id_attach' => $attachmentOptions['id'],
					]
				);

				rename($thumb_path,$attachmentOptions['id_folder'] . '/' . $attachmentOptions['thumb'] . '_' . $thumb_file_hash . '.elk');
			}
		}
	}

	return true;
}

/**
 * Binds a set of attachments to a specific article.
 *
 * @param int $id_article
 * @param int[] $attachment_ids
 */
function bindArticleAttachments($id_article, $attachment_ids)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}sp_attachments
		SET id_article = {int:id_article}
		WHERE id_attach IN ({array_int:attachment_list})',
		[
			'attachment_list' => $attachment_ids,
			'id_article' => $id_article,
		]
	);
}

/**
 * Get all attachments associated with an article or set of articles.
 *
 * What it does:
 *  - This does *not* check permissions.
 *
 * @param int|int[] $articles array of article ids
 * @param bool $template if to load some data into context
 * @return array
 */
function sportal_get_articles_attachments($articles, $template = false)
{
	global $modSettings;

	$db = database();

	if (empty($articles))
	{
		return [];
	}

	if (!is_array($articles))
	{
		$articles = [$articles];
	}

	$attachments = [];
	$temp = [];

	$db->query('', '
	SELECT
		a.id_attach, a.id_article, a.filename, a.file_hash, IFNULL(a.size, 0) AS filesize, a.width, a.height,
		IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height
	FROM {db_prefix}sp_attachments AS a
		LEFT JOIN {db_prefix}sp_attachments AS thumb ON (thumb.id_attach = a.id_thumb)
	WHERE a.id_article IN ({array_int:article_list})
		AND a.attachment_type = {int:attachment_type}',
		[
			'article_list' => $articles,
			'attachment_type' => 0,
		]
	)->fetch_callback(
		function($row) use (&$temp, &$attachments) {
			$temp[$row['id_attach']] = $row;

			if (!isset($attachments[$row['id_article']]))
			{
				$attachments[$row['id_article']] = [];
			}
		}
	);

	// This is better than sorting it with the query...
	ksort($temp);
	foreach ($temp as $id => $row)
	{
		$attachments[$row['id_article']][$id] = $row;
	}

	if ($template)
	{
		$return = [];
		foreach ($attachments as $aid => $attachment)
		{
			foreach ($attachment as $current)
			{
				$return[$aid][] = [
					'name' => htmlspecialchars($current['filename'], ENT_COMPAT),
					'size' => $current['filesize'],
					'id' => $current['id_attach'],
					'approved' => true,
					'id_folder' => $modSettings['sp_articles_attachment_dir']
				];
			}
		}

		return $return;
	}

	return $attachments;
}

/**
 * This loads an attachment's contextual data including, most importantly, its size if it is an image.
 *
 * What it does:
 * - It requires the view_attachments permission to calculate image size.
 * - It attempts to keep the "aspect ratio" of the posted image in line, even if it has to be resized by
 * the max_image_width and max_image_height settings.
 *
 * @param int $id_article article number to load attachments for
 * @return array of attachments
 */
function sportal_load_attachment_context($id_article)
{
	global $modSettings, $txt, $scripturl;

	// Set up the attachment info
	$attachments = sportal_get_articles_attachments($id_article);

	$attachmentData = [];
	if (isset($attachments[$id_article]) && !empty($modSettings['attachmentEnable']))
	{
		foreach ($attachments[$id_article] as $i => $attachment)
		{
			$attachmentData[$i] = [
				'id' => $attachment['id_attach'],
				'name' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($attachment['filename'], ENT_COMPAT)),
				'size' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . $txt['megabyte'],
				'byte_size' => $attachment['filesize'],
				'href' => $scripturl . '?action=portal;sa=spattach;article=' . $id_article . ';attach=' . $attachment['id_attach'],
				'link' => '<a href="' . $scripturl . '?action=portal;sa=spattach;article=' . $id_article . ';attach=' . $attachment['id_attach'] . '">' . htmlspecialchars($attachment['filename'], ENT_COMPAT) . '</a>',
				'is_image' => !empty($attachment['width']) && !empty($attachment['height']) && !empty($modSettings['attachmentShowImages']),
				'file_hash' => $attachment['file_hash'],
				'id_folder' => $modSettings['sp_articles_attachment_dir'],
			];

			if (!$attachmentData[$i]['is_image'])
			{
				continue;
			}

			$attachmentData[$i]['real_width'] = $attachment['width'];
			$attachmentData[$i]['width'] = $attachment['width'];
			$attachmentData[$i]['real_height'] = $attachment['height'];
			$attachmentData[$i]['height'] = $attachment['height'];

			// Let's see, do we want thumbs?
			if (!empty($modSettings['attachmentThumbnails']) && !empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachment['width'] > $modSettings['attachmentThumbWidth'] || $attachment['height'] > $modSettings['attachmentThumbHeight']) && strlen($attachment['filename']) < 249)
			{
				// Only adjust dimensions on successful thumbnail creation.
				if (!empty($attachment['thumb_width']) && !empty($attachment['thumb_height']))
				{
					$attachmentData[$i]['width'] = $attachment['thumb_width'];
					$attachmentData[$i]['height'] = $attachment['thumb_height'];
				}
			}

			if (!empty($attachment['id_thumb']))
			{
				$attachmentData[$i]['thumbnail'] = [
					'id' => $attachment['id_thumb'],
					'href' => $scripturl . '?action=portal;sa=spattach;article=' . $id_article . ';attach=' . $attachment['id_thumb'] . ';image',
				];
			}
			$attachmentData[$i]['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

			// If thumbnails are disabled, check the maximum size of the image.
			if (!$attachmentData[$i]['thumbnail']['has_thumb'] && ((!empty($modSettings['max_image_width']) && $attachment['width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachment['height'] > $modSettings['max_image_height'])))
			{
				if (!empty($modSettings['max_image_width']) && (empty($modSettings['max_image_height']) || $attachment['height'] * $modSettings['max_image_width'] / $attachment['width'] <= $modSettings['max_image_height']))
				{
					$attachmentData[$i]['width'] = $modSettings['max_image_width'];
					$attachmentData[$i]['height'] = floor($attachment['height'] * $modSettings['max_image_width'] / $attachment['width']);
				}
				elseif (!empty($modSettings['max_image_width']))
				{
					$attachmentData[$i]['width'] = floor($attachment['width'] * $modSettings['max_image_height'] / $attachment['height']);
					$attachmentData[$i]['height'] = $modSettings['max_image_height'];
				}
			}
			elseif ($attachmentData[$i]['thumbnail']['has_thumb'])
			{
				// Data attributes for use in expandThumb
				$attachmentData[$i]['thumbnail']['lightbox'] = 'data-lightboxmessage="' . $id_article . '" data-lightboximage="' . $attachment['id_attach'] . '"';

				// If the image is too large to show inline, make it a popup.
				$attachmentData[$i]['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['id_attach'] . ');';
			}
		}
	}

	return $attachmentData;
}

/**
 * Get an attachment associated with an article, part of spattach.
 *
 * @param int $article the article id
 * @param int $attach the attachment id
 * @return array
 */
function sportal_get_attachment_from_article($article, $attach)
{
	$db = database();

	// Make sure this attachment is part of this article
	$request = $db->query('', '
		SELECT 
			a.filename, a.file_hash, a.fileext, a.id_attach, a.attachment_type, a.mime_type, a.width, a.height
		FROM {db_prefix}sp_attachments AS a
		WHERE a.id_attach = {int:attach}
			AND a.id_article = {int:article}
		LIMIT 1',
		[
			'attach' => $attach,
			'article' => $article,
		]
	);
	$attachmentData = [];
	if ($request->num_rows() !== 0)
	{
		$attachmentData = $request->fetch_row();
	}
	$request->free_result();

	return $attachmentData;
}

function sportal_get_attachment_thumb_from_article($article, $attach)
{
	$db = database();

	require_once(SUBSDIR . '/Attachments.subs.php');

	// Make sure this attachment is with this article.
	$attachmentData = array_fill(0, 8, '');
	$db->query('', '
	SELECT 
		th.filename, th.file_hash, th.fileext, th.id_attach, th.id_thumb, th.attachment_type, th.mime_type, th.width, th.height,
		a.filename AS attach_filename, a.file_hash AS attach_file_hash, a.fileext AS attach_fileext,
		a.id_attach AS attach_id_attach, a.id_thumb AS attach_id_thumb, a.attachment_type AS attach_attachment_type, 
		a.mime_type AS attach_mime_type, a.width AS attach_width, a.height AS attach_height
	FROM {db_prefix}sp_attachments AS a
		LEFT JOIN {db_prefix}sp_attachments AS th ON (th.id_attach = a.id_thumb)
	WHERE a.id_attach = {int:attach}',
		[
			'attach' => $attach,
			'article' => $article,
		]
	)->fetch_callback(function($row) use (&$attachmentData) {
		// If there is a hash, then the thumbnail exists
		if (!empty($row['file_hash']))
		{
			$attachmentData = [
				$row['filename'],
				$row['file_hash'],
				$row['fileext'],
				$row['id_attach'],
				$row['attachment_type'],
				$row['mime_type'],
				$row['width'],
				$row['height'],
			];
		}
		// otherwise $modSettings['attachmentThumbnails'] may be (or was) off, so original file
		elseif (getValidMimeImageType($row['attach_mime_type']) !== '')
		{
			$attachmentData = [
				$row['attach_filename'],
				$row['attach_file_hash'],
				$row['attach_fileext'],
				$row['attach_id_attach'],
				$row['attach_attachment_type'],
				$row['attach_mime_type'],
				$row['attach_width'],
				$row['attach_height'],
			];
		}
	});

	return $attachmentData;
}

/**
 * Returns if the given attachment ID is an image file or not
 *
 * What it does:
 *
 * - Given an attachment id, checks that it exists as an attachment
 * - Verifies the message its associated is on a board the user can see
 * - Sets 'is_image' if the attachment is an image file
 * - Returns basic attachment values
 *
 * @package Attachments
 * @param int $id_attach
 *
 * @returns array|boolean
 */
function isArticleAttachmentImage($id_attach)
{
	$db = database();

	// Make sure this attachment is on this board.
	$attachmentData = [];
	$db->query('', '
	SELECT
		a.filename, a.fileext, a.id_attach, a.attachment_type, a.mime_type, a.size, a.width, a.height
	FROM {db_prefix}sp_attachments as a
	WHERE id_attach = {int:attach}
		AND attachment_type = {int:type}
	LIMIT 1',
		[
			'attach' => $id_attach,
			'type' => 0,
		]
	)->fetch_callback(function($row) use (&$attachmentData) {
		$attachmentData = $row;
		$attachmentData['is_image'] = str_starts_with($attachmentData['mime_type'], 'image');
		$attachmentData['size'] = byte_format($attachmentData['size']);
	});

	return !empty($attachmentData) ? $attachmentData : false;
}

/**
 * Get attachment count for an article or group of articles
 *
 * @param int|int[] $articles the article id
 * @return array
 */
function sportal_article_has_attachments($articles)
{
	$db = database();

	if (empty($articles))
	{
		return [];
	}

	if (!is_array($articles))
	{
		$articles = (array) $articles;
	}

	// Make sure this attachment is part of this article
	$attachments = [];
	$db->query('', '
    SELECT 
        id_article, COUNT(id_attach) AS number
    FROM {db_prefix}sp_attachments
    WHERE id_article IN ({array_int:articles})
    GROUP BY id_article',
		[
			'articles' => $articles,
		]
	)->fetch_callback(function($row) use (&$attachments) {
		$attachments[$row['id_article']] = $row['number'];
	});

	return $attachments;
}

/**
 * Load the first available attachment in an article (if any) for a group of articles
 *
 * - Does not check permission, assumes article access has been vetted
 *
 * @param array $articles array as loaded by sportal_get_articles();
 */
function getBlogAttachments($articles)
{
	// Just ones with attachments
	$getAttachments = [];
	foreach ($articles as $id_article => $article)
	{
		if (!empty($article['has_attachments']))
		{
			$getAttachments[] = $id_article;
		}
	}

	// For each article, grab the first *image* attachment
	$attachments = sportal_get_articles_attachments($getAttachments);
	foreach ($attachments as $id_article => $attach)
	{
		if (!isset($articles[$id_article]['attachments']))
		{
			foreach ($attach as $val)
			{
				$is_image = !empty($val['width']) && !empty($val['height']);
				if ($is_image)
				{
					$articles[$id_article]['attachments'] = $val;
					break;
				}
			}
		}
	}

	return $articles;
}

/**
 * Load the blog level attachment details
 *
 * - If the article was found to have attachments (via getBlogAttachments), then
 * it will load that attachment data for use in a template
 * - If the message did not have attachments, it is then searched for the first
 * bbc IMG tag, and that image is used.
 *
 * @param array $articles as setup by getBlogAttachments();
 */
function setBlogAttachments($articles)
{
	global $scripturl;

	foreach ($articles as $id_article => $article)
	{
		if (!empty($article['attachments']))
		{
			$attachment = $article['attachments'];
			$articles[$id_article]['attachments'] += [
				'id' => $attachment['id_attach'],
				'href' => $scripturl . '?action=portal;sa=spattach;article=' . $id_article . ';attach=' . $attachment['id_attach'],
				'link' => '<a href="' . $scripturl . '?action=portal;sa=spattach;article=' . $id_article . ';attach=' . $attachment['id_attach'] . '">' . htmlspecialchars($attachment['filename'], ENT_COMPAT, 'UTF-8') . '</a>',
				'name' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($attachment['filename'], ENT_COMPAT, 'UTF-8')),
			];
		}
		// No attachments, perhaps an IMG tag then?
		else
		{
			$body = $article['body'];
			$pos = strpos($body, '[img');
			if ($pos !== false)
			{
				$img_tag = substr($body, $pos, strpos($body, '[/img]', $pos) + 6);
				$parser = ParserWrapper::instance();
				$img_html = $parser->parseMessage($img_tag, true);
				$articles[$id_article]['body'] = str_replace($img_tag, '<div class="sp_attachment_thumb">' . $img_html . '</div>', $body);
			}
		}
	}

	return $articles;
}
