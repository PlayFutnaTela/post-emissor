<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para validação de URLs e outros dados
 *
 * Fornece métodos para validação robusta de URLs e outros dados
 * usados no plugin
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Validator {
    
    /**
     * Valida uma URL de receptor
     *
     * @param string $url URL para validar
     * @return bool True se a URL for válida, false caso contrário
     */
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
        if (self::is_local_address($parsed['host'])) {
            return false;
        }
        
        // Verificar se é um endereço IP privado ou loopback
        if (filter_var($parsed['host'], FILTER_VALIDATE_IP)) {
            if (!filter_var($parsed['host'], FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verifica se o host é um endereço local
     *
     * @param string $host Host para verificar
     * @return bool True se for local, false caso contrário
     */
    private static function is_local_address($host) {
        // Verificar se é um endereço IPv4 local
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        // Verificar endereços comuns de localhost
        $local_hosts = array('localhost', '127.0.0.1', '::1', '0.0.0.0');
        return in_array(strtolower($host), $local_hosts);
    }
    
    /**
     * Valida um token de autenticação
     *
     * @param string $token Token para validar
     * @return bool True se o token for válido, false caso contrário
     */
    public static function validate_auth_token($token) {
        if (empty($token)) {
            return false;
        }
        
        // Tokens devem ter pelo menos 10 caracteres
        if (strlen($token) < 10) {
            return false;
        }
        
        // Para tokens JWT, verificar formato básico
        if (strpos($token, '.') !== false && substr_count($token, '.') === 2) {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            // Verificar se cada parte é uma string base64 válida
            foreach ($parts as $part) {
                if (!self::is_base64($part)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Verifica se uma string é válida em base64
     *
     * @param string $str String para verificar
     * @return bool True se for base64 válido, false caso contrário
     */
    private static function is_base64($str) {
        return base64_encode(base64_decode($str)) === $str;
    }
    
    /**
     * Valida dados de post para envio
     *
     * @param array $post_data Dados do post para validar
     * @return bool True se os dados forem válidos, false caso contrário
     */
    public static function validate_post_data($post_data) {
        // Verificar campos obrigatórios
        $required_fields = array('ID', 'title', 'content', 'slug');
        foreach ($required_fields as $field) {
            if (!isset($post_data[$field]) || empty($post_data[$field])) {
                return false;
            }
        }
        
        // Validar ID do post
        if (!is_numeric($post_data['ID']) || $post_data['ID'] <= 0) {
            return false;
        }
        
        // Validar título
        if (!is_string($post_data['title'])) {
            return false;
        }
        
        // Validar conteúdo
        if (!is_string($post_data['content'])) {
            return false;
        }
        
        // Validar slug
        if (!is_string($post_data['slug'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Valida configurações do plugin
     *
     * @param array $settings Configurações para validar
     * @return array Resultado da validação
     */
    public static function validate_settings($settings) {
        $errors = array();
        
        // Validar idioma de origem
        $allowed_languages = array('pt_BR', 'pt_PT', 'en_US', 'en_GB', 'es_ES', 'fr_FR', 'de_DE');
        if (isset($settings['dashi_emissor_origin_language']) && 
            !in_array($settings['dashi_emissor_origin_language'], $allowed_languages)) {
            $errors[] = 'Idioma de origem inválido';
        }
        
        // Validar receptores
        if (isset($settings['dashi_emissor_receivers']) && is_array($settings['dashi_emissor_receivers'])) {
            foreach ($settings['dashi_emissor_receivers'] as $index => $receiver) {
                if (isset($receiver['url']) && !empty($receiver['url'])) {
                    if (!self::validate_receiver_url($receiver['url'])) {
                        $errors[] = "URL inválida para receptor na posição {$index}: {$receiver['url']}";
                    }
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Sanitiza dados do post
     *
     * @param array $post_data Dados do post para sanitizar
     * @return array Dados sanitizados
     */
    public static function sanitize_post_data($post_data) {
        $sanitized = array();
        
        // Sanitizar campos específicos
        if (isset($post_data['ID'])) {
            $sanitized['ID'] = absint($post_data['ID']);
        }
        
        if (isset($post_data['title'])) {
            $sanitized['title'] = sanitize_text_field($post_data['title']);
        }
        
        if (isset($post_data['content'])) {
            // Permitir conteúdo rico mas remover código perigoso
            $sanitized['content'] = self::sanitize_content($post_data['content']);
        }
        
        if (isset($post_data['excerpt'])) {
            $sanitized['excerpt'] = sanitize_text_field($post_data['excerpt']);
        }
        
        if (isset($post_data['slug'])) {
            $sanitized['slug'] = sanitize_title($post_data['slug']);
        }
        
        if (isset($post_data['status'])) {
            $allowed_statuses = array('publish', 'draft', 'pending', 'private', 'trash');
            $sanitized['status'] = in_array($post_data['status'], $allowed_statuses) ? $post_data['status'] : 'draft';
        }
        
        if (isset($post_data['categories'])) {
            $sanitized['categories'] = array_map('absint', (array)$post_data['categories']);
        }
        
        if (isset($post_data['tags'])) {
            $tags = array();
            foreach ((array)$post_data['tags'] as $tag) {
                if (is_array($tag) && isset($tag['name'])) {
                    $tags[] = array(
                        'term_id' => isset($tag['term_id']) ? absint($tag['term_id']) : 0,
                        'name' => sanitize_text_field($tag['name']),
                        'slug' => sanitize_title($tag['slug'])
                    );
                } else {
                    $tags[] = sanitize_text_field($tag);
                }
            }
            $sanitized['tags'] = $tags;
        }
        
        // Copiar campos restantes, sanitizando conforme apropriado
        $copy_fields = array('origin_language', 'author', 'focus_keyword', 'yoast_metadesc', 'elementor');
        foreach ($copy_fields as $field) {
            if (isset($post_data[$field])) {
                $sanitized[$field] = sanitize_text_field($post_data[$field]);
            }
        }
        
        if (isset($post_data['author_data'])) {
            $sanitized['author_data'] = self::sanitize_author_data($post_data['author_data']);
        }
        
        if (isset($post_data['media'])) {
            $sanitized['media'] = self::sanitize_media_data($post_data['media']);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitiza conteúdo do post
     *
     * @param string $content Conteúdo para sanitizar
     * @return string Conteúdo sanitizado
     */
    private static function sanitize_content($content) {
        // Permitir tags HTML comuns em posts do WordPress
        $allowed_tags = wp_kses_allowed_html('post');
        return wp_kses($content, $allowed_tags);
    }
    
    /**
     * Sanitiza dados do autor
     *
     * @param array $author_data Dados do autor para sanitizar
     * @return array Dados do autor sanitizados
     */
    private static function sanitize_author_data($author_data) {
        $sanitized = array();
        
        $text_fields = array('user_login', 'first_name', 'last_name', 'nickname', 'display_name', 'user_email', 'user_url', 'facebook', 'instagram', 'linkedin', 'username_x', 'youtube');
        foreach ($text_fields as $field) {
            if (isset($author_data[$field])) {
                $sanitized[$field] = sanitize_text_field($author_data[$field]);
            }
        }
        
        if (isset($author_data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($author_data['description']);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitiza dados de mídia
     *
     * @param array $media_data Dados de mídia para sanitizar
     * @return array Dados de mídia sanitizados
     */
    private static function sanitize_media_data($media_data) {
        $sanitized = array();
        
        if (isset($media_data['featured_image'])) {
            $sanitized['featured_image'] = self::sanitize_attachment_data($media_data['featured_image']);
        }
        
        if (isset($media_data['attachments']) && is_array($media_data['attachments'])) {
            $sanitized['attachments'] = array();
            foreach ($media_data['attachments'] as $attachment) {
                $sanitized['attachments'][] = self::sanitize_attachment_data($attachment);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitiza dados de anexo
     *
     * @param array $attachment_data Dados do anexo para sanitizar
     * @return array Dados do anexo sanitizados
     */
    private static function sanitize_attachment_data($attachment_data) {
        $sanitized = array();
        
        $int_fields = array('ID');
        foreach ($int_fields as $field) {
            if (isset($attachment_data[$field])) {
                $sanitized[$field] = absint($attachment_data[$field]);
            }
        }
        
        $text_fields = array('url', 'alt', 'title', 'caption', 'description');
        foreach ($text_fields as $field) {
            if (isset($attachment_data[$field])) {
                if ($field === 'url') {
                    $sanitized[$field] = esc_url_raw($attachment_data[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($attachment_data[$field]);
                }
            }
        }
        
        return $sanitized;
    }
}