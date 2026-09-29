<?php
/**
 * Plugin Name: Shirt Size Custom Checkout Blocks
 * Description: Conditional shirt size dropdowns with Admin and Email notifications.
 * Version: 2.0
 * Author: Stephen Cabading
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Shirt_Size_Checkout_Blocks {

    const VERSION = '2';

    const PRODUCT_ADULT  = 16806;
    const PRODUCT_JUNIOR = 16808;
    const PRODUCT_FAMILY  = 16807;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', function() {
            if ( is_checkout() && ! is_wc_endpoint_url() ) {
                $this->enqueue_assets();
            }
        });
        add_action( 'init', [ $this, 'register_fields' ] );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'show_admin_data' ] );
        add_action( 'woocommerce_email_order_meta', [ $this, 'show_email_data' ], 10, 3 );
        add_action( 'woocommerce_thankyou', [ $this, 'display_custom_order_meta' ], 20 );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'validate_required_fields' ], 10, 1 );

        add_action('wp_ajax_add_shirt_size_to_order', [ $this, 'handle_shirt_size_ajax' ] );
        add_action('wp_ajax_nopriv_add_shirt_size_to_order', [ $this, 'handle_shirt_size_ajax' ] );

        add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'custom_meta' ] );
        add_action( 'template_redirect', [ $this, 'clear_shirt_sizes_session_data' ] );

        add_filter( 'woocommerce_checkout_fields', function( $fields ) {

            if ( isset( $fields['order']['order_comments'] ) ) {
                unset( $fields['order']['order_comments'] );
            }

            return $fields;
        });

    }

    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'shirt-size-checkout',
            plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'shirt-size-heading',
            plugin_dir_url( __FILE__ ) . 'assets/js/scripts.js',
            [ 'jquery' ],
            self::VERSION,
            true
        );
    }

    /**
     * Register all checkout fields
     */
    public function register_fields() {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            return;
        }

        $this->register_sized_fields(
            'adult',
            self::PRODUCT_ADULT,
        );

        $this->register_sized_fields(
            'junior',
            self::PRODUCT_JUNIOR,
        );

        $this->register_family_fields();
    }

    /**
     * Register adult / junior fields
     */
    private function register_sized_fields( $type, $product_id ) {

        $qty = 0;

        if ( function_exists( 'WC' ) && WC()->cart && is_object( WC()->cart ) ) {
            $cart = WC()->cart->get_cart();

            foreach ( $cart as $item ) {
                if ( isset( $item['variation_id'] ) && $item['variation_id'] == $product_id ) {
                    $qty += isset( $item['quantity'] ) ? $item['quantity'] : 0;
                }
            }
        }

        for ( $i = 1; $i <= $qty; $i++ ) {
            woocommerce_register_additional_checkout_field( [
                'id'       => "shirt-app/{$type}-size-{$i}",
                'label'    => ucfirst( $type ) . " Shirt Size {$i}",
                'location' => 'order',
                'type'     => 'select',
                'options'  => $this->get_size_options( $type ),
                'hidden'   => $this->get_quantity_visibility_rule( $product_id, $i ),
                'store_api_field' => true,
                'required' => true,
                'show_in_order_confirmation' => false,
                'show_in_email' => false,
            ] );
        }
    }


    /**
     * Register family fields
     */
    private function register_family_fields() {
        $family = [
            'family-adult-shirt-size-1' => 'Adult Shirt Size 1',
            'family-adult-shirt-size-2' => 'Adult Shirt Size 2',
            'family-junior-shirt-size-1' => 'Junior Shirt Size 1',
            'family-junior-shirt-size-2' => 'Junior Shirt Size 2',
        ];

        foreach ( $family as $key => $label ) {
            woocommerce_register_additional_checkout_field( [
                'id'       => "shirt-app/{$key}",
                'label'    => "{$label}",
                'location' => 'order',
                'type'     => 'select',
                'options'  => in_array( $key, [ 'family-adult-shirt-size-1', 'family-adult-shirt-size-2' ] ) ? $this->get_size_options( 'adult' ) : $this->get_size_options( 'junior' ),
                'hidden'   => $this->get_product_visibility_rule( self::PRODUCT_FAMILY ),
                'store_api_field' => true,
                'required' => true,
                'show_in_order_confirmation' => false,
                'show_in_email' => false,
            ] );
        }
    }

    /**
     * Size options
     */
    private function get_size_options( $type ) {
        $options = [
            'adult' => [
                [ 'value' => 'S',  'label' => 'S' ],
                [ 'value' => 'M',  'label' => 'M' ],
                [ 'value' => 'L',  'label' => 'L' ],
                [ 'value' => 'XL', 'label' => 'XL' ],
                [ 'value' => '2XL', 'label' => '2XL' ],
                [ 'value' => '3XL', 'label' => '3XL' ],
            ],
            'junior' => [
                [ 'value' => '2',  'label' => '2' ],
                [ 'value' => '4',  'label' => '4' ],
                [ 'value' => '6',  'label' => '6' ],
                [ 'value' => '8',  'label' => '8' ],
                [ 'value' => '10', 'label' => '10' ],
                [ 'value' => '12', 'label' => '12' ],
                [ 'value' => '14', 'label' => '14' ],
                [ 'value' => '16', 'label' => '16' ],
            ],
        ];

        return $options[ $type ] ?? [];
    }

    /**
     * Visibility rules
     */
    private function get_quantity_visibility_rule( $product_id, $qty ) {
        return [
            'type'       => 'object',
            'properties' => [
                'cart' => [
                    'type'       => 'object',
                    'properties' => [
                        'items' => [
                            'not' => [
                                'contains' => [
                                    'const'       => $product_id,
                                    'minQuantity' => $qty,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function get_product_visibility_rule( $product_id ) {
        return [
            'type'       => 'object',
            'properties' => [
                'cart' => [
                    'type'       => 'object',
                    'properties' => [
                        'items' => [
                            'not' => [
                                'contains' => [
                                    'const' => $product_id,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Validation
     */
    public function validate_required_fields( $order ) {

    foreach ( $order->get_meta_data() as $meta ) {
            $key = $meta->key;

            if (
                (strpos( $key, 'shirt-app/' ) === 0 ||
                strpos( $key, 'order-shirt-app-' ) === 0)
                && empty( $meta->value )
            ) {
                throw new Exception(
                    __( 'Please select all required shirt sizes.', 'shirt-app' )
                );
            }
        }
    }

    /**
     * Admin display
     */
    public function show_admin_data( $order ) {
        $data = $this->get_formatted_shirt_data( $order );
        if ( $data ) {
            echo '<h4>Shirt Sizes</h4>' . $data;
        } else {
            echo '<h4>Shirt Sizes</h4><p>None selected</p>';
        }
    }

    /**
     * Email display
     */
    public function show_email_data( $order, $sent_to_admin, $plain_text ) {
        $data = $this->get_formatted_shirt_data( $order );
        if ( $data ) {
            echo '<h2>Shirt Selections</h2>' . $data;
        }
    }
    /**
     * Thank You Page display
     */
    public function display_custom_order_meta( $order_id ) {
        $order = wc_get_order( $order_id );
        $data = $this->get_formatted_shirt_data( $order );
        if ( $data ) {
            echo '<h2>Shirt Selections</h2>' . $data;
        }
    }

    /**
     * Format output
     */
    private function get_formatted_shirt_data( $order ) {
        $data = json_decode( $order->get_meta( 'shirt_sizes', true ) );

        if ( empty( $data ) ) {
            return '';
        }

        $output = '<table class="wc-order-table widefat"><thead><tr><th class="product-name">Label</th><th class="product-total">Size</th></tr></thead><tbody>';

        foreach ( $data as $size ) {
            $output .= '<tr class="order_item"><td class="product-name">' . esc_html( $size->label ) . '</td><td class="product-total">' . esc_html( $size->size ) . '</td></tr>';
        }

        $output .= '</tbody></table>';

        return $output;
    }

    public function handle_shirt_size_ajax() {
        if ( !isset($_POST['size']) ) {
            wp_send_json_error('No size received');
        }
        
        $shirt_sizes = WC()->session->get( 'shirt_sizes' );
        $shirt_sizes[$_POST['uid']] = [
            'label' => $_POST['label'],
            'size' => $_POST['size'],
        ];

        try { 
            WC()->session->set( 'shirt_sizes', $shirt_sizes );
            wp_send_json_success(
                [
                    'message' => 'Shirt size saved successfully',
                    'data' => $shirt_sizes,
                ]
            );
        } 
        catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
        
        wp_die();
    }

    /**
     * Save order meta
     */
    public function custom_meta( WC_Order $order ) {
        $shirt_sizes = WC()->session->get( 'shirt_sizes' );
        $order->update_meta_data('shirt_sizes', json_encode($shirt_sizes));
        $order->save();
    }

    /**
     * Clear session
     */
    public function clear_shirt_sizes_session_data() {

        if ( is_checkout() && !is_wc_endpoint_url( 'order-pay' ) && !is_wc_endpoint_url( 'order-received' ) && isset( WC()->session->shirt_sizes ) ) {
            unset( WC()->session->shirt_sizes );
        }
    }

    /**
     * Remove additional info
     */
    public function remove_additional_info ( $total_rows, $order, $tax_display ) {
        unset( $total_rows['additional_info'] );
        return $total_rows;
    }

}  

new Shirt_Size_Checkout_Blocks();
