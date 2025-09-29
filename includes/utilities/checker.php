<?php
/**
 * Arquivo de verificação do sistema Dashi Emissor
 * 
 * Este arquivo verifica se todas as componentes do sistema
 * foram implementadas corretamente
 */

// Verificar se está sendo acessado corretamente
if (!defined('ABSPATH')) {
    exit;
}

class Dashi_Emissor_Checker {
    
    public static function check_installation() {
        $results = array(
            'status' => 'success',
            'messages' => array(),
            'components' => array()
        );
        
        // Verificar arquivos principais
        $required_files = array(
            'dashi-emissor.php',
            'includes/class-emissor-core.php',
            'includes/utilities/class-logger.php',
            'includes/api/class-emissor-api-client.php',
            'includes/class-emissor-cron.php',
            'includes/models/class-receiver.php',
            'includes/models/class-post-data.php',
            'includes/handlers/class-post-handler.php',
            'includes/class-emissor-admin.php',
            'includes/class-emissor-metabox.php',
            'includes/handlers/class-translation-handler.php',
            'includes/utilities/class-validator.php',
            'includes/utilities/class-sanitizer.php',
            'includes/utilities/class-reports.php'
        );
        
        foreach ($required_files as $file) {
            $path = DASHI_EMISSOR_DIR . $file;
            if (file_exists($path)) {
                $results['components'][$file] = 'ok';
            } else {
                $results['components'][$file] = 'missing';
                $results['status'] = 'error';
                $results['messages'][] = "Arquivo ausente: {$file}";
            }
        }
        
        // Verificar diretórios
        $required_dirs = array(
            'assets/css',
            'assets/js',
            'templates/admin',
            'templates/admin/modals',
            'includes/abstracts',
            'includes/api',
            'includes/handlers',
            'includes/models',
            'includes/utilities'
        );
        
        foreach ($required_dirs as $dir) {
            $path = DASHI_EMISSOR_DIR . $dir;
            if (is_dir($path)) {
                $results['components'][$dir] = 'ok';
            } else {
                $results['components'][$dir] = 'missing';
                $results['status'] = 'error';
                $results['messages'][] = "Diretório ausente: {$dir}";
            }
        }
        
        // Verificar tabelas do banco de dados
        global $wpdb;
        $tables = array(
            'dashi_emissor_logs',
            'dashi_emissor_queue',
            'dashi_emissor_reports',
            'dashi_emissor_receivers'
        );
        
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
            
            if ($table_exists) {
                $results['components'][$table] = 'ok';
            } else {
                $results['components'][$table] = 'missing';
                $results['status'] = 'error';
                $results['messages'][] = "Tabela ausente: {$table}";
            }
        }
        
        // Verificar se as funcionalidades básicas estão disponíveis
        if (class_exists('Dashi_Emissor_Core')) {
            $results['components']['core_class'] = 'ok';
        } else {
            $results['components']['core_class'] = 'missing';
            $results['status'] = 'error';
            $results['messages'][] = "Classe principal não encontrada";
        }
        
        if (class_exists('Dashi_Emissor_Logger')) {
            $results['components']['logger_class'] = 'ok';
        } else {
            $results['components']['logger_class'] = 'missing';
            $results['status'] = 'error';
            $results['messages'][] = "Classe de logger não encontrada";
        }
        
        if (class_exists('Dashi_Emissor_Api_Client')) {
            $results['components']['api_client_class'] = 'ok';
        } else {
            $results['components']['api_client_class'] = 'missing';
            $results['status'] = 'error';
            $results['messages'][] = "Classe de cliente API não encontrada";
        }
        
        return $results;
    }
    
    public static function print_report($results) {
        echo "<div class='wrap'>";
        echo "<h1>Verificação do Sistema Dashi Emissor</h1>";
        
        if ($results['status'] === 'success') {
            echo "<div class='notice notice-success'><p><strong>Sistema completo!</strong> Todas as componentes estão devidamente instaladas e configuradas.</p></div>";
        } else {
            echo "<div class='notice notice-error'><p><strong>Problemas detectados!</strong> Algumas componentes estão faltando ou com problemas.</p></div>";
        }
        
        echo "<h2>Resumo</h2>";
        echo "<ul>";
        foreach ($results['components'] as $component => $status) {
            $status_class = $status === 'ok' ? 'success' : 'error';
            $status_text = $status === 'ok' ? 'OK' : 'AUSENTE';
            echo "<li class='{$status_class}'>{$component}: <strong>{$status_text}</strong></li>";
        }
        echo "</ul>";
        
        if (!empty($results['messages'])) {
            echo "<h2>Mensagens</h2>";
            echo "<ul>";
            foreach ($results['messages'] as $message) {
                echo "<li>{$message}</li>";
            }
            echo "</ul>";
        }
        
        echo "</div>";
    }
}

// Adicionar página de verificação no admin
add_action('admin_menu', function() {
    add_submenu_page(
        'dashi-emissor',
        'Verificação do Sistema',
        'Verificação',
        'manage_options',
        'dashi-emissor-check',
        function() {
            $checker = new Dashi_Emissor_Checker();
            $results = $checker->check_installation();
            $checker->print_report($results);
        }
    );
});