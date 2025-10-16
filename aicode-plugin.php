<?php
/**
 * Plugin Name: AI Code (360group) – Chat + Generador de código
 * Description: Integra el asistente aiCode dentro de WordPress (sin iframes). Shortcode [aicode].
 * Version: 1.0.1
 * Author: 360group.es
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: aicode
 */

if (!defined('ABSPATH')) exit;

define('AICODE_PLUGIN_VERSION', '1.0.1');
define('AICODE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICODE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once AICODE_PLUGIN_DIR . 'includes/class-aicode-plugin.php';

add_action('plugins_loaded', ['AICode_Plugin', 'init']);
