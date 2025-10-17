<?php
if (!defined('ABSPATH')) exit;

/**
 * Contributor Role Guardrails + Simplified Admin Experience
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - This module tailors wp-admin for the `contributor` role in a private, team-based
 *   research workflow using the custom post types `lcp_entry` and `lcp_city`.
 * - Goals:
 *   1) Least-privilege: contributors can work only within allowed CPTs and their team scope.
 *   2) Minimal, distraction-free UI for contributors.
 *   3) Enforce team-based access at capability mapping level (edit/read/delete).
 * - Admins are unaffected and retain full visibility and control.
 *
 * REPRODUCIBILITY
 * - Uses only stable WordPress core hooks/APIs: roles/caps, admin menu, pre_get_posts,
 *   dashboard widgets, map_meta_cap, etc. No external deps.
 * - Hooks are defensive (is_admin + is_main_query checks) to avoid side effects.
 *
 * PERFORMANCE
 * - Queries that constrain to team use `author__in` based on current user's team.
 *   On large sites, consider caching user lists per team if needed.
 */

/* -----------------------------------------------------------------------------
 * 1) CAPABILITIES AND ROLE SETUP
 * -------------------------------------------------------------------------- */

/**
 * Allow contributors to upload files (e.g., for ACF image fields).
 * - This runs on every admin_init; harmless if already granted.
 * - Stored in DB; persists across requests.
 */
function _lcp_allow_contributor_uploads() {
	$role = get_role('contributor');
	if ($role && !$role->has_cap('upload_files')) {
		$role->add_cap('upload_files');
	}
}
add_action('admin_init', '_lcp_allow_contributor_uploads');


/* -----------------------------------------------------------------------------
 * 2) ADMIN MENU CLEANUP (LEFT SIDEBAR)
 * -------------------------------------------------------------------------- */

/**
 * Streamline the admin menu for contributors:
 * - Adds an explicit "Log out" item at the bottom for clarity.
 * - Removes screens that contributors don't need (Posts, Media, Tools, etc.).
 *   Editing happens only via our CPTs and dashboard guidance.
 *
 * NOTE:
 * - We don't remove our CPT menus here because access to those is further controlled
 *   by capability checks and page-level guards below.
 */
function _lcp_clean_admin_menu_for_contributors() {
	if (!current_user_can('contributor')) return;

	// Add a convenient logout menu item
	add_menu_page(
		'Log out',
		'Log out',
		'read',
		wp_logout_url(),
		'',
		'dashicons-migrate',
		99
	);

	// Remove standard WP menus that are not used in this workflow
	remove_menu_page('edit.php');                    // Posts
	remove_menu_page('upload.php');                  // Media
	remove_menu_page('edit-comments.php');           // Comments
	remove_menu_page('tools.php');                   // Tools
	remove_menu_page('profile.php');                 // Profile
	remove_menu_page('users.php');                   // Users
	remove_menu_page('plugins.php');                 // Plugins
	remove_menu_page('themes.php');                  // Appearance
	remove_menu_page('options-general.php');         // Settings
	remove_menu_page('edit.php?post_type=page');     // Pages
}
add_action('admin_menu', '_lcp_clean_admin_menu_for_contributors', 999);


/* -----------------------------------------------------------------------------
 * 3) PAGE ACCESS RESTRICTION
 * -------------------------------------------------------------------------- */

/**
 * Hard-redirect contributors away from unsupported admin screens.
 * Allowed:
 *   - Dashboard
 *   - Post editor/list for our CPTs: lcp_entry, lcp_city
 * Everything else redirects to the Dashboard.
 *
 * RATIONALE:
 * - This complements capability checks, providing a clear, guided UX and
 *   preventing confusing dead-ends in admin.
 */
function _lcp_restrict_admin_pages_for_contributors() {
    if (!current_user_can('contributor')) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;

    // Always allow the main dashboard
    if ($screen->id === 'dashboard') return;

    // Allow only our CPTs on post-related screens; redirect others to dashboard
    if (in_array($screen->base, ['post','post-new','edit'], true)) {
        $pt = isset($screen->post_type) ? $screen->post_type : '';
        if (in_array($pt, ['lcp_entry','lcp_city'], true)) {
            return;
        }
        wp_safe_redirect(admin_url('index.php'));
        exit;
    }

    // Any other admin page → dashboard
    wp_safe_redirect(admin_url('index.php'));
    exit;
}
add_action('admin_init', '_lcp_restrict_admin_pages_for_contributors');


/* -----------------------------------------------------------------------------
 * 4) CUSTOM DASHBOARD CONTENT (WELCOME/INTRO)
 * -------------------------------------------------------------------------- */

/**
 * Replace the default dashboard with a simplified "Welcome" widget for contributors,
 * and remove default WP dashboard widgets to reduce noise.
 *
 * CONTENT SOURCE:
 * - Reads the content of the page with slug `intro` (if it exists) and renders it.
 */
function _lcp_custom_dashboard_for_contributors() {
	if (current_user_can('contributor')) {
		remove_meta_box('dashboard_primary',     'dashboard', 'side');
		remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
		remove_meta_box('dashboard_activity',    'dashboard', 'normal');
		remove_meta_box('dashboard_right_now',   'dashboard', 'normal');
		remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
	}

	wp_add_dashboard_widget(
		'tw_contributor_welcome',
		'Welcome',
		'_lcp_contributor_dashboard_content'
	);
}
add_action('wp_dashboard_setup', '_lcp_custom_dashboard_for_contributors');

/**
 * Render content from page slug `intro`.
 * - If missing, provides a helpful instruction to create it.
 */
function _lcp_contributor_dashboard_content() {
	$intro_page = get_page_by_path('intro');

	if ($intro_page) {
		$content = apply_filters('the_content', $intro_page->post_content);
		echo $content;
	} else {
		echo '<p>No intro page found. Please create a page with the slug <strong>intro</strong>.</p>';
	}
}


/* -----------------------------------------------------------------------------
 * 5) HIDE ADMIN BAR AND TOP UI ELEMENTS
 * -------------------------------------------------------------------------- */

/**
 * For contributors, hide the admin bar and other top-level UI affordances to
 * keep focus on the dashboard widgets and CPT editor screens.
 *
 * Includes:
 * - Admin bar removal (both rendering and nodes)
 * - Screen options, help tabs
 * - A bit of CSS to hide footer and action links
 */
function _lcp_hide_everything_top_for_contributors() {
	if (!is_admin() || !current_user_can('contributor')) return;

	// Hide admin bar
	add_filter('show_admin_bar', '__return_false');
	remove_action('admin_footer', 'wp_admin_bar_render', 1000);
	remove_action('wp_footer', 'wp_admin_bar_render', 1000);

	// Remove all admin bar nodes just in case
	add_action('admin_bar_menu', function($wp_admin_bar) {
		foreach ($wp_admin_bar->get_nodes() as $node) {
			$wp_admin_bar->remove_node($node->id);
		}
	}, 999);

	// Remove Screen Options and Help tabs
	add_filter('screen_options_show_screen', '__return_false');
	add_filter('contextual_help', '__return_false', 999, 3);

	// Minimal CSS cleanup
	add_action('admin_head', function() {
		echo '<style>
			#wpadminbar,
			#wpfooter,
			#contextual-help-link-wrap,
			#screen-options-link-wrap { display: none !important; }
			.wrap .page-title-action { display: none !important; }
		</style>';
	});
}
add_action('admin_init', '_lcp_hide_everything_top_for_contributors');

/**
 * After login, contributors land on the Dashboard (not the Posts list, etc.).
 */
function lcp_redirect_contributors_to_dashboard($redirect_to, $request, $user) {
	if (isset($user->roles) && in_array('contributor', $user->roles, true)) {
		return admin_url('index.php');
	}
	return $redirect_to;
}
add_filter('login_redirect', 'lcp_redirect_contributors_to_dashboard', 10, 3);


/* -----------------------------------------------------------------------------
 * X) GRANT CPT CAPS TO CONTRIBUTORS (RUNS ON THEME SWITCH)
 * -------------------------------------------------------------------------- */

/**
 * Grant contributor the custom capabilities for our CPTs (no publishing).
 * - Runs once on theme switch; stored in DB.
 * - Safe to run multiple times; add_cap is idempotent per capability.
 */
add_action('after_switch_theme', function () {
    if ($role = get_role('contributor')) {
        foreach ([
            // LCP
            'create_lcp_entries',
            'edit_lcp_entries',
            'read_private_lcp_entries',
            'edit_others_lcp_entries',
            'delete_lcp_entries',
            'delete_others_lcp_entries',
            'edit_private_lcp_entries',
            'edit_published_lcp_entries',
            'delete_private_lcp_entries',
            'delete_published_lcp_entries',

            // City
            'create_lcp_cities',
            'edit_lcp_cities',
            'read_private_lcp_cities',
            'edit_others_lcp_cities',
            'delete_lcp_cities',
            'delete_others_lcp_cities',
            'edit_private_lcp_cities',
            'edit_published_lcp_cities',
            'delete_private_lcp_cities',
            'delete_published_lcp_cities',

            // Uploads (fallback)
            'upload_files',
        ] as $cap) {
            $role->add_cap($cap);
        }
    }
});


/* -----------------------------------------------------------------------------
 * Y) TEAM-BASED META CAPS (PER-POST CHECKS)
 * -------------------------------------------------------------------------- */

/**
 * Team-aware per-post capability mapping.
 *
 * ALGORITHM:
 * - For edit/read/delete of lcp_entry/lcp_city:
 *   * Admins: always allowed.
 *   * Contributors: allowed only if the current user shares the same `lcp_team`
 *     (user meta) as the post author. Otherwise denied.
 *
 * WHY HERE:
 * - map_meta_cap runs late enough to evaluate a specific post + current user,
 *   which is ideal for team-scoped access control.
 */
add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    $per_post_caps = [
        'edit_lcp_entry', 'delete_lcp_entry', 'read_lcp_entry',
        'edit_lcp_city',  'delete_lcp_city',  'read_lcp_city',
    ];
    if (!in_array($cap, $per_post_caps, true)) {
        return $caps; // Not a cap we handle here
    }

    $post_id = isset($args[0]) ? (int)$args[0] : 0;
    if (!$post_id) return ['do_not_allow'];

    $post = get_post($post_id);
    if (!$post) return ['do_not_allow'];

    // Only our CPTs
    if (!in_array($post->post_type, ['lcp_entry','lcp_city'], true)) {
        return $caps;
    }

    // Admins: always allowed
    if (user_can($user_id, 'administrator')) {
        return ['exist'];
    }

    // Team equality check between current user and post author
    $current_team = get_user_meta($user_id, 'lcp_team', true);
    $author_team  = get_user_meta($post->post_author, 'lcp_team', true);

    if ($current_team && $current_team === $author_team) {
        return ['exist']; // allow
    }

    return ['do_not_allow']; // deny
}, 10, 4);


/* -----------------------------------------------------------------------------
 * Z) FAILSAFE: ENSURE CONTRIBUTOR HAS REQUIRED CPT CAPS
 * -------------------------------------------------------------------------- */

/**
 * Verify (on every admin_init) that the contributor role has all required caps.
 * - Idempotent: adds only missing caps.
 * - Useful if roles are edited externally or plugins reset roles.
 */
add_action('admin_init', function () {
    $role = get_role('contributor');
    if (!$role) return;

    $needed = [
        // LCP
        'create_lcp_entries','edit_lcp_entries','read_private_lcp_entries',
        'edit_others_lcp_entries','delete_lcp_entries','delete_others_lcp_entries',
        'edit_private_lcp_entries','edit_published_lcp_entries',
        'delete_private_lcp_entries','delete_published_lcp_entries',

        // City
        'create_lcp_cities','edit_lcp_cities','read_private_lcp_cities',
        'edit_others_lcp_cities','delete_lcp_cities','delete_others_lcp_cities',
        'edit_private_lcp_cities','edit_published_lcp_cities',
        'delete_private_lcp_cities','delete_published_lcp_cities',

        // Uploads (if not already set)
        'upload_files',
    ];

    foreach ($needed as $cap) {
        if (!$role->has_cap($cap)) {
            $role->add_cap($cap);
        }
    }
});


/* -----------------------------------------------------------------------------
 * TEAM FILTERS FOR LIST VIEWS (edit.php) FOR LCP/CITIES
 * -------------------------------------------------------------------------- */

/**
 * Limit the list tables (wp-admin/edit.php) to current contributor's team.
 * - Admins see everything (no limitation).
 * - Contributors:
 *     * If they have no team → see nothing (author__in = [0]).
 *     * Otherwise → see posts authored by any user in their team.
 *
 * NOTE:
 * - Applies to both CPTs: lcp_entry and lcp_city.
 * - This is a visibility guard rail in the list view; actual edit/read/delete
 *   permissions are enforced via map_meta_cap above.
 */
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;

    $pt = $q->get('post_type');
    if (!in_array($pt, ['lcp_entry', 'lcp_city'], true)) return;

    // Admins: unrestricted
    if (current_user_can('administrator')) return;

    // Resolve current user's team
    $team = get_user_meta(get_current_user_id(), 'lcp_team', true);

    // No team → no posts
    if (!$team) {
        $q->set('author__in', [0]);
        return;
    }

    // Collect all user IDs in the same team
    $user_ids = get_users([
        'meta_key'   => 'lcp_team',
        'meta_value' => $team,
        'fields'     => 'ID',
    ]);

    // Constrain to team authors (or none)
    $q->set('author__in', $user_ids ?: [0]);
});


/* -----------------------------------------------------------------------------
 * MEDIA LIBRARY SCOPING FOR CONTRIBUTORS
 * -------------------------------------------------------------------------- */

/**
 * (1) Admin list view for Media Library (/wp-admin/upload.php):
 *     Contributors see only their own attachments. Admins see all.
 */
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;

    // Apply only to attachment queries
    $pt = $q->get('post_type');
    if ($pt && $pt !== 'attachment') return;

    // Admins see everything
    if (current_user_can('administrator')) return;

    // Contributors: limit to own uploads
    if (current_user_can('contributor')) {
        $q->set('author', get_current_user_id());
    }
});

/**
 * (2) Media modal (AJAX in post editor):
 *     Same rule: contributors only see their own attachments.
 */
add_filter('ajax_query_attachments_args', function ($args) {
    // Admins see everything
    if (current_user_can('administrator')) return $args;

    if (current_user_can('contributor')) {
        $args['author'] = get_current_user_id();
    }
    return $args;
});
