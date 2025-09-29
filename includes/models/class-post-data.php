<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe modelo para dados do post
 *
 * Representa os dados de um post que serão enviados para receptores
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Post_Data {
    
    public $post_id;
    public $title;
    public $content;
    public $excerpt;
    public $slug;
    public $status;
    public $categories;
    public $tags;
    public $origin_language;
    public $author;
    public $author_data;
    public $focus_keyword;
    public $yoast_metadesc;
    public $elementor;
    public $media;
    
    /**
     * Construtor da classe
     *
     * @param object $post Objeto do post do WordPress
     */
    public function __construct($post = null) {
        if ($post) {
            $this->prepare_data($post);
        }
    }
    
    /**
     * Prepara os dados do post a serem enviados via REST API
     *
     * @param object $post Objeto do post do WordPress
     */
    private function prepare_data($post) {
        $this->post_id = $post->ID;
        $this->title = sanitize_text_field(get_the_title($post->ID));
        $this->content = $post->post_content; // Usa conteúdo bruto para preservar shortcodes
        $this->excerpt = get_the_excerpt($post->ID);
        $this->slug = sanitize_title($post->post_name);
        $this->status = $post->post_status;
        $this->categories = wp_get_post_categories($post->ID, array('fields' => 'all'));
        $this->tags = wp_get_post_tags($post->ID, array('fields' => 'all'));
        $this->origin_language = get_option('dashi_emissor_origin_language', get_locale());
        $this->author = get_the_author_meta('user_login', $post->post_author);
        
        // Dados completos do autor
        $this->author_data = $this->prepare_author_data($post->post_author);
        
        // Dados do SEO (Yoast)
        if (defined('WPSEO_VERSION')) {
            $focus_kw = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
            if ($focus_kw) {
                $this->focus_keyword = sanitize_text_field($focus_kw);
            }
        }
        $yoast_metadesc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if ($yoast_metadesc) {
            $this->yoast_metadesc = sanitize_text_field($yoast_metadesc);
        }
        
        // Dados do Elementor
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($elementor_data) {
            $this->elementor = $elementor_data;
        }
        
        // Mídias
        $this->media = $this->get_post_media($post->ID);
    }
    
    /**
     * Prepara os dados completos do autor
     *
     * @param int $author_id ID do autor
     * @return array Dados do autor
     */
    private function prepare_author_data($author_id) {
        $author = get_userdata($author_id);
        if (!$author) {
            return array();
        }
        return array(
            'user_login' => $author->user_login,
            'first_name' => $author->first_name,
            'last_name' => $author->last_name,
            'nickname' => $author->nickname,
            'display_name' => $author->display_name,
            'user_email' => $author->user_email,
            'user_url' => $author->user_url,
            'facebook' => get_user_meta($author->ID, 'facebook', true),
            'instagram' => get_user_meta($author->ID, 'instagram', true),
            'linkedin' => get_user_meta($author->ID, 'linkedin', true),
            'username_x' => get_user_meta($author->ID, 'username_x', true),
            'youtube' => get_user_meta($author->ID, 'youtube', true),
            'description' => $author->description,
        );
    }
    
    /**
     * Obtém os dados de mídia do post
     *
     * @param int $post_id ID do post
     * @return array Dados de mídia
     */
    private function get_post_media($post_id) {
        $media = array();
        
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $media['featured_image'] = $this->get_media_details($thumbnail_id);
        }
        
        $attachments = get_attached_media('image', $post_id);
        $media['attachments'] = array();
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if ($attachment->ID == $thumbnail_id) continue;
                $media['attachments'][] = $this->get_media_details($attachment->ID);
            }
        }
        return $media;
    }
    
    /**
     * Obtém detalhes de um anexo de mídia
     *
     * @param int $attachment_id ID do anexo
     * @return array Detalhes do anexo
     */
    private function get_media_details($attachment_id) {
        $attachment = get_post($attachment_id);
        return array(
            'ID' => $attachment_id,
            'url' => esc_url_raw(wp_get_attachment_url($attachment_id)),
            'alt' => sanitize_text_field(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'title' => sanitize_text_field($attachment->post_title),
            'caption' => sanitize_text_field($attachment->post_excerpt),
            'description' => sanitize_text_field($attachment->post_content),
        );
    }
    
    /**
     * Converte os dados para array
     *
     * @return array Dados do post
     */
    public function to_array() {
        return array(
            'ID' => $this->post_id,
            'title' => $this->title,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'slug' => $this->slug,
            'status' => $this->status,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'origin_language' => $this->origin_language,
            'author' => $this->author,
            'author_data' => $this->author_data,
            'focus_keyword' => $this->focus_keyword,
            'yoast_metadesc' => $this->yoast_metadesc,
            'elementor' => $this->elementor,
            'media' => $this->media,
        );
    }
    
    /**
     * Obtém os dados do post no formato necessário para envio
     *
     * @param object $post Objeto do post do WordPress
     * @return array Dados formatados para envio
     */
    public static function get_formatted_data($post) {
        $post_data = new self($post);
        return $post_data->to_array();
    }
}