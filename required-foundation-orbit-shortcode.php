<?php
/**
 * Plugin Name: r+ Orbit Shortcode
 * Plugin URI: http://themes.required.ch/
 * Description: A [orbit] shortcode plugin for the required+ Foundation parent theme and child themes.
 * Version: 1.1.0-wip
 * Author: required+ Team
 * Author URI: http://required.ch
 *
 * @package   required+ Foundation
 * @version   1.1.0-wip
 * @author    Silvan Hagen <silvan@required.ch>
 * @copyright Copyright (c) 2012, Silvan Hagen
 * @link      http://themes.required.ch/theme-features/shortcodes/
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * REQ_Orbit Shortcode Class
 *
 * @version 0.1.0
 */
class REQ_Orbit {

    /**
     * Sets up our actions/filters.
     *
     * @since 0.1.0
     * @access public
     * @return void
     */
    public function __construct() {

        /* Register shortcodes on 'init'. */
        add_action( 'init', array( &$this, 'register_shortcode' ) );
    }

    /**
     * Registers the [orbit] shortcode.
     *
     * @since  0.1.0
     * @access public
     * @return void
     */
    public function register_shortcode() {
        add_shortcode( 'orbit', array( &$this, 'do_shortcode' ) );
    }

    /**
     * Returns the content of the orbit shortcode.
     *
     * @since  0.1.0
     * @access public
     * @param  array  $attr The user-inputted arguments.
     * @param  string $content The content to wrap in a shortcode.
     * @return string
     */
    public function do_shortcode( $attr, $content = null ) {

        global $post;

        /* Set up the default variables. */
        $output = '';
        $caption = '';
        $row_before = '';
        $row_after = '';
        $id_suffix = '';

        /* Set up the possible script options */
        $orbit_script_options = '';

        // We're trusting author input, so let's at least make sure it looks like a valid orderby statement
        if ( isset( $attr['orderby'] ) ) {
            $attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
            if ( ! $attr['orderby'] )
                unset( $attr['orderby'] );
        }

        /* Set up the default arguments. */
        $defaults = apply_filters(
            'req_orbit_defaults',
            array(
                'order'      => 'ASC',
                'orderby'    => 'menu_order ID',
                'id'         => $post->ID,
                'size'       => 'large',
                'include'    => '',
                'exclude'    => '',
                'draw_row'   => true,
            )
        );

        /* Combine the shortcode attrs with the default values */
        $attr = shortcode_atts( $defaults, $attr );

        /* Allow devs to filter the arguments. */
        $attr = apply_filters( 'req_orbit_args', $attr );

        /* Parse the arguments. */
        extract( $attr );

        /* Parse the ID as int */
        $id = intval( $id );

        /* Random order */
        if ( 'RAND' == $order )
            $orderby = 'none';

        /**
         * Include and Exclude are not combined
         *
         * Use $include or $exclude if they exist. We don't combine the two parameters as this doesn't work with the query
         * . See [gallery] shortcode from WordPress Core for more info.
         */
        if ( ! empty( $include ) ) {
            $include = preg_replace( '/[^0-9,]+/', '', $include );
            $id_suffix = '_' . str_replace( ',', '_', $include );
            $_attachments = get_posts( array( 'include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby ) );

            $attachments = array();
            foreach ( $_attachments as $key => $val ) {
                $attachments[$val->ID] = $_attachments[$key];
            }
        } elseif ( ! empty( $exclude ) ) {
            $exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
            $id_suffix = '_' . str_replace( ',', '_', $exclude );
            $attachments = get_children( array( 'post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby ) );
        } else {
            $attachments = get_children( array( 'post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby ) );
        }

        /**
         * No attachments found?
         *
         * We return an empty string, so the visitor is not bothered by an error. Sadly this way the author is no getting
         * any feedback on the problem.
         */
        if ( empty( $attachments ) )
            return '';

        /**
         * Feed Output
         *
         * We only output the attachments, no scripts and html.
         */
        if ( is_feed() ) {
            $output = "\n";
            foreach ( $attachments as $att_id => $attachment )
                $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
            return $output;
        }


        /* Global script options */
        $orbit_script_args = apply_filters(
            'req_orbit_script_args',
            array()
        );

        /* Page/Post specific script options */
        $orbit_script_args = apply_filters(
            "req_orbit_script_args_{$id}{$id_suffix}",
            $orbit_script_args
        );

        /**
         * Orbit Script options
         *
         * Create a simple string with key:value; pairs for the <code>data-options</code> attr on the output.
         */
        if ( !empty( $orbit_script_args ) ) {
            foreach ($orbit_script_args as $key => $value) {
                $orbit_script_options .= "{$key}:{$value};";
            }
        }

        /**
         * Fixing an Orbit Bug / @neverything: 2013-04-01
         *
         * Orbit has a bug in 4.0.9 so we need to draw a row and columns around it, otherwise multiple orbits on the page
         * have trouble with the bullets below the orbit.
         */
        if ( true === $draw_row ) {
            $row_before = apply_filters( 'req_orbit_row_before', '<div class="row"><div class="large-12 small-12 columns">' );
            $row_after = apply_filters( 'req_orbit_row_after', '</div></div>' );
        }

        /* Let the magic happen */
        $output = "<!-- Start Orbit Shortcode -->{$row_before}<ul data-orbit data-options='{$orbit_script_options}' class='req-orbit' id='req-orbit-{$id}{$id_suffix}'>";

        foreach ( $attachments as $id => $attachment ) {

            /* Image source for the thumbnail image */
            $img_src = wp_get_attachment_image_src( $id, $size );

            if ( trim($attachment->post_excerpt) ) {
                $caption = '<div class="orbit-caption">' . wptexturize( $attachment->post_excerpt ) . '</div>';
            }

            /* Generate final item output */
            $output .= '<li><img src="' . esc_url( $img_src[0] ) . '" />' . $caption . '</li>';
        }

        $output .= "</ul>{$row_after}<!-- // End Orbit Shortcode -->";

        /* Return the output of the orbit. */
        return apply_filters( 'req_orbit', $output );
    }

}

new REQ_Orbit();
