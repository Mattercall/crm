<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$integration = FluentCRMIntegration();
$rules = $integration->getOption( 'rules', array() );
$tags = $integration->getAvailableTags();
$standardEvents = $integration->getStandardEventNames();
?>

<div class="card card-primary">
	<div class="card-header">
		<h3 class="secondary_heading"><?php esc_html_e( 'FluentCRM Tag Event Mapping', 'pixelyoursite' ); ?></h3>
	</div>
	<div class="card-body">
		<p class="mb-24">
			<?php esc_html_e( 'Map FluentCRM tag changes to PixelYourSite events so tags like Lead or Customer can trigger tracking.', 'pixelyoursite' ); ?>
		</p>

		<div class="mb-24">
			<label class="primary_heading mb-8 d-block"><?php esc_html_e( 'Enable Integration', 'pixelyoursite' ); ?></label>
			<?php $integration->render_switcher_input( 'enabled' ); ?>
		</div>

		<?php if ( empty( $tags ) ) : ?>
			<p class="mb-24">
				<?php esc_html_e( 'FluentCRM tags were not found. Make sure FluentCRM is active and tags exist.', 'pixelyoursite' ); ?>
			</p>
		<?php endif; ?>

		<table class="widefat striped pys-table" id="pys-fluentcrm-rules">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rule', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Tags', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Event', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Destinations', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Params', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Fire once', 'pixelyoursite' ); ?></th>
					<th><?php esc_html_e( 'Remove', 'pixelyoursite' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $rules ) ) : ?>
				<?php foreach ( $rules as $index => $rule ) : ?>
					<?php
					$ruleId = $rule['id'] ?? '';
					$trigger = $rule['trigger'] ?? 'tag_added';
					$tagIds = $rule['tag_ids'] ?? array();
					$eventType = $rule['event_type'] ?? 'standard';
					$eventName = $rule['event_name'] ?? 'Lead';
					$destinations = $rule['destinations'] ?? array();
					$params = $rule['params'] ?? array();
					$fireOnce = ! empty( $rule['fire_once'] );
					$enabled = ! empty( $rule['enabled'] );
					?>
					<tr class="pys-fluentcrm-rule">
						<td>
							<input type="hidden" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $ruleId ); ?>">
							<label>
								<input type="hidden" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][enabled]" value="0">
								<input type="checkbox" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $enabled, true ); ?>>
								<?php esc_html_e( 'Enabled', 'pixelyoursite' ); ?>
							</label>
						</td>
						<td>
							<select name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][trigger]" class="pys-fluentcrm-trigger">
								<option value="tag_added" <?php selected( $trigger, 'tag_added' ); ?>><?php esc_html_e( 'When tag applied', 'pixelyoursite' ); ?></option>
								<option value="tag_removed" <?php selected( $trigger, 'tag_removed' ); ?>><?php esc_html_e( 'When tag removed', 'pixelyoursite' ); ?></option>
							</select>
						</td>
						<td>
							<select name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][tag_ids][]" multiple="multiple" class="pys-tags-pysselect2">
								<?php foreach ( $tags as $tagId => $tagName ) : ?>
									<option value="<?php echo esc_attr( $tagId ); ?>" <?php selected( in_array( $tagId, $tagIds, true ), true ); ?>>
										<?php echo esc_html( $tagName ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][event_type]" class="pys-fluentcrm-event-type">
								<option value="standard" <?php selected( $eventType, 'standard' ); ?>><?php esc_html_e( 'Standard', 'pixelyoursite' ); ?></option>
								<option value="custom" <?php selected( $eventType, 'custom' ); ?>><?php esc_html_e( 'Custom', 'pixelyoursite' ); ?></option>
							</select>
							<div class="mt-8 pys-fluentcrm-event-standard" <?php if ( $eventType !== 'standard' ) : ?>style="display:none;"<?php endif; ?>>
								<select name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][event_name]" <?php disabled( $eventType !== 'standard' ); ?>>
									<?php foreach ( $standardEvents as $standardEvent ) : ?>
										<option value="<?php echo esc_attr( $standardEvent ); ?>" <?php selected( $eventName, $standardEvent ); ?>>
											<?php echo esc_html( $standardEvent ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="mt-8 pys-fluentcrm-event-custom" <?php if ( $eventType !== 'custom' ) : ?>style="display:none;"<?php endif; ?>>
								<input type="text" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][event_name]" value="<?php echo esc_attr( $eventName ); ?>" placeholder="<?php esc_attr_e( 'Custom event name', 'pixelyoursite' ); ?>" <?php disabled( $eventType !== 'custom' ); ?>>
							</div>
						</td>
						<td>
							<label class="d-block">
								<input type="checkbox" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][destinations][]" value="facebook" <?php checked( in_array( 'facebook', $destinations, true ), true ); ?>>
								<?php esc_html_e( 'Meta', 'pixelyoursite' ); ?>
							</label>
							<label class="d-block">
								<input type="checkbox" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][destinations][]" value="ga" <?php checked( in_array( 'ga', $destinations, true ), true ); ?>>
								<?php esc_html_e( 'GA4', 'pixelyoursite' ); ?>
							</label>
							<label class="d-block">
								<input type="checkbox" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][destinations][]" value="gtm" <?php checked( in_array( 'gtm', $destinations, true ), true ); ?>>
								<?php esc_html_e( 'GTM', 'pixelyoursite' ); ?>
							</label>
						</td>
						<td>
							<label class="d-block">
								<?php esc_html_e( 'Value', 'pixelyoursite' ); ?>
								<input type="text" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][params][value]" value="<?php echo esc_attr( $params['value'] ?? '' ); ?>">
							</label>
							<label class="d-block">
								<?php esc_html_e( 'Currency', 'pixelyoursite' ); ?>
								<input type="text" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][params][currency]" value="<?php echo esc_attr( $params['currency'] ?? '' ); ?>" placeholder="USD">
							</label>
							<label class="d-block">
								<?php esc_html_e( 'Content name', 'pixelyoursite' ); ?>
								<input type="text" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][params][content_name]" value="<?php echo esc_attr( $params['content_name'] ?? '' ); ?>">
							</label>
						</td>
						<td>
							<label>
								<input type="hidden" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][fire_once]" value="0">
								<input type="checkbox" name="pys[fluentcrm][rules][<?php echo esc_attr( $index ); ?>][fire_once]" value="1" <?php checked( $fireOnce, true ); ?>>
								<?php esc_html_e( 'Once per contact/tag', 'pixelyoursite' ); ?>
							</label>
						</td>
						<td>
							<button type="button" class="btn button-remove-row pys-fluentcrm-remove-rule">
								<i class="icon-delete" aria-hidden="true"></i>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="pys-fluentcrm-empty">
					<td colspan="8"><?php esc_html_e( 'No rules configured yet.', 'pixelyoursite' ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<div class="mt-16">
			<button type="button" class="btn btn-primary btn-primary-type2" id="pys-fluentcrm-add-rule">
				<?php esc_html_e( 'Add Rule', 'pixelyoursite' ); ?>
			</button>
		</div>
	</div>
</div>

<script type="text/html" id="pys-fluentcrm-rule-template">
	<tr class="pys-fluentcrm-rule">
		<td>
			<input type="hidden" name="pys[fluentcrm][rules][__index__][id]" value="__uuid__">
			<label>
				<input type="hidden" name="pys[fluentcrm][rules][__index__][enabled]" value="0">
				<input type="checkbox" name="pys[fluentcrm][rules][__index__][enabled]" value="1" checked="checked">
				<?php esc_html_e( 'Enabled', 'pixelyoursite' ); ?>
			</label>
		</td>
		<td>
			<select name="pys[fluentcrm][rules][__index__][trigger]" class="pys-fluentcrm-trigger">
				<option value="tag_added"><?php esc_html_e( 'When tag applied', 'pixelyoursite' ); ?></option>
				<option value="tag_removed"><?php esc_html_e( 'When tag removed', 'pixelyoursite' ); ?></option>
			</select>
		</td>
		<td>
			<select name="pys[fluentcrm][rules][__index__][tag_ids][]" multiple="multiple" class="pys-tags-pysselect2">
				<?php foreach ( $tags as $tagId => $tagName ) : ?>
					<option value="<?php echo esc_attr( $tagId ); ?>"><?php echo esc_html( $tagName ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select name="pys[fluentcrm][rules][__index__][event_type]" class="pys-fluentcrm-event-type">
				<option value="standard"><?php esc_html_e( 'Standard', 'pixelyoursite' ); ?></option>
				<option value="custom"><?php esc_html_e( 'Custom', 'pixelyoursite' ); ?></option>
			</select>
			<div class="mt-8 pys-fluentcrm-event-standard">
				<select name="pys[fluentcrm][rules][__index__][event_name]">
					<?php foreach ( $standardEvents as $standardEvent ) : ?>
						<option value="<?php echo esc_attr( $standardEvent ); ?>"><?php echo esc_html( $standardEvent ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="mt-8 pys-fluentcrm-event-custom" style="display:none;">
				<input type="text" name="pys[fluentcrm][rules][__index__][event_name]" value="" placeholder="<?php esc_attr_e( 'Custom event name', 'pixelyoursite' ); ?>" disabled="disabled">
			</div>
		</td>
		<td>
			<label class="d-block">
				<input type="checkbox" name="pys[fluentcrm][rules][__index__][destinations][]" value="facebook">
				<?php esc_html_e( 'Meta', 'pixelyoursite' ); ?>
			</label>
			<label class="d-block">
				<input type="checkbox" name="pys[fluentcrm][rules][__index__][destinations][]" value="ga">
				<?php esc_html_e( 'GA4', 'pixelyoursite' ); ?>
			</label>
			<label class="d-block">
				<input type="checkbox" name="pys[fluentcrm][rules][__index__][destinations][]" value="gtm">
				<?php esc_html_e( 'GTM', 'pixelyoursite' ); ?>
			</label>
		</td>
		<td>
			<label class="d-block">
				<?php esc_html_e( 'Value', 'pixelyoursite' ); ?>
				<input type="text" name="pys[fluentcrm][rules][__index__][params][value]" value="">
			</label>
			<label class="d-block">
				<?php esc_html_e( 'Currency', 'pixelyoursite' ); ?>
				<input type="text" name="pys[fluentcrm][rules][__index__][params][currency]" value="" placeholder="USD">
			</label>
			<label class="d-block">
				<?php esc_html_e( 'Content name', 'pixelyoursite' ); ?>
				<input type="text" name="pys[fluentcrm][rules][__index__][params][content_name]" value="">
			</label>
		</td>
		<td>
			<label>
				<input type="hidden" name="pys[fluentcrm][rules][__index__][fire_once]" value="0">
				<input type="checkbox" name="pys[fluentcrm][rules][__index__][fire_once]" value="1">
				<?php esc_html_e( 'Once per contact/tag', 'pixelyoursite' ); ?>
			</label>
		</td>
		<td>
			<button type="button" class="btn button-remove-row pys-fluentcrm-remove-rule">
				<i class="icon-delete" aria-hidden="true"></i>
			</button>
		</td>
	</tr>
</script>

<script>
	jQuery( document ).ready( function ( $ ) {
		const $tableBody = $( '#pys-fluentcrm-rules tbody' );
		const template = $( '#pys-fluentcrm-rule-template' ).html();

		function bindRow( $row ) {
			$row.find( '.pys-fluentcrm-remove-rule' ).on( 'click', function () {
				$row.remove();
				if ( $tableBody.find( 'tr' ).length === 0 ) {
					$tableBody.append( '<tr class="pys-fluentcrm-empty"><td colspan="8"><?php echo esc_js( __( 'No rules configured yet.', 'pixelyoursite' ) ); ?></td></tr>' );
				}
			} );

			$row.find( '.pys-fluentcrm-event-type' ).on( 'change', function () {
				const $currentRow = $( this ).closest( 'tr' );
				const type = $( this ).val();
				const $standard = $currentRow.find( '.pys-fluentcrm-event-standard' );
				const $custom = $currentRow.find( '.pys-fluentcrm-event-custom' );
				$standard.toggle( type === 'standard' );
				$custom.toggle( type === 'custom' );
				$standard.find( 'select' ).prop( 'disabled', type !== 'standard' );
				$custom.find( 'input' ).prop( 'disabled', type !== 'custom' );
			} );
		}

		$( '#pys-fluentcrm-add-rule' ).on( 'click', function () {
			const uuid = `fluentcrm_${Date.now()}_${Math.floor( Math.random() * 100000 )}`;
			const rowHtml = template
				.replace( /__index__/g, uuid )
				.replace( /__uuid__/g, uuid );
			$tableBody.find( '.pys-fluentcrm-empty' ).remove();
			const $row = $( rowHtml );
			$tableBody.append( $row );
			bindRow( $row );
		} );

		$tableBody.find( '.pys-fluentcrm-rule' ).each( function () {
			bindRow( $( this ) );
		} );
	} );
</script>
