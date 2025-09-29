<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para componentes administrativos do plugin
 *
 * Gerencia a interface administrativa, incluindo configurações,
 * modais e recursos AJAX
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Admin {
    
    public $receivers;
    public $origin_language;
    
    /**
     * Inicializa os componentes administrativos
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_dashi_emissor_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_dashi_emissor_save_token', array($this, 'ajax_save_token'));
    }
    
    /**
     * Adiciona o menu do plugin no painel administrativo
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Dashi Emissor', 'dashi-emissor'),
            __('Dashi Emissor', 'dashi-emissor'),
            'manage_options',
            'dashi-emissor',
            array($this, 'settings_page'),
            'dashicons-email-alt'
        );
    }
    
    /**
     * Registra as configurações do plugin utilizando a Settings API do WordPress
     */
    public function register_settings() {
        register_setting('dashi_emissor_settings_group', 'dashi_emissor_receivers', array(
            'sanitize_callback' => array($this, 'sanitize_receivers')
        ));
        register_setting('dashi_emissor_settings_group', 'dashi_emissor_origin_language');
    }
    
    /**
     * Callback para sanitizar o array de receptores
     * Para cada receptor, se o campo "auth_token" estiver vazio,
     * preserva o token previamente armazenado (se houver)
     *
     * @param array $input O array de receptores submetido
     * @return array O array de receptores processado
     */
    public function sanitize_receivers($input) {
        $old = get_option('dashi_emissor_receivers', array());
        if (is_array($input)) {
            foreach ($input as $key => $receiver) {
                if (empty($receiver['auth_token']) && isset($old[$key]['auth_token'])) {
                    // Descriptografar o token antigo antes de comparar
                    $old_token = $old[$key]['auth_token'];
                    if (!empty($old_token)) {
                        $old_token = $this->decrypt_token($old_token);
                    }
                    $input[$key]['auth_token'] = $old_token;
                } else {
                    // Criptografar o novo token
                    if (!empty($receiver['auth_token'])) {
                        $input[$key]['auth_token'] = $this->encrypt_token($receiver['auth_token']);
                    }
                }
            }
        }
        return $input;
    }
    
    /**
     * Encripta um token
     *
     * @param string $token Token para encriptar
     * @return string Token encriptado
     */
    private function encrypt_token($token) {
        if (!function_exists('openssl_encrypt')) {
            return $token; // Fallback para instalações sem OpenSSL
        }
        
        $method = 'AES-256-CBC';
        $key = hash('sha256', DASHI_EMISSOR_ENCRYPTION_KEY);
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($token, $method, $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decripta um token
     *
     * @param string $encrypted_token Token encriptado
     * @return string Token decriptado
     */
    private function decrypt_token($encrypted_token) {
        if (!function_exists('openssl_decrypt')) {
            return $encrypted_token; // Fallback para instalações sem OpenSSL
        }
        
        $method = 'AES-256-CBC';
        $key = hash('sha256', DASHI_EMISSOR_ENCRYPTION_KEY);
        $data = base64_decode($encrypted_token);
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }
    
    /**
     * Carrega scripts e estilos administrativos
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_dashi-emissor') {
            return;
        }
        
        wp_enqueue_style('dashi-emissor-admin', DASHI_EMISSOR_URL . 'assets/css/admin.css', array(), DASHI_EMISSOR_VERSION);
        wp_enqueue_script('dashi-emissor-admin', DASHI_EMISSOR_URL . 'assets/js/modals.js', array('jquery'), DASHI_EMISSOR_VERSION, true);
        
        wp_localize_script('dashi-emissor-admin', 'dashi_emissor', array(
            'nonce' => wp_create_nonce('dashi_emissor_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'receiver_count' => count($this->get_receivers())
        ));
    }
    
    /**
     * Renderiza a página de configurações do plugin
     */
    public function settings_page() {
        // Carrega o idioma de origem salvo
        $this->origin_language = get_option('dashi_emissor_origin_language', get_locale());
        // Recupera os sites receptores cadastrados e reindexa para índices sequenciais
        $this->receivers = get_option('dashi_emissor_receivers', array());
        if (!is_array($this->receivers)) {
            $this->receivers = array();
        }
        $this->receivers = array_values($this->receivers);
        
        // Carregar templates
        require DASHI_EMISSOR_DIR . 'templates/admin/settings.php';
        require DASHI_EMISSOR_DIR . 'templates/admin/modals/connection-test-modal.php';
        require DASHI_EMISSOR_DIR . 'templates/admin/modals/error-details-modal.php';
        require DASHI_EMISSOR_DIR . 'templates/admin/modals/status-report-modal.php';
        
        // Adicionar script JavaScript com o número correto de receptores
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Atualizar o índice do receptor para novos itens
            var receiverIndex = <?php echo count($this->receivers); ?>;
            // Isso é feito novamente aqui para garantir que o JS tenha o valor correto
        });
        </script>
        <?php
    }
    
    /**
     * Manipulador AJAX para testar conexão com receptor
     */
    public function ajax_test_connection() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dashi_emissor_nonce')) {
            wp_die('Nonce inválido', 'Erro', array('response' => 403));
        }
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão', 'Erro', array('response' => 403));
        }
        
        $url = sanitize_url($_POST['url']);
        $token = sanitize_text_field($_POST['token']);
        
        if (empty($url)) {
            wp_send_json_error(array('message' => 'URL é obrigatória'));
        }
        
        // Testar conexão
        require_once DASHI_EMISSOR_DIR . 'includes/api/class-emissor-api-client.php';
        $logger = new Dashi_Emissor_Logger(); // Criar uma instância temporária para testes
        $api_client = new Dashi_Emissor_Api_Client($logger);
        
        $receiver = array(
            'url' => $url,
            'auth_token' => $token
        );
        
        $result = $api_client->test_connection($receiver);
        
        if ($result['status'] === 'ok') {
            wp_send_json_success(array('message' => 'Conectado com sucesso'));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Manipulador AJAX para salvar token
     */
    public function ajax_save_token() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dashi_emissor_save_token')) {
            wp_send_json_error('Nonce inválido');
        }
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        $receiver_index = intval($_POST['receiver_index']);
        $receiver_url = sanitize_url($_POST['receiver_url']);
        $auth_token = sanitize_text_field($_POST['auth_token']);
        
        if (empty($receiver_url) || empty($auth_token)) {
            wp_send_json_error('URL e Token são obrigatórios');
        }
        
        // Carrega receptores atuais
        $receivers = get_option('dashi_emissor_receivers', array());
        if (!is_array($receivers)) {
            $receivers = array();
        }
        
        // Garante que o índice existe
        if (!isset($receivers[$receiver_index])) {
            $receivers[$receiver_index] = array();
        }
        
        // Atualiza o receptor com token criptografado
        $receivers[$receiver_index]['url'] = $receiver_url;
        $receivers[$receiver_index]['auth_token'] = $this->encrypt_token($auth_token);
        
        // Salva no banco
        $updated = update_option('dashi_emissor_receivers', $receivers);
        
        if ($updated) {
            wp_send_json_success('Token salvo com sucesso');
        } else {
            wp_send_json_error('Erro ao salvar no banco de dados');
        }
    }
    
    /**
     * Obtém a lista de receptores
     */
    private function get_receivers() {
        $receivers = get_option('dashi_emissor_receivers', array());
        if (!is_array($receivers)) {
            $receivers = array();
        }
        return array_values($receivers);
    }
}