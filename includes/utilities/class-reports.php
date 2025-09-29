<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para geração de relatórios
 *
 * Gera relatórios de envio e outras métricas do sistema
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Reports {
    
    /**
     * Gera relatório de envio para um post específico
     *
     * @param int $post_id ID do post
     * @param array $results Resultados do envio
     * @return array Relatório gerado
     */
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
    
    /**
     * Gera sumário de resultados
     *
     * @param array $results Resultados para gerar sumário
     * @return array Sumário dos resultados
     */
    private static function generate_summary($results) {
        $total = count($results);
        $success = 0;
        $errors = 0;
        
        foreach ($results as $result) {
            if (isset($result['status']) && $result['status'] === 'ok') {
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
    
    /**
     * Recupera relatório para um post específico
     *
     * @param int $post_id ID do post
     * @return array|false Relatório ou false se não encontrado
     */
    public static function get_post_report($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_reports';
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC LIMIT 1",
                $post_id
            ),
            ARRAY_A
        );
        
        if ($result) {
            $report = maybe_unserialize($result['report_data']);
            return $report;
        }
        
        return false;
    }
    
    /**
     * Recupera relatórios recentes
     *
     * @param int $limit Número de relatórios para retornar
     * @return array Lista de relatórios
     */
    public static function get_recent_reports($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_reports';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        $reports = array();
        foreach ($results as $result) {
            $report = maybe_unserialize($result['report_data']);
            $reports[] = $report;
        }
        
        return $reports;
    }
    
    /**
     * Calcula estatísticas gerais
     *
     * @return array Estatísticas gerais
     */
    public static function get_general_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dashi_emissor_reports';
        
        $stats = array(
            'total_reports' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'success_rate' => 0
        );
        
        // Total de relatórios
        $total_reports = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $stats['total_reports'] = (int)$total_reports;
        
        if ($stats['total_reports'] > 0) {
            // Obter todos os relatórios para calcular estatísticas
            $results = $wpdb->get_results("SELECT report_data FROM $table_name", ARRAY_A);
            
            $total_attempts = 0;
            $total_success = 0;
            
            foreach ($results as $result) {
                $report = maybe_unserialize($result['report_data']);
                if (isset($report['summary'])) {
                    $total_attempts += $report['summary']['total'];
                    $total_success += $report['summary']['success'];
                }
            }
            
            $stats['success_count'] = $total_success;
            $stats['error_count'] = $total_attempts - $total_success;
            
            if ($total_attempts > 0) {
                $stats['success_rate'] = round(($total_success / $total_attempts) * 100, 2);
            }
        }
        
        return $stats;
    }
}