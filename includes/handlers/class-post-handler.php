<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para manipular eventos relacionados a posts
 *
 * Gerencia os eventos de criação, atualização e deleção de posts
 * e dispara as ações apropriadas para envio aos receptores
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Post_Handler {
    
    private $logger;
    
    /**
     * Construtor da classe
     *
     * @param Dashi_Emissor_Logger $logger Instância do logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Inicializa os hooks para manipulação de posts
     */
    public function init() {
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('before_delete_post', array($this, 'handle_post_deletion'));
        add_action('save_post', array($this, 'handle_save_post'), 10, 3);
    }
    
    /**
     * Lida com a mudança de status do post
     *
     * @param string $new_status Novo status do post
     * @param string $old_status Status anterior do post
     * @param WP_Post $post Objeto do post
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        // Verifica se é um post do tipo correto
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Se o post for publicado agora (e não estava publicado antes), envia para os receptores
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->send_post_data($post);
        }
        // Se ele estava publicado e muda para outro status, propaga essa atualização de status
        if ($old_status === 'publish' && $new_status !== 'publish') {
            $this->update_post_status($post, $new_status);
        }
    }
    
    /**
     * Captura atualizações em posts publicados
     *
     * @param int $post_ID ID do post
     * @param WP_Post $post Objeto do post
     * @param bool $update Se é atualização (true) ou nova inserção (false)
     */
    public function handle_save_post($post_ID, $post, $update) {
        // Verifica se é um post do tipo correto
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Ignora autosave, revisões, ou se for novo e ainda não publicado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_ID)) return;
        if (!$update) return;

        // Se já está publicado, envia para os receptores
        if ('publish' === $post->post_status) {
            $this->send_post_data($post);
        }
    }
    
    /**
     * Trata a deleção de um post
     *
     * @param int $post_id ID do post a ser deletado
     */
    public function handle_post_deletion($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'post') {
            $delete_data = array(
                'ID' => $post_id,
                'action' => 'delete'
            );
            
            // Adicionar à fila para processamento assíncrono
            $this->queue_post_deletion($delete_data);
        }
    }
    
    /**
     * Envia os dados do post via REST API para os sites receptores selecionados
     *
     * @param WP_Post $post Objeto do post
     */
    public function send_post_data($post) {
        require_once DASHI_EMISSOR_DIR . 'includes/models/class-post-data.php';
        require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-cron.php';
        require_once DASHI_EMISSOR_DIR . 'includes/handlers/class-translation-handler.php';
        
        $post_data = Dashi_Emissor_Post_Data::get_formatted_data($post);
        
        // Verificar se a tradução está ativada e aplicar se necessário
        $target_language = $this->get_target_language_for_receivers($post->ID);
        if ($target_language && $this->is_translation_enabled()) {
            $translation_handler = new Dashi_Emissor_Translation_Handler($this->logger);
            
            $source_language = get_option('dashi_emissor_origin_language', get_locale());
            
            // Traduzir campos específicos
            $post_data['title'] = $translation_handler->translate_text_context($post_data['title'], $source_language, $target_language, 'title');
            $post_data['content'] = $translation_handler->translate_text_context($post_data['content'], $source_language, $target_language, 'body');
            $post_data['excerpt'] = $translation_handler->translate_text_context($post_data['excerpt'], $source_language, $target_language, 'default');
        }
        
        $all_receivers = $this->get_all_receivers();
        $selected = get_post_meta($post->ID, '_dashi_emissor_selected_receivers', true);
        if (!is_array($selected)) {
            $selected = array();
        }
        
        $receivers_to_send = array();
        foreach ($selected as $index) {
            if (isset($all_receivers[$index])) {
                $receivers_to_send[] = $all_receivers[$index];
            }
        }
        
        if (empty($receivers_to_send)) {
            $this->logger->info('Nenhum receptor selecionado para o envio do post', array('post_id' => $post->ID));
            return; // nada a enviar
        }
        
        // Adicionar à fila para processamento assíncrono
        $cron = new Dashi_Emissor_Cron($this->logger);
        $cron->add_to_queue($post_data, $receivers_to_send, $post->ID, 'send');
        
        $this->logger->info(sprintf(
            'Post adicionado à fila para envio: %s',
            $post->post_title
        ), array(
            'post_id' => $post->ID,
            'receivers_count' => count($receivers_to_send)
        ));
    }
    
    /**
     * Verifica se a tradução está ativada
     *
     * @return bool True se a tradução estiver ativada, false caso contrário
     */
    private function is_translation_enabled() {
        $openai_api_key = get_option('dashi_emissor_openai_api_key', '');
        return !empty($openai_api_key);
    }
    
    /**
     * Obtém o idioma alvo para os receptores selecionados
     *
     * @param int $post_id ID do post
     * @return string|false Idioma alvo ou false se não definido
     */
    private function get_target_language_for_receivers($post_id) {
        // Por enquanto, retornando o idioma padrão
        // Em implementações futuras, isso pode ser baseado em configurações específicas por receptor
        return false;
    }
    
    /**
     * Propaga a atualização do status do post para os sites receptores
     *
     * @param WP_Post $post Objeto do post
     * @param string $new_status Novo status do post
     */
    public function update_post_status($post, $new_status) {
        require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-cron.php';
        
        $update_data = array(
            'ID' => $post->ID,
            'status' => $new_status
        );
        
        $selected_receivers = get_post_meta($post->ID, '_dashi_emissor_selected_receivers', true);
        if (!is_array($selected_receivers)) {
            $selected_receivers = array();
        }
        
        if (empty($selected_receivers)) {
            $this->logger->info('Nenhum receptor selecionado para atualização de status', array('post_id' => $post->ID));
            return;
        }
        
        $all_receivers = $this->get_all_receivers();
        $receivers_to_update = array();
        
        foreach ($selected_receivers as $index) {
            if (isset($all_receivers[$index])) {
                $receivers_to_update[] = $all_receivers[$index];
            }
        }
        
        if (empty($receivers_to_update)) {
            $this->logger->info('Nenhum receptor válido encontrado para atualização de status', array('post_id' => $post->ID));
            return;
        }
        
        // Adicionar à fila para processamento assíncrono
        $cron = new Dashi_Emissor_Cron($this->logger);
        $cron->add_to_queue($update_data, $receivers_to_update, $post->ID, 'update_status');
        
        $this->logger->info(sprintf(
            'Atualização de status adicionada à fila: %s (novo status: %s)',
            $post->post_title,
            $new_status
        ), array(
            'post_id' => $post->ID,
            'new_status' => $new_status,
            'receivers_count' => count($receivers_to_update)
        ));
    }
    
    /**
     * Adiciona comando de deleção à fila
     *
     * @param array $delete_data Dados da deleção
     */
    private function queue_post_deletion($delete_data) {
        require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-cron.php';
        
        $selected_receivers = get_post_meta($delete_data['ID'], '_dashi_emissor_selected_receivers', true);
        if (!is_array($selected_receivers)) {
            $selected_receivers = array();
        }
        
        if (empty($selected_receivers)) {
            $this->logger->info('Nenhum receptor selecionado para deleção', array('post_id' => $delete_data['ID']));
            return;
        }
        
        $all_receivers = $this->get_all_receivers();
        $receivers_to_delete = array();
        
        foreach ($selected_receivers as $index) {
            if (isset($all_receivers[$index])) {
                $receivers_to_delete[] = $all_receivers[$index];
            }
        }
        
        if (empty($receivers_to_delete)) {
            $this->logger->info('Nenhum receptor válido encontrado para deleção', array('post_id' => $delete_data['ID']));
            return;
        }
        
        // Adicionar à fila para processamento assíncrono
        $cron = new Dashi_Emissor_Cron($this->logger);
        $cron->add_to_queue($delete_data, $receivers_to_delete, $delete_data['ID'], 'delete');
        
        $this->logger->info(sprintf(
            'Comando de deleção adicionado à fila: %d',
            $delete_data['ID']
        ), array(
            'post_id' => $delete_data['ID'],
            'receivers_count' => count($receivers_to_delete)
        ));
    }
    
    /**
     * Obtém todos os receptores configurados
     *
     * @return array Lista de receptores
     */
    private function get_all_receivers() {
        // Tenta obter do cache primeiro
        $cache_key = 'dashi_emissor_receivers';
        $receivers = get_transient($cache_key);
        
        if ($receivers === false) {
            $receivers = get_option('dashi_emissor_receivers', array());
            set_transient($cache_key, $receivers, 15 * MINUTE_IN_SECONDS);
        }
        
        // Descriptografar tokens antes de retornar
        foreach ($receivers as &$receiver) {
            if (isset($receiver['auth_token']) && !empty($receiver['auth_token'])) {
                $receiver['auth_token'] = $this->decrypt_token($receiver['auth_token']);
            }
        }
        
        return $receivers;
    }
    
    /**
     * Descriptografa um token
     *
     * @param string $encrypted_token Token criptografado
     * @return string Token descriptografado
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
}