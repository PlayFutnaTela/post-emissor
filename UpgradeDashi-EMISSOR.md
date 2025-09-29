# UpgradeDashi-EMISSOR.md - Diretrizes para Criação de um Novo Plugin Emissor Profissional

## Visão Geral do Projeto

Este documento apresenta diretrizes detalhadas para a criação de um novo plugin WordPress Emissor com arquitetura profissional, segura, rápida e altamente depurável. O objetivo é resolver todos os problemas estruturais identificados no plugin original e implementar práticas avançadas de desenvolvimento.

O novo plugin manterá as funcionalidades essenciais do plugin original:
- Replicação de posts para sites receptores via REST API
- Suporte a conteúdo bruto, mídias, metadados, SEO e Elementor
- Tradução automática via API OpenAI
- Comandos de atualização de status e deleção remota
- Interface administrativa para configuração

## Arquitetura e Estrutura do Plugin

### 1. Organização de Arquivos

```
dashi-emissor/
├── dashi-emissor.php (arquivo principal)
├── LICENSE
├── README.md
├── composer.json
├── .gitignore
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── admin.js
│       └── modals.js
├── includes/
│   ├── abstracts/
│   │   └── class-emissor-handler.php
│   ├── api/
│   │   ├── class-emissor-api-client.php
│   │   └── class-emissor-api-request.php
│   ├── handlers/
│   │   ├── class-post-handler.php
│   │   └── class-translation-handler.php
│   ├── models/
│   │   ├── class-receiver.php
│   │   └── class-post-data.php
│   ├── utilities/
│   │   ├── class-logger.php
│   │   ├── class-validator.php
│   │   └── class-sanitizer.php
│   ├── class-emissor-core.php
│   ├── class-emissor-admin.php
│   ├── class-emissor-cron.php
│   └── class-emissor-metabox.php
├── languages/
└── templates/
    └── admin/
        ├── settings.php
        └── modals/
            ├── connection-test-modal.php
            ├── error-details-modal.php
            └── status-report-modal.php
```

### 2. Constantes e Definições

```php
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
```

## Práticas de Segurança

### 1. Validação e Sanitização

- Implementar classes de validação e sanitização robustas
- Utilizar funções nativas do WordPress para validação de dados
- Implementar verificação de permissões (capabilities) em todas as ações
- Validar e sanitizar todos os dados de entrada e saída

### 2. Proteção contra CSRF

- Implementar nonces em todas as requisições de formulários e AJAX
- Utilizar wp_create_nonce() e wp_verify_nonce() corretamente
- Implementar sessão de usuário para proteção adicional

### 3. Sanitização de Dados

- Sanitizar URLs, textos, números e arrays antes do armazenamento
- Utilizar funções como esc_url(), esc_html(), sanitize_text_field()
- Implementar sanitização personalizada para estruturas de dados complexas

## Implementação de Funcionalidades

### 1. Classe Principal (Core)

```php
class Dashi_Emissor_Core {
    
    private static $instance = null;
    public $logger;
    public $api_client;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->init_logger();
        $this->init_hooks();
    }
    
    private function init_logger() {
        $this->logger = new Dashi_Emissor_Logger();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    public function init() {
        $this->init_handlers();
        $this->init_cron();
        $this->init_metabox();
    }
}
```

### 2. Sistema de Logs Profissional

Implementar sistema de logs com níveis (info, warning, error, debug) e armazenamento em banco de dados:

```php
class Dashi_Emissor_Logger {
    
    public function log($level, $message, $context = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_logs';
        
        $data = array(
            'level' => $level,
            'message' => $message,
            'context' => serialize($context),
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        $wpdb->insert($table_name, $data);
    }
    
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }
    
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }
    
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }
}
```

### 3. Cliente API Profissional

