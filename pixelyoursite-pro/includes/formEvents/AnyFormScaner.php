<?php

namespace PixelYourSite;

class AnyFormScanner {

    private static $_instance;

    private $dom;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
    public function __construct() {
        add_action( 'wp_ajax_pys_scan_any_form', [ $this, 'scan_handler' ] );

        add_action( 'template_redirect', function () {
            if ( isset( $_GET['pys_fake_cart'] ) && is_checkout() ) {
                if ( WC()->cart && WC()->cart->is_empty() ) {
                    $product_id = $this->pys_get_first_simple_product_id();

                    if ( $product_id ) {
                        WC()->cart->add_to_cart( $product_id, 1 );
                    }
                }
            }
            if ( isset($_GET['pys_fake_cart']) && function_exists( 'edd_is_checkout' ) &&
                edd_is_checkout() && !edd_get_cart_contents() ) {

                $download_id = $this->pys_get_first_edd_download_id();

                if ( $download_id ) {
                    edd_empty_cart(); // очистим на всякий случай
                    edd_add_to_cart( $download_id );
                }
            }
        }, 1 );
    }

    public function scan_handler() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Access denied' );
        }

        $urls = isset( $_POST['urls'] ) ? $_POST['urls'] : [];

        if ( empty( $urls ) || ! is_array( $urls ) ) {
            wp_send_json_error( 'Invalid URLs' );
        }

        $results = [];

        foreach ( $urls as $url ) {
            $parsed_url = wp_parse_url( $url );
            $clean_url = rtrim( $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'], '/' );
            $url_with_fake_cart = add_query_arg(
                [
                    'pys_fake_cart' => 1,
                    'ship_to_different_address' => 1
                ],
                $clean_url
            );

            $html = $this->fetch_url_content( $url_with_fake_cart );

            if ( ! $html ) {
                $results[] = [
                    'url' => $url_with_fake_cart,
                    'error' => 'Failed to fetch content'
                ];
                continue;
            }

            $forms_data = $this->parse_forms( $html );
            $results[] = [
                'url'   => $url,
                'forms' => $forms_data
            ];
        }

        wp_send_json_success( $results );
    }

    private function fetch_url_content( $url ) {
        $response = wp_remote_get( $url, [
            'sslverify' => false,
        ]  );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        return wp_remote_retrieve_body( $response );
    }

    private function parse_forms( $html ) {
        libxml_use_internal_errors( true );
        $this->dom = new \DOMDocument();
        $this->dom->loadHTML( $html );
        $xpath = new \DOMXPath( $this->dom );

        $forms = $this->dom->getElementsByTagName( 'form' );

        $results = [];

        foreach ( $forms as $form ) {
            $form_info = [];

            // Найти заголовок формы: H1 > H2 > ID/class
            $form_info['title'] = $this->find_form_title( $form, $xpath );
            $form_info['attributes'] = [
                'id'    => $form->getAttribute( 'id' ),
                'class' => $form->getAttribute( 'class' ),
                'action' => $form->getAttribute( 'action' ),
                'method' => $form->getAttribute( 'method' ),
            ];
            $form_info['selector'] = $this->generate_css_selector( $form );


            $fields = [];
            $field_tags = ['input', 'textarea', 'select'];

            foreach ( $field_tags as $tag ) {
                $elements = $form->getElementsByTagName( $tag );

                foreach ( $elements as $el ) {
                    $type = '';

                    if($el->getAttribute('type') === 'hidden') {
                        continue; // Пропускаем скрытые поля
                    }

                    if ( $tag === 'input' ) {
                        // если не указан, по умолчанию — text
                        $type = $el->hasAttribute('type')
                            ? $el->getAttribute('type')
                            : 'text';
                    } elseif ( $tag === 'textarea' ) {
                        $type = 'textarea';
                    } elseif ( $tag === 'select' ) {
                        $type = 'select';
                    }

                    $field = [
                        'tag'         => $tag,
                        'name'        => $el->getAttribute( 'name' ),
                        'id'          => $el->getAttribute( 'id' ),
                        'class'       => $el->getAttribute( 'class' ),
                        'type'        => $type,
                    ];

                    // Уникальный селектор
                    $field['selector'] = $this->generate_css_selector( $el );

                    $fields[] = $field;
                }
            }

            $form_info['fields'] = $fields;
            $results[] = $form_info;
        }

        return $results;
    }

    private function find_form_title( \DOMElement $form ) {
        $max_depth = 2;
        $current   = $form->parentNode;

        for ( $i = 0; $i < $max_depth && $current instanceof \DOMElement; $i++ ) {
            foreach ( ['h1', 'h2'] as $tag ) {
                $headings = $current->getElementsByTagName( $tag );
                if ( $headings->length > 0 ) {
                    return trim( $headings->item(0)->textContent );
                }
            }

            $current = $current->parentNode;
        }

        // fallback: id или class формы
        $id    = $form->getAttribute( 'id' );
        $class = $form->getAttribute( 'class' );

        if ( ! empty( $id ) ) {
            return "Form #$id";
        } elseif ( ! empty( $class ) ) {
            return "Form .$class";
        }

        return 'Unnamed form';
    }

    private function generate_css_selector( \DOMElement $el ) {
        $parts = [];
        $depth = 0;
        $max_depth = 6;

        while ( $el && $el->nodeType === XML_ELEMENT_NODE && $depth < $max_depth ) {
            $selector = $el->nodeName;

            if ( $el->hasAttribute( 'id' ) ) {
                $selector .= '#' . $el->getAttribute( 'id' );
                $parts[] = $selector;
                break;
            } elseif ( $el->hasAttribute( 'class' ) ) {
                $class = preg_split( '/\s+/', $el->getAttribute( 'class' ) );
                $selector .= '.' . implode( '.', array_filter( $class ) );
            }

            $parts[] = $selector;
            $el = $el->parentNode;
            $depth++;
        }

        return implode( ' > ', array_reverse( $parts ) );
    }

    private function pys_get_first_simple_product_id() {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '='
                ]
            ],
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => [ 'simple' ]
                ]
            ]
        ];

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            return $query->posts[0]->ID;
        }

        return false;
    }

    function pys_get_first_edd_download_id() {
        $args = [
            'post_type'      => 'download',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ];

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            return $query->posts[0]->ID;
        }

        return false;
    }

}

/**
 * @return AnyFormScanner
 */
function AnyFormScanner() {
    return AnyFormScanner::instance();
}

AnyFormScanner();