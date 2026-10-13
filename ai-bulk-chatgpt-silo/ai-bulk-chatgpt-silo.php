<?php
/**
 * Plugin Name:       AI Bulk ChatGPT Silo
 * Description:       Viết bài SEO hàng loạt bằng OpenAI, tạo ảnh theo H2/H3, ảnh đại diện, liên kết silo, đồng bộ Rank Math/Yoast. Bản Free (1 domain, 1 bài/ngày) và Pro theo gói.
 * Version:           7.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Fame Media
 * Author URI:        https://aibulksilo.com/
 * Text Domain:       ai-bulk-chatgpt-silo
 */

defined( 'ABSPATH' ) || exit;

define( 'ABCGS_VERSION', '7.0.0' );
define( 'ABCGS_FILE', __FILE__ );
define( 'ABCGS_DIR', plugin_dir_path( __FILE__ ) );

require_once ABCGS_DIR . 'includes/class-abcgs-settings.php';
require_once ABCGS_DIR . 'includes/class-abcgs-secrets.php';
require_once ABCGS_DIR . 'includes/class-abcgs-lock.php';
require_once ABCGS_DIR . 'includes/class-abcgs-notices.php';
require_once ABCGS_DIR . 'includes/class-abcgs-license.php';
require_once ABCGS_DIR . 'includes/class-abcgs-openai.php';
require_once ABCGS_DIR . 'includes/class-abcgs-prompts.php';
require_once ABCGS_DIR . 'includes/class-abcgs-content.php';
require_once ABCGS_DIR . 'includes/class-abcgs-seo.php';
require_once ABCGS_DIR . 'includes/class-abcgs-silo.php';
require_once ABCGS_DIR . 'includes/class-abcgs-images.php';
require_once ABCGS_DIR . 'includes/class-abcgs-queue.php';
require_once ABCGS_DIR . 'includes/class-abcgs-image-queue.php';
require_once ABCGS_DIR . 'includes/class-abcgs-upgrade.php';
require_once ABCGS_DIR . 'includes/class-abcgs-admin.php';
require_once ABCGS_DIR . 'includes/class-abcgs-plugin.php';

register_activation_hook( __FILE__, array( 'ABCGS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ABCGS_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ABCGS_Plugin', 'boot' ) );
