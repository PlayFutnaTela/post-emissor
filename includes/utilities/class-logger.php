<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gerenciamento de logs do plugin
 *
 * Implementa um sistema profissional de logs com níveis e armazenamento em banco de dados
 *
 * @since 2.0.0
 */
class Post_Emissor_Logger {
    
    /**
     * Registra uma mensagem de log
     *
     * @param string $level Nível do log (info, warning, error, debug)
     * @param string $message Mensagem a ser registrada
     * @param array $context Informações adicionais para contexto
     */
    public function log($level, $message, $context = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'post_emissor_logs';
        
        $data = array(
            'level' => sanitize_text_field($level),
            'message' => sanitize_textarea_field($message),
            'context' => serialize($context),
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        // Se falhar, registra via error_log como fallback
        if ($result === false) {
            error_log("Post Emissor Log - Nível: {$level}, Mensagem: {$message}");
        }
    }
    
    /**
     * Registra uma mensagem de informação
     *
     * @param string $message Mensagem a ser registrada
     * @param array $context Informações adicionais para contexto
     */
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Registra uma mensagem de aviso
     *
     * @param string $message Mensagem a ser registrada
     * @param array $context Informações adicionais para contexto
     */
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Registra uma mensagem de erro
     *
     * @param string $message Mensagem a ser registrada
     * @param array $context Informações adicionais para contexto
     */
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Registra uma mensagem de debug
     *
     * @param string $message Mensagem a ser registrada
     * @param array $context Informações adicionais para contexto
     */
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Recupera logs do banco de dados
     *
     * @param string $level Nível do log para filtrar (opcional)
     * @param int $limit Número de registros para retornar (padrão: 50)
     * @return array Array de logs
     */
    public function get_logs($level = '', $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_logs';
        
        $query = "SELECT * FROM $table_name";
        $where = array();
        $params = array();
        
        if (!empty($level)) {
            $where[] = 'level = %s';
            $params[] = $level;
        }
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $query .= ' ORDER BY timestamp DESC';
        
        if ($limit > 0) {
            $query .= ' LIMIT %d';
            $params[] = $limit;
        }
        
        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        } else {
            $logs = $wpdb->get_results($query, ARRAY_A);
        }
        
        // Desserializar o contexto
        foreach ($logs as &$log) {
            $log['context'] = maybe_unserialize($log['context']);
        }
        
        return $logs;
    }
    
    /**
     * Limpa logs antigos
     *
     * @param int $days Número de dias atrás para manter logs (padrão: 30 dias)
     */
    public function cleanup_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->delete(
            $table_name,
            array('timestamp' => '<' . $cutoff_date),
            array('%s')
        );
        
        return $result;
    }
}