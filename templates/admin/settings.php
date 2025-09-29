<!-- templates/admin/settings.php -->
<div class="wrap">
    <h1><?php _e('Configurações do Dashi Emissor', 'dashi-emissor'); ?></h1>
    <form method="post" action="options.php">
        <?php 
            settings_fields('dashi_emissor_settings_group'); 
            do_settings_sections('dashi_emissor_settings_group'); 
        ?>
        
        <!-- Seção para o idioma de origem -->
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Idioma de Origem', 'dashi-emissor'); ?></th>
                <td>
                    <select name="dashi_emissor_origin_language">
                        <option value="pt_BR" <?php selected($this->origin_language, 'pt_BR'); ?>><?php _e('Português do Brasil', 'dashi-emissor'); ?></option>
                        <option value="pt_PT" <?php selected($this->origin_language, 'pt_PT'); ?>><?php _e('Português de Portugal', 'dashi-emissor'); ?></option>
                        <option value="en_US" <?php selected($this->origin_language, 'en_US'); ?>><?php _e('Inglês Americano', 'dashi-emissor'); ?></option>
                        <option value="en_GB" <?php selected($this->origin_language, 'en_GB'); ?>><?php _e('Inglês Britânico', 'dashi-emissor'); ?></option>
                        <option value="es_ES" <?php selected($this->origin_language, 'es_ES'); ?>><?php _e('Espanhol da Espanha', 'dashi-emissor'); ?></option>
                        <option value="fr_FR" <?php selected($this->origin_language, 'fr_FR'); ?>><?php _e('Francês da França', 'dashi-emissor'); ?></option>
                        <option value="de_DE" <?php selected($this->origin_language, 'de_DE'); ?>><?php _e('Alemão da Alemanha', 'dashi-emissor'); ?></option>
                    </select>
                    <p class="description"><?php _e('Selecione o idioma de origem das publicações.', 'dashi-emissor'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Seção para listar e gerenciar receptores -->
        <h2><?php _e('Sites Receptores', 'dashi-emissor'); ?></h2>
        <table class="widefat" id="dashi_emissor_receivers_table">
            <thead>
                <tr>
                    <th><?php _e('Nome', 'dashi-emissor'); ?></th>
                    <th><?php _e('URL do Receptor', 'dashi-emissor'); ?></th>
                    <th><?php _e('Token de Autenticação', 'dashi-emissor'); ?></th>
                    <th><?php _e('Status da Conexão', 'dashi-emissor'); ?></th>
                    <th><?php _e('Ações', 'dashi-emissor'); ?></th>
                </tr>
            </thead>
            <tbody id="dashi_emissor_receivers_body">
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
                                       name="dashi_emissor_receivers[<?php echo esc_attr($index); ?>][name]"
                                       value="<?php echo $current_name; ?>"
                                       placeholder="<?php _e('Nome do Site', 'dashi-emissor'); ?>"
                                       style="width: 100%;" required />
                            </td>
                            <td>
                                <input type="url"
                                       name="dashi_emissor_receivers[<?php echo esc_attr($index); ?>][url]"
                                       value="<?php echo $current_url; ?>"
                                       placeholder="<?php _e('https://example.com', 'dashi-emissor'); ?>"
                                       style="width: 100%;" required />
                            </td>
                            <td>
                                <!-- Botão para inserir token -->
                                <button type="button" class="button dashi-insert-token-btn" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Inserir Token', 'dashi-emissor'); ?>
                                </button>
                                <?php if ($stored_token): ?>
                                <button type="button" class="button dashi-show-token-btn" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Ver Token', 'dashi-emissor'); ?>
                                </button>
                                <?php endif; ?>
                                <!-- Campo de token: sempre exibido vazio -->
                                <input type="password" class="dashi-token-field"
                                       name="dashi_emissor_receivers[<?php echo esc_attr($index); ?>][auth_token]"
                                       value=""
                                       style="display:none;width: 100%;margin-top:5px;"
                                       placeholder="<?php _e('Cole o Token aqui', 'dashi-emissor'); ?>" />
                                <button type="button" class="button dashi-save-token-btn" data-index="<?php echo esc_attr($index); ?>" style="display:none;margin-top:5px;">
                                    <?php _e('Salvar Token', 'dashi-emissor'); ?>
                                </button>
                            </td>
                            <td>
                                <span class="dashi-status-connection"><?php echo $status_conexao; ?></span>
                                <button type="button" class="button dashi-show-error-details" style="display:none;margin-left:5px;">
                                    <?php _e('Ver Detalhes', 'dashi-emissor'); ?>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="button dashi-test-connection" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Testar Conexão', 'dashi-emissor'); ?>
                                </button>
                                <button type="button" class="button dashi-remove-receiver" data-index="<?php echo esc_attr($index); ?>">
                                    <?php _e('Remover', 'dashi-emissor'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p>
            <button type="button" class="button" id="dashi_add_receiver"><?php _e('Adicionar Novo Receptor', 'dashi-emissor'); ?></button>
        </p>
        
        <?php submit_button(); ?>
    </form>
</div>