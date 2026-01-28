<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$type = "woo";

?>
<div class="wrap" id="pys">
    <div class=" pys-general-menu">
        <div class="pys-logo">
            <img src="<?php echo PYS_URL; ?>/dist/images/pys-logo.svg" alt="pys-logo">
        </div>

        <nav class="nav nav-tabs">

            <?php foreach ( getAdminPrimaryNavTabs() as $tab_key => $tab_data ) : ?>

                <?php

                $classes = array(
                    'nav-item',
                    'nav-link',
                );

                if ( $tab_key == getCurrentAdminTab() ) {
                    $classes[] = 'active';
                }

                $classes = implode( ' ', $classes );

                if ( isset( $tab_data[ 'class' ] ) ) {
                    $classes .= ' ' . $tab_data[ 'class' ];
                }

                ?>

                <a class="<?php echo esc_attr( $classes ); ?>"
                   href="<?php echo esc_url( $tab_data[ 'url' ] ); ?>">
                    <?php esc_html_e( $tab_data[ 'name' ] ); ?>
                </a>

            <?php endforeach; ?>

        </nav>
    </div>
    <h1 id="pys-title" class="primary_heading"><?php _e( 'WooCommerce Reports', 'pys' ); ?></h1>
    <div class="container pys_stat">
        <div class="general-row d-flex">
            <div class="general-col">

                <?php include __DIR__."/../../modules/statistic/views/html-report.php"?>
            </div>
        </div>
    </div>
</div>