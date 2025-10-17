<?php
if (!defined('ABSPATH')) exit;

/**
 * Team Registration via Invite Codes
 * -----------------------------------------------------------------------------
 * DESIGN NOTES (2025-10-17)
 * - Users select/enter a short invite code at registration time.
 * - The code maps to a canonical team slug and is stored on the user as
 *   user meta `lcp_team`. This becomes the single source of truth for a user's team.
 *
 * FLOW
 *  1) Registration form: adds a text field "Team invite code".
 *  2) Validation: requires a non-empty code that exists in the configured map.
 *  3) On successful registration: resolves code → team slug and stores as user meta.
 *  4) Admin profile screen: administrators can view/change the user's team
 *     via a dropdown (values come from the same invite-code map).
 *  5) Dashboard widget: surfaces codes to admins and shows the current user's
 *     team + their team's code to contributors.
 *
 * REPRODUCIBILITY
 * - No plugins required. Uses only WordPress core hooks/APIs for registration
 *   form customization, validation, saving user meta, and dashboard widgets.
 * - Team/Code mapping is kept in a single function for easy maintenance.
 *
 * SECURITY
 * - Input is sanitized with `sanitize_text_field`.
 * - Errors are returned via the standard `registration_errors` filter.
 * - Admin-only profile editing is gated with `current_user_can('administrator')`.
 *
 * LIMITATIONS
 * - Invite code comparison is case-sensitive as written; adjust if needed.
 * - Codes are public to admins; non-admins only see their own team + code (if found).
 */


/* -----------------------------------------------------------------------------
 * 1) TEAM INVITE CODE MAP
 * -------------------------------------------------------------------------- */

/**
 * Return the set of invite code → team slug mappings.
 * - Keep this list in sync with your organization/team structure.
 * - Team slugs are lowercased canonical identifiers used across the site.
 */
function lcp_get_team_invite_codes() {
	return [
		'zup' => 'dortmund',
		'mek' => 'gdansk',
		'rav' => 'madrid',
		'dil' => 'rome',
		'won' => 'sevilla',
		'feb' => 'stockholm',
		'kyz' => 'strasbourg',
		'lom' => 'trencin',
	];
}


/* -----------------------------------------------------------------------------
 * 2) REGISTRATION FORM: ADD INVITE CODE FIELD
 * -------------------------------------------------------------------------- */

/**
 * Adds a simple text input to the default WP registration form.
 * - Label: "Team invite code"
 * - Name/ID: invite_code
 *
 * NOTE: This affects the classic /wp-login.php?action=register flow.
 */
function lcp_register_form_field() {
	echo '<p>
		<label for="invite_code">Team invite code<br/>
		<input type="text" name="invite_code" id="invite_code" class="input" value="" size="25" /></label>
	</p>';
}
add_action('register_form', 'lcp_register_form_field');


/* -----------------------------------------------------------------------------
 * 3) REGISTRATION VALIDATION
 * -------------------------------------------------------------------------- */

/**
 * Validates the invite code during registration.
 * - Requires a non-empty code.
 * - Requires that the code exists in the configured map.
 *
 * Return:
 * - Augments the WP_Error instance with messages on failure.
 */
function lcp_register_form_validation($errors, $sanitized_user_login, $user_email) {
	$code  = isset($_POST['invite_code']) ? sanitize_text_field($_POST['invite_code']) : '';
	$codes = lcp_get_team_invite_codes();

	if (empty($code)) {
		$errors->add('invite_code_error', __('<strong>ERROR</strong>: You must enter an invite code.'));
	} elseif (!array_key_exists($code, $codes)) {
		$errors->add('invite_code_invalid', __('<strong>ERROR</strong>: Invalid invite code.'));
	}

	return $errors;
}
add_filter('registration_errors', 'lcp_register_form_validation', 10, 3);


/* -----------------------------------------------------------------------------
 * 4) ON SUCCESSFUL REGISTRATION: ASSIGN TEAM
 * -------------------------------------------------------------------------- */

/**
 * If a valid invite code was posted, look up its team slug and store it on the user.
 * - User meta key: lcp_team
 * - This runs after core user creation has succeeded.
 */
function lcp_save_team_on_registration($user_id) {
	if (!empty($_POST['invite_code'])) {
		$code  = sanitize_text_field($_POST['invite_code']);
		$codes = lcp_get_team_invite_codes();

		if (isset($codes[$code])) {
			update_user_meta($user_id, 'lcp_team', $codes[$code]);
		}
	}
}
add_action('user_register', 'lcp_save_team_on_registration');


/* -----------------------------------------------------------------------------
 * 5) ADMIN: EDIT USER'S TEAM IN PROFILE
 * -------------------------------------------------------------------------- */

/**
 * Adds a "Team" section to the user profile screen (wp-admin/profile.php or user-edit.php).
 * - Admins only.
 * - Dropdown options are derived from the team slug values in the invite-code map.
 */
function lcp_add_team_meta_to_user_profile($user) {
	if (!current_user_can('administrator')) return;

	$current_team = get_user_meta($user->ID, 'lcp_team', true);
	$teams_map    = lcp_get_team_invite_codes(); // code => slug
	$team_slugs   = array_values($teams_map);    // only slugs for dropdown
	$team_slugs   = array_unique($team_slugs);
	sort($team_slugs, SORT_NATURAL | SORT_FLAG_CASE);
	?>
	<h2>Team</h2>
	<table class="form-table">
		<tr>
			<th><label for="lcp_team">Assigned Team</label></th>
			<td>
				<select name="lcp_team" id="lcp_team">
					<option value="">&mdash; Select team &mdash;</option>
					<?php foreach ($team_slugs as $slug): ?>
						<option value="<?php echo esc_attr($slug); ?>" <?php selected($current_team, $slug); ?>>
							<?php echo esc_html(ucfirst($slug)); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">Select or update the team this user belongs to.</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action('show_user_profile', 'lcp_add_team_meta_to_user_profile');
add_action('edit_user_profile', 'lcp_add_team_meta_to_user_profile');

/**
 * Saves the selected team slug back to user meta when an admin updates a profile.
 */
function lcp_save_team_meta_from_user_profile($user_id) {
	if (current_user_can('administrator') && isset($_POST['lcp_team'])) {
		update_user_meta($user_id, 'lcp_team', sanitize_text_field($_POST['lcp_team']));
	}
}
add_action('personal_options_update', 'lcp_save_team_meta_from_user_profile');
add_action('edit_user_profile_update', 'lcp_save_team_meta_from_user_profile');


/* -----------------------------------------------------------------------------
 * 6) DASHBOARD WIDGET: INVITE CODE INFO
 * -------------------------------------------------------------------------- */

/**
 * Adds a small dashboard widget:
 *  - Admins: see all available invite codes (code → team).
 *  - Non-admins:
 *      * If they have a team, show their team and the corresponding invite code.
 *      * If their team is not mapped to a code, ask them to contact an admin.
 *      * If they have no team, instruct them to contact an admin.
 */
function lcp_show_invite_code_in_dashboard() {
	wp_add_dashboard_widget(
		'lcp_team_invite_code',
		'Team Invite Code',
		'lcp_render_invite_code_dashboard_widget'
	);
}
add_action('wp_dashboard_setup', 'lcp_show_invite_code_in_dashboard');

/**
 * Render logic for the dashboard widget described above.
 */
function lcp_render_invite_code_dashboard_widget() {
	$current_user_id = get_current_user_id();
	$current_team    = lcp_user_team($current_user_id); // helper from elsewhere in theme
	$codes           = lcp_get_team_invite_codes();     // code => team slug

	if (current_user_can('administrator')) {
		echo '<p><strong>All available invite codes:</strong></p><ul>';
		foreach ($codes as $code => $team) {
			echo '<li><code>' . esc_html($code) . '</code> &rarr; ' . esc_html(ucfirst($team)) . '</li>';
		}
		echo '</ul><p>Invite codes assign new users to the correct team.</p>';

	} elseif ($current_team) {
		// Reverse lookup: find the code for this team, if any
		$invite_code = array_search($current_team, $codes, true);

		if ($invite_code) {
			echo '<p><strong>Your team:</strong> ' . esc_html(ucfirst($current_team)) . '</p>';
			echo '<p><strong>Invite code:</strong> <code>' . esc_html($invite_code) . '</code></p>';
			echo '<p>Use this code to invite others to your team.</p>';
		} else {
			echo '<p>Your team is not linked to a code. Contact an admin.</p>';
		}

	} else {
		echo '<p>You have not been assigned to a team. Contact an administrator.</p>';
	}
}
