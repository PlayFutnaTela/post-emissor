<?php
/*
Plugin Name: Dashi Emissor
Description: Plugin para enviar posts publicados para sites receptores via REST API, replicando conteúdo, mídias, SEO, e aplicando traduções. Versão profissional e otimizada.
Version: 2.0.0
Author: Alexandre Chaves
License: GPL2
Text Domain: dashi-emissor
Domain Path: /languages
*/

// Impede o acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definições de diretórios
if (!defined('DASHI_EMISSOR_VERSION')) {
    define('DASHI_EMISSOR_VERSION', '2.0.0');
}
if (!defined('DASHI_EMISSOR_DIR')) {
    define('DASHI_EMISSOR_DIR', plugin_dir_path(__FILE__));
}
if (!defined('DASHI_EMISSOR_URL')) {
    define('DASHI_EMISSOR_URL', plugin_dir_url(__FILE__));
}
if (!defined('DASHI_EMISSOR_SLUG')) {
    define('DASHI_EMISSOR_SLUG', 'dashi-emissor');
}

/**
 * Ativação do plugin
 */
function dashi_emissor_activate() {
    // Inicializar instância principal para criar tabelas
    require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-core.php';
    $emissor = Dashi_Emissor_Core::get_instance();
    $emissor->create_database_tables();
    
    // Agendar eventos do WP-Cron
    if (!wp_next_scheduled('dashi_emissor_process_queue')) {
        wp_schedule_event(time(), 'every_minute', 'dashi_emissor_process_queue');
    }
}
register_activation_hook(__FILE__, 'dashi_emissor_activate');

/**
 * Desativação do plugin
 */
function dashi_emissor_deactivate() {
    wp_clear_scheduled_hook('dashi_emissor_process_queue');
}
register_deactivation_hook(__FILE__, 'dashi_emissor_deactivate');

/**
 * Desinstalação do plugin
 */
function dashi_emissor_uninstall() {
    global $wpdb;
    
    // Opções a serem removidas
    $options = array(
        'dashi_emissor_receivers',
        'dashi_emissor_origin_language',
        'dashi_emissor_encryption_key'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Tabelas a serem removidas
    $tables = array(
        $wpdb->prefix . 'dashi_emissor_logs',
        $wpdb->prefix . 'dashi_emissor_queue',
        $wpdb->prefix . 'dashi_emissor_reports',
        $wpdb->prefix . 'dashi_emissor_receivers'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
register_uninstall_hook(__FILE__, 'dashi_emissor_uninstall');

// Carrega os arquivos de tradução do plugin
function dashi_emissor_load_textdomain() {
    load_plugin_textdomain('dashi-emissor', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'dashi_emissor_load_textdomain');

// Inclui os arquivos necessários
require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-core.php';

// Inicializa o plugin
function init_dashi_emissor() {
    $emissor = Dashi_Emissor_Core::get_instance();
    $emissor->init();
}
add_action('plugins_loaded', 'init_dashi_emissor');

// Adiciona admin notice para mostrar relatórios
function dashi_emissor_show_admin_notices() {
    global $pagenow;
    if ($pagenow === 'post.php' && isset($_GET['post'])) {
        $post_id = (int) $_GET['post'];
        $results = get_transient('dashi_emissor_report_' . $post_id);
        if ($results !== false) {
            // Apaga o transient para não repetir o aviso
            delete_transient('dashi_emissor_report_' . $post_id);
            
            // Monta a mensagem
            $messages = array();
            foreach ($results as $item) {
                if ($item['status'] === 'ok') {
                    $messages[] = sprintf(
                        '<li style="color:green;"><strong>%s:</strong> %s</li>',
                        esc_html($item['url']),
                        esc_html($item['message'])
                    );
                } else {
                    $messages[] = sprintf(
                        '<li style="color:red;"><strong>%s:</strong> %s</li>',
                        esc_html($item['url']),
                        esc_html($item['message'])
                    );
                }
            }
            ?>
            <div class="notice notice-info">
                <p><strong><?php _e('Relatório de Envio aos Receptores:', 'dashi-emissor'); ?></strong></p>
                <ul>
                    <?php echo implode('', $messages); ?>
                </ul>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'dashi_emissor_show_admin_notices');