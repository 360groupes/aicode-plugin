<?php
if (!defined('ABSPATH')) exit;

class AICode_Admin_Page {
    public static function register_menu() {
        add_options_page(
            __('AI Code', 'aicode'),
            __('AI Code', 'aicode'),
            'manage_options',
            'aicode-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting('aicode_settings', AICode_Plugin::OPTION_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => function($v){ return is_string($v) ? trim($v) : ''; },
            'default' => '',
        ]);
        register_setting('aicode_settings', AICode_Plugin::OPTION_MODEL, [
            'type' => 'string',
            'sanitize_callback' => function($v){ return is_string($v) ? trim($v) : 'gpt-4.1'; },
            'default' => 'gpt-4.1',
        ]);
        // NUEVO: Prompt editable
        register_setting('aicode_settings', AICode_Plugin::OPTION_PROMPT, [
            'type' => 'string',
            'sanitize_callback' => function($v){
                // Permitimos texto largo; recortamos a ~24k chars por seguridad
                $v = is_string($v) ? trim($v) : '';
                return mb_substr($v, 0, 24000);
            },
            'default' => '',
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        $nonce = wp_create_nonce('aicode_admin_nonce');
        ?>
        <div class="wrap">
            <h1>AI Code – Ajustes</h1>
            <p>Gestiona aquí la clave de OpenAI, el modelo y el prompt del sistema.</p>

            <form method="post" action="options.php">
                <?php settings_fields('aicode_settings'); ?>
                <?php do_settings_sections('aicode_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aicode_openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(AICode_Plugin::OPTION_API_KEY); ?>" id="aicode_openai_api_key"
                                   value="<?php echo esc_attr(get_option(AICode_Plugin::OPTION_API_KEY, '')); ?>" class="regular-text" />
                            <label><input type="checkbox" id="aicode_show_key" /> Mostrar</label>
                            <p class="description">La clave se almacena en opciones de WordPress (BD). Se recomienda limitar el acceso a este panel.</p>
                            <p>
                                <button type="button" class="button" id="aicode_test_key_btn">Probar conexión</button>
                                <span id="aicode_test_key_result" style="margin-left:8px;"></span>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="aicode_openai_model">Modelo</label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(AICode_Plugin::OPTION_MODEL); ?>" id="aicode_openai_model"
                                   value="<?php echo esc_attr(get_option(AICode_Plugin::OPTION_MODEL, 'gpt-4.1')); ?>" class="regular-text" />
                            <p class="description">Ejemplo: gpt-4.1 (ajústalo según tus contratos/compatibilidades).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="aicode_system_prompt">System Prompt</label></th>
                        <td>
                            <textarea name="<?php echo esc_attr(AICode_Plugin::OPTION_PROMPT); ?>" id="aicode_system_prompt" class="large-text code" rows="12"><?php
                                echo esc_textarea(get_option(AICode_Plugin::OPTION_PROMPT, ''));
                            ?></textarea>
                            <p class="description">Déjalo vacío para usar el prompt por defecto incluido en el plugin.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar cambios'); ?>
            </form>

            <script>
            (function(){
                const show = document.getElementById('aicode_show_key');
                const key  = document.getElementById('aicode_openai_api_key');
                if (show && key) {
                    show.addEventListener('change', ()=> key.type = show.checked ? 'text' : 'password');
                }

                const testBtn = document.getElementById('aicode_test_key_btn');
                const result  = document.getElementById('aicode_test_key_result');
                if (testBtn && result) {
                    testBtn.addEventListener('click', ()=>{
                        result.textContent = 'Probando...';
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'aicode_test_key',
                                nonce: '<?php echo esc_js($nonce); ?>'
                            })
                        })
                        .then(r=>r.json())
                        .then(data=>{
                            if (data.success) {
                                result.textContent = '✅ ' + (data.data && data.data.message ? data.data.message : 'OK');
                                result.style.color = '#229954';
                            } else {
                                result.textContent = '❌ ' + (data.data && data.data.message ? data.data.message : 'Error');
                                result.style.color = '#C0392B';
                            }
                        })
                        .catch(err=>{
                            result.textContent = '❌ ' + err.message;
                            result.style.color = '#C0392B';
                        });
                    });
                }
            })();
            </script>
        </div>
        <?php
    }
}
