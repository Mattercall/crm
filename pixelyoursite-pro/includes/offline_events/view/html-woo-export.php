<?php
namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="card card-style5 woo-export offline-conversions">
    <div class="card-header card-header-style3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <h4 class="secondary_heading_type2">Export transactions as offline conversions - Facebook (Meta)</h4>
        </div>

		<?php cardCollapseSettings(); ?>
    </div>

    <div class="card-body">
        <p class="primary_heading mb-24">Learn how to use it: <a href="https://www.youtube.com/watch?v=vNsiWh0cakA"
                                                                 target="_blank" class="link">watch video</a></p>

        <div class="woo-export-statuses mb-24">
            <div class="mb-12">
                <label class="primary_heading">Order status:</label>
            </div>
			<?php
			$allStatus = wc_get_order_statuses();
			foreach ( $allStatus as $key => $label ) :
				$checked = "";
				if ( $key == "wc-completed" ) {
					$checked = "checked";
				}
				$id = "pys_order_status_" . $key;

				?>
                <div class="small-checkbox">
                    <input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="order_status[]"
                           value="<?php echo esc_attr( $key ); ?>"
                           class="small-control-input order_status"
						<?php echo esc_attr( $checked ); ?>>
                    <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
                        <span class="small-control-indicator"><i class="icon-check"></i></span>
                        <span class="small-control-description"><?php echo wp_kses_post( $label ); ?></span>
                    </label>
                </div>
			<?php endforeach; ?>
        </div>

        <div class="line mb-24"></div>

        <div class="mb-24">
            <div class="mb-8">
                <label class="primary_heading">Select</label>
            </div>

			<?php PYS()->render_text_input( "woo_last_export_date", '', false, true ); ?>

            <div class="select-standard-wrap">
                <select class="select-standard"
                        id="woo_export_purchase">
                    <option value="export_last_time" selected="selected">Export from last time</option>
                    <option value="export_by_date">Export by dates</option>
                    <option value="export_all">Export all orders</option>
                </select>
            </div>
        </div>

        <div id="pys_purchase_export_datepickers" class="form-inline mt-24 mb-24" style="display: none">
            <div>
                <div class="mb-8">
                    <label for="from" class="primary_heading">From</label>
                </div>
                <input type="text" class="pys_datepicker pys_datepickers_from input-short" name="from">
            </div>

            <div>
                <div class="mb-8">
                    <label for="to" class="primary_heading">to</label>
                </div>

                <input type="text" class="pys_datepicker pys_datepickers_to input-short" name="to">
            </div>
        </div>

        <div class="woo-export-actions">
            <div class="woo-export-actions-buttons">
                <input type="hidden" id="woo_generate_export_wpnonce"
                       value="<?= wp_create_nonce( "woo_generate_export_wpnonce" ) ?>"/>
                <a href="#" target="_blank" class="btn btn-primary btn-primary-type2"
                   id="woo_generate_export"><?php _e( 'Export for Meta', 'pys' ); ?></a>

                <input type="hidden" id="woo_generate_export_wpnonce"
                       value="<?= wp_create_nonce( "woo_generate_export_wpnonce" ) ?>"/>
                <a href="#" target="_blank" class="btn btn-primary btn-primary-type2"
                   id="woo_generate_all_data_offline_events_report"><?php _e( 'Export all tracked data', 'pys' ); ?></a>
            </div>

            <div id="woo_generate_export_loading" style="display:none">
                <div class="export-loading">
                    <img src="<?php echo esc_url( PYS_URL . '/dist/images/loader.svg' ); ?>" class="pys-loader waiting"
                         alt="pys-loader"/>
                    <div>
                        <span class="current">0</span>/<span class="max">0</span>
                    </div>
                </div>
            </div>
        </div>

		<?php
        $offlineEvents = new OfflineEvents();
		$files = glob( trailingslashit( PYS_PATH ) . 'tmp/*csv' );
		usort( // sort by filemtime
			$files, function ( $file1, $file2 ) {
			return filemtime( $file1 ) > filemtime( $file2 ) ? -1 : 1;
		} );
		$sortedFiles = [];
		for ( $i = 0; $i < count( $files ); $i++ ) {
			if ( $i < 3 ) {
				$sortedFiles[] = $files[ $i ];
			} else {
				unlink( $files[ $i ] );
			}
		} ?>

        <div class="export_links_wrap" style="<?php if ( count( $sortedFiles ) == 0 ) echo "display:none" ?>">
            <ul class="export_links mt-24">
                <li class="export_links_title"><span class="primary-text-color primary_heading">Exports:</span></li>
				<?php
				foreach ( $sortedFiles as $file ) {
					$fileName = basename( $file, ".csv" );
					$parts = explode( "-", $fileName );

					$created = str_replace( "_", "/", $parts[ 0 ] );
					$type = $parts[ 1 ];
					$name = "<li data-name='$fileName'>Created on $created<span class='primary-text-color primary_heading'> Export ";
                    $fileUrl = $offlineEvents->getFileUrl($fileName);
					if ( $type == "export_all" ) {
						$name .= "All orders";
					} else {
						$start = str_replace( "_", "/", $parts[ 2 ] );
						$end = str_replace( "_", "/", $parts[ 3 ] );
						$name .= "from $start to $end";
					}
					$name .= "</span> - <a href='" . $fileUrl . "' download class='link'>download CSV</a></li>";
					echo $name;
				}
				?>
            </ul>
        </div>
    </div>
</div>
