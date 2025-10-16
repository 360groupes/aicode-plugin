<?php
if (!defined('ABSPATH')) exit;

require_once AICODE_PLUGIN_DIR . 'includes/extractors.php';
require_once AICODE_PLUGIN_DIR . 'includes/admin-page.php';

class AICode_Plugin {
    const OPTION_API_KEY = 'aicode_openai_api_key';
    const OPTION_MODEL   = 'aicode_openai_model';
    const OPTION_PROMPT  = 'aicode_system_prompt'; // NUEVO

    public static function init() {
        // Shortcode
        add_shortcode('aicode', [__CLASS__, 'render_shortcode']);

        // Assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX (logueados y no logueados)
        add_action('wp_ajax_aicode_chat', [__CLASS__, 'handle_chat']);
        add_action('wp_ajax_nopriv_aicode_chat', [__CLASS__, 'handle_chat']);

        // Test de clave desde admin
        add_action('wp_ajax_aicode_test_key', [__CLASS__, 'handle_test_key']); // NUEVO (sólo admin)

        // Página de Ajustes
        add_action('admin_init', ['AICode_Admin_Page', 'register_settings']);
        add_action('admin_menu', ['AICode_Admin_Page', 'register_menu']);
    }

    public static function enqueue_assets() {
        wp_enqueue_style('aicode-css', AICODE_PLUGIN_URL . 'assets/css/aicode.css', [], AICODE_PLUGIN_VERSION);
        wp_enqueue_style('aicode-hljs', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/styles/default.min.css', [], '11.5.1');
        wp_enqueue_script('aicode-hljs', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/highlight.min.js', [], '11.5.1', true);
        wp_enqueue_script('aicode-js', AICODE_PLUGIN_URL . 'assets/js/aicode.js', ['jquery'], AICODE_PLUGIN_VERSION, true);

        wp_localize_script('aicode-js', 'AICODE', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('aicode_nonce'),
            'model'     => self::get_model(),
        ]);
    }

    public static function render_shortcode($atts) {
        if (!self::get_api_key()) {
            $admin_url = admin_url('options-general.php?page=aicode-settings');
            return '<div class="aicode-warning">Falta la API Key de OpenAI. Añádela en <a href="' . esc_url($admin_url) . '">Ajustes → AI Code</a>.</div>';
        }

        ob_start(); ?>
        <div class="aicode-container">
            <div class="chat-container">
                <div class="left-panel">
                    <div id="aicode-chat-box" class="chat-box"></div>

                    <div id="aicode-typing" class="message assistant" style="display:none;">
                        <div class="typing-container">
                            <div class="typing"></div><div class="typing"></div><div class="typing"></div>
                        </div>
                        <div class="timer" id="aicode-timer">Tiempo: 0s</div>
                    </div>

                    <div class="input-container">
                        <input type="text" id="aicode-user-input" placeholder="Escribe aquí lo que necesitas ..." />
                        <button id="aicode-voice-btn" title="Hablar">&#x1F3A4;</button>
                        <label for="aicode-file-upload" class="file-label">Adjuntar Archivos</label>
                        <input id="aicode-file-upload" type="file" name="files[]" multiple style="display:none;" />
                        <button id="aicode-clear-btn">Vaciar</button>
                        <button id="aicode-send-btn">Enviar</button>
                    </div>
                    <div id="aicode-file-info" class="file-info"></div>
                </div>

                <div class="right-panel">
                    <div class="code-actions">
                        <div class="code-actions-left">
                            <button id="aicode-code-view" class="primary-button">Código</button>
                            <button id="aicode-preview-view" class="primary-button">Vista Previa</button>
                            <button id="ai
