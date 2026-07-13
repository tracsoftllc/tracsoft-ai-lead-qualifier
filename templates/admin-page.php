<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = array(
	'general' => 'General Settings',
	'flow' => 'Chatbot Flow',
	'scoring' => 'Scoring Rules',
	'routing' => 'Routing',
	'openai' => 'OpenAI Settings',
	'notion' => 'Notion Settings',
	'email' => 'Email Alerts',
	'knowledge' => 'Bot Knowledge',
	'prompt' => 'Advanced Prompt Settings',
	'logs' => 'Logs / Hot Lead Records',
);

if ( ! function_exists( 'tracsoft_lb_field' ) ) {
function tracsoft_lb_field( $name, $value, $type = 'text', $label = '', $attrs = '' ) {
	$id = 'tracsoft_lb_' . preg_replace( '/[^a-z0-9_]+/i', '_', $name );
	echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
	if ( 'textarea' === $type ) {
		echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="tracsoft_lb' . esc_attr( $name ) . '" ' . $attrs . '>' . esc_textarea( $value ) . '</textarea>';
	} elseif ( 'checkbox' === $type ) {
		echo '<label><input type="hidden" name="tracsoft_lb' . esc_attr( $name ) . '" value="0"><input id="' . esc_attr( $id ) . '" type="checkbox" name="tracsoft_lb' . esc_attr( $name ) . '" value="1" ' . checked( 1, $value, false ) . '> Enabled</label>';
	} elseif ( 'color' === $type ) {
		echo '<input class="tracsoft-lb-color-field" id="' . esc_attr( $id ) . '" type="text" name="tracsoft_lb' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-default-color="' . esc_attr( $value ) . '" ' . $attrs . '>';
	} else {
		echo '<input class="regular-text" id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="tracsoft_lb' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $attrs . '>';
	}
	echo '</td></tr>';
}
}
?>

