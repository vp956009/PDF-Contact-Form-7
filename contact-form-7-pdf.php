<?php
/**
 * Plugin Name: PDF Contact Form 7 
 * Description: The very first plugin that I have ever created.
 * Version: 1.0
 */

if(!defined('ABSPATH')) {
    die('-1');
}
if(!defined('CF7PDF_PLUGIN_NAME')) {
    define('CF7PDF_PLUGIN_NAME', 'PDF Contact Form 7');
}
if(!defined('CF7PDF_VERSION')) {
    define('CF7PDF_VERSION', '1.0.0');
}
if(!defined('CF7PDF_PATH')) {
    define('CF7PDF_PATH', __FILE__);
}
if(!defined('CF7PDF_PLUGIN_DIR')) {
    define('CF7PDF_PLUGIN_DIR',plugins_url('', __FILE__));
}
if(!defined('CF7PDF_PLUGIN_AB_PATH')) {
    define('CF7PDF_PLUGIN_AB_PATH',plugin_dir_path( __FILE__ ));
}
if(!defined('CF7PDF_DOMAIN')) {
    define('CF7PDF_DOMAIN', 'CF7PDF');
}
if(!defined('CF7PDF_PREFIX')) {
    define('CF7PDF_PREFIX', "cf7pdf_");
}
if(!defined('CF7PDF_PAGE_SLUG')) {
    define('CF7PDF_PAGE_SLUG', "cf7pdf_entries");
}
if(!defined('CF7PDF_TABLE')) {
    define('CF7PDF_TABLE', "cf7pdf_table");
}
if(!defined('CF7PDF_UPLOAD')) {
    define('CF7PDF_UPLOAD', "cf7pdf_uploads");
}


if (!class_exists('CF7PDF')) {

    class CF7PDF {

        protected static $instance;
        function includes() {
        	include_once('admin/cf7pdf-panel.php');
            include_once('admin/cf7pdf-backend.php');      
        }


        function init() {
            add_action( 'admin_enqueue_scripts', array($this, 'CF7PDF_load_admin_script_style'));
            add_action( 'wp_enqueue_scripts', array($this, 'CF7PDF_load_front_script_style'));
            session_start();


            global $wpdb;
            $table_name = $wpdb->prefix.CF7PDF_TABLE;
            if( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE $table_name (
                    form_id bigint(20) NOT NULL AUTO_INCREMENT,
                    form_post_id bigint(20) NOT NULL,
                    form_value longtext NOT NULL,
                    form_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    PRIMARY KEY  (form_id)
                ) $charset_collate;";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql );
            }


            $upload_dir      = wp_upload_dir();
            $cf7pdf_dirname = $upload_dir['basedir'].'/'.CF7PDF_UPLOAD;
            if ( ! file_exists( $cf7pdf_dirname ) ) {
                wp_mkdir_p( $cf7pdf_dirname );
            }
        }


        function CF7PDF_load_admin_script_style() {
            wp_enqueue_style( 'CF7PDF-back-style', CF7PDF_PLUGIN_DIR . '/includes/css/back_style.css', false, '1.0.0' );
            wp_enqueue_script( 'CF7PDF-back-script', CF7PDF_PLUGIN_DIR . '/includes/js/back_script.js', false, '1.0.0' );
        }

        function CF7PDF_load_front_script_style() {
            wp_enqueue_style( 'CF7PDF-front-style', CF7PDF_PLUGIN_DIR . '/includes/css/front_style.css', false, '1.0.0' );
            
        }
      
        public static function instance() {
            if (!isset(self::$instance)) {
                self::$instance = new self();
                self::$instance->init();
                self::$instance->includes();
            }
            return self::$instance;
        }
    }
    add_action('plugins_loaded', array('CF7PDF', 'instance'));
}



