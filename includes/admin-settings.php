<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Callback para sanitizar o array de receptores.
 * Para cada receptor, se o campo "auth_token" estiver vazio,
 * preserva o token previamente armazenado (se houver).
 *
 * @param array $input O array de receptores submetido.
 * @return array O array de receptores processado.
 */
function post_emissor_sanitize_receivers( $input ) {
    $old = get_option( 'post_emissor_receivers', array() );
    if ( is_array( $input ) ) {
        foreach ( $input as $key => $receiver ) {
            if ( empty( $receiver['auth_token'] ) && isset( $old[$key]['auth_token'] ) ) {
                $input[$key]['auth_token'] = $old[$key]['auth_token'];
            }
        }
    }
    return $input;
}

class Post_Emissor_Admin_Settings {
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }
    
    /**
     * Adiciona o menu do plugin no painel administrativo.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Post Emissor', 'post-emissor' ),
            __( 'Post Emissor', 'post-emissor' ),
            'manage_options',
            'post-emissor',
            array( $this, 'settings_page' ),
            'dashicons-email-alt'
        );
    }
    
    /**
     * Registra as configurações do plugin utilizando a Settings API do WordPress.
     */
    public function register_settings() {
        register_setting( 'post_emissor_settings_group', 'post_emissor_receivers', array(
            'sanitize_callback' => 'post_emissor_sanitize_receivers'
        ) );
        register_setting( 'post_emissor_settings_group', 'post_emissor_origin_language' );
    }
    
    /**
     * Renderiza a página de configurações do plugin.
     */
    public function settings_page() {
        // Carrega o idioma de origem salvo
        $origin_language = get_option( 'post_emissor_origin_language', get_locale() );
        // Recupera os sites receptores cadastrados e reindexa para índices sequenciais
        $receivers = get_option( 'post_emissor_receivers', array() );
        if ( ! is_array( $receivers ) ) {
            $receivers = array();
        }
        $receivers = array_values( $receivers );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Configurações do Post Emissor', 'post-emissor' ); ?></h1>
            <form method="post" action="options.php">
                <?php 
                    settings_fields( 'post_emissor_settings_group' ); 
                    do_settings_sections( 'post_emissor_settings_group' ); 
                ?>
                
                <!-- Seção para o idioma de origem -->
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Idioma de Origem', 'post-emissor' ); ?></th>
                        <td>
                            <select name="post_emissor_origin_language">
                                <option value="pt_BR" <?php selected( $origin_language, 'pt_BR' ); ?>><?php _e( 'Português do Brasil', 'post-emissor' ); ?></option>
                                <option value="pt_PT" <?php selected( $origin_language, 'pt_PT' ); ?>><?php _e( 'Português de Portugal', 'post-emissor' ); ?></option>
                                <option value="en_US" <?php selected( $origin_language, 'en_US' ); ?>><?php _e( 'Inglês Americano', 'post-emissor' ); ?></option>
                                <option value="en_GB" <?php selected( $origin_language, 'en_GB' ); ?>><?php _e( 'Inglês Britânico', 'post-emissor' ); ?></option>
                                <option value="es_ES" <?php selected( $origin_language, 'es_ES' ); ?>><?php _e( 'Espanhol da Espanha', 'post-emissor' ); ?></option>
                                <option value="fr_FR" <?php selected( $origin_language, 'fr_FR' ); ?>><?php _e( 'Francês da França', 'post-emissor' ); ?></option>
                                <option value="de_DE" <?php selected( $origin_language, 'de_DE' ); ?>><?php _e( 'Alemão da Alemanha', 'post-emissor' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Selecione o idioma de origem das publicações.', 'post-emissor' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Seção para listar e gerenciar receptores -->
                <h2><?php _e( 'Sites Receptores', 'post-emissor' ); ?></h2>
                <table class="widefat" id="post_emissor_receivers_table">
                    <thead>
                        <tr>
                            <th><?php _e( 'URL do Receptor', 'post-emissor' ); ?></th>
                            <th><?php _e( 'Token de Autenticação', 'post-emissor' ); ?></th>
                            <th><?php _e( 'Status da Conexão', 'post-emissor' ); ?></th>
                            <th><?php _e( 'Ações', 'post-emissor' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="post_emissor_receivers_body">
                        <?php if ( ! empty( $receivers ) ): ?>
                            <?php foreach ( $receivers as $index => $receiver ): ?>
                                <?php
                                    // Exibe status inicial como "Verificando..."
                                    $status_conexao = '<span style="color:gray;font-weight:bold;">Verificando...</span>';
                                    $current_url = isset($receiver['url']) ? esc_attr($receiver['url']) : '';
                                    // Obtém o token armazenado, se existir, mas não será exibido no input
                                    $stored_token = isset($receiver['auth_token']) ? esc_attr($receiver['auth_token']) : '';
                                ?>
                                <tr data-receiver-index="<?php echo esc_attr($index); ?>" data-stored-token="<?php echo $stored_token; ?>">
                                    <td>
                                        <input type="url"
                                               name="post_emissor_receivers[<?php echo esc_attr($index); ?>][url]"
                                               value="<?php echo $current_url; ?>"
                                               placeholder="<?php _e( 'https://example.com', 'post-emissor' ); ?>"
                                               style="width: 100%;" required />
                                    </td>
                                    <td>
                                        <!-- Botão para inserir token -->
                                        <button type="button" class="button inserir-token-btn" data-index="<?php echo esc_attr($index); ?>">
                                            <?php _e( 'Inserir Token', 'post-emissor' ); ?>
                                        </button>
                                        <!-- Campo de token: sempre exibido vazio -->
                                        <input type="text" class="token-field"
                                               name="post_emissor_receivers[<?php echo esc_attr($index); ?>][auth_token]"
                                               value=""
                                               style="display:none;width: 100%;margin-top:5px;"
                                               placeholder="<?php _e( 'Cole o Token aqui', 'post-emissor' ); ?>" />
                                    </td>
                                    <td>
                                        <span class="status-connection"><?php echo $status_conexao; ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="button remove-receiver" data-index="<?php echo esc_attr($index); ?>">
                                            <?php _e( 'Remover', 'post-emissor' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" class="button" id="add_receiver"><?php _e( 'Adicionar Novo Receptor', 'post-emissor' ); ?></button>
                </p>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
            (function($){
                $(document).ready(function(){
                    // Inicializa receiverIndex com o número atual de receptores
                    var receiverIndex = <?php echo ! empty($receivers) ? count($receivers) : 0; ?>;
                    
                    // Adiciona novo receptor
                    $('#add_receiver').on('click', function(){
                        var newRow = '' +
                            '<tr data-receiver-index="' + receiverIndex + '">' +
                                '<td>' +
                                    '<input type="url" name="post_emissor_receivers[' + receiverIndex + '][url]"' +
                                    ' placeholder="https://example.com" style="width: 100%;" required />' +
                                '</td>' +
                                '<td>' +
                                    '<button type="button" class="button inserir-token-btn" data-index="' + receiverIndex + '">' +
                                        '<?php _e( "Inserir Token", "post-emissor" ); ?>' +
                                    '</button>' +
                                    '<input type="text" class="token-field" style="display:none;width:100%;margin-top:5px;"' +
                                    ' name="post_emissor_receivers[' + receiverIndex + '][auth_token]"' +
                                    ' placeholder="<?php _e( "Cole o Token aqui", "post-emissor" ); ?>" value=""/>' +
                                '</td>' +
                                '<td><span class="status-connection" style="color:gray;font-weight:bold;">Verificando...</span></td>' +
                                '<td>' +
                                    '<button type="button" class="button remove-receiver" data-index="' + receiverIndex + '">' +
                                        '<?php _e( "Remover", "post-emissor" ); ?>' +
                                    '</button>' +
                                '</td>' +
                            '</tr>';
                        $('#post_emissor_receivers_body').append(newRow);
                        receiverIndex++;
                    });
                    
                    // Botão "Inserir Token": alterna a visibilidade do campo de token (sempre vazio)
                    $(document).on('click', '.inserir-token-btn', function(){
                        var row = $(this).closest('tr');
                        row.find('.token-field').toggle();
                    });
                    
                    // Botão "Remover" para excluir a linha
                    $(document).on('click', '.remove-receiver', function(){
                        $(this).closest('tr').remove();
                    });
                    
                    // Verifica a conexão via AJAX para cada receptor existente
                    $('#post_emissor_receivers_body tr').each(function(){
                        var row   = $(this);
                        var url   = row.find('input[type="url"]').val();
                        var token = row.find('.token-field').val();
                        // Se o campo token estiver vazio, utiliza o valor armazenado no atributo data-stored-token
                        if(!token){
                            token = row.data('stored-token');
                        }
                        if(!url || !token){
                            row.find('.status-connection').html('<span style="color:red;font-weight:bold;">Desconectado</span>');
                            return;
                        }
                        
                        var checkEndpoint = url.replace(/\/+$/, '') + '/wp-json/post-receptor/v1/check-token';
                        
                        $.ajax({
                            url: checkEndpoint,
                            method: 'GET',
                            headers: {
                                'Authorization': 'Bearer ' + token
                            },
                            timeout: 8000
                        }).done(function(response){
                            if(response && response.success){
                                row.find('.status-connection').html('<span style="color:green;font-weight:bold;">Conectado</span>');
                            } else {
                                row.find('.status-connection').html('<span style="color:red;font-weight:bold;">Desconectado</span>');
                            }
                        }).fail(function(){
                            row.find('.status-connection').html('<span style="color:red;font-weight:bold;">Desconectado</span>');
                        });
                    });
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}

new Post_Emissor_Admin_Settings();
