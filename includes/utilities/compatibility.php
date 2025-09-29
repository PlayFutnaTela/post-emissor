<?php
if (!defined('ABSPATH')) {
    exit;
}

// Arquivo para manter compatibilidade com possíveis integrações antigas
// e funções auxiliares

/**
 * Função auxiliar para obter todos os receptores
 * Mantida para compatibilidade com versões anteriores
 */
function dashi_emissor_get_receivers() {
    $receivers = get_option('dashi_emissor_receivers', array());
    
    // Descriptografar tokens antes de retornar
    foreach ($receivers as &$receiver) {
        if (isset($receiver['auth_token']) && !empty($receiver['auth_token'])) {
            $receiver['auth_token'] = dashi_emissor_decrypt_token($receiver['auth_token']);
        }
    }
    
    return $receivers;
}

/**
 * Função para criptografar tokens
 */
function dashi_emissor_encrypt_token($token) {
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
 * Função para descriptografar tokens
 */
function dashi_emissor_decrypt_token($encrypted_token) {
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
 * Função auxiliar para enviar dados do post
 * Mantida para compatibilidade com possíveis chamadas diretas
 */
function dashi_emissor_send_post_data($post) {
    $emissor = Dashi_Emissor_Core::get_instance();
    $post_handler = new Dashi_Emissor_Post_Handler($emissor->logger);
    $post_handler->send_post_data($post);
}