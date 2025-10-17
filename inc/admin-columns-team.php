<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Columns: Show and work with "Team" for lcp_entry and lcp_city
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - The "Team" is not stored on the post object. It lives on the author (user)
 *   as user meta: `lcp_team`.
 * - This module adds:
 *     1) a visible "Team" column in the post list tables for the CPTs
 *        `lcp_entry` and `lcp_city` (wp-admin/edit.php).
 *     2) sorting by the "Team" column.
 *     3) a top-of-table dropdown to filter by team.
 * - Sorting relies on a JOIN to the usermeta table (alias `um`) so we can
 *   order by the author's `lcp_team` value.
 * - Filtering translates the selected team into an `author__in` constraint,
 *   by finding users who have that `lcp_team`.
 *
 * REPRODUCIBILITY
 * - Uses only WordPress core hooks/APIs (no external deps).
 * - The JOIN alias (`um`) is scoped to the current request via `posts_join`.
 * - Defensive checks:
 *     * only modify main admin queries (`is_admin()` + `$q->is_main_query()`).
 *     * only target the two CPTs in question.
 *
 * PERFORMANCE
 * - Sorting will add a LEFT JOIN to the usermeta table on demand. On very large
 *   user tables, consider adding an index on (user_id, meta_key, meta_value)
 *   if performance becomes a concern.
 *
 * UX NOTE
 * - The filter dropdown lists only teams that are currently registered on the
 *   site (derived from existing users' `lcp_team` meta). Teams with no users
 *   will not appear.
 */

/**
 * 1) Add the visible "Team" column after the Title column.
 */
add_filter('manage_lcp_entry_posts_columns', 'lcp_add_team_col');
add_filter('manage_lcp_city_posts_columns',  'lcp_add_team_col');
function lcp_add_team_col($cols) {
    $new = [];
    foreach ($cols as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            // Insert our "Team" column immediately after the Title column.
            $new['lcp_team'] = __('Team', 'lcp');
        }
    }
    return $new;
}

/**
 * 2) Render the "Team" column cells by reading the author's user meta.
 *    We do not read post meta here because the team is an author property.
 */
add_action('manage_lcp_entry_posts_custom_column', 'lcp_show_team_col', 10, 2);
add_action('manage_lcp_city_posts_custom_column',  'lcp_show_team_col', 10, 2);
function lcp_show_team_col($column, $post_id) {
    if ($column !== 'lcp_team') return;

    // Get the post author and read their team label from user meta.
    $author_id = (int) get_post_field('post_author', $post_id);
    $team = get_user_meta($author_id, 'lcp_team', true);

    echo $team ? esc_html($team) : '<span style="color:#888;">–</span>';
}

/**
 * 3) Mark the "Team" column as sortable for both CPTs.
 *    This does not itself perform the sorting; it just enables the UI.
 */
add_filter('manage_edit-lcp_entry_sortable_columns', function($cols){
    $cols['lcp_team'] = 'lcp_team';
    return $cols;
});
add_filter('manage_edit-lcp_city_sortable_columns', function($cols){
    $cols['lcp_team'] = 'lcp_team';
    return $cols;
});

/**
 * 4) Apply sorting when the user clicks the "Team" column header.
 *    We sort by the author's user meta `lcp_team`, which requires a JOIN on usermeta.
 *
 *    Implementation details:
 *    - We set orderby to the usermeta alias column (`um.meta_value`).
 *    - We clear `meta_query` to avoid unexpected interactions with other meta filters.
 *    - We attach a `posts_join` filter to add the LEFT JOIN to usermeta (scoped to this request).
 */
add_action('pre_get_posts', function($q){
    if (!is_admin() || !$q->is_main_query()) return;

    $orderby  = $q->get('orderby');
    $postType = $q->get('post_type');

    if (in_array($postType, ['lcp_entry','lcp_city'], true) && $orderby === 'lcp_team') {
        global $wpdb;

        // Sort by the string value of usermeta (team label).
        // Using meta_value here since we are not doing numeric comparison.
        $q->set('orderby', 'um.meta_value');
        $q->set('meta_key', 'lcp_team'); // informative; not strictly required for JOIN
        $q->set('meta_type', 'CHAR');

        // Clear any other meta queries to avoid conflicts or redundant joins.
        $q->set('meta_query', []);

        // Add the JOIN only once and only for this query.
        add_filter('posts_join', function($join) use ($wpdb){
            // Avoid duplicating the JOIN if some other code already added it.
            if (strpos($join, "JOIN {$wpdb->usermeta}") === false) {
                $join .= " LEFT JOIN {$wpdb->usermeta} AS um"
                      .  " ON ({$wpdb->posts}.post_author = um.user_id AND um.meta_key = 'lcp_team')";
            }
            return $join;
        });
    }
});

/**
 * 5) Add a filter dropdown above the list table to filter by Team.
 *    SOURCE OF TRUTH:
 *    - Team labels are pulled from existing users' `lcp_team` user meta.
 *    - The dropdown therefore lists only teams that are currently registered on the site.
 *
 *    SECURITY:
 *    - The actual filtering is applied in a separate pre_get_posts hook (below),
 *      using author__in with the set of user IDs matching the chosen team.
 */
add_action('restrict_manage_posts', function($post_type){
    if (!in_array($post_type, ['lcp_entry','lcp_city'], true)) return;

    // Query users to collect distinct team labels.
    $users = get_users([
        'meta_key' => 'lcp_team',
        'fields'   => ['meta_value'], // returns an array of stdClass with 'meta_value'
        'number'   => -1,
    ]);
    // Extract and sanitize distinct values.
    $values = array_unique(array_filter(wp_list_pluck($users, 'meta_value')));
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);

    // Current selection from the request (if any).
    $current = isset($_GET['lcp_team_filter']) ? sanitize_text_field(wp_unslash($_GET['lcp_team_filter'])) : '';

    // Render dropdown
    echo '<label for="lcp_team_filter" class="screen-reader-text">Team</label>';
    echo '<select name="lcp_team_filter" id="lcp_team_filter">';
    echo '<option value="">' . esc_html__('All teams', 'lcp') . '</option>';
    foreach ($values as $t) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($t),
            selected($current, $t, false),
            esc_html($t)
        );
    }
    echo '</select>';

    // Small helper note for admins
    echo '<span class="description" style="margin-left:8px;">';
    echo esc_html__('Note: The list shows only teams currently registered on this site (from user meta lcp_team).', 'lcp');
    echo '</span>';
});

/**
 * 6) Apply the selected Team filter to the main query.
 *    - We translate the chosen team into author IDs that have user_meta('lcp_team') = <team>.
 *    - Then we set author__in to constrain posts to those authors.
 *
 *    Edge cases:
 *    - If no users have the selected team, we set author__in to [0] to return an empty list.
 */
add_action('pre_get_posts', function($q){
    if (!is_admin() || !$q->is_main_query()) return;

    $postType = $q->get('post_type');
    if (!in_array($postType, ['lcp_entry','lcp_city'], true)) return;

    if (!empty($_GET['lcp_team_filter'])) {
        $team = sanitize_text_field(wp_unslash($_GET['lcp_team_filter']));

        // Find user IDs whose lcp_team equals the requested value.
        $ids = get_users([
            'meta_key'   => 'lcp_team',
            'meta_value' => $team,
            'fields'     => 'ID',
            'number'     => -1,
        ]);

        // Constrain the query to those authors; use [0] to force no results if none found.
        $q->set('author__in', $ids ? array_map('intval', $ids) : [0]);
    }
});
