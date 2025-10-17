<?php
if (!defined('ABSPATH')) exit;

/**
 * LCP Custom Post Types + Admin Customizations
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - Two backend-only CPTs used for internal research data collection:
 *     1) lcp_entry  ("LCP")
 *     2) lcp_city   ("Cities/regions")
 * - There is NO public UI and NO public REST for these CPTs:
 *     * public=false, publicly_queryable=false, show_in_rest=false
 * - Data lifecycle:
 *     * Posts are created/edited only by authenticated users inside wp-admin.
 *     * Items live as drafts; publishing is disabled at capability level.
 * - Roles/caps:
 *     * Custom capability types are defined for fine-grained control.
 *     * Admin gets all caps (granted elsewhere).
 *     * Contributors are restricted (see contributor module).
 * - Editor UX:
 *     * Replace the default “Publish” box with a simple “Save” box.
 *     * Disable autosave to avoid collisions (teams edit collaboratively).
 *     * Hide the Author meta box for non-admins (author is implied by team).
 * - Dashboard:
 *     * Two dashboard widgets list recent posts for each CPT, restricted
 *       to the current user’s team (team lives on user meta 'lcp_team').
 *
 * REPRODUCIBILITY
 * - Uses WordPress core APIs only: register_post_type, meta boxes, dashboard widgets, etc.
 * - No dependencies on site-specific plugins for the CPT definitions.
 *
 * SECURITY / PRIVACY
 * - Not public, not queryable, not in REST; the admin-only exports use a private route elsewhere.
 * - Team-based scoping is enforced in separate modules (list filters, meta caps).
 */


/* -----------------------------------------------------------------------------
 * 1) REGISTER CUSTOM POST TYPES
 * -------------------------------------------------------------------------- */

add_action('init', '_lcp_register_custom_post_types');
function _lcp_register_custom_post_types() {

    /* -----------------------------
     * CPT #1: LCP Entries (lcp_entry)
     * -----------------------------
     * - Internal text entries tied to a research template (ACF fields live on the post).
     * - Publishing is disabled entirely.
     */
    $labels = array(
        'name'               => 'LCP Entries',
        'singular_name'      => 'LCP',
        'menu_name'          => 'LCP',
        'name_admin_bar'     => 'LCP',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New LCP',
        'new_item'           => 'New LCP',
        'edit_item'          => 'Edit LCP',
        'view_item'          => 'View LCP',
        'all_items'          => 'All LCP Entries',
        'search_items'       => 'Search LCP Entries',
        'not_found'          => 'No LCP Entries found.',
        'not_found_in_trash' => 'No LCP Entries found in Trash.',
    );

    register_post_type('lcp_entry', array(
        'labels' => $labels,
        // Visibility: backend-only
        'public'             => false,               // no front-end URLs
        'publicly_queryable' => false,               // no WP_Query on front end
        'show_ui'            => true,                // visible in admin
        'show_in_menu'       => true,                // has menu in admin
        'show_in_rest'       => false,               // NOT exposed in REST (private route is defined elsewhere)
        'query_var'          => false,               // no custom query var
        'rewrite'            => false,               // no rewrites
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-media-document',

        // Editing features (title/author/custom fields; ACF uses custom-fields)
        'supports'           => array('title', 'author', 'custom-fields'),

        // Capability model: custom type with full cap map (see caps array below)
        'map_meta_cap'       => true,
        'capability_type'    => ['lcp_entry', 'lcp_entries'],
        'capabilities'       => [
            // Single-post primitive caps
            'create_posts'           => 'create_lcp_entries',
            'edit_post'              => 'edit_lcp_entry',
            'read_post'              => 'read_lcp_entry',
            'delete_post'            => 'delete_lcp_entry',
            // Collection/others
            'edit_posts'             => 'edit_lcp_entries',
            'edit_others_posts'      => 'edit_others_lcp_entries',
            'read_private_posts'     => 'read_private_lcp_entries',
            'delete_posts'           => 'delete_lcp_entries',
            'delete_others_posts'    => 'delete_others_lcp_entries',
            'edit_private_posts'     => 'edit_private_lcp_entries',
            'edit_published_posts'   => 'edit_published_lcp_entries',
            'delete_private_posts'   => 'delete_private_lcp_entries',
            'delete_published_posts' => 'delete_published_lcp_entries',
            // Hard-disable publishing for this CPT
            'publish_posts'          => 'do_not_allow',
        ],
    ));

    /* -----------------------------
     * CPT #2: Cities/regions (lcp_city)
     * -----------------------------
     * - Stores background data for case study cities/regions.
     * - Same constraints as lcp_entry (no public exposure, no publishing).
     */
    $labels = array(
        'name'               => 'Cities/regions',
        'singular_name'      => 'City',
        'menu_name'          => 'Cities/regions',
        'name_admin_bar'     => 'City',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New City',
        'new_item'           => 'New City',
        'edit_item'          => 'Edit City',
        'view_item'          => 'View City',
        'all_items'          => 'All Cities/regions',
        'search_items'       => 'Search Cities/regions',
        'not_found'          => 'No cities found.',
        'not_found_in_trash' => 'No cities found in Trash.',
        'description'        => 'Data on the cities',
    );

    register_post_type('lcp_city', array(
        'labels' => $labels,
        // Visibility: backend-only
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => false,               // NOT exposed in REST
        'query_var'          => false,
        'rewrite'            => false,
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-location-alt',

        // Editing features
        'supports'           => array('title', 'author', 'custom-fields'),

        // Capability model mirroring lcp_entry
        'map_meta_cap'       => true,
        'capability_type'    => ['lcp_city', 'lcp_cities'],
        'capabilities'       => [
            'create_posts'           => 'create_lcp_cities',
            'edit_post'              => 'edit_lcp_city',
            'read_post'              => 'read_lcp_city',
            'delete_post'            => 'delete_lcp_city',

            'edit_posts'             => 'edit_lcp_cities',
            'edit_others_posts'      => 'edit_others_lcp_cities',
            'read_private_posts'     => 'read_private_lcp_cities',
            'delete_posts'           => 'delete_lcp_cities',
            'delete_others_posts'    => 'delete_others_lcp_cities',
            'edit_private_posts'     => 'edit_private_lcp_cities',
            'edit_published_posts'   => 'edit_published_lcp_cities',
            'delete_private_posts'   => 'delete_lcp_cities',
            'delete_published_posts' => 'delete_published_lcp_cities',

            // No publishing
            'publish_posts'          => 'do_not_allow',
        ],
    ));
}


