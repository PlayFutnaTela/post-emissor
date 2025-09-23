<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Emissor {

    /* ====================================================
     * Seção 1: Inicialização e Hooks
     * ==================================================== */

    /**
     * Inicializa o plugin adicionando os hooks necessários.
     */
    public function init() {
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('before_delete_post', array($this, 'handle_post_deletion'));
        add_action('save_post', array($this, 'handle_save_post'), 10, 3);
    }


    /* ====================================================
     * Seção 2: Manipuladores de Eventos
     * ==================================================== */

    /**
     * Lida com a mudança de status do post.
     *
     * @param string  $new_status Novo status do post.
     * @param string  $old_status Status anterior do post.
     * @param WP_Post $post       Objeto do post.
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'post') {
            return;
        }
        // Se o post for publicado agora (e não estava publicado antes), envia para os receptores
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->send_post_data($post);
        }
        // Se ele estava publicado e muda para outro status, propaga essa atualização de status
        if ($old_status === 'publish' && $new_status !== 'publish') {
            $this->update_post_status($post, $new_status);
        }
    }

    /**
     * Captura atualizações em posts publicados.
     *
     * @param int     $post_ID ID do post.
     * @param WP_Post $post    Objeto do post.
     * @param bool    $update  Se é atualização (true) ou nova inserção (false).
     */
    public function handle_save_post($post_ID, $post, $update) {
        // Ignora autosave, revisões, ou se for novo e ainda não publicado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_ID)) return;
        if (!$update) return;

        // Se já está publicado, envia para os receptores
        if ('publish' === $post->post_status) {
            $this->send_post_data($post);
        }
    }

    /**
     * Trata a deleção de um post.
     *
     * @param int $post_id ID do post a ser deletado.
     */
    public function handle_post_deletion($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'post') {
            $delete_data = array(
                'ID'     => $post_id,
                'action' => 'delete'
            );
            if (function_exists('post_emissor_delete_via_rest')) {
                post_emissor_delete_via_rest($delete_data);
            }
        }
    }


    /* ====================================================
     * Seção 3: Preparação dos Dados do Post
     * ==================================================== */

    /**
     * Prepara o array de dados do post a ser enviado via REST API.
     */
    private function prepare_post_data($post) {
        $post_data = array(
            'ID'              => $post->ID,
            'title'           => sanitize_text_field(get_the_title($post->ID)),
            'content'         => $post->post_content, // Usa conteúdo bruto para preservar shortcodes
            'excerpt'         => get_the_excerpt($post->ID),
            'slug'            => sanitize_title($post->post_name),
            'status'          => $post->post_status,
            'categories'      => wp_get_post_categories($post->ID, array('fields' => 'all')),
            'tags'            => wp_get_post_tags($post->ID, array('fields' => 'all')),
            'origin_language' => get_option('post_emissor_origin_language', get_locale()),
            'author'          => get_the_author_meta('user_login', $post->post_author),
        );

        // Dados completos do autor
        $post_data['author_data'] = $this->prepare_author_data($post->post_author);

        // Dados do SEO (Yoast)
        if (defined('WPSEO_VERSION')) {
            $focus_kw = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
            if ($focus_kw) {
                $post_data['focus_keyword'] = sanitize_text_field($focus_kw);
            }
        }
        $yoast_metadesc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if ($yoast_metadesc) {
            $post_data['yoast_metadesc'] = sanitize_text_field($yoast_metadesc);
        }

        // Dados do Elementor
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if ($elementor_data) {
            $post_data['elementor'] = $elementor_data;
        }

        // Mídias
        $post_data['media'] = $this->get_post_media($post->ID);

        return $post_data;
    }

    /**
     * Prepara os dados completos do autor.
     */
    private function prepare_author_data($author_id) {
        $author = get_userdata($author_id);
        if (!$author) {
            return array();
        }
        return array(
            'user_login'    => $author->user_login,
            'first_name'    => $author->first_name,
            'last_name'     => $author->last_name,
            'nickname'      => $author->nickname,
            'display_name'  => $author->display_name,
            'user_email'    => $author->user_email,
            'user_url'      => $author->user_url,
            'facebook'      => get_user_meta($author->ID, 'facebook', true),
            'instagram'     => get_user_meta($author->ID, 'instagram', true),
            'linkedin'      => get_user_meta($author->ID, 'linkedin', true),
            'username_x'    => get_user_meta($author->ID, 'username_x', true),
            'youtube'       => get_user_meta($author->ID, 'youtube', true),
            'description'   => $author->description,
        );
    }


    /* ====================================================
     * Seção 4: Ações (Envio, Atualização e Deleção)
     * ==================================================== */

    /**
     * Envia os dados do post via REST API para os sites receptores selecionados.
     */
    public function send_post_data($post) {
        $post_data = $this->prepare_post_data($post);

        $all_receivers = get_option('post_emissor_receivers', array());
        $selected = get_post_meta($post->ID, '_post_emissor_selected_receivers', true);
        if (!is_array($selected)) {
            $selected = array();
        }

        $receivers_to_send = array();
        foreach ($selected as $index) {
            if (isset($all_receivers[$index])) {
                $receivers_to_send[] = $all_receivers[$index];
            }
        }

        if (empty($receivers_to_send)) {
            return; // nada a enviar
        }

        // Chama a função que envia via REST (caso exista) sem coletar retorno
        if (function_exists('post_emissor_send_via_rest')) {
            post_emissor_send_via_rest($post_data, $receivers_to_send);
        }
    }

    /**
     * Propaga a atualização do status do post para os sites receptores via REST API.
     */
    public function update_post_status($post, $new_status) {
        $update_data = array(
            'ID'     => $post->ID,
            'status' => $new_status
        );
        if (function_exists('post_emissor_update_status_via_rest')) {
            post_emissor_update_status_via_rest($update_data);
        }
    }

    /**
     * Dispara a deleção do post via REST API para os sites receptores.
     */
    public function delete_post($delete_data) {
        if (function_exists('post_emissor_delete_via_rest')) {
            post_emissor_delete_via_rest($delete_data);
        }
    }


    /* ====================================================
     * Funções Auxiliares para Mídias
     * ==================================================== */

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

    private function get_media_details($attachment_id) {
        $attachment = get_post($attachment_id);
        return array(
            'ID'          => $attachment_id,
            'url'         => esc_url_raw(wp_get_attachment_url($attachment_id)),
            'alt'         => sanitize_text_field(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'title'       => sanitize_text_field($attachment->post_title),
            'caption'     => sanitize_text_field($attachment->post_excerpt),
            'description' => sanitize_text_field($attachment->post_content),
        );
    }


    /* ====================================================
     * Função para Tradução de Texto via OpenAI
     * ==================================================== */

    /**
     * Traduz o texto considerando o idioma de origem e destino, protegendo shortcodes e limpando spans.
     */
    private function translate_text_context($text, $source_lang, $target_lang, $context = 'default') {
        if ($source_lang === $target_lang) {
            return $text;
        }
        
        // Log do texto original para depuração
        error_log('Post Emissor - Texto original antes da limpeza: ' . substr($text, 0, 500));
        
        // Protege shortcodes [video] com placeholders
        $shortcodes = [];
        $placeholder = '%%SHORTCODE%%';
        $index = 0;
        $text = preg_replace_callback(
            '/\[video\b.*?\[\/video\]/i',
            function ($match) use (&$shortcodes, &$index, $placeholder) {
                $shortcodes[$placeholder . $index] = $match[0];
                return $placeholder . $index++;
            },
            $text
        );
        
        // Limpa os <span> com a classe _fadeIn_m1hgl_8 e outros spans
        $clean_text = preg_replace('/<span\b[^>]*class=["\'][^"\']*_fadeIn_m1hgl_8[^"\']*["\'][^>]*>(.*?)<\/span>/i', '$1', $text);
        $clean_text = preg_replace('/<span\b[^>]*>(.*?)<\/span>/i', '$1', $clean_text); // Remove qualquer outro span
        
        // Log do texto limpo para depuração
        error_log('Post Emissor - Texto após limpeza de spans e proteção de shortcodes: ' . substr($clean_text, 0, 500));
        
        $language_names = array(
            "pt_BR" => "Brazilian Portuguese",
            "pt_PT" => "European Portuguese",
            "en_US" => "American English",
            "en_GB" => "British English",
            "es_ES" => "Spanish (Spain)",
            "fr_FR" => "French (France)",
            "de_DE" => "German (Germany)"
        );
        
        $source_name = isset($language_names[$source_lang]) ? $language_names[$source_lang] : $source_lang;
        $target_name = isset($language_names[$target_lang]) ? $language_names[$target_lang] : $target_lang;
        
        $openai_api_key = get_option('post_receptor_openai_api_key', '');
        if (empty($openai_api_key)) {
            error_log('Post Emissor - Chave da API OpenAI não configurada, retornando texto limpo');
            // Restaura shortcodes antes de retornar
            $clean_text = str_replace(array_keys($shortcodes), array_values($shortcodes), $clean_text);
            return $clean_text;
        }
        
        $stored_system_prompt = get_option('post_receptor_system_prompt', '');
        $fluency_statement    = "You are fluent in both {$source_name} and {$target_name}.";

        switch ($context) {
            case 'title':
                $custom_prompt = "$fluency_statement Translate the following title from {$source_name} to {$target_name}, maintaining its tone and intent.";
                break;
            case 'body':
                $custom_prompt = "$fluency_statement Translate the following content from {$source_name} to {$target_name}, preserving the meaning and context while ignoring any HTML markup or placeholders like %%SHORTCODE%% in the translation process.";
                break;
            default:
                $custom_prompt = "$fluency_statement Translate the following text from {$source_name} to {$target_name}.";
                break;
        }
        
        $prompt = $custom_prompt . "\n\n" . $clean_text;
        $max_retries = 3;
        $translated_text = '';

        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            $body_payload = wp_json_encode([
                "model"       => "gpt-4o-mini",
                "messages"    => [
                    [ "role" => "system", "content" => $custom_prompt ],
                    [ "role" => "user",   "content" => $prompt ]
                ],
                "temperature" => 0.3,
                "max_tokens"  => 2000,
            ]);

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $openai_api_key,
                ],
                'body'    => $body_payload,
                'timeout' => 20,
            ]);

            if ( is_wp_error($response) ) {
                error_log('Post Emissor - Erro na API de tradução (' . $context . ') na tentativa ' . ($attempt+1) . ': ' . $response->get_error_message());
                continue;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ( isset($data['choices'][0]['message']['content'])12) {
                $translated_text = trim($data['choices'][0]['message']['content']);
                error_log('Post Emissor - Texto traduzido: ' . substr($translated_text, 0, 500));
                if ( !empty($translated_text) && strtolower(trim($translated_text)) !== strtolower(trim($clean_text)) ) {
                    break;
                }
            }
        }

        // Restaura shortcodes no texto traduzido
        $translated_text = $translated_text ? $translated_text : $clean_text;
        $translated_text = str_replace(array_keys($shortcodes), array_values($shortcodes), $translated_text);
        
        return $translated_text;
    }
}