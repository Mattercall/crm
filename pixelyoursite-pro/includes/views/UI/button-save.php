<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$added_classes = 'save-settings';
if (isset($_GET['tab']) && $_GET['tab'] === 'events' && isset($_GET['action']) && $_GET['action'] === 'edit') :
    $added_classes .= ' edit-event';
endif;
?>

<div class="<?php echo esc_attr($added_classes); ?>">
    <div class="video-link">
		<?php if ( !empty( PYS_VIDEO_URL ) && !empty( PYS_VIDEO_TITLE ) ) : ?>
            <span class="font-semibold">Recommended: </span>
            <a href="<?php echo esc_url( PYS_VIDEO_URL ); ?>" target="_blank" class="link link-underline">
				<?php echo esc_html( PYS_VIDEO_TITLE ); ?>
            </a>
		<?php endif; ?>
    </div>

    <div class="save-settings-actions">
		<?php
		if ( isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'events' && isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] === 'edit' ) : ?>
            <a href="<?php echo buildAdminUrl( 'pixelyoursite', 'events' ); ?>" class="back-button">Back</a>
		<?php endif; ?>
        <?php
        if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] === 'pixelyoursite_settings') : ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'pys_save_settings' );?>
            <input type="hidden" name="pys[reset_settings]" value="1">
            <button
                    type="submit"
                    class="back-button restore-settings"
                    data-title="<?php _e('Reset All Settings To Defaults', 'pys'); ?>"
                    data-content="<?php _e('If you continue, all your custom settings will be lost and the plugin will go back to the default configuration. If you use any add-ons, like the Pinterest add-on or the Super Pack, their settings will be affected too. Custom events and scripts added with the Head & Footer option won\'t be affected.', 'pys'); ?>"
                    data-button-yes="<?php _e('Yes, reset settings', 'pys'); ?>"
                    data-button-no="<?php _e('No, go back', 'pys'); ?>"
            >

                <?php _e('Restore settings', 'pys'); ?>
            </button>
        </form>
        <?php endif; ?>

        <button id="pys-save-settings">Save Changes</button>
    </div>
</div>