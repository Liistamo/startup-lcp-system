<?php
/**
 * Tools → Export LCP Data (admin only)
 * CSV-only with a team dropdown fed from user meta.
 *
 * DESIGN NOTES (2025-10-17):
 * - We do NOT expose CPTs to the public REST API; this module registers its own
 *   private/admin-only REST route to fetch rows for export/preview.
 * - "Team" is NOT stored on the post; it lives on the author user (user_meta 'lcp_team').
 *   Therefore, when a team filter is applied, we translate it to author__in based on users
 *   whose lcp_team matches the selected value.
 * - We keep the output generic: always include id, title, team (derived from author),
 *   then add all non-internal post meta (keys not starting with "_"). For ACF Google Map
 *   stored as a serialized array, we expand into individual columns (<meta>_address, _lat, etc.).
 * - Only CSV export is provided (no Excel dependency).
 *
 * Reproducibility:
 * - This code uses core WordPress APIs (admin_menu, WP_Query, register_rest_route, get_users).
 * - No external libraries are required; client-side CSV generation is done in a small JS file.
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 * Utility: Collect distinct team labels from user meta 'lcp_team'
 * -------------------------------------------------------------------------
 * We build the dropdown from users who currently exist in the installation.
 * NOTE FOR OPERATORS: The list will only show teams that are registered on
 * this site (i.e., teams assigned to at least one user).
 */
function lcp_export_get_distinct_teams(): array {
    $users = get_users([
        'meta_key' => 'lcp_team',
        'fields'   => ['ID'],
        'number'   => -1,
    ]);
    $set = [];
    foreach ($users as $u) {
        $t = get_user_meta($u->ID, 'lcp_team', true);
        if (is_string($t) && $t !== '') {
            $set[$t] = true;
        }
    }
    $teams = array_keys($set);
    sort($teams, SORT_NATURAL | SORT_FLAG_CASE);
    return $teams;
}

/* -------------------------------------------------------------------------
 * Admin page under Tools
 * ------------------------------------------------------------------------- */
add_action('admin_menu', function () {
    add_management_page(
        'Export LCP Data',          // Page title
        'Export LCP Data',          // Menu title
        'manage_options',           // Admin only
        'lcp-export',               // Slug
        'lcp_export_tools_page'     // Callback
    );
});

function lcp_export_tools_page() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    $ver = '0.6.0';

    // Our admin JS (CSV-only + preview)
    wp_enqueue_script(
        'lcp-export-js',
        get_template_directory_uri() . '/inc/export-lcp-data.js',
        [],
        $ver,
        true
    );

    // Localized data for JS
    wp_localize_script('lcp-export-js', 'LCP_EXPORT', [
        'rest'       => [
            'base'  => esc_url_raw( rest_url('lcp-export/v1') ),
            'nonce' => wp_create_nonce('wp_rest'),
        ],
        'filePrefix' => 'startup-lcp'
    ]);

    // Build team choices from user meta (see helper above)
    $teams = lcp_export_get_distinct_teams();

    echo '<div class="wrap">';
    echo '<h1>Export LCP Data</h1>';
    echo '<p>Export internal datasets to <strong>CSV</strong>. Admin only.</p>';
    echo '<table class="form-table"><tbody>';

    // Post type selector
    echo '<tr>';
    echo '<th scope="row"><label for="lcp-posttype">Post type</label></th>';
    echo '<td>';
    echo '<select id="lcp-posttype">';
    echo '<option value="lcp_entry" selected>LCP entries (lcp_entry)</option>';
    echo '<option value="lcp_city">Cities/regions (lcp_city)</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';

    // Team dropdown (built from users' lcp_team)
    echo '<tr>';
    echo '<th scope="row"><label for="lcp-team-select">Team (optional)</label></th>';
    echo '<td>';
    echo '<select id="lcp-team-select">';
    echo '<option value="">All teams</option>';
    foreach ($teams as $t) {
        printf(
            '<option value="%s">%s</option>',
            esc_attr($t),
            esc_html($t)
        );
    }
    echo '</select>';
    echo '<p class="description" style="margin-top:6px;">';
    echo 'Note: This list only shows teams that are currently registered on this site (derived from user meta <code>lcp_team</code>).';
    echo '</p>';
    echo '</td>';
    echo '</tr>';

    echo '</tbody></table>';

    echo '<p>';
    echo '<button id="lcp-refresh-preview" class="button">Refresh preview</button> ';
    echo '<button id="lcp-export-csv" class="button button-primary">Download CSV</button>';
    echo '</p>';

    echo '<div id="lcp-export-status" style="margin:8px 0;"></div>';

    // Preview container
    echo '<div id="lcp-export-preview" style="margin-top:10px;">';
    echo '<h2 style="margin-bottom:8px;">Preview (first 20 rows)</h2>';
    echo '<div id="lcp-export-preview-table" style="overflow:auto; max-height:420px; border:1px solid #ddd; background:#fff;"></div>';
    echo '</div>';

    // Minimal styles for table
    echo '<style>
      .lcp-table { border-collapse: collapse; width: 100%; font-size: 12px; }
      .lcp-table th, .lcp-table td { border: 1px solid #eee; padding: 6px 8px; vertical-align: top; }
      .lcp-table thead th { position: sticky; top: 0; background: #f6f7f7; z-index: 1; }
      .lcp-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
      .lcp-nowrap { white-space: nowrap; }
      .lcp-dim { color:#666; }
    </style>';

    echo '</div>';
}

/* -------------------------------------------------------------------------
 * Private REST route
 * ------------------------------------------------------------------------- */
add_action('rest_api_init', function () {
    register_rest_route('lcp-export/v1', '/entries', [
        'methods'  => 'GET',
        'callback' => 'lcp_export_rest_entries',
        'permission_callback' => function () {
            return current_user_can('manage_options'); // admin only
        },
        'args' => [
            'post_type' => [
                'description' => 'CPT to export',
                'type'        => 'string',
                'enum'        => ['lcp_entry','lcp_city'],
                'default'     => 'lcp_entry',
            ],
            'team' => [
                // IMPORTANT: This is a user team label from user meta 'lcp_team',
                // not a post meta. We will translate it into author__in in the query.
                'description' => 'Filter by user meta "lcp_team" (exact label, author-based filter)',
                'type'        => 'string',
                'required'    => false,
            ],
            'paged' => [
                'description' => 'Page (1-based)',
                'type'        => 'integer',
                'minimum'     => 1,
                'default'     => 1,
            ],
            'per_page' => [
                'description' => 'Rows per page (max 2000)',
                'type'        => 'integer',
                'minimum'     => 1,
                'maximum'     => 2000,
                'default'     => 1000,
            ],
            'status' => [
                'description' => 'WP post_status filter',
                'type'        => 'string',
                'enum'        => ['any','draft','private','pending','inherit','trash'],
                'default'     => 'any',
            ],
        ],
    ]);
});

/* -------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */
function lcp_is_serialized_array_string($val) {
    return is_string($val) && preg_match('/^a:\d+:{.*}$/s', $val);
}

/**
 * Detect ACF Google Map stored as serialized array and expand to columns.
 * Returns true if handled, false otherwise.
 */
function lcp_expand_acf_map_array(&$row, $key, $maybe) {
    if (!is_array($maybe)) return false;

    $map_keys = [
        'address','lat','lng','zoom','place_id','name',
        'street_number','street_name','city','state','post_code','country','country_short'
    ];

    $has_any = false;
    foreach ($map_keys as $mk) {
        if (array_key_exists($mk, $maybe)) {
            $row[$key . '_' . $mk] = is_scalar($maybe[$mk]) ? (string)$maybe[$mk] : wp_json_encode($maybe[$mk], JSON_UNESCAPED_UNICODE);
            $has_any = true;
        }
    }
    return $has_any;
}

/* -------------------------------------------------------------------------
 * Main REST callback
 * ------------------------------------------------------------------------- */
function lcp_export_rest_entries( WP_REST_Request $req ) {
    $post_type = $req->get_param('post_type') ?: 'lcp_entry';
    $team      = trim((string)$req->get_param('team') ?: '');
    $paged     = max(1, (int)$req->get_param('paged'));
    $per_page  = min(2000, max(1, (int)$req->get_param('per_page')));
    $status    = $req->get_param('status') ?: 'any';

    $q = [
        'post_type'      => $post_type,
        'post_status'    => $status,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ];

    // Translate team label → author__in based on users whose user_meta('lcp_team') matches
    if ($team !== '') {
        $author_ids = get_users([
            'meta_key'   => 'lcp_team',
            'meta_value' => $team,
            'fields'     => 'ID',
            'number'     => -1,
        ]);
        // If no authors for that team, constrain to none
        $q['author__in'] = $author_ids ? array_map('intval', $author_ids) : [0];
    }

    $wpq  = new WP_Query($q);
    $ids  = $wpq->posts;
    $rows = [];

    foreach ($ids as $id) {
        $author_id = (int) get_post_field('post_author', $id);
        $team_user = (string) get_user_meta($author_id, 'lcp_team', true);

        $row = [
            'id'    => $id,
            'title' => get_the_title($id),
            'team'  => $team_user, // derived from author meta
        ];

        // Include ALL non-internal meta (unknown future fields).
        // We skip keys starting with "_" to avoid internal/_acf noise.
        $all_meta = get_post_meta($id);
        foreach ($all_meta as $k => $vals) {
            if (strpos($k, '_') === 0) continue;    // skip internal/_acf keys
            if (!is_array($vals)) continue;

            // Normalize to single value if single meta
            $val = count($vals) === 1 ? $vals[0] : $vals;

            // If PHP-serialized string -> decode
            if (is_string($val) && lcp_is_serialized_array_string($val)) {
                $decoded = @maybe_unserialize($val);
                // If decoded looks like an ACF Map array -> expand
                if (is_array($decoded) && lcp_expand_acf_map_array($row, $k, $decoded)) {
                    continue;
                }
                // Checkbox-like flat arrays: flatten nicely; otherwise JSON
                if (is_array($decoded)) {
                    $is_assoc = count(array_filter(array_keys($decoded), 'is_string')) > 0;
                    if (!$is_assoc) {
                        $val = implode(', ', array_map('strval', $decoded));
                    } else {
                        $val = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $val = (string)$decoded;
                }
            }

            // If ACF returns array (e.g., repeater/relationship) -> JSON encode
            if (is_array($val)) {
                $row[$k] = wp_json_encode($val, JSON_UNESCAPED_UNICODE);
            } else {
                $row[$k] = $val;
            }
        }

        $rows[] = $row;
    }

    $resp = [
        'rows'       => $rows,
        'pagination' => [
            'paged'     => (int)$paged,
            'per_page'  => (int)$per_page,
            'total'     => (int)$wpq->found_posts,
            'max_pages' => (int)$wpq->max_num_pages,
        ],
        'post_type'  => $post_type,
        'team'       => $team, // echo back the filter parameter
        'status'     => $status,
    ];

    return new WP_REST_Response($resp, 200);
}
