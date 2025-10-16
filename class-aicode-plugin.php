<?php
if (!defined('ABSPATH')) exit;

require_once AICODE_PLUGIN_DIR . 'includes/extractors.php';
require_once AICODE_PLUGIN_DIR . 'includes/admin-page.php';

class AICode_Plugin {
    const OPTION_API_KEY = 'aicode_openai_api_key';
    const OPTION_MODEL   = 'aicode_openai_model'; // por si quieres cambiarlo desde ajustes

    public static function init() {
        // Shortcode
        add_shortcode('aicode', [__CLASS__, 'render_shortcode']);

        // Assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX (logueados y no logueados)
        add_action('wp_ajax_aicode_chat', [__CLASS__, 'handle_chat']);
        add_action('wp_ajax_nopriv_aicode_chat', [__CLASS__, 'handle_chat']);

        // Página de Ajustes (en includes/admin-page.php)
        add_action('admin_init', ['AICode_Admin_Page', 'register_settings']);
        add_action('admin_menu', ['AICode_Admin_Page', 'register_menu']);
    }

    public static function enqueue_assets() {
        // CSS
        wp_enqueue_style('aicode-css', AICODE_PLUGIN_URL . 'assets/css/aicode.css', [], AICODE_PLUGIN_VERSION);
        // Highlight.js CDN (ligero)
        wp_enqueue_style('aicode-hljs', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/styles/default.min.css', [], '11.5.1');

        // JS
        wp_enqueue_script('aicode-hljs', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/highlight.min.js', [], '11.5.1', true);
        wp_enqueue_script('aicode-js', AICODE_PLUGIN_URL . 'assets/js/aicode.js', ['jquery'], AICODE_PLUGIN_VERSION, true);

        // Datos para JS
        wp_localize_script('aicode-js', 'AICODE', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('aicode_nonce'),
            'model'     => self::get_model(),
        ]);
    }

    public static function render_shortcode($atts) {
        // Comprobación de clave
        if (!self::get_api_key()) {
            $admin_url = admin_url('options-general.php?page=aicode-settings');
            return '<div class="aicode-warning">Falta la API Key de OpenAI. Añádela en <a href="' . esc_url($admin_url) . '">Ajustes → AI Code</a> o define la constante <code>OPENAI_API_KEY</code> en wp-config.php.</div>';
        }

        ob_start();
        ?>
        <div class="aicode-container">
            <div class="chat-container">
                <div class="left-panel">
                    <div id="aicode-chat-box" class="chat-box"></div>

                    <div id="aicode-typing" class="message assistant" style="display:none;">
                        <div class="typing-container">
                            <div class="typing"></div>
                            <div class="typing"></div>
                            <div class="typing"></div>
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

    /** Manejo de la llamada al modelo + extracción de ficheros */
    public static function handle_chat() {
        check_ajax_referer('aicode_nonce', 'nonce');

        // Seguridad básica de origen
        if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['HTTP_ORIGIN'])) {
            // seguimos, WordPress ya mitiga CSRF con nonce
        }

        $user_input = isset($_POST['user_input']) ? wp_kses_post(trim($_POST['user_input'])) : '';
        $action_cmd = isset($_POST['action_cmd']) ? sanitize_text_field($_POST['action_cmd']) : '';

        // Reset conversación (opcional: podrías usar transients por usuario)
        if ($action_cmd === 'reset') {
            delete_transient(self::conv_key());
            wp_send_json_success(['response' => 'Conversación vaciada.']);
        }

        // Juntar contenido de archivos
        $file_text = '';
        if (!empty($_FILES['files'])) {
            $file_text = self::process_files($_FILES['files']);
        }

        $ai_prompt = self::base_system_prompt();

        // Recuperar histórico
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

        // Guardar histórico
        $conversation[] = ['role' => 'assistant', 'content' => $assistant_text];
        set_transient(self::conv_key(), $conversation, HOUR_IN_SECONDS);

        wp_send_json_success(['response' => $assistant_text]);
    }

    /** Procesa subidas y extrae texto si es posible */
    private static function process_files($files) {
        $out = '';
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $out .= "El archivo " . sanitize_text_field($files['name'][$i]) . " no se pudo subir.\n\n";
                continue;
            }

            // Manejo WordPress
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

            $path = $moved['file'];
            $mime = wp_check_filetype($path)['type'];

            // Intentar extracción
            $out .= AICode_Extractors::extract_text($path, $mime);
            $out .= "\n\n";
        }

        return $out;
    }

    /** Llamada a OpenAI usando WP HTTP API */
    private static function openai_chat(array $messages) {
        $api_key = self::get_api_key();
        $model   = self::get_model();

        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $payload  = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => 30000,
            'temperature' => 0.1,
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 60,
        ];

        return wp_remote_post($endpoint, $args);
    }

    /** Clave de conversación por usuario/sesión */
    private static function conv_key() {
        $user_id = get_current_user_id();
        $suffix  = $user_id ? ('user_' . $user_id) : ('ip_' . md5($_SERVER['REMOTE_ADDR'] ?? 'guest'));
        return 'aicode_conv_' . $suffix;
    }

    /** System Prompt (tu prompt, con microajustes de seguridad) */
    private static function base_system_prompt() {
        return <<<PROMPT
Actúa como aiCode, mi asistente de programación inteligente, especializado en generar cualquier tipo de código en múltiples lenguajes como HTML, Python, PHP y Visual Basic. Tu objetivo es transformar conceptos o textos en código funcional y eficiente. Directrices:

Generación de Código: A partir de un concepto o texto proporcionado, genera código funcional en el lenguaje solicitado. Si se solicita código en HTML, enciérralo siempre dentro de un <div> (con <style> interno si hace falta). No generes un documento HTML completo salvo que se pida explícitamente.

Recepción de Código: Usa siempre el código que te facilite el usuario como referencia para mejorar/ajustar.

Sugerencias de Optimización: Acompaña con consejos de rendimiento, seguridad y buenas prácticas, de forma breve y clara.

Solución de Problemas: Da soluciones claras y prioriza la más eficiente.

Asistencia Multilenguaje: Adapta las convenciones al lenguaje solicitado.

Integración con Plesk: Puedes ofrecer instrucciones para desplegar en Plesk.

Formato de Respuesta: Enciérralo entre etiquetas <code> y </code> y sugiere "Nombre del archivo sugerido: ..." al inicio.

Interacción: Tono profesional, claro y accesible.

Importante: Sólo si te lo piden, genera una página HTML completa con <html> y <body>.

Tu Rol: Proporciona soluciones precisas y eficientes según las necesidades del usuario.
PROMPT;
    }

    /** Lee la API key (prioridad: wp-config, luego ajustes) */
    public static function get_api_key() {
        if (defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
            return OPENAI_API_KEY;
        }
        $opt = get_option(self::OPTION_API_KEY, '');
        return is_string($opt) ? trim($opt) : '';
    }

    /** Modelo por defecto (editable en Ajustes) */
    public static function get_model() {
        $model = get_option(self::OPTION_MODEL, 'gpt-4.1');
        return $model ?: 'gpt-4.1';
    }
}
