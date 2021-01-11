<?php

namespace MySiteDigital\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JobAlertsLog {


    /**
     * Meta box error messages.
     *
     * @var array
     */
    public static $meta_box_errors  = array();

    /**
     * Is meta boxes saved once?
     *
     * @var boolean
     */
    private static $saved_meta_boxes = false;

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ), 5 );

        add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
        add_action( 'admin_menu', array( $this, 'remove_publish_box' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
    }


    /**
     * Register core post type.
     */
    public function register_post_type() {
        if ( post_type_exists('job-alerts-log' ) ) {
            return;
        }

        $labels = array(
                        'name'                  => __( 'Job Alert Logs', 'job-alerts-log' ),
                        'singular_name'         => _x(
                                                        'Job Alert Log',
                                                        'Job Alert Log post type singular name',
                                                        'job-alerts-log'
                                                    ),
                        'add_new'               => __( 'Add Job Alert Log', 'job-alerts-log' ),
                        'add_new_item'          => __( 'Add New Job Alert Log', 'job-alerts-log' ),
                        'edit'                  => __( 'Edit', 'job-alerts-log' ),
                        'edit_item'             => __( 'Edit Job Alert Log', 'job-alerts-log' ),
                        'new_item'              => __( 'New Job Alert Log', 'job-alerts-log' ),
                        'view'                  => __( 'View Job Alert Log', 'job-alerts-log' ),
                        'view_item'             => __( 'View Job Alert Log', 'job-alerts-log' ),
                        'search_items'          => __( 'Search Job Alert Logs', 'job-alerts-log' ),
                        'not_found'             => __( 'No Job Alert Logs found', 'job-alerts-log' ),
                        'not_found_in_trash'    => __( 'No Job Alert Logs found in trash', 'job-alerts-log' ),
                        'parent'                => __( 'Parent Job Alert Logs', 'job-alerts-log' ),
                        'menu_name'             => _x( 'Job Alert Logs', 'Admin menu name', 'job-alerts-log' ),
                        'filter_items_list'     => __( 'Filter Job Alert Logs', 'job-alerts-log' ),
                        'items_list_navigation' => __( 'Job Alert Logs navigation', 'job-alerts-log' ),
                        'items_list'            => __( 'Job Alert Logs list', 'job-alerts-log' ),
                    );

        register_post_type(
            'job-alerts-log',
            array(
                'labels'              => $labels,
                'description'         => 'This is where Job Alert Logs are stored.',
                'public'              => false,
                'show_ui'             => true,
                'capability_type'     => 'post',
                'capabilities'        => ['create_posts' => 'do_not_allow'],
                'map_meta_cap'        => true,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_in_menu'        => false,
                'hierarchical'        => false,
                'show_in_nav_menus'   => false,
                'rewrite'             => false,
                'query_var'           => false,
                'supports'            => false,
                'has_archive'         => false,
            )
        );
    }

    public function remove_publish_box() {
        remove_meta_box( 'submitdiv', 'job-alerts-log', 'side' );
    }

    public function add_to_menu() {
        add_submenu_page(
            'app-dashboard',
            'Job Alerts Logs',
            'Job Alerts Logs',
            'manage_options',
            'edit.php?post_type=job-alerts-log',
            null
        );
    }

    /**
     * Add Meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'job-alerts-log-ouput',
            'Job Alerts Process',
            [ $this, 'meta_box_output' ],
            'job-alerts-log',
            'normal',
            'high'
        );
    }

    public function meta_box_output(){
        global $post;
        $summary = get_post_meta( $post->ID, 'log_summary', true );
        echo '<hr><h1>Summary</h1><hr>';
        echo $summary;
        echo '<hr><h1>Full Details</h1><hr>';
        echo $post->post_content;
    }

}

new JobAlertsLog();
