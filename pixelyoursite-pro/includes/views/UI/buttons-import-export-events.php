<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="export-import-buttons">
    <input type="hidden" id="import_events_file_nonce" value="<?=wp_create_nonce("import_events_file_nonce")?>"/>
    <input type="file" id="import_events_file" name="import_events_file" accept="application/json"/>
    <label for="import_events_file" class="btn-small btn-gray btn-small-icon secondary_heading"><i class="icon-import"></i>Import Events</label>
    <a href="<?php echo esc_url( $export_url ); ?>" target="_blank" class="btn-small btn-gray btn-small-icon secondary_heading"><i class="icon-export"></i>Export Events</a>
</div>