/* -----------------------------------------------------------------------------
 * 2) DASHBOARD WIDGETS (TEAM-FILTERED LISTS)
 * -------------------------------------------------------------------------- */

add_action('wp_dashboard_setup', 'lcp_dashboard_widgets');
function lcp_dashboard_widgets() {
    // Widget listing recent LCP entries for the current team
    wp_add_dashboard_widget(
        'lcp_dashboard_entries',
        'LCP',
        'lcp_render_dashboard_entries_widget'
    );

    // Widget listing recent Cities/regions for the current team
    wp_add_dashboard_widget(
        'lcp_dashboard_cities',
        'Cities/regions',
        'lcp_render_dashboard_cities_widget'
    );
}

/**
 * Render a simple list of recent LCP entries for the current user's team.
 * - Team label is read from user meta 'lcp_team'.
 * - We collect user IDs in the same team and restrict the list via author__in.
 */
function lcp_render_dashboard_entries_widget() {
    $team = lcp_user_team();
    if (!$team) {
        echo '<p>You are not assigned to a team.</p>';
        return;
    }

    $user_ids = get_users(array(
        'meta_key'   => 'lcp_team',
        'meta_value' => $team,
        'fields'     => 'ID',
    ));

    $posts = get_posts(array(
        'post_type'      => 'lcp_entry',
        'post_status'    => 'any',      // internal use; drafts etc.
        'posts_per_page' => 50,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'author__in'     => $user_ids,
    ));

    echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=lcp_entry')) . '" class="button button-primary">Add new LCP</a></p>';

    if ($posts) {
        echo '<ul>';
        foreach ($posts as $post) {
            printf(
                '<li><a href="%s">%s</a> <small>by %s</small></li>',
                esc_url(get_edit_post_link($post->ID)),
                esc_html(get_the_title($post)),
                esc_html(get_the_author_meta('display_name', $post->post_author))
            );
        }
        echo '</ul>';
    }
}

/**
 * Render a simple list of recent Cities/regions for the current user's team.
 * Mirrors the logic of the LCP widget above.
 */
function lcp_render_dashboard_cities_widget() {
    $team = lcp_user_team();
    if (!$team) {
        echo '<p>You are not assigned to a team.</p>';
        return;
    }

    $user_ids = get_users(array(
        'meta_key'   => 'lcp_team',
        'meta_value' => $team,
        'fields'     => 'ID',
    ));

    $posts = get_posts(array(
        'post_type'      => 'lcp_city',
        'post_status'    => 'any',
        'posts_per_page' => 50,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'author__in'     => $user_ids,
    ));

    echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=lcp_city')) . '" class="button button-primary">Add new City</a></p>';

    if ($posts) {
        echo '<ul>';
        foreach ($posts as $post) {
            printf(
                '<li><a href="%s">%s</a> <small>by %s</small></li>',
                esc_url(get_edit_post_link($post->ID)),
                esc_html(get_the_title($post)),
                esc_html(get_the_author_meta('display_name', $post->post_author))
            );
        }
        echo '</ul>';
    }
}


/* -----------------------------------------------------------------------------
 * 3) HIDE CPT MENUS FOR CONTRIBUTORS
 * -------------------------------------------------------------------------- */

/**
 * For contributors, hide direct CPT menus to drive their workflow via the
 * dashboard widgets (more guided and less cluttered). Admins keep full menus.
 */
