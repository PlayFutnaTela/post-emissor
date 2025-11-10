<!-- templates/admin/settings.php -->
<div class="wrap">
    <h1><?php _e('Configurações do Post Emissor', 'post-emissor'); ?></h1>
    <form method="post" action="options.php">
        <?php 
            settings_fields('post_emissor_settings_group'); 
            do_settings_sections('post_emissor_settings_group'); 
        ?>
        
        <!-- Seção para o idioma de origem -->
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Idioma de Origem', 'post-emissor'); ?></th>
                <td>
                    <select name="post_emissor_origin_language">
                        <option value="pt_BR" <?php selected($this->origin_language, 'pt_BR'); ?>><?php _e('Português do Brasil', 'post-emissor'); ?></option>
                        <option value="pt_PT" <?php selected($this->origin_language, 'pt_PT'); ?>><?php _e('Português de Portugal', 'post-emissor'); ?></option>
                        <option value="en_US" <?php selected($this->origin_language, 'en_US'); ?>><?php _e('Inglês Americano', 'post-emissor'); ?></option>
                        <option value="en_GB" <?php selected($this->origin_language, 'en_GB'); ?>><?php _e('Inglês Britânico', 'post-emissor'); ?></option>
                        <option value="es_ES" <?php selected($this->origin_language, 'es_ES'); ?>><?php _e('Espanhol da Espanha', 'post-emissor'); ?></option>
                        <option value="fr_FR" <?php selected($this->origin_language, 'fr_FR'); ?>><?php _e('Francês da França', 'post-emissor'); ?></option>
                        <option value="de_DE" <?php selected($this->origin_language, 'de_DE'); ?>><?php _e('Alemão da Alemanha', 'post-emissor'); ?></option>
                    </select>
                    <p class="description"><?php _e('Selecione o idioma de origem das publicações.', 'post-emissor'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Seção para listar e gerenciar receptores -->
        <h2><?php _e('Sites Receptores', 'post-emissor'); ?></h2>
        <table class="widefat" id="post_emissor_receivers_table">
            <thead>
                <tr>
                    <th><?php _e('Nome', 'post-emissor'); ?></th>
                    <th><?php _e('URL do Receptor', 'post-emissor'); ?></th>
                    <th><?php _e('Token de Autenticação', 'post-emissor'); ?></th>
                    <th><?php _e('Status da Conexão', 'post-emissor'); ?></th>
                    <th><?php _e('Ações', 'post-emissor'); ?></th>
                </tr>
            </thead>
            <tbody id="post_emissor_receivers_body">
                <?php if (!empty($this->receivers)): ?>
                    <?php foreach ($this->receivers as $index => $receiver): ?>
                        <?php
                            // Exibe status inicial como "Verificando..."
                            $status_conexao = '<span style="color:gray;font-weight:bold;">Verificando...</span>';
                            $current_url = isset($receiver['url']) ? esc_attr($receiver['url']) : '';
                            $current_name = isset($receiver['name']) ? esc_attr($receiver['name']) : '';
                            // Obtém o token armazenado, se existir, mas não será exibido no input
                            $stored_token = isset($receiver['auth_token']) ? esc_attr($receiver['auth_token']) : '';
                        ?>
                        <tr data-receiver-index="<?php echo esc_attr($index); ?>" data-stored-token="<?php echo $stored_token; ?>">
                            <td>
                                <input type="text"
                                       name="post_emissor_receivers[<?php echo esc_attr($index); ?>][name]"
                                       value="<?php echo $current_name; ?>"
                                       placeholder="<?php _e('Nome do Site', 'post-emissor'); ?>"
                                       style="width: 100%;" required />
                            </td>
                            <td>
                                <input type="url"
                                       name="post_emissor_receivers[<?php echo esc_attr($index); ?>][url]"
                                       value="<?php echo $current_url; ?>"
                                       placeholder="<?php _e('https://example.com', 'post-emissor'); ?>"
                                       style="width: 100%;" required />
                            </td>
                            <td>
                                <!-- Botão para inserir token -->
                                <button type="button" class="button post-insert-token-btn" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Inserir Token', 'post-emissor'); ?>
                                </button>
                                <?php if ($stored_token): ?>
                                <button type="button" class="button post-show-token-btn" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Ver Token', 'post-emissor'); ?>
                                </button>
                                <?php endif; ?>
                                <!-- Campo de token: sempre exibido vazio -->
                                <input type="password" class="post-token-field"
                                       name="post_emissor_receivers[<?php echo esc_attr($index); ?>][auth_token]"
                                       value=""
                                       style="display:none;width: 100%;margin-top:5px;"
                                       placeholder="<?php _e('Cole o Token aqui', 'post-emissor'); ?>" />
                                <button type="button" class="button post-save-token-btn" data-index="<?php echo esc_attr($index); ?>" style="display:none;margin-top:5px;">
                                    <?php _e('Salvar Token', 'post-emissor'); ?>
                                </button>
                            </td>
                            <td>
                                <span class="post-status-connection"><?php echo $status_conexao; ?></span>
                                <button type="button" class="button post-show-error-details" style="display:none;margin-left:5px;">
                                    <?php _e('Ver Detalhes', 'post-emissor'); ?>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="button post-test-connection" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Testar Conexão', 'post-emissor'); ?>
                                </button>
                                <button type="button" class="button post-remove-receiver" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Remover', 'post-emissor'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p>
            <button type="button" class="button" id="post_add_receiver"><?php _e('Adicionar Novo Receptor', 'post-emissor'); ?></button>
        </p>
        
        <?php submit_button(); ?>
    </form>
</div>