<?php
/**
 * Plugin Name:       HOA/COA File Archive Assistant
 * Plugin URI:        https://sflwa.com/
 * Description:       Automated file archiving and statutory posting reminders for Florida HOAs and Condos (FS 718/720).
 * Version:           1.2.1
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            South Florida Web Advisors
 * Author URI:        https://sflwa.com/
 * Text Domain:       hoa-coa-file-archive-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HOACOA_FAA_VERSION', '1.2.1' );
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
    }

    private function load_dependencies(): void {
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-logger.php';
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-admin.php';
        require_once HOACOA_FAA_INC . 'class-hoacoa-faa-engine.php';
    }

    private function init_components(): void {
        // Instantiate Admin UI
        new HOACOA_FAA_Admin();
        // Instantiate Engine to register AJAX hooks
        new HOACOA_FAA_Engine();
    }
}

HOACOA_FAA_Archive_Assistant::get_instance();
