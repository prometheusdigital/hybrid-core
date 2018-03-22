<?php
/**
 * Comment template tags.
 *
 * Functions for handling how comments are displayed and used on the site.
 *
 * @package   HybridCore
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2008 - 2018, Justin Tadlock
 * @link      https://themehybrid.com/hybrid-core
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Hybrid;

# Filter the comments template.
add_filter( 'comments_template', __NAMESPACE__ . '\comments_template', 5 );

/**
 * Outputs the comment reply link.  Only use outside of `wp_list_comments()`.
 *
 * @since  5.0.0
 * @access public
 * @param  array   $args
 * @return void
 */
function comment_reply_link( $args = [] ) {

	echo get_comment_reply_link( $args );
}

/**
 * Outputs the comment reply link.  Note that WP's `comment_reply_link()`
 * doesn't work outside of `wp_list_comments()` without passing in the proper
 * arguments (it isn't meant to).  This function is just a wrapper for
 * `get_comment_reply_link()`, which adds in the arguments automatically.
 *
 * @since  5.0.0
 * @access public
 * @param  array  $args
 * @return string
 */
function get_comment_reply_link( $args = [] ) {

	if ( ! get_option( 'thread_comments' ) || in_array( get_comment_type(), [ 'pingback', 'trackback' ] ) ) {
		return '';
	}

	$args = wp_parse_args( $args, [
		'depth'     => intval( $GLOBALS['comment_depth'] ),
		'max_depth' => get_option( 'thread_comments_depth' )
	] );

	return \get_comment_reply_link( $args );
}

/**
 * Prints the comment parent link.
 *
 * @since  5.0.0
 * @access public
 * @param  array   $args
 * @return void
 */
function comment_parent_link( $args = [] ) {

	echo get_comment_parent_link( $args );
}

/**
 * Gets the link to the comment's parent comment.
 *
 * @since  5.0.0
 * @access public
 * @param  array   $args
 * @return string
 */
function get_comment_parent_link( $args = [] ) {

	$link = '';

	$args = wp_parse_args( $args, [
		'text'   => '%s', // Defaults to parent comment author.
		'depth'  => 2,    // At what level should the link show.
		'before' => '',
		'after'  => ''
	] );

	// Only display the link if the current comment is greater than or equal
	// to the depth requested.
	if ( $args['depth'] <= $GLOBALS['comment_depth'] ) {

		$parent = get_comment()->comment_parent;

		if ( 0 < $parent ) {

			$url  = esc_url( get_comment_link( $parent ) );
			$text = sprintf( $args['text'], esc_html( get_comment_author( $parent ) ) );

			$link = sprintf(
				'%s<a class="comment-parent-link" href="%s">%s</a>%s',
				$args['before'],
				$url,
				$text,
				$args['after']
			);
		}
	}

	return apply_filters( app()->namespace . '/comment_parent_link', $link, $args );
}

/**
 * Uses the `$comment_type` to determine which comment template should be used.
 * Once the template is located, it is loaded for use. Child themes can create
 * custom templates based off the `$comment_type`. The comment template hierarchy
 * is `comment-$comment_type.php`, `comment.php`.
 *
 * The templates are saved in `\Hybrid\app()->comment_templates[ $comment_type ]`,
 * so each comment template is only located once if it is needed. Following
 * comments will use the saved template.
 *
 * @since  5.0.0
 * @access public
 * @param  object  $comment
 * @return void
 */
function comments_callback( $comment ) {

	// Get the comment type of the current comment.
	$comment_type = get_comment_type( $comment->comment_ID );

	// Check if a template has been provided for the specific comment type.
	// If not, get the template.
	if ( ! isset( app()->comment_templates[ $comment_type ] ) ) {

		// Create an array of template files to look for.
		$templates = [
			"comment-{$comment_type}.php",
			"comment/{$comment_type}.php"
		];

		// If the comment type is a 'pingback' or 'trackback', allow the use of 'comment-ping.php'.
		if ( 'pingback' == $comment_type || 'trackback' == $comment_type ) {
			$templates[] = 'comment-ping.php';
			$templates[] = 'comment/ping.php';
		}

		// Add the fallback 'comment.php' template.
		$templates[] = 'comment/comment.php';
		$templates[] = 'comment.php';

		// Allow devs to filter the template hierarchy.
		$templates = apply_filters(
			app()->namespace . '/comment_template_hierarchy',
			$templates,
			$comment_type
		);

		// Locate the comment template.
		$template = locate_template( $templates );

		// Set the template in the comment templates array.
		app()->comment_templates[ $comment_type ] = $template;
	}

	// If a template was found, load the template.
	if ( ! empty( app()->comment_templates[ $comment_type ] ) ) {
		require( app()->comment_templates[ $comment_type ] );
	}
}

/**
 * Ends the display of individual comments. Uses the callback parameter for
 * `wp_list_comments()`. Needs to be used in conjunction with
 * `hybrid_comments_callback()`. Not needed but used just in case something is
 * changed.
 *
 * @since  5.0.0
 * @access public
 * @return void
 */
function comments_end_callback() {

	echo '</li><!-- .comment -->';
}

/**
 * Overrides the default comments template.  This filter allows for a
 * `comments-{$post_type}.php` template based on the post type of the current
 * single post view.  If this template is not found, it falls back to the
 * default `comments.php` template.
 *
 * @since  5.0.0
 * @access public
 * @param  string $template
 * @return string
 */
function comments_template( $template ) {

	$templates = [];

	// Allow for custom templates entered into comments_template( $file ).
	$template = str_replace( app()->child_dir, '', $template );

	if ( 'comments.php' !== $template ) {
		$templates[] = $template;
	}

	// Add a comments template based on the post type.
	$templates[] = 'comments-' . get_post_type() . '.php';

	// Add the default comments template.
	$templates[] = 'comments.php';

	// Return the found template.
	return locate_template( $templates );
}
