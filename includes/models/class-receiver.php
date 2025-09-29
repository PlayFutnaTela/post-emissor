<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe modelo para receptor
 *
 * Representa um site receptor no sistema de envio de posts
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Receiver {
    
    public $id;
    public $url;
    public $auth_token;
    public $name;
    public $status;
    public $created_at;
    public $updated_at;
    
    /**
     * Construtor da classe
     *
     * @param array $data Dados do receptor
     */
    public function __construct($data = array()) {
        $this->id = isset($data['id']) ? (int)$data['id'] : 0;
        $this->url = isset($data['url']) ? esc_url_raw($data['url']) : '';
        $this->auth_token = isset($data['auth_token']) ? sanitize_text_field($data['auth_token']) : '';
        $this->name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $this->status = isset($data['status']) ? sanitize_text_field($data['status']) : 'active';
        $this->created_at = isset($data['created_at']) ? sanitize_text_field($data['created_at']) : current_time('mysql');
        $this->updated_at = isset($data['updated_at']) ? sanitize_text_field($data['updated_at']) : current_time('mysql');
    }
    
    /**
     * Valida os dados do receptor
     *
     * @return bool True se os dados forem válidos, false caso contrário
     */
    public function validate() {
        // Validar URL
        if (empty($this->url) || !filter_var($this->url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Validar protocolo
        $parsed_url = parse_url($this->url);
        if (!in_array($parsed_url['scheme'], array('http', 'https'))) {
            return false;
        }
        
        // Verificar se é um endereço local (não permitido)
        if ($this->is_local_address($parsed_url['host'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se o host é um endereço local
     *
     * @param string $host Host para verificar
     * @return bool True se for local, false caso contrário
     */
    private function is_local_address($host) {
        // Verificar se é um endereço IPv4 local
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        // Verificar endereços comuns de localhost
        $local_hosts = array('localhost', '127.0.0.1', '::1');
        return in_array(strtolower($host), $local_hosts);
    }
    
    /**
     * Criptografa o token de autenticação
     */
    public function encrypt_token() {
        if (!empty($this->auth_token)) {
            $this->auth_token = $this->encrypt_data($this->auth_token);
        }
    }
    
    /**
     * Descriptografa o token de autenticação
     */
    public function decrypt_token() {
        if (!empty($this->auth_token)) {
            $this->auth_token = $this->decrypt_data($this->auth_token);
        }
    }
    
    /**
     * Criptografa dados usando OpenSSL
     *
     * @param string $data Dados para criptografar
     * @return string Dados criptografados
     */
    private function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return $data; // Fallback para instalações sem OpenSSL
        }
        
        $method = 'AES-256-CBC';
        $key = hash('sha256', DASHI_EMISSOR_ENCRYPTION_KEY);
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Descriptografa dados usando OpenSSL
     *
     * @param string $encrypted_data Dados criptografados
     * @return string Dados descriptografados
     */
    private function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return $encrypted_data; // Fallback para instalações sem OpenSSL
        }
        
        $method = 'AES-256-CBC';
        $key = hash('sha256', DASHI_EMISSOR_ENCRYPTION_KEY);
        $data = base64_decode($encrypted_data);
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }
    
    /**
     * Salva o receptor no banco de dados
     *
     * @return bool True se salvo com sucesso, false caso contrário
     */
    public function save() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_receivers';
        
        // Criptografar token antes de salvar
        $receiver_data = array(
            'url' => $this->url,
            'auth_token' => $this->auth_token,
            'name' => $this->name,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => current_time('mysql')
        );
        
        $format = array('%s', '%s', '%s', '%s', '%s', '%s');
        
        if ($this->id > 0) {
            // Atualizar receptor existente
            $result = $wpdb->update(
                $table_name,
                $receiver_data,
                array('id' => $this->id),
                $format,
                array('%d')
            );
        } else {
            // Inserir novo receptor
            $result = $wpdb->insert($table_name, $receiver_data, $format);
            
            if ($result !== false) {
                $this->id = $wpdb->insert_id;
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Remove o receptor do banco de dados
     *
     * @return bool True se removido com sucesso, false caso contrário
     */
    public function delete() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_receivers';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $this->id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Recupera um receptor pelo ID
     *
     * @param int $id ID do receptor
     * @return Dashi_Emissor_Receiver|false Instância do receptor ou false se não encontrado
     */
    public static function get_by_id($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_receivers';
        
        $data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
            ARRAY_A
        );
        
        if ($data) {
            $receiver = new self($data);
            // Descriptografar token ao recuperar
            $receiver->decrypt_token();
            return $receiver;
        }
        
        return false;
    }
    
    /**
     * Recupera todos os receptores
     *
     * @return array Array de receptores
     */
    public static function get_all() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_receivers';
        
        $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        $receivers = array();
        
        foreach ($results as $data) {
            $receiver = new self($data);
            // Descriptografar token ao recuperar
            $receiver->decrypt_token();
            $receivers[] = $receiver;
        }
        
        return $receivers;
    }
}