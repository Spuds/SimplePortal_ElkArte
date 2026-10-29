<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */


function template_view_categories()
{
	global $context, $txt;

	echo '
	<div id="sp_view_categories">
		<h3 class="category_header">
			', $context['page_title'], '
		</h3>';

	if (empty($context['categories']))
	{
		echo '
		<div class="sp_content_padding">', $txt['error_sp_no_categories'], '</div>';
	}

	foreach ($context['categories'] as $category)
	{
		echo '
		<div class="sp_content_padding">
			<h4>', $category['link'], '</h4>
			<p>', $category['description'], '</p>
			<span>', sprintf($category['articles'] == 1 ? $txt['sp_has_article'] : $txt['sp_has_articles'], $category['articles']) ,'</span>
		</div>';
	}

	echo '
	</div>';
}

/**
 * View a specific category and all of its articles that it contains
 */
function template_view_category()
{
	global $context, $txt;

	echo '
	<section id="sp_view_category" class="forumposts">
		<h3 class="category_header">
			', $context['page_title'], '
		</h3>';

	if (empty($context['articles']))
	{
		echo '
		<div class="infobox">', $txt['error_sp_no_articles'], '</div>';
	}

	foreach ($context['articles'] as $article)
	{
		// Show the updated date in the header when the article has been modified
		$show_updated = !empty($article['show_modified']);
		$posted_text = $show_updated ? $txt['sp_updated_on_in_by'] : (!empty($context['using_relative_time']) ? $txt['sp_posted_on_in_by'] : $txt['sp_posted_in_on_by']);
		$posted_time = $show_updated ? $article['modified'] : $article['date'];
		$posted = sprintf($posted_text, $article['category']['link'], $posted_time, $article['author']['link']);

		echo '
		<article class="sp_article_content">
			<header class="category_header sp_article_header">';

		if (!empty($article['author']['avatar']['image']))
		{
			echo '
				<span class="sp_article_avatar">', $article['author']['avatar']['image'], '</span>';
		}

		echo '
				<h4 class="sp_article_title">', $article['link'], '</h4>
			</header>
			<div class="generalinfo sp_article_generalinfo">
				<span class="sp_article_latest">
					', $posted, '
				</span>
				<span class="sp_article_stats">
					', sprintf($article['view_count'] == 1 ? $txt['sp_viewed_time'] : $txt['sp_viewed_times'], $article['view_count']), ', ', sprintf($article['comment_count'] == 1 ? $txt['sp_commented_on_time'] : $txt['sp_commented_on_times'], $article['comment_count']), '
				</span>';

		if (!empty($article['show_modified']))
		{
			echo '
				<span class="sp_article_latest sp_article_modified">', sprintf($txt['sp_article_updated_on'], $article['modified']), '</span>';
		}

		echo '
			</div>
			<div id="msg_', $article['id'], '" class="post inner sp_inner">';

		if (!empty($article['attachments']))
		{
			echo '
					<div class="sp_attachment_thumb">';

			// If you want Fancybox to tag this, remove nfb_ from the id
			echo '
						<a href="', $article['href'], '" id="nfb_link_', $article['attachments']['id'], '">
							<img src="', $article['attachments']['href'], '" alt="" title="', $article['attachments']['name'], '" id="thumb_', $article['attachments']['id'], '" />
						</a>';

			echo '
					</div>';
		}

		echo
				$article['preview'], '
				<div class="sp_article_extra clear">',
					(!empty($article['cut']) ? '<a class="linkbutton" href="' . $article['href'] . '">' . $txt['sp_read_more'] . '</a>' : ''),
					(!empty($article['comment_count']) ? '<a class="linkbutton" href="' . $article['href'] . '#sp_view_comments">' . $txt['sp-articlesComments'] . '</a>' : ''),
					'<a class="linkbutton" href="', $article['href'], '#sp_comment">', $txt['sp_write_comment'], '</a>
				</div>
			</div>
		</article>';
	}

	echo '
	</section>';

	// Pages as well?
	if (!empty($context['page_index']))
		template_pagesection();

	if (!empty($context['using_relative_time']))
	{
		theme()->addInlineJavascript('$(\'.sp_article_latest\').addClass(\'relative\');', true);
	}
}
