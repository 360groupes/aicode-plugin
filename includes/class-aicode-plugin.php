<?php
if (!defined('ABSPATH')) exit;

require_once AICODE_PLUGIN_DIR . 'includes/extractors.php';
require_once AICODE_PLUGIN_DIR . 'includes/admin-page.php';

class AICode_Plugin {
    const OPTION_API_KEY = 'aicode_openai_api_key';
    const OPTION_MODEL   = 'aicode_openai_model';
    const OPTION_PROMPT  = 'aicode_system_prompt';

    public static function init() {
        // Shortcode
        add_shortcode('aicode', [__CLASS__, 'render_shortcode']);

        // Assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX chat (público)
        add_action('wp_ajax_aicode_chat', [__CLASS__, 'handle_chat']);
        add_action('wp_ajax_nopriv_aicode_chat', [__CLASS__, 'handle_chat']);

        // AJAX test key (solo admin)
        add_action('wp_ajax_aicode_test_key', [__CLASS__, 'handle_test_key']);

        // Ajustes (en admin-page.php)
        add_action('admin_init', ['AICode_Admin_Page', 'register_settings']);
        add_action('admin_menu', ['AICode_Admin_Page', 'register_menu']);
    }

    public static function enqueue_assets() {
        // CSS
        wp_enqueue_style('aicode-css', AICODE_PLUGIN_URL . 'assets/css/aicode.css', [], AICODE_PLUGIN_VERSION);
        wp_enqueue_style('aicode-hljs', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/styles/default.min.css', [], '11.5.1');

        // JS
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
                            <button id="aicode-copy-btn">Copiar</button>
                            <button id="aicode-download-btn">Descargar</button>
                        </div>
                        <div class="device-buttons" id="aicode-device-buttons" style="display:none;">
                            <button id="aicode-desktop-btn">Escritorio</button>
                            <button id="aicode-tablet-btn">Tablet</button>
                            <button id="aicode-mobile-btn">Móvil</button>
                        </div>
                    </div>
                    <pre id="aicode-generated-code" class="language-javascript"></pre>
                    <iframe id="aicode-preview" class="hidden"></iframe>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_chat() {
        check_ajax_referer('aicode_nonce', 'nonce');

        // Asegurar funciones de manejo de archivos disponibles
        if ( ! function_exists('wp_handle_sideload') ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $user_input = isset($_POST['user_input']) ? wp_kses_post(trim($_POST['user_input'])) : '';
        $action_cmd = isset($_POST['action_cmd']) ? sanitize_text_field($_POST['action_cmd']) : '';

        if ($action_cmd === 'reset') {
            delete_transient(self::conv_key());
            wp_send_json_success(['response' => 'Conversación vaciada.']);
        }

        // Procesar archivos
        $file_text = '';
        if (!empty($_FILES['files'])) {
            $file_text = self::process_files($_FILES['files']);
        }

        // Cargar prompt del sistema (editable en ajustes)
        $ai_prompt = self::base_system_prompt();

        // Cargar conversación (transient por usuario/IP)
        $conversation = get_transient(self::conv_key());
        if (!$conversation) {
            $conversation = [
                ['role' => 'system', 'content' => $ai_prompt]
            ];
        }

        $combined_input = $user_input . "\n\nContenido de los archivos adjuntos:\n\n" . $file_text;
        $conversation[] = ['role' => 'user', 'content' => $combined_input];

        // Llamada a OpenAI
        $api_response = self::openai_chat($conversation);

        if (is_wp_error($api_response)) {
            wp_send_json_error(['response' => 'Error al conectar con OpenAI: ' . $api_response->get_error_message()]);
        }

        $body = json_decode(wp_remote_retrieve_body($api_response), true);
        if (!is_array($body) || empty($body['choices'][0]['message']['content'])) {
            wp_send_json_error(['response' => 'Respuesta no válida de OpenAI. Detalles: ' . wp_remote_retrieve_body($api_response)]);
        }

        $assistant_text = $body['choices'][0]['message']['content'];
        $conversation[] = ['role' => 'assistant', 'content' => $assistant_text];
        set_transient(self::conv_key(), $conversation, HOUR_IN_SECONDS);

        wp_send_json_success(['response' => $assistant_text]);
    }

    public static function handle_test_key() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permiso denegado.']);
        check_ajax_referer('aicode_admin_nonce', 'nonce');

        $api_key = self::get_api_key();
        if (!$api_key) wp_send_json_error(['message' => 'No hay clave configurada.']);

        $payload = [
            'model'       => self::get_model(),
            'messages'    => [['role' => 'user', 'content' => 'ping']],
            'max_tokens'  => 1,
            'temperature' => 0,
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ];

        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($resp)) wp_send_json_error(['message' => $resp->get_error_message()]);

        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 200 && $code < 300) {
            wp_send_json_success(['message' => 'Conexión correcta.']);
        } else {
            $body = wp_remote_retrieve_body($resp);
            wp_send_json_error(['message' => "HTTP $code: $body"]);
        }
    }

    /** Procesado de ficheros con extracción de texto si hay librerías */
    private static function process_files($files) {
        $out = '';
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $out .= "El archivo " . sanitize_text_field($files['name'][$i]) . " no se pudo subir.\n\n";
                continue;
            }

            $file_array = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];

            $overrides = ['test_form' => false];
            $moved = wp_handle_sideload($file_array, $overrides);

            if (isset($moved['error'])) {
                $out .= "El archivo " . sanitize_text_field($files['name'][$i]) . " no se pudo procesar.\n\n";
                continue;
            }
