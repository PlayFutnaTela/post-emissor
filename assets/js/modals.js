/* assets/js/modals.js */
jQuery(document).ready(function($) {
    // Abrir modal de detalhes do erro
    $(document).on('click', '.dashi-show-error-details', function() {
        var details = $(this).closest('tr').data('error-details') || 'Nenhum detalhe disponível';
        $('#dashi-error-details-content').text(details);
        $('#dashi-emissor-error-modal').fadeIn(200);
    });
    
    // Abrir modal de teste de conexão
    $(document).on('click', '.dashi-test-connection', function() {
        var index = $(this).data('index');
        var $row = $(this).closest('tr');
        
        // Obter dados do receptor
        var url = $row.find('input[type="url"]').val();
        var token = $row.find('.dashi-token-field').val();
        if (!token) {
            token = $row.data('stored-token');
        }
        
        if (!url) {
            alert('Por favor, informe a URL do receptor.');
            return;
        }
        
        // Mostrar indicador de carregamento
        var originalText = $(this).text();
        $(this).text('Testando...').prop('disabled', true);
        
        // Exibir status de teste
        $row.find('.dashi-status-connection')
            .text('Testando...')
            .removeClass('connected disconnected testing')
            .addClass('testing')
            .css({'color': 'blue', 'font-weight': 'bold'});
        
        // Fazer requisição AJAX para testar conexão
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'dashi_emissor_test_connection',
                nonce: dashi_emissor.nonce,
                url: url,
                token: token
            },
            success: function(response) {
                if (response.success) {
                    $row.find('.dashi-status-connection')
                        .text('Conectado')
                        .removeClass('connected disconnected testing')
                        .addClass('connected')
                        .css({'color': 'green', 'font-weight': 'bold'});
                } else {
                    $row.find('.dashi-status-connection')
                        .text('Desconectado: ' + (response.data.message || 'Erro desconhecido'))
                        .removeClass('connected disconnected testing')
                        .addClass('disconnected')
                        .css({'color': 'red', 'font-weight': 'bold'});
                }
            },
            error: function() {
                $row.find('.dashi-status-connection')
                    .text('Erro de conexão')
                    .removeClass('connected disconnected testing')
                    .addClass('disconnected')
                    .css({'color': 'red', 'font-weight': 'bold'});
            },
            complete: function() {
                // Restaurar botão
                $(this).text(originalText).prop('disabled', false);
            }
        }.bind(this));
    });
    
    // Fechar modais
    $('.dashi-modal-close, .dashi-modal-overlay').click(function() {
        $('.dashi-modal').fadeOut(200);
    });
    
    // Fechar modal ao pressionar ESC
    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            $('.dashi-modal').fadeOut(200);
        }
    });
    
    // Botão "Inserir Token": alterna a visibilidade do campo de token e botão salvar
    $(document).on('click', '.dashi-insert-token-btn', function() {
        var row = $(this).closest('tr');
        row.find('.dashi-token-field, .dashi-save-token-btn').toggle();
    });

    // Botão "Ver Token": exibe o token armazenado
    $(document).on('click', '.dashi-show-token-btn', function() {
        var row = $(this).closest('tr');
        var storedToken = row.data('stored-token');
        if (storedToken) {
            // Usar modal em vez de alert
            $('#dashi-error-details-content').text('Token: ' + storedToken);
            $('#dashi-emissor-error-modal h3').text('Token de Autenticação');
            $('#dashi-emissor-error-modal').fadeIn(200);
        } else {
            alert('Nenhum token encontrado.');
        }
    });
    
    // Adicionar novo receptor
    var receiverIndex = 0; // Isso será definido dinamicamente por PHP
    $('#dashi_add_receiver').on('click', function() {
        var newRow = '' +
            '<tr data-receiver-index="' + receiverIndex + '">' +
                '<td>' +
                    '<input type="text" name="dashi_emissor_receivers[' + receiverIndex + '][name]"' +
                    ' placeholder="Nome do Site" style="width: 100%;" required />' +
                '</td>' +
                '<td>' +
                    '<input type="url" name="dashi_emissor_receivers[' + receiverIndex + '][url]"' +
                    ' placeholder="https://example.com" style="width: 100%;" required />' +
                '</td>' +
                '<td>' +
                    '<button type="button" class="button dashi-insert-token-btn" data-index="' + receiverIndex + '">' +
                        'Inserir Token' +
                    '</button>' +
                    '<input type="password" class="dashi-token-field" style="display:none;width:100%;margin-top:5px;"' +
                    ' name="dashi_emissor_receivers[' + receiverIndex + '][auth_token]"' +
                    ' placeholder="Cole o Token aqui" value=""/>' +
                    '<button type="button" class="button dashi-save-token-btn" data-index="' + receiverIndex + '" style="display:none;margin-top:5px;">' +
                        'Salvar Token' +
                    '</button>' +
                '</td>' +
                '<td><span class="dashi-status-connection" style="color:gray;font-weight:bold;">Aguardando teste</span>' +
                '<button type="button" class="button dashi-show-error-details" style="display:none;margin-left:5px;">Ver Detalhes</button></td>' +
                '<td>' +
                    '<button type="button" class="button dashi-test-connection" data-index="' + receiverIndex + '">' +
                        'Testar Conexão' +
                    '</button>' +
                    '<button type="button" class="button dashi-remove-receiver" data-index="' + receiverIndex + '">' +
                        'Remover' +
                    '</button>' +
                '</td>' +
            '</tr>';
        $('#dashi_emissor_receivers_body').append(newRow);
        receiverIndex++;
    });
    
    // Botão "Remover" para excluir a linha
    $(document).on('click', '.dashi-remove-receiver', function() {
        if (confirm('Tem certeza que deseja remover este receptor?')) {
            $(this).closest('tr').remove();
        }
    });
    
    // Verifica a conexão automaticamente ao carregar a página
    $('#dashi_emissor_receivers_body tr').each(function() {
        // Fazer o teste de conexão para cada receptor existente
        // Isso é feito com um pequeno atraso para garantir que os eventos estejam carregados
        setTimeout(function() {
            $(this).find('.dashi-test-connection').click();
        }.bind(this), 500);
    });
});