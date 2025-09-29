<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para metabox de seleção de receptores
 *
 * Adiciona uma metabox na tela de edição de posts para selecionar
 * receptores para envio do conteúdo
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Metabox {

    public function __construct() {
        // Hook para adicionar a meta box
        add_action('add_meta_boxes', array($this, 'add_receivers_metabox'));
        // Hook para salvar os dados
        add_action('save_post', array($this, 'save_receivers_selection'));
    }

    /**
     * Adiciona a meta box na coluna lateral (side).
     */
    public function add_receivers_metabox() {
        add_meta_box(
            'dashi_emissor_receivers_box',
            __('Sites Receptores', 'dashi-emissor'),
            array($this, 'render_receivers_metabox'),
            'post', // pode ser 'post', 'page' ou outro post_type
            'side',
            'default'
        );
    }

    /**
     * Renderiza o conteúdo da meta box.
     */
    public function render_receivers_metabox($post) {
        // Recupera os sites cadastrados no plugin emissor
        $receivers = get_option('dashi_emissor_receivers', array());

        // Recupera a seleção salva para esse post
        $selected = get_post_meta($post->ID, '_dashi_emissor_selected_receivers', true);

        // Se não existe meta (retornou string vazia), marcamos todos por padrão
        if ($selected === '') {
            $selected = array_keys($receivers);
        }
        // Se existir meta mas não for array, forçamos um array vazio (caso raro).
        elseif (!is_array($selected)) {
            $selected = array();
        }

        // Cria um nonce de segurança
        wp_nonce_field('dashi_emissor_receivers_nonce', 'dashi_emissor_receivers_nonce_field');

        if (empty($receivers)) {
            echo '<p>' . __('Nenhum site receptor cadastrado.', 'dashi-emissor') . '</p>';
            return;
        }

        echo '<p>' . __('Selecione os sites que deverão receber a cópia/tradução deste post:', 'dashi-emissor') . '</p>';

        echo '<ul style="list-style:none; padding-left:0;">';
        foreach ($receivers as $index => $receiver) {
            $name = isset($receiver['name']) ? esc_html($receiver['name']) : esc_url($receiver['url']);
            $url = isset($receiver['url']) ? esc_url($receiver['url']) : '';
            $checkbox_id = 'dashi_emissor_receiver_' . $index;

            // Verifica se está marcado
            $checked = in_array($index, $selected) ? 'checked="checked"' : '';

            echo '<li style="margin-bottom:4px;">';
            echo '<label for="' . $checkbox_id . '">';
            echo '<input type="checkbox" id="' . $checkbox_id . '" name="dashi_emissor_selected_receivers[]" value="' . $index . '" ' . $checked . ' />';
            echo ' ' . $name . ' (' . $url . ')';
            echo '</label>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Salva a seleção de sites no meta do post.
     */
    public function save_receivers_selection($post_id) {
        // Verifica nonce e permissões
        if (!isset($_POST['dashi_emissor_receivers_nonce_field']) || 
             !wp_verify_nonce($_POST['dashi_emissor_receivers_nonce_field'], 'dashi_emissor_receivers_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Recupera as escolhas do formulário
        $selected = isset($_POST['dashi_emissor_selected_receivers']) ? (array) $_POST['dashi_emissor_selected_receivers'] : array();

        // Salva em um meta
        update_post_meta($post_id, '_dashi_emissor_selected_receivers', $selected);
    }
}