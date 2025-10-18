<?php
if (!defined('ABSPATH')) exit;

/**
 * Theme bootstrap and modular includes
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - This file serves as the central entry point for backend-only functionality
 *   related to the LCP (Local Cultural Practices) data collection system.
 * - The architecture favors modular separation: each `inc/*.php` file handles a
 *   clearly defined subsystem (e.g., CPT, contributor restrictions, export tools).
 *
 * GOALS
 * - Keep `functions.php` minimal and declarative.
 * - Use require() to load isolated feature modules.
 * - Facilitate long-term maintainability (explicit includes, no autoloading).
 *
 * FILES LOADED:
 *  1) Custom Post Types & Save UI ............................ inc/lcp-posttype.php
 *  2) Contributor Role Restrictions .......................... inc/contributor-access.php
 *  3) Team Registration via Invite Code ...................... inc/team-registration.php
 *  4) Dashboard README Viewer ................................ inc/readme-dashboard.php
 *  5) Contributor “How it works” Widget ...................... inc/todo-dashboard.php
 *  6) CSV Export Tool (Admin Only) ........................... inc/export-lcp-data.php
 *  7) Admin “Team” Column & Filter ........................... inc/admin-columns-team.php
 *
 * ADDITIONAL:
 * - Cleans up the dashboard to remove core widgets.
 * - Registers the Google Maps API key for ACF.
 *
 * REPRODUCIBILITY
 * - Designed to run on a clean WordPress installation without plugins other than ACF.
 * - All features depend only on core WordPress hooks and Advanced Custom Fields (ACF).
 */


/* -----------------------------------------------------------------------------
 * 1) CUSTOM POST TYPES & SAVE LOGIC
 * -------------------------------------------------------------------------- */

// Registers the custom post types `lcp_entry` and `lcp_city`.
// Replaces the default Publish box with a custom Save button.
// All entries remain backend-only (not public or queryable).
require get_template_directory() . '/inc/lcp-posttype.php';


/* -----------------------------------------------------------------------------
 * 2) CONTRIBUTOR RESTRICTIONS
 * -------------------------------------------------------------------------- */

// Defines contributor role behavior in the admin area.
// Contributors can only see and edit entries within their own team.
// Removes menus, toolbar, and nonessential UI elements.
require get_template_directory() . '/inc/contributor-access.php';


/* -----------------------------------------------------------------------------
 * 3) TEAM REGISTRATION VIA INVITE CODES
 * -------------------------------------------------------------------------- */

// Adds an "Invite Code" field to the user registration form.
// On valid input, assigns a team slug to the new user (stored as user_meta).
// Admins can later view and modify team assignment from the user profile page.
require get_template_directory() . '/inc/team-registration.php';


/* -----------------------------------------------------------------------------
 * 4) DASHBOARD README WIDGET
 * -------------------------------------------------------------------------- */

// Adds a widget that renders README.md inside the WordPress Dashboard.
// Provides quick technical documentation access for internal users.
require get_template_directory() . '/inc/readme-dashboard.php';


/* -----------------------------------------------------------------------------
 * 5) DASHBOARD “HOW IT WORKS” WIDGET
 * -------------------------------------------------------------------------- */

// Displays short contributor instructions (“How it works”) at the top right of the Dashboard.
// Aimed at non-technical users, providing a minimal onboarding guide.
require_once get_template_directory() . '/inc/todo-dashboard.php';


/* -----------------------------------------------------------------------------
 * 6) DATA EXPORT TOOL (ADMIN ONLY)
 * -------------------------------------------------------------------------- */

// Adds “Export LCP Data” under Tools in wp-admin.
// Provides a REST-based backend to export CSVs for both CPTs (lcp_entry and lcp_city).
require get_template_directory() . '/inc/export-lcp-data.php';


/* -----------------------------------------------------------------------------
 * 7) ADMIN TEAM COLUMN
 * -------------------------------------------------------------------------- */

// Adds a sortable “Team” column to the admin post lists for `lcp_entry` and `lcp_city`.
// Allows admins to quickly see which team authored each post.
require get_template_directory() . '/inc/admin-columns-team.php';


/* -----------------------------------------------------------------------------
 * 8) DASHBOARD CLEANUP
 * -------------------------------------------------------------------------- */

/**
 * Removes all default WordPress dashboard widgets, keeping only custom LCP widgets.
 * Helps maintain focus on internal tools and avoids clutter.
 *
 * Hook: wp_dashboard_setup (priority 20)
 */
add_action('wp_dashboard_setup', 'lcp_clean_up_dashboard', 20);

function lcp_clean_up_dashboard() {
	global $wp_meta_boxes;

	// Whitelist of widgets to keep
	$allowed_widgets = [
		'lcp_dashboard_entries',  // List of LCP entries
		'lcp_dashboard_cities',   // List of cities
		'lcp_team_invite_code',   // Team invite info
		'lcp_readme_widget',      // Internal README
	];

	// Iterate through all dashboard contexts and remove non-whitelisted widgets
	foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
		foreach ($priorities as $priority => $widgets) {
			foreach ($widgets as $widget_id => $widget) {
				if (!in_array($widget_id, $allowed_widgets, true)) {
					unset($wp_meta_boxes['dashboard'][$context][$priority][$widget_id]);
				}
			}
		}
	}
}


/* -----------------------------------------------------------------------------
 * 9) ADVANCED CUSTOM FIELDS (ACF) — GOOGLE MAPS API
 * -------------------------------------------------------------------------- */
/**
 * Registers the Google Maps API key for use in ACF’s Google Map field.
 *
 * SECURITY & DEPLOYMENT
 * - The key is loaded from the .env file, not hardcoded.
 * - The key must be restricted in Google Cloud Console:
 *   * Application restrictions: HTTP referrers only (your domain URLs)
 *   * API restrictions: “Maps JavaScript API” (and optionally “Places API”)
 *
 * VISIBILITY
 * - The key is public client-side when ACF loads the Google Maps JavaScript.
 *
 * REFERENCE
 * @link https://www.advancedcustomfields.com/resources/google-map/
 */
function lcp_acf_init() {
    // Load environment variables if not already loaded
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }

    // Get API key from .env
    $api_key = getenv('ACF_GOOGLE_MAPS_API_KEY');

    if ($api_key) {
        acf_update_setting('google_api_key', $api_key);
    }
}
add_action('acf/init', 'lcp_acf_init');