```php
class Dashi_Emissor_Api_Client {
    
    private $timeout = 30;
    private $max_retries = 3;
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function send_post_data($post_data, $receivers) {
        $results = array();
        
        foreach ($receivers as $receiver) {
            $result = $this->send_single_post($post_data, $receiver);
            $results[] = $result;
            
            // Registrar detalhes da tentativa
            $this->logger->info(sprintf(
                'Tentativa de envio para receptor: %s - Status: %s',
                $receiver['url'],
                $result['status']
            ), array('receiver' => $receiver, 'result' => $result));
        }
        
        return $results;
    }
    
    private function send_single_post($post_data, $receiver) {
        $endpoint = trailingslashit($receiver['url']) . 'wp-json/post-receptor/v1/receive';
        
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'Dashi-Emissor/' . DASHI_EMISSOR_VERSION
        );
        
        if (!empty($receiver['auth_token'])) {
            $headers['Authorization'] = 'Bearer ' . $receiver['auth_token'];
        }
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($post_data),
            'timeout' => $this->timeout,
            'sslverify' => true,
            'data_format' => 'body'
        );
        
        $response = $this->execute_request_with_retry($endpoint, $args, $receiver);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            $this->logger->error(sprintf(
                'Erro ao enviar dados para receptor: %s - Erro: %s',
                $receiver['url'],
                $error_message
            ), array(
                'receiver' => $receiver,
                'error' => $error_message,
                'post_id' => $post_data['ID']
            ));
            
            return array(
                'url' => $receiver['url'],
                'status' => 'fail',
                'message' => $error_message,
                'error_code' => $response->get_error_code()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->logger->info(sprintf(
            'Envio para receptor: %s - Código de resposta: %d',
            $receiver['url'],
            $response_code
        ), array(
            'receiver' => $receiver,
            'response_code' => $response_code,
            'response_body' => $response_body
        ));
        
        return array(
            'url' => $receiver['url'],
            'status' => ($response_code >= 200 && $response_code < 300) ? 'ok' : 'fail',
            'message' => 'Código de resposta: ' . $response_code,
            'response_code' => $response_code
        );
    }
    
    private function execute_request_with_retry($endpoint, $args, $receiver) {
        $attempt = 0;
        
        while ($attempt < $this->max_retries) {
            $attempt++;
            
            $response = wp_remote_post($endpoint, $args);
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    break; // Requisição bem-sucedida
                }
            }
            
            if ($attempt < $this->max_retries) {
                $sleep_time = pow(2, $attempt) * 1000; // Exponential backoff
                usleep($sleep_time * 1000); // Converter para microssegundos
            }
        }
        
        return $response;
    }
}
```

### 4. Sistema de Filas Assíncronas

Implementar sistema de filas com WP-Cron para envios assíncronos:

```php
class Dashi_Emissor_Cron {
    
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
        add_action('init', array($this, 'schedule_events'));
        add_action('dashi_emissor_process_queue', array($this, 'process_queue'));
    }
    
    public function schedule_events() {
        if (!wp_next_scheduled('dashi_emissor_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'dashi_emissor_process_queue');
        }
    }
    
    public function add_to_queue($post_data, $receivers, $post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_queue';
        
        $data = array(
            'post_id' => $post_id,
            'post_data' => serialize($post_data),
            'receivers' => serialize($receivers),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            $this->logger->info(sprintf('Post adicionado à fila para envio: %d', $post_id));
        } else {
            $this->logger->error(sprintf('Erro ao adicionar post à fila: %d', $post_id));
        }
    }
    
    public function process_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_queue';
        
        // Selecionar até 10 itens pendentes
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at ASC LIMIT 10",
                'pending'
            ),
            ARRAY_A
        );
        
        if (empty($items)) {
            return;
        }
        
        $api_client = new Dashi_Emissor_Api_Client($this->logger);
        
        foreach ($items as $item) {
            $post_data = unserialize($item['post_data']);
            $receivers = unserialize($item['receivers']);
            
            // Processar envio
            $results = $api_client->send_post_data($post_data, $receivers);
            
            // Atualizar status
            $wpdb->update(
                $table_name,
                array('status' => 'completed', 'processed_at' => current_time('mysql')),
                array('id' => $item['id'])
            );
            
            // Armazenar resultados para exibição posterior
            $this->store_results($item['post_id'], $results);
        }
    }
    
    private function store_results($post_id, $results) {
        // Armazenar resultados em um transient com expiração
        set_transient(
            'dashi_emissor_report_' . $post_id,
            $results,
            12 * HOUR_IN_SECONDS
        );
    }
}
```

### 5. Interface Administrativa com Modais

Substituir alerts por modais profissionais:

```php
// Em admin-settings.php
function dashi_emissor_admin_page() {
    // Carregar templates de modais
    include DASHI_EMISSOR_DIR . 'templates/admin/settings.php';
    include DASHI_EMISSOR_DIR . 'templates/admin/modals/connection-test-modal.php';
    include DASHI_EMISSOR_DIR . 'templates/admin/modals/error-details-modal.php';
    include DASHI_EMISSOR_DIR . 'templates/admin/modals/status-report-modal.php';
}
```

Template de modal para detalhes de erro:
```php
<!-- templates/admin/modals/error-details-modal.php -->
<div id="dashi-emissor-error-modal" style="display:none;">
    <div class="dashi-modal-overlay"></div>
    <div class="dashi-modal">
        <div class="dashi-modal-header">
            <h3>Detalhes do Erro</h3>
            <button class="dashi-modal-close">&times;</button>
        </div>
        <div class="dashi-modal-body">
            <pre id="dashi-error-details-content"></pre>
        </div>
        <div class="dashi-modal-footer">
            <button class="dashi-modal-close button">Fechar</button>
        </div>
    </div>
</div>
```

