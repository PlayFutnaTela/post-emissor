<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para sanitização de dados
 *
 * Fornece métodos para sanitizar diferentes tipos de dados
 * usados no plugin
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Sanitizer {
    
    /**
     * Sanitiza uma URL
     *
     * @param string $url URL para sanitizar
     * @return string URL sanitizada
     */
    public static function sanitize_url($url) {
        return esc_url_raw($url);
    }
    
    /**
     * Sanitiza texto
     *
     * @param string $text Texto para sanitizar
     * @return string Texto sanitizado
     */
    public static function sanitize_text($text) {
        return sanitize_text_field($text);
    }
    
    /**
     * Sanitiza HTML seguro
     *
     * @param string $html HTML para sanitizar
     * @return string HTML sanitizado
     */
    public static function sanitize_html($html) {
        // Permitir tags HTML comuns em posts do WordPress
        $allowed_tags = wp_kses_allowed_html('post');
        return wp_kses($html, $allowed_tags);
    }
    
    /**
     * Sanitiza campo de textarea
     *
     * @param string $textarea Textarea para sanitizar
     * @return string Textarea sanitizado
     */
    public static function sanitize_textarea($textarea) {
        return sanitize_textarea_field($textarea);
    }
    
    /**
     * Sanitiza um array recursivamente
     *
     * @param array $array Array para sanitizar
     * @param string $type Tipo de sanitização a aplicar ('text', 'html', 'url', 'textarea')
     * @return array Array sanitizado
     */
    public static function sanitize_array($array, $type = 'text') {
        if (!is_array($array)) {
            return $array;
        }
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sanitize_array($value, $type);
            } else {
                switch ($type) {
                    case 'html':
                        $array[$key] = self::sanitize_html($value);
                        break;
                    case 'url':
                        $array[$key] = self::sanitize_url($value);
                        break;
                    case 'textarea':
                        $array[$key] = self::sanitize_textarea($value);
                        break;
                    case 'text':
                    default:
                        $array[$key] = self::sanitize_text($value);
                        break;
                }
            }
        }
        
        return $array;
    }
}