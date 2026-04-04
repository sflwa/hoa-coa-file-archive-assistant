<?php
/**
 * Plugin Name:       HOA/COA File Archive Assistant
 * Plugin URI:        https://sflwa.com/
 * Description:       Automated file archiving and statutory posting reminders for Florida HOAs and Condos (FS 718/720).
 * Version:           1.3.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            South Florida Web Advisors
 * Author URI:        https://sflwa.com/
 * Text Domain:       hoa-coa-file-archive-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HOACOA_FAA_VERSION', '1.3.0' );
define( 'HOACOA_FAA_PATH', plugin_dir_path( __FILE__ ) );
define( 'HOACOA_FAA_INC', HOACOA_FAA_PATH . 'includes/' );

final class HOACOA_FAA_Archive_Assistant {

    private static ?HOACOA_FAA_Archive_Assistant $instance = null;

    public static function get_instance(): HOACOA_FAA_Archive_Assistant {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_components();

        add_action( 'init', [ $this, 'register_statute_taxonomies' ] );
        add_action( 'init', [ $this, 'register_statute_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_statute_meta_boxes' ] );
        add_action( 'save_post_hcaa_statute', [ $this, 'save_statute_meta' ] );

        // Front-end Print Logic
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'handle_frontend_print' ] );
    }

    private function load_dependencies(): void {
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-logger.php';
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-admin.php';
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-engine.php';
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-dashboard.php';
    }

    private function init_components(): void {
        new HOACOA_FAA_Admin();
        new HOACOA_FAA_Engine();
        new HOACOA_FAA_Dashboard();
    }

    public function register_query_vars( $vars ) {
        $vars[] = 'hcaa_print_log';
        $vars[] = 'hcaa_chapter';
        return $vars;
    }

    public function handle_frontend_print(): void {
        if ( get_query_var( 'hcaa_print_log' ) ) {
            $chapter_id = (int) get_query_var( 'hcaa_chapter' );
            $dashboard = new HOACOA_FAA_Dashboard();
            $dashboard->render_print_view( $chapter_id );
            exit;
        }
    }

    public function register_statute_taxonomies(): void {
        register_taxonomy( 'hcaa_statute_type', [ 'hcaa_statute' ], [
            'labels' => [
                'name'          => 'Statute Chapters',
                'singular_name' => 'Chapter',
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
            'rewrite'           => false,
        ]);
    }

    public function register_statute_cpt(): void {
        $labels = [
            'name'               => 'Statute Archive',
            'singular_name'      => 'Statute Version',
            'all_items'          => 'Statute History',
            'menu_name'          => 'Statute Archive'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'false',
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => [ 'title', 'editor' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => true,
            'menu_icon'          => 'dashicons-book-alt',
        ];

        register_post_type( 'hcaa_statute', $args );
    }

    public function add_statute_meta_boxes(): void {
        add_meta_box( 'hcaa_statute_log', 'Operational Change Log (Markdown)', [ $this, 'render_log_metabox' ], 'hcaa_statute', 'normal', 'high' );
    }

    public function render_log_metabox( $post ): void {
        $val = get_post_meta( $post->ID, '_hcaa_statute_markdown_log', true );
        wp_nonce_field( 'hcaa_statute_meta_nonce', 'hcaa_statute_nonce' );
        echo '<textarea name="hcaa_markdown_log" style="width:100%; height:400px; font-family:monospace; padding:10px;">' . esc_textarea( $val ) . '</textarea>';
    }

    public function save_statute_meta( $post_id ): void {
        if ( ! isset( $_POST['hcaa_statute_nonce'] ) || ! wp_verify_nonce( $_POST['hcaa_statute_nonce'], 'hcaa_statute_meta_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( isset( $_POST['hcaa_markdown_log'] ) ) {
            update_post_meta( $post_id, '_hcaa_statute_markdown_log', $_POST['hcaa_markdown_log'] );
        }
    }
}

HOACOA_FAA_Archive_Assistant::get_instance();