JavaScript para modais:
```javascript
// assets/js/modals.js
jQuery(document).ready(function($) {
    // Abrir modal de detalhes do erro
    $(document).on('click', '.dashi-show-error-details', function() {
        var details = $(this).closest('tr').data('error-details') || 'Nenhum detalhe disponível';
        $('#dashi-error-details-content').text(details);
        $('#dashi-emissor-error-modal').fadeIn(200);
    });
    
    // Fechar modais
    $('.dashi-modal-close, .dashi-modal-overlay').click(function() {
        $('.dashi-modal').fadeOut(200);
    });
    
    // Fechar modal ao pressionar ESC
    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            $('.dashi-modal').fadeOut(200);
        }
    });
});
```

### 6. Correção de Problemas Conhecidos

#### 6.1. Inicialização de $clean_text
Corrigir a função de tradução para inicializar $clean_text corretamente:

```php
private function translate_text_context($text, $source_lang, $target_lang, $context = 'default') {
    if ($source_lang === $target_lang) {
        return $text;
    }
    
    // Log do texto original para depuração
    $this->logger->debug('Texto original para tradução: ' . substr($text, 0, 500));
    
    // Protege shortcodes com placeholders
    $shortcodes = array();
    $placeholder = '%%SHORTCODE%%';
    $index = 0;
    
    // Função para proteger todos os tipos de shortcodes
    $clean_text = preg_replace_callback(
        '/\[([a-zA-Z0-9_-]+)(.*?)(\](.*?)\[\/\1\]|\])/s',
        function ($matches) use (&$shortcodes, &$index, $placeholder) {
            $shortcodes[$placeholder . $index] = $matches[0];
            return $placeholder . $index++;
        },
        $text
    );
    
    // Limpeza de HTML problemático (se necessário)
    // Remover spans com classes específicas
    $clean_text = preg_replace('/<span\b[^>]*class=[\"\'\[][^\"\']*_fadeIn_m1hgl_8[^\"\']*[\"\']?\b[^>]*>(.*?)<\/span>/is', '$1', $clean_text);
    
    // Remover outros spans genéricos
    $clean_text = preg_replace('/<span\b[^>]*>(.*?)<\/span>/is', '$1', $clean_text);
    
    // ... restante da lógica de tradução
}
```

#### 6.2. Verificação de Tipo de Post Consistente
Aplicar verificação de tipo de post em todos os handlers:

```php
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
```

### 7. Sistema de Relatórios e Monitoramento

Implementar sistema de relatórios detalhados:

```php
class Dashi_Emissor_Reports {
    
    public static function generate_post_report($post_id, $results) {
        $report = array(
            'post_id' => $post_id,
            'timestamp' => current_time('mysql'),
            'results' => $results,
            'summary' => self::generate_summary($results)
        );
        
        // Armazenar relatório em banco de dados
        global $wpdb;
        $table_name = $wpdb->prefix . 'dashi_emissor_reports';
        
        $wpdb->insert($table_name, array(
            'post_id' => $post_id,
            'report_data' => serialize($report),
            'created_at' => current_time('mysql')
        ));
        
        return $report;
    }
    
    private static function generate_summary($results) {
        $total = count($results);
        $success = 0;
        $errors = 0;
        
        foreach ($results as $result) {
            if ($result['status'] === 'ok') {
                $success++;
            } else {
                $errors++;
            }
        }
        
        return array(
            'total' => $total,
            'success' => $success,
            'errors' => $errors,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0
        );
    }
}
```

### 8. Melhorias de Performance

#### 8.1. Cache e Transients

```php
class Dashi_Emissor_Cache {
    
    public static function get_receivers($force_refresh = false) {
        $cache_key = 'dashi_emissor_receivers';
        $receivers = get_transient($cache_key);
        
        if ($force_refresh || $receivers === false) {
            $receivers = get_option('dashi_emissor_receivers', array());
            set_transient($cache_key, $receivers, 15 * MINUTE_IN_SECONDS);
        }
        
        return $receivers;
    }
    
    public static function clear_receivers_cache() {
        delete_transient('dashi_emissor_receivers');
    }
}
```

#### 8.2. Processamento em Lotes

```php
// Implementar processamento em lotes para grandes volumes de dados
public function send_posts_in_batches($posts, $receivers, $batch_size = 5) {
    $batches = array_chunk($posts, $batch_size);
    
    foreach ($batches as $batch) {
        foreach ($batch as $post) {
            $this->send_single_post_data($post, $receivers);
        }
        
        // Pequeno intervalo entre lotes para evitar sobrecarga
        usleep(500000); // 0.5 segundos
    }
}
```

## Implementação de Práticas Profissionais

### 1. Documentação de Código

Implementar blocos de documentação PHPDoc em todas as funções e classes:

```php
/**
 * Prepara os dados do post para envio via API.
 *
 * Esta função coleta todos os dados relevantes do post, incluindo:
 * - Conteúdo bruto do post
 * - Mídias (imagem destacada e anexos)
 * - Dados do autor
 * - Dados de SEO (quando Yoast está presente)
 * - Dados do Elementor
 *
 * @since 2.0.0
 * @param WP_Post $post O objeto do post a ser preparado
 * @return array Array contendo todos os dados do post estruturados
 */
private function prepare_post_data($post) {
    // Implementação conforme necessário
}
```

### 2. Tratamento de Erros e Exceções

Implementar tratamento robusto de erros:

```php
class Dashi_Emissor_Exception extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

// Em funções que podem falhar
public function send_post_data($post) {
    try {
        $post_data = $this->prepare_post_data($post);
        
        if (empty($post_data)) {
            throw new Dashi_Emissor_Exception('Falha ao preparar dados do post');
        }
        
        $receivers = $this->get_selected_receivers($post->ID);
        
        if (empty($receivers)) {
            return; // Nenhum receptor selecionado
        }
        
        // Enviar para a fila assíncrona
        $this->cron->add_to_queue($post_data, $receivers, $post->ID);
        
    } catch (Dashi_Emissor_Exception $e) {
        $this->logger->error('Erro ao enviar post: ' . $e->getMessage(), array(
            'post_id' => $post->ID,
            'error' => $e->getTraceAsString()
        ));
    }
}
```

### 3. Testes e Debugging

Implementar sistema de debug com níveis:

```php
class Dashi_Emissor_Debug {
    
    const LEVEL_OFF = 0;
    const LEVEL_ERROR = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_INFO = 3;
    const LEVEL_DEBUG = 4;
    
    private static $level = null;
    
    public static function get_level() {
        if (self::$level === null) {
            $level = get_option('dashi_emissor_debug_level', self::LEVEL_OFF);
            self::$level = apply_filters('dashi_emissor_debug_level', $level);
        }
        return self::$level;
    }
    
    public static function log($level, $message, $context = array()) {
        if ($level <= self::get_level()) {
            do_action('dashi_emissor_debug_log', $level, $message, $context);
        }
    }
    
    public static function error($message, $context = array()) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    public static function debug($message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
}
```

## Implementação de Funcionalidades de Segurança

### 1. Criptografia de Tokens

Armazenar tokens de forma criptografada:

```php
class Dashi_Emissor_Security {
    
    public static function encrypt_token($token) {
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
    
    public static function decrypt_token($encrypted_token) {
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
```

### 2. Validação de URLs

Implementar validação robusta de URLs de receptores:

```php
class Dashi_Emissor_Url_Validator {
    
    public static function validate_receiver_url($url) {
        // Validação básica
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Verificar protocolo
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'], array('http', 'https'))) {
            return false;
        }
        
        // Verificar se é um endereço local (não permitido)
        $host = $parsed['host'];
        if (self::is_local_address($host)) {
            return false;
        }
        
        // Verificar conectividade (opcional)
        $test_response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true
        ));
        
        if (is_wp_error($test_response)) {
            return false;
        }
        
        return true;
    }
    
    private static function is_local_address($host) {
        // Verificar se é um endereço IPv4 local
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        // Verificar endereços comuns de localhost
        $local_hosts = array('localhost', '127.0.0.1', '::1');
        return in_array(strtolower($host), $local_hosts);
    }
}
```

## Considerações Finais

Este upgrade propõe uma reestruturação completa do plugin Emissor com foco em:

1. **Profissionalismo:** Código bem documentado, estrutura clara e padrões de desenvolvimento seguidos
2. **Segurança:** Validação rigorosa de entrada, criptografia de tokens sensíveis, proteção contra CSRF
3. **Performance:** Processamento assíncrono, caching eficiente, otimização de requisições
4. **Depurabilidade:** Sistema de logs detalhado, níveis de debug, relatórios de operação
5. **Interface:** Substituição de alerts por modais elegantes e funcionais
6. **Manutenibilidade:** Código modular, classes bem definidas, fácil de estender e modificar

A abordagem proposta mantém as funcionalidades essenciais do plugin original enquanto resolve todos os problemas estruturais identificados, resultando em um plugin mais confiável, seguro e eficiente.