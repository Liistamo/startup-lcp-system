<?php
if (!defined('ABSPATH')) exit;

/**
 * Dashboard Widget: Project README.md Viewer
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - Adds a WordPress admin dashboard widget that displays the project's
 *   README.md content directly inside wp-admin. This helps onboard users
 *   or developers without them needing to open files locally.
 *
 * FEATURES
 * - Automatically loads the README.md file from the current theme directory.
 * - Converts very basic Markdown syntax into readable HTML using regex.
 * - Escapes raw content first (defensive XSS mitigation).
 * - Displays inside a scrollable container for usability.
 *
 * LIMITATIONS
 * - This is NOT a full Markdown parser (for speed and dependency-free design).
 *   It supports:
 *       #, ##, ### headings
 *       *italic*, **bold**, `inline code`
 *       - lists
 *       (and basic <br> line breaks)
 * - Does not handle nested lists or fenced code blocks.
 *
 * SECURITY
 * - All raw file content is escaped with `esc_html()` before applying regex replacements,
 *   preventing execution of HTML/JS within README.md.
 *
 * PERFORMANCE
 * - The file is read synchronously from disk on dashboard load.
 *   Keep README.md reasonably small (< 100 KB) to avoid slowdown.
 *
 * REPRODUCIBILITY
 * - Works without any plugins. Uses only `wp_add_dashboard_widget()`
 *   and core PHP filesystem functions.
 */


/* -----------------------------------------------------------------------------
 * 1) REGISTER THE DASHBOARD WIDGET
 * -------------------------------------------------------------------------- */

/**
 * Hook into wp_dashboard_setup to add our widget.
 * - Widget is placed in the “normal” column at top priority (“low” context means top).
 */
add_action('wp_dashboard_setup', 'lcp_add_readme_widget');

function lcp_add_readme_widget() {
	wp_add_dashboard_widget(
		'lcp_readme_widget',        // Widget ID
		'Technical setup',          // Widget title
		'lcp_render_readme_widget', // Display callback
		null,                       // No control callback
		null,                       // No args
		'normal',                   // Column: first/main
		'low'                       // Order: top
	);
}


/* -----------------------------------------------------------------------------
 * 2) RENDER FUNCTION (Markdown → HTML)
 * -------------------------------------------------------------------------- */

/**
 * Render README.md contents with minimal Markdown-to-HTML conversion.
 *
 * Logic:
 *  - 1) Read the README.md file from the theme root.
 *  - 2) Escape all content to neutralize raw HTML/JS.
 *  - 3) Apply regex replacements for headings, emphasis, lists, etc.
 *  - 4) Wrap in a scrollable container.
 */
function lcp_render_readme_widget() {
	$readme_path = get_template_directory() . '/README.md';

	// Defensive existence check
	if (!file_exists($readme_path)) {
		echo '<p><em>README.md not found in theme directory.</em></p>';
		return;
	}

	// Read file content
	$markdown = file_get_contents($readme_path);

	// Escape entire string before formatting (prevents XSS)
	$markdown = esc_html($markdown);

	/* ---------------------------------------------------------
	 * Minimal Markdown parsing (regex-based)
	 * ------------------------------------------------------- */

	// Headings
	$markdown = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
	$markdown = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $markdown);
	$markdown = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $markdown);

	// Emphasis and inline code
	$markdown = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $markdown);
	$markdown = preg_replace('/\*(.+?)\*/s',      '<em>$1</em>', $markdown);
	$markdown = preg_replace('/`(.+?)`/s',        '<code>$1</code>', $markdown);

	// Unordered list items
	$markdown = preg_replace('/^- (.+)$/m', '<li>$1</li>', $markdown);
	$markdown = preg_replace('/(<li>.+<\/li>)/s', '<ul>$1</ul>', $markdown);

	// Convert plain newlines to <br> for readability
	$markdown = preg_replace('/\n/', '<br>', $markdown);

	// ---------------------------------------------------------
	// Output the parsed README inside a scrollable container
	// ---------------------------------------------------------
	echo '<div class="readme-content" style="max-height:400px; overflow-y:auto;">';
	echo $markdown;
	echo '</div>';
}
