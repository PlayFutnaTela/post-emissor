<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cliente API para envio de dados para sites receptores
 *
 * Implementa funcionalidades avançadas de envio com tratamento de erros,
 * tentativas com exponencial backoff e logging detalhado
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Api_Client {
    
    private $timeout = 30;
    private $max_retries = 3;
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
     * Envia dados do post para múltiplos receptores
     *
     * @param array $post_data Dados do post a serem enviados
     * @param array $receivers Lista de receptores para enviar
     * @return array Resultados de envio para cada receptor
     */
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
    
    /**
     * Envia dados do post para um único receptor
     *
     * @param array $post_data Dados do post a serem enviados
     * @param array $receiver Dados do receptor
     * @return array Resultado do envio
     */
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
    
    /**
     * Executa requisição com tentativas em caso de falha
     *
     * @param string $endpoint URL do endpoint
     * @param array $args Argumentos para wp_remote_post
     * @param array $receiver Dados do receptor
     * @return array|WP_Error Resposta da requisição
     */
    private function execute_request_with_retry($endpoint, $args, $receiver) {
        $attempt = 0;
        
        while ($attempt < $this->max_retries) {
            $attempt++;
            
            $response = wp_remote_post($endpoint, $args);
            
            // Verificar se não é um erro e se o código de resposta é positivo
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    break; // Requisição bem-sucedida
                }
            }
            
            if ($attempt < $this->max_retries) {
                $sleep_time = pow(2, $attempt) * 1000; // Exponential backoff em milissegundos
                usleep($sleep_time * 1000); // Converter para microssegundos
            }
        }
        
        return $response;
    }
    
    /**
     * Atualiza status do post em receptores
     *
     * @param array $update_data Dados da atualização
     * @param array $receivers Lista de receptores
     * @return array Resultados da atualização
     */
    public function update_post_status($update_data, $receivers) {
        $results = array();
        
        foreach ($receivers as $receiver) {
            $result = $this->send_update_status_request($update_data, $receiver);
            $results[] = $result;
            
            $this->logger->info(sprintf(
                'Atualização de status para receptor: %s - Status: %s',
                $receiver['url'],
                $result['status']
            ), array('receiver' => $receiver, 'result' => $result));
        }
        
        return $results;
    }
    
    /**
     * Envia requisição de atualização de status para um receptor
     *
     * @param array $update_data Dados da atualização
     * @param array $receiver Dados do receptor
     * @return array Resultado da requisição
     */
    private function send_update_status_request($update_data, $receiver) {
        $endpoint = trailingslashit($receiver['url']) . 'wp-json/post-receptor/v1/update-status';
        
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
            'body' => wp_json_encode($update_data),
            'timeout' => $this->timeout,
            'sslverify' => true,
            'data_format' => 'body'
        );
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            $this->logger->error(sprintf(
                'Erro ao atualizar status para receptor: %s - Erro: %s',
                $receiver['url'],
                $error_message
            ), array(
                'receiver' => $receiver,
                'error' => $error_message,
                'post_id' => $update_data['ID']
            ));
            
            return array(
                'url' => $receiver['url'],
                'status' => 'fail',
                'message' => $error_message
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return array(
            'url' => $receiver['url'],
            'status' => ($response_code >= 200 && $response_code < 300) ? 'ok' : 'fail',
            'message' => 'Código de resposta: ' . $response_code
        );
    }
    
    /**
     * Envia comando de deleção para receptores
     *
     * @param array $delete_data Dados da deleção
     * @param array $receivers Lista de receptores
     * @return array Resultados da deleção
     */
    public function delete_post($delete_data, $receivers) {
        $results = array();
        
        foreach ($receivers as $receiver) {
            $result = $this->send_delete_request($delete_data, $receiver);
            $results[] = $result;
            
            $this->logger->info(sprintf(
                'Comando de deleção para receptor: %s - Status: %s',
                $receiver['url'],
                $result['status']
            ), array('receiver' => $receiver, 'result' => $result));
        }
        
        return $results;
    }
    
    /**
     * Envia requisição de deleção para um receptor
     *
     * @param array $delete_data Dados da deleção
     * @param array $receiver Dados do receptor
     * @return array Resultado da requisição
     */
    private function send_delete_request($delete_data, $receiver) {
        $endpoint = trailingslashit($receiver['url']) . 'wp-json/post-receptor/v1/delete';
        
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
            'body' => wp_json_encode($delete_data),
            'timeout' => $this->timeout,
            'sslverify' => true,
            'data_format' => 'body'
        );
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            $this->logger->error(sprintf(
                'Erro ao enviar comando de deleção para receptor: %s - Erro: %s',
                $receiver['url'],
                $error_message
            ), array(
                'receiver' => $receiver,
                'error' => $error_message,
                'post_id' => $delete_data['ID']
            ));
            
            return array(
                'url' => $receiver['url'],
                'status' => 'fail',
                'message' => $error_message
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        return array(
            'url' => $receiver['url'],
            'status' => ($response_code >= 200 && $response_code < 300) ? 'ok' : 'fail',
            'message' => 'Código de resposta: ' . $response_code
        );
    }
    
    /**
     * Testa a conexão com um receptor específico
     *
     * @param array $receiver Dados do receptor
     * @return array Resultado do teste de conexão
     */
    public function test_connection($receiver) {
        $endpoint = trailingslashit($receiver['url']) . 'wp-json/post-receptor/v1/check-token';
        
        $headers = array(
            'User-Agent' => 'Dashi-Emissor/' . DASHI_EMISSOR_VERSION
        );
        
        if (!empty($receiver['auth_token'])) {
            $headers['Authorization'] = 'Bearer ' . $receiver['auth_token'];
        }
        
        $args = array(
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => 15,
            'sslverify' => true
        );
        
        $response = wp_remote_get($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            $this->logger->warning(sprintf(
                'Falha no teste de conexão para: %s - Erro: %s',
                $receiver['url'],
                $error_message
            ), array('receiver' => $receiver));
            
            return array(
                'url' => $receiver['url'],
                'status' => 'fail',
                'message' => $error_message
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        $is_connected = ($response_code >= 200 && $response_code < 300) && 
                         isset($response_body['success']) && 
                         $response_body['success'] === true;
        
        return array(
            'url' => $receiver['url'],
            'status' => $is_connected ? 'ok' : 'fail',
            'message' => $is_connected ? 'Conectado' : 'Token inválido ou falha na conexão'
        );
    }
}