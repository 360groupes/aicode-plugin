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
            'sanitize_callback' => function($v){ return trim($v); },
            'default' => '',
        ]);
        register_setting('aicode_settings', AICode_Plugin::OPTION_MODEL, [
            'type' => 'string',
            'sanitize_callback' => function($v){ return trim($v); },
            'default' => 'gpt-4.1',
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;

        ?>
        <div class="wrap">
            <h1>AI Code – Ajustes</h1>
            <p>Recomendado: define tu clave en <code>wp-config.php</code> con <code>define('OPENAI_API_KEY','...');</code>.  
            Si la dejas vacía, se usará la opción guardada abajo.</p>

            <form method="post" action="options.php">
                <?php settings_fields('aicode_settings'); ?>
                <?php do_settings_sections('aicode_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aicode_openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(AICode_Plugin::OPTION_API_KEY); ?>" id="aicode_openai_api_key"
                                   value="<?php echo esc_attr(get_option(AICode_Plugin::OPTION_API_KEY, '')); ?>" class="regular-text" />
                            <p class="description">Se usará sólo si no está definida la constante <code>OPENAI_API_KEY</code>.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="aicode_openai_model">Modelo</label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(AICode_Plugin::OPTION_MODEL); ?>" id="aicode_openai_model"
                                   value="<?php echo esc_attr(get_option(AICode_Plugin::OPTION_MODEL, 'gpt-4.1')); ?>" class="regular-text" />
                            <p class="description">Ej: gpt-4.1 (puedes cambiar a otro compatible).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr/>
            <h2>Uso</h2>
            <p>Inserta en cualquier página o entrada el shortcode: <code>[aicode]</code></p>
        </div>
        <?php
    }
}
