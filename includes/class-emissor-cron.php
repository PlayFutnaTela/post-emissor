<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gerenciamento de filas assíncronas
 *
 * Implementa sistema de filas com WP-Cron para processamento assíncrono
 * de envios e outras operações
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Cron {
    
    private $logger;
    
    /**
     * Construtor da classe
     *
     * @param Dashi_Emissor_Logger $logger Instância do logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
        add_action('init', array($this, 'schedule_events'));
        add_action('dashi_emissor_process_queue', array($this, 'process_queue'));
        
        // Adicionar intervalo personalizado para WP-Cron
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
    }
    
    /**
     * Adiciona intervalos personalizados para o WP-Cron
     *
     * @param array $schedules Intervalos existentes
     * @return array Intervalos atualizados
     */
    public function add_custom_intervals($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('A cada minuto', 'dashi-emissor')
        );
        $schedules['every_5_minutes'] = array(
            'interval' => 300,
            'display' => __('A cada 5 minutos', 'dashi-emissor')
        );
        return $schedules;
    }
    
    /**
     * Agenda eventos do plugin
     */
    public function schedule_events() {
        if (!wp_next_scheduled('dashi_emissor_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'dashi_emissor_process_queue');
        }
    }
    
    /**
     * Adiciona um item à fila para processamento assíncrono
     *
     * @param array $post_data Dados do post
     * @param array $receivers Lista de receptores
     * @param int $post_id ID do post
     * @param string $action Tipo de ação (send, update_status, delete)
     * @return bool True se adicionado com sucesso, false caso contrário
     */
    public function add_to_queue($post_data, $receivers, $post_id, $action = 'send') {
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
        
        if ($result !== false) {
            $this->logger->info(sprintf(
                'Item adicionado à fila para processamento: %d (ação: %s)',
                $post_id,
                $action
            ), array(
                'post_id' => $post_id,
                'action' => $action
            ));
            
            return true;
        } else {
            $this->logger->error(sprintf(
                'Erro ao adicionar item à fila: %d (ação: %s)',
                $post_id,
                $action
            ), array(
                'post_id' => $post_id,
                'action' => $action
            ));
            
            return false;
        }
    }
    
    /**
     * Processa itens da fila
     */
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
        
        // Carregar o cliente API
        require_once DASHI_EMISSOR_DIR . 'includes/api/class-emissor-api-client.php';
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
            
            $this->logger->info(sprintf(
                'Item da fila processado com sucesso: %d',
                $item['post_id']
            ), array(
                'post_id' => $item['post_id'],
                'results' => $results
            ));
        }
    }
    
    /**
     * Armazena resultados de processamento
     *
     * @param int $post_id ID do post
     * @param array $results Resultados do processamento
     */
    private function store_results($post_id, $results) {
        // Armazenar resultados em um transient com expiração
        set_transient(
            'dashi_emissor_report_' . $post_id,
            $results,
            12 * HOUR_IN_SECONDS
        );
        
        // Também armazenar no banco de dados para histórico
        require_once DASHI_EMISSOR_DIR . 'includes/utilities/class-reports.php';
        Dashi_Emissor_Reports::generate_post_report($post_id, $results);
    }
    
    /**
     * Remove itens processados antigos da fila
     *
     * @param int $days Dias para manter itens na fila (padrão: 7 dias)
     * @return int Número de itens removidos
     */
    public function cleanup_queue($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_queue';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->delete(
            $table_name,
            array('processed_at' => '<' . $cutoff_date, 'status' => 'completed'),
            array('%s', '%s')
        );
        
        $this->logger->info(sprintf(
            'Limpeza da fila realizada, %d itens removidos',
            $result
        ), array('days' => $days));
        
        return $result;
    }
    
    /**
     * Cancela um item específico da fila
     *
     * @param int $item_id ID do item na fila
     * @return bool True se cancelado com sucesso, false caso contrário
     */
    public function cancel_item($item_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_queue';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => 'cancelled'),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->logger->info(sprintf('Item da fila cancelado: %d', $item_id));
            return true;
        } else {
            $this->logger->error(sprintf('Falha ao cancelar item da fila: %d', $item_id));
            return false;
        }
    }
    
    /**
     * Retorna estatísticas da fila
     *
     * @return array Estatísticas da fila
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_queue';
        
        $stats = array(
            'pending' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total' => 0
        );
        
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
            ARRAY_A
        );
        
        foreach ($results as $row) {
            $stats[$row['status']] = intval($row['count']);
            $stats['total'] += intval($row['count']);
        }
        
        return $stats;
    }
}