<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principal do plugin Dashi Emissor
 *
 * Implementa o padrão Singleton para garantir uma única instância do plugin
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Core {
    
    private static $instance = null;
    public $logger;
    public $api_client;
    
    /**
     * Construtor privado para implementar o padrão Singleton
     */
    private function __construct() {
        $this->init_constants();
        $this->init_hooks();
    }
    
    /**
     * Implementa o padrão Singleton
     *
     * @return Dashi_Emissor_Core
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Impede que a classe seja clonada
     */
    private function __clone() {}
    
    /**
     * Impede que a classe seja deserializada
     */
    private function __wakeup() {}
    
    /**
     * Inicializa constantes do plugin
     */
    private function init_constants() {
        // Chave de criptografia para tokens de segurança
        if (!defined('DASHI_EMISSOR_ENCRYPTION_KEY')) {
            $encryption_key = get_option('dashi_emissor_encryption_key');
            if (!$encryption_key) {
                $encryption_key = wp_generate_password(32, true, true);
                update_option('dashi_emissor_encryption_key', $encryption_key);
            }
            define('DASHI_EMISSOR_ENCRYPTION_KEY', $encryption_key);
        }
    }
    
    /**
     * Inicializa hooks do WordPress
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'init_checker'));
        add_action('wp_loaded', array($this, 'create_database_tables'));
    }
    
    /**
     * Inicializa componentes principais do plugin
     */
    public function init() {
        $this->init_logger();
        $this->init_handlers();
        $this->init_cron();
        $this->init_metabox();
        $this->init_admin();
    }
    
    /**
     * Inicializa o sistema de logs
     */
    private function init_logger() {
        require_once DASHI_EMISSOR_DIR . 'includes/utilities/class-logger.php';
        $this->logger = new Dashi_Emissor_Logger();
    }
    
    /**
     * Inicializa os manipuladores de eventos
     */
    private function init_handlers() {
        require_once DASHI_EMISSOR_DIR . 'includes/handlers/class-post-handler.php';
        $post_handler = new Dashi_Emissor_Post_Handler($this->logger);
        $post_handler->init();
        
        // Inicializar manipulador de tradução
        require_once DASHI_EMISSOR_DIR . 'includes/handlers/class-translation-handler.php';
    }
    
    /**
     * Inicializa o sistema de filas assíncronas
     */
    private function init_cron() {
        require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-cron.php';
        $cron = new Dashi_Emissor_Cron($this->logger);
    }
    
    /**
     * Inicializa metabox para seleção de receptores
     */
    private function init_metabox() {
        require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-metabox.php';
    }
    
    /**
     * Inicializa componentes administrativos
     */
    private function init_admin() {
        if (is_admin()) {
            require_once DASHI_EMISSOR_DIR . 'includes/class-emissor-admin.php';
            $admin = new Dashi_Emissor_Admin();
            $admin->init();
        }
    }
    
    /**
     * Carrega o domínio de tradução do plugin
     */
    public function load_textdomain() {
        load_plugin_textdomain('dashi-emissor', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Inicializa o verificador do sistema
     */
    public function init_checker() {
        if (is_admin()) {
            require_once DASHI_EMISSOR_DIR . 'includes/utilities/checker.php';
        }
    }
    
    /**
     * Cria tabelas do banco de dados necessárias
     */
    public function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela para logs
        $logs_table = $wpdb->prefix . 'dashi_emissor_logs';
        $sql_logs = "CREATE TABLE $logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) UNSIGNED,
            PRIMARY KEY (id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        // Tabela para fila de envios
        $queue_table = $wpdb->prefix . 'dashi_emissor_queue';
        $sql_queue = "CREATE TABLE $queue_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            post_data longtext NOT NULL,
            receivers longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime,
            PRIMARY KEY (id),
            KEY status (status),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        // Tabela para relatórios
        $reports_table = $wpdb->prefix . 'dashi_emissor_reports';
        $sql_reports = "CREATE TABLE $reports_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            report_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        // Tabela para receptores (separada para gerenciamento individual)
        $receivers_table = $wpdb->prefix . 'dashi_emissor_receivers';
        $sql_receivers = "CREATE TABLE $receivers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            auth_token text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_queue);
        dbDelta($sql_reports);
        dbDelta($sql_receivers);
    }
}