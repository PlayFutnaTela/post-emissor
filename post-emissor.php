<?php
/*
Plugin Name: Post Emissor
Description: Plugin para enviar posts publicados para sites receptores via REST API, replicando conteúdo, mídias, SEO, e aplicando traduções.
Version: 1.0.0
Author: Alexandre Chaves
License: GPL2
Text Domain: post-emissor
Domain Path: /languages
*/

// Impede o acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Carrega os arquivos de tradução do plugin
function post_emissor_load_textdomain() {
    load_plugin_textdomain('post-emissor', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'post_emissor_load_textdomain');

// Define constantes para o diretório e URL do plugin
if (!defined('POST_EMISSOR_DIR')) {
    define('POST_EMISSOR_DIR', plugin_dir_path(__FILE__));
}
if (!defined('POST_EMISSOR_URL')) {
    define('POST_EMISSOR_URL', plugin_dir_url(__FILE__));
}

// Inclui os arquivos necessários
require_once POST_EMISSOR_DIR . 'includes/class-post-emissor.php';
require_once POST_EMISSOR_DIR . 'includes/class-post-emissor-metabox.php';
require_once POST_EMISSOR_DIR . 'includes/rest-api.php';
require_once POST_EMISSOR_DIR . 'includes/admin-settings.php';

// Inicializa o plugin
function init_post_emissor() {
    $post_emissor = new Post_Emissor();
    $post_emissor->init();
}
add_action('plugins_loaded', 'init_post_emissor');

function post_emissor_show_admin_notices() {
    global $pagenow;
    if ( $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
        $post_id = (int) $_GET['post'];
        $results = get_transient( 'post_emissor_report_' . $post_id );
        if ( $results !== false ) {
            // Apaga o transient para não repetir o aviso
            delete_transient( 'post_emissor_report_' . $post_id );
            
            // Monta a mensagem
            $messages = array();
            foreach ( $results as $item ) {
                if ( $item['status'] === 'ok' ) {
                    $messages[] = sprintf(
                        '<li style="color:green;"><strong>%s:</strong> %s</li>',
                        esc_html( $item['url'] ),
                        esc_html( $item['message'] )
                    );
                } else {
                    $messages[] = sprintf(
                        '<li style="color:red;"><strong>%s:</strong> %s</li>',
                        esc_html( $item['url'] ),
                        esc_html( $item['message'] )
                    );
                }
            }
            ?>
            <div class="notice notice-info">
                <p><strong><?php _e('Relatório de Envio aos Receptores:', 'post-emissor'); ?></strong></p>
                <ul>
                    <?php echo implode('', $messages); ?>
                </ul>
            </div>
            <?php
        }
    }
}
add_action( 'admin_notices', 'post_emissor_show_admin_notices' );

