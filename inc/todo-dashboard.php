<?php
if (!defined('ABSPATH')) exit;

/**
 * Dashboard Widget: Contributor “How it works” instructions
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - This widget provides lightweight onboarding guidance for contributors.
 * - Appears on the WordPress Dashboard (wp-admin/index.php) immediately after login.
 * - The copy explains the minimal workflow for contributors using the LCP system.
 *
 * POSITIONING
 * - By default, it registers in the "side" (right) column at the top (“high” priority).
 * - If the user’s dashboard layout has a single column, WordPress automatically places
 *   side-context widgets in the main column, effectively showing this widget first.
 *
 * FEATURES
 * - Plain HTML for clarity and speed.
 * - Styled inline (no external stylesheet dependency).
 * - Only visible to users who can `read` (contributors and above).
 *
 * REPRODUCIBILITY
 * - No dependencies, uses only `wp_add_dashboard_widget()`.
 * - Safe for inclusion in custom themes or mu-plugins.
 *
 * SECURITY
 * - No dynamic content or user data output, so escaping not required here.
 */


/* -----------------------------------------------------------------------------
 * 1) RENDER FUNCTION
 * -------------------------------------------------------------------------- */

/**
 * Outputs the instructional content for contributors.
 * - Encourages creation of LCP or City entries.
 * - Mentions basic workflow: add, save, log out.
 * - Styled inline for minimal footprint.
 */
function lcp_render_todo_widget() {
    if (!current_user_can('read')) return; // safety guard (non-authenticated users won't see dashboard anyway)
    ?>
    <div class="lcp-todo-widget">
        <p><strong>Create your first entry</strong><br>
        In the Dashboard, click <em>Add new LCP</em> to create a new LCP.<br>
        Add a title and fill in the fields according to the instructions from Sabine and Jan.<br>
        If you want to add location information instead, click <em>Add new City</em>, fill in the fields, and click <em>Save</em>.</p>

        <p><strong>Navigate and continue working</strong><br>
        In the Dashboard, go to <em>LCP</em> or <em>Cities/Regions</em> to see your saved entries. Click one to open and continue editing.</p>

        <p><strong>Save often</strong><br>
        Click <em>Save</em> whenever you make changes.</p>

        <p><strong>Done for now?</strong><br>
        Log out via the menu when you’re finished.</p>
    </div>

    <style>
        /* Inline styling for self-containment and simplicity */
        .lcp-todo-widget p { margin: 0 0 12px; }
        .lcp-todo-widget em { font-style: normal; text-decoration: underline; }
    </style>
    <?php
}


/* -----------------------------------------------------------------------------
 * 2) REGISTER WIDGET
 * -------------------------------------------------------------------------- */

/**
 * Registers the “How it works” dashboard widget.
 * - Appears in the right column (“side”) at the top (“high” priority).
 * - Registered late (priority 99) so it reliably appears after all other widgets.
 *   This ensures WordPress doesn’t override its position when other plugins load first.
 */
function lcp_register_todo_widget() {
    wp_add_dashboard_widget(
        'lcp_todo_widget',       // Widget ID (unique slug)
        'How it works',          // Widget title
        'lcp_render_todo_widget',// Callback to render the content
        null,                    // No control callback
        null,                    // No arguments
        'side',                  // Display in right-hand column (or main column if single layout)
        'high'                   // Top position in column
    );
}

// Run after all default widgets are added to ensure final positioning
add_action('wp_dashboard_setup', 'lcp_register_todo_widget', 99);