<div class="wrap tracsoft-lb-admin">
	<h1><?php esc_html_e( 'Tracsoft Lead Bot', 'tracsoft-ai-lead-qualifier' ); ?></h1>
	<?php if ( ! empty( $_GET['notice'] ) ) : ?>
		<?php
		$notice = sanitize_text_field( wp_unslash( $_GET['notice'] ) );
		$notice_detail = get_transient( 'tracsoft_lb_admin_notice_' . get_current_user_id() );
		delete_transient( 'tracsoft_lb_admin_notice_' . get_current_user_id() );
		$is_error = false !== strpos( $notice, 'failed' );
		?>
		<div class="notice <?php echo $is_error ? 'notice-error' : 'notice-success'; ?> is-dismissible">
			<p><?php echo esc_html( str_replace( '_', ' ', $notice ) ); ?></p>
			<?php if ( $notice_detail ) : ?>
				<p><strong>Details:</strong> <?php echo esc_html( $notice_detail ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'tracsoft-lead-bot', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'logs' !== $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tracsoft_lb_save">
		<?php wp_nonce_field( 'tracsoft_lb_save' ); ?>
	<?php endif; ?>

	<?php if ( 'general' === $active_tab ) : ?>
		<table class="form-table" role="presentation">
			<?php
			tracsoft_lb_field( '[enabled]', $settings['enabled'], 'checkbox', 'Enable chatbot' );
			tracsoft_lb_field( '[display_all_pages]', $settings['display_all_pages'], 'checkbox', 'Display on all pages' );
			tracsoft_lb_field( '[include_pages]', $settings['include_pages'], 'text', 'Page include list' );
			tracsoft_lb_field( '[exclude_pages]', $settings['exclude_pages'], 'text', 'Page exclude list' );
			tracsoft_lb_field( '[widget_title]', $settings['widget_title'], 'text', 'Chat widget title' );
			tracsoft_lb_field( '[intro_message]', $settings['intro_message'], 'textarea', 'Chat widget intro message' );
			?>
			<tr><th scope="row">Chat widget position</th><td><select name="tracsoft_lb[position]"><option value="bottom_right" <?php selected( $settings['position'], 'bottom_right' ); ?>>Bottom right</option><option value="bottom_left" <?php selected( $settings['position'], 'bottom_left' ); ?>>Bottom left</option></select></td></tr>
			<?php
			tracsoft_lb_field( '[primary_color]', $settings['primary_color'], 'color', 'Primary color' );
			tracsoft_lb_field( '[secondary_color]', $settings['secondary_color'], 'color', 'Secondary color' );
			tracsoft_lb_field( '[button_label]', $settings['button_label'], 'text', 'Button label' );
			tracsoft_lb_field( '[privacy_note]', $settings['privacy_note'], 'textarea', 'Privacy note text' );
			tracsoft_lb_field( '[analytics_enabled]', $settings['analytics_enabled'], 'checkbox', 'Anonymous analytics' );
			?>
		</table>
	<?php elseif ( 'flow' === $active_tab ) : ?>
		<table class="form-table" role="presentation">
			<?php
			tracsoft_lb_field( '[flow][opening_question]', $settings['flow']['opening_question'], 'textarea', 'Opening question' );
			foreach ( $settings['flow']['service_labels'] as $key => $value ) {
				tracsoft_lb_field( '[flow][service_labels][' . $key . ']', $value, 'text', 'Service label: ' . $key );
			}
			foreach ( $settings['flow']['pain_questions'] as $key => $value ) {
				tracsoft_lb_field( '[flow][pain_questions][' . $key . ']', $value, 'textarea', 'Pain point question: ' . $key );
			}
			tracsoft_lb_field( '[flow][timeline_question]', $settings['flow']['timeline_question'], 'textarea', 'Timeline question' );
			foreach ( $settings['flow']['budget_questions'] as $key => $value ) {
				tracsoft_lb_field( '[flow][budget_questions][' . $key . ']', $value, 'textarea', 'Budget question: ' . $key );
			}
			tracsoft_lb_field( '[flow][decision_question]', $settings['flow']['decision_question'], 'textarea', 'Decision-maker question' );
			tracsoft_lb_field( '[flow][relationship_question]', $settings['flow']['relationship_question'], 'textarea', 'Relationship fit question' );
			tracsoft_lb_field( '[flow][hot_message]', $settings['flow']['hot_message'], 'textarea', 'Hot lead message' );
			tracsoft_lb_field( '[flow][hot_thank_you]', $settings['flow']['hot_thank_you'], 'textarea', 'Hot lead thank-you message' );
			tracsoft_lb_field( '[flow][qualified_message]', $settings['flow']['qualified_message'], 'textarea', 'Qualified lead message' );
			tracsoft_lb_field( '[flow][nurture_message]', $settings['flow']['nurture_message'], 'textarea', 'Nurture lead message' );
			tracsoft_lb_field( '[flow][low_fit_message]', $settings['flow']['low_fit_message'], 'textarea', 'Low-fit lead message' );
			?>
		</table>
	<?php elseif ( 'scoring' === $active_tab ) : ?>
		<h2>Weights</h2><table class="form-table" role="presentation">
			<?php foreach ( $settings['scoring']['weights'] as $key => $value ) { tracsoft_lb_field( '[scoring][weights][' . $key . ']', $value, 'number', ucwords( str_replace( '_', ' ', $key ) ) . ' weight' ); } ?>
		</table><h2>Thresholds</h2><table class="form-table" role="presentation">
			<?php foreach ( $settings['scoring']['thresholds'] as $key => $value ) { tracsoft_lb_field( '[scoring][thresholds][' . $key . ']', $value, 'number', ucwords( str_replace( '_', ' ', $key ) ) ); } ?>
		</table><h2>Budget Thresholds</h2><table class="form-table" role="presentation">
			<?php
			foreach ( $settings['scoring']['budgets'] as $service => $budget_settings ) {
				foreach ( $budget_settings as $key => $value ) {
					tracsoft_lb_field( '[scoring][budgets][' . $service . '][' . $key . ']', $value, is_numeric( $value ) ? 'number' : 'text', $service . ' ' . $key );
				}
			}
			?>
		</table>
	<?php elseif ( 'routing' === $active_tab ) : ?>
		<table class="form-table" role="presentation">
			<?php
			tracsoft_lb_field( '[consultation_url]', $settings['consultation_url'], 'url', 'Consultation URL' );
			tracsoft_lb_field( '[newsletter_url]', $settings['newsletter_url'], 'url', 'Newsletter URL' );
			tracsoft_lb_field( '[collect_hot_contact]', $settings['collect_hot_contact'], 'checkbox', 'Collect contact info for hot leads' );
			tracsoft_lb_field( '[create_notion_hot]', $settings['create_notion_hot'], 'checkbox', 'Create Notion record for hot leads' );
			tracsoft_lb_field( '[email_hot]', $settings['email_hot'], 'checkbox', 'Email hot lead alert' );
			?>
		</table>
	<?php elseif ( 'openai' === $active_tab ) : ?>
		<table class="form-table" role="presentation">
			<?php
			tracsoft_lb_field( '[openai_api_key]', $settings['openai_api_key'], 'password', 'OpenAI API key', 'autocomplete="off"' );
			tracsoft_lb_field( '[openai_model]', $settings['openai_model'], 'text', 'Model selection' );
			tracsoft_lb_field( '[openai_temperature]', $settings['openai_temperature'], 'number', 'Temperature', 'step="0.1" min="0" max="2"' );
			tracsoft_lb_field( '[openai_max_tokens]', $settings['openai_max_tokens'], 'number', 'Max response length' );
			tracsoft_lb_field( '[system_prompt]', $settings['system_prompt'], 'textarea', 'System prompt textarea', 'rows="14"' );
			?>
		</table>
	<?php elseif ( 'notion' === $active_tab ) : ?>
		<table class="form-table" role="presentation">
			<?php
			tracsoft_lb_field( '[notion_api_key]', $settings['notion_api_key'], 'password', 'Notion API key', 'autocomplete="off"' );
			tracsoft_lb_field( '[notion_database_id]', $settings['notion_database_id'], 'text', 'Notion database ID' );
			foreach ( $settings['notion_mapping'] as $key => $value ) {
				tracsoft_lb_field( '[notion_mapping][' . $key . ']', $value, 'text', 'Map field: ' . ucwords( str_replace( '_', ' ', $key ) ) );
			}
			?>
		</table>
	<?php elseif ( 'email' === $active_tab ) : ?>
		<table class="form-table" role="presentation">
			<?php
			tracsoft_lb_field( '[email_recipient]', $settings['email_recipient'], 'email', 'Hot lead alert recipient' );
			tracsoft_lb_field( '[email_from_name]', $settings['email_from_name'], 'text', 'From name' );
			tracsoft_lb_field( '[email_from_email]', $settings['email_from_email'], 'email', 'From email' );
			tracsoft_lb_field( '[email_subject]', $settings['email_subject'], 'text', 'Email subject template' );
			tracsoft_lb_field( '[email_body]', $settings['email_body'], 'textarea', 'Email body template', 'rows="16"' );
			?>
		</table>
	<?php elseif ( 'knowledge' === $active_tab ) : ?>
		<div class="tracsoft-lb-knowledge">
			<?php foreach ( $settings['knowledge'] as $index => $item ) : ?>
				<div class="tracsoft-lb-knowledge-item">
					<input type="hidden" name="tracsoft_lb[knowledge][<?php echo esc_attr( $index ); ?>][enabled]" value="0">
					<label>Enabled <input type="checkbox" name="tracsoft_lb[knowledge][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( 1, $item['enabled'] ); ?>></label>
					<label>Title <input class="regular-text" type="text" name="tracsoft_lb[knowledge][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $item['title'] ); ?>"></label>
					<textarea class="large-text" rows="5" name="tracsoft_lb[knowledge][<?php echo esc_attr( $index ); ?>][content]"><?php echo esc_textarea( $item['content'] ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>
	<?php elseif ( 'prompt' === $active_tab ) : ?>
		<p class="description">Editing this prompt may affect chatbot behavior and qualification accuracy.</p>
		<table class="form-table" role="presentation"><?php tracsoft_lb_field( '[system_prompt]', $settings['system_prompt'], 'textarea', 'System prompt editor', 'rows="18"' ); ?></table>
	<?php elseif ( 'logs' === $active_tab ) : ?>
		<table class="widefat striped">
			<thead><tr><th>Timestamp</th><th>Score</th><th>Status</th><th>Service</th><th>Contact</th><th>Email</th><th>Notion</th><th>Email Alert</th><th>Summary</th></tr></thead>
			<tbody>
			<?php foreach ( $logs as $log ) : ?>
				<tr><td><?php echo esc_html( $log->created_at ); ?></td><td><?php echo esc_html( $log->lead_score ); ?></td><td><?php echo esc_html( $log->lead_status ); ?></td><td><?php echo esc_html( $log->service_bucket ); ?></td><td><?php echo esc_html( $log->contact_name . ' / ' . $log->company_name ); ?></td><td><?php echo esc_html( $log->email ); ?></td><td><?php echo esc_html( $log->notion_status ); ?></td><td><?php echo esc_html( $log->email_status ); ?></td><td><details><summary>View</summary><pre><?php echo esc_html( $log->summary . "\n\n" . $log->transcript_json ); ?></pre></details></td></tr>
			<?php endforeach; ?>
			<?php if ( empty( $logs ) ) : ?><tr><td colspan="9">No hot lead records yet.</td></tr><?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( 'logs' !== $active_tab ) : ?>
		<?php submit_button( 'Save Settings' ); ?>
	</form>
	<?php endif; ?>

	<?php if ( 'openai' === $active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="tracsoft_lb_test_openai"><?php wp_nonce_field( 'tracsoft_lb_test_openai' ); ?><?php submit_button( 'Test API Connection', 'secondary' ); ?></form>
	<?php elseif ( 'notion' === $active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="tracsoft_lb_test_notion"><?php wp_nonce_field( 'tracsoft_lb_test_notion' ); ?><?php submit_button( 'Test Notion Connection', 'secondary' ); ?></form>
	<?php elseif ( 'email' === $active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="tracsoft_lb_test_email"><?php wp_nonce_field( 'tracsoft_lb_test_email' ); ?><?php submit_button( 'Send Test Email', 'secondary' ); ?></form>
	<?php elseif ( 'prompt' === $active_tab ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="tracsoft_lb_reset_prompt"><?php wp_nonce_field( 'tracsoft_lb_reset_prompt' ); ?><?php submit_button( 'Reset to Default Prompt', 'secondary' ); ?></form>
	<?php endif; ?>
</div>