add_action('admin_menu', 'lcp_hide_menus_for_contributors', 999);
function lcp_hide_menus_for_contributors() {
    if (current_user_can('contributor')) {
        remove_menu_page('edit.php?post_type=lcp_entry');
        remove_menu_page('edit.php?post_type=lcp_city');
    }
}


/* -----------------------------------------------------------------------------
 * 4) UTILITY: Current user's team (user meta 'lcp_team')
 * -------------------------------------------------------------------------- */

function lcp_user_team($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    return get_user_meta($user_id, 'lcp_team', true);
}


/* -----------------------------------------------------------------------------
 * 5) EDITOR UX: Custom “Save” box + disable autosave
 * -------------------------------------------------------------------------- */

/**
 * Replace the standard "Publish" meta box with a simplified "Save" box for both CPTs.
 * RATIONALE:
 * - Publishing is disabled by caps; we also simplify the UI to remove ambiguity.
 */
add_action('add_meta_boxes', 'lcp_customize_save_boxes', 10);
function lcp_customize_save_boxes() {
    // LCP
    remove_meta_box('submitdiv', 'lcp_entry', 'side');
    add_meta_box(
        'lcp_custom_save_entry',
        'Save LCP',
        'lcp_render_custom_save_box',
        'lcp_entry',
        'side',
        'high'
    );

    // City
    remove_meta_box('submitdiv', 'lcp_city', 'side');
    add_meta_box(
        'lcp_custom_save_city',
        'Save City',
        'lcp_render_custom_save_box',
        'lcp_city',
        'side',
        'high'
    );
}

/**
 * Output for the custom "Save" meta box.
 * - Explains that autosave is disabled and encourages frequent manual saves.
 */
function lcp_render_custom_save_box() {
    echo '<div class="submitbox">';
    echo '<div id="major-publishing-actions">';
    echo '<div id="publishing-action">';
    echo '<input name="save" type="submit" class="button button-primary button-large" value="Save">';
    echo '</div><div class="clear"></div>';
    echo '</div>'; // major-publishing-actions

    echo '<div class="misc-pub-section" style="margin-top:10px; font-size:13px; color:#555;">';
    echo '<p><strong>Note:</strong> Autosave is disabled. Remember to click <strong>Save</strong> after each change to make sure your latest updates are stored.</p>';
    echo '<p>Frequent saving also helps avoid conflicts when several team members work on the same entry.</p>';
    echo '</div>';

    echo '</div>'; // submitbox
}

/**
 * Disable WordPress autosave on our CPT editor screens.
 * Avoids unexpected intermediate revisions when multiple team members collaborate.
 */
add_action('admin_enqueue_scripts', 'lcp_disable_autosave', 10);
function lcp_disable_autosave() {
    global $post;
    if (isset($post->post_type) && in_array($post->post_type, array('lcp_entry', 'lcp_city'), true)) {
        wp_dequeue_script('autosave');
    }
}


/* -----------------------------------------------------------------------------
 * 6) HIDE "AUTHOR" BOX FOR NON-ADMINS
 * -------------------------------------------------------------------------- */

/**
 * Author selection is not needed for non-admins; the author is the current contributor.
 * Hiding reduces confusion and prevents manual reassignments outside team policy.
 */
add_action('add_meta_boxes', function () {
    if (!current_user_can('administrator')) {
        remove_meta_box('authordiv', 'lcp_entry', 'normal');
        remove_meta_box('authordiv', 'lcp_city',  'normal');
    }
}, 99);


/* -----------------------------------------------------------------------------
 * 7) ENSURE ADMINS HAVE ALL LCP/CITY CAPS
 * -------------------------------------------------------------------------- */

/**
 * Idempotently grant all custom CPT caps to administrators on admin_init.
 * Useful safety net if roles are modified elsewhere.
 */
add_action('admin_init', function () {
    $role = get_role('administrator');
    if (!$role) return;

    $caps = [
        // LCP single + collection
        'create_lcp_entries','edit_lcp_entries','read_private_lcp_entries',
        'edit_others_lcp_entries','delete_lcp_entries','delete_others_lcp_entries',
        'edit_private_lcp_entries','edit_published_lcp_entries',
        'delete_private_lcp_entries','delete_published_lcp_entries',
        'edit_lcp_entry','read_lcp_entry','delete_lcp_entry',

        // City single + collection
        'create_lcp_cities','edit_lcp_cities','read_private_lcp_cities',
        'edit_others_lcp_cities','delete_lcp_cities','delete_others_lcp_cities',
        'edit_private_lcp_cities','edit_published_lcp_cities',
        'delete_private_lcp_cities','delete_published_lcp_cities',
        'edit_lcp_city','read_lcp_city','delete_lcp_city',
    ];

    foreach ($caps as $cap) {
        if (!$role->has_cap($cap)) {
            $role->add_cap($cap);
        }
    }
});
