<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Envia os dados do post via REST API para cada site receptor configurado
 * e retorna um array de resultados indicando se houve sucesso (enviado) ou falha (erro cURL).
 *
 * @param array $post_data Dados do post a serem enviados.
 * @param array $receivers Lista de receptores (opcional). Se vazio, pega do banco.
 *
 * @return array Array de resultados, cada item contendo:
 *               [ 'url' => '...', 'status' => 'ok'|'fail', 'message' => '...' ]
 */
function post_emissor_send_via_rest( $post_data, $receivers = array() ) {
    // Se não receber lista em $receivers, pega do banco (lógica antiga).
    if ( empty( $receivers ) ) {
        $receivers = get_option( 'post_emissor_receivers', array() );
    }

    // Array para armazenar o relatório de cada envio
    $results = array();

    if ( empty( $receivers ) ) {
        return $results; // Nenhum receptor, retorna relatório vazio
    }

    foreach ( $receivers as $receiver ) {
        if ( ! isset( $receiver['url'] ) || empty( $receiver['url'] ) ) {
            continue;
        }

        $endpoint = trailingslashit( $receiver['url'] ) . 'wp-json/post-receptor/v1/receive';

        $headers = array( 'Content-Type' => 'application/json' );
        if ( isset( $receiver['auth_token'] ) && ! empty( $receiver['auth_token'] ) ) {
            $headers['Authorization'] = 'Bearer ' . $receiver['auth_token'];
        }

        $args = array(
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => wp_json_encode( $post_data ),
            'timeout' => 15,
        );

        $response = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            // Houve erro de cURL
            $error_msg = $response->get_error_message();
            error_log( 'Post Emissor - Erro ao enviar dados para ' . $endpoint . ': ' . $error_msg );

            $results[] = array(
                'url'     => $receiver['url'],
                'status'  => 'fail',
                'message' => $error_msg,
            );
        } else {
            // Se chegou aqui, consideramos "enviado" com sucesso
            $results[] = array(
                'url'     => $receiver['url'],
                'status'  => 'ok',
                'message' => 'Enviado',
            );
        }
    }

    return $results;
}


/**
 * Atualiza o status do post via REST API para cada site receptor configurado.
 *
 * @param array $update_data Dados da atualização (ID do post e novo status).
 */
function post_emissor_update_status_via_rest( $update_data ) {
    $receivers = get_option( 'post_emissor_receivers', array() );
    if ( empty( $receivers ) ) {
        return;
    }

    foreach ( $receivers as $receiver ) {
        if ( ! isset( $receiver['url'] ) || empty( $receiver['url'] ) ) {
            continue;
        }

        $endpoint = trailingslashit( $receiver['url'] ) . 'wp-json/post-receptor/v1/update-status';

        $headers = array( 'Content-Type' => 'application/json' );
        if ( isset( $receiver['auth_token'] ) && ! empty( $receiver['auth_token'] ) ) {
            $headers['Authorization'] = 'Bearer ' . $receiver['auth_token'];
        }

        $args = array(
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => wp_json_encode( $update_data ),
            'timeout' => 15,
        );

        $response = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $response ) ) {
            error_log( 'Post Emissor - Erro ao atualizar status para ' . $endpoint . ': ' . $response->get_error_message() );
        }
    }
}

/**
 * Envia um comando de deleção do post via REST API para cada site receptor configurado.
 *
 * @param array $delete_data Dados da deleção (ID do post e ação 'delete').
 */
function post_emissor_delete_via_rest( $delete_data ) {
    $receivers = get_option( 'post_emissor_receivers', array() );
    if ( empty( $receivers ) ) {
        return;
    }

    foreach ( $receivers as $receiver ) {
        if ( ! isset( $receiver['url'] ) || empty( $receiver['url'] ) ) {
            continue;
        }

        $endpoint = trailingslashit( $receiver['url'] ) . 'wp-json/post-receptor/v1/delete';

        $headers = array( 'Content-Type' => 'application/json' );
        if ( isset( $receiver['auth_token'] ) && ! empty( $receiver['auth_token'] ) ) {
            $headers['Authorization'] = 'Bearer ' . $receiver['auth_token'];
        }

        $args = array(
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => wp_json_encode( $delete_data ),
            'timeout' => 15,
        );

        $response = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $response ) ) {
            error_log( 'Post Emissor - Erro ao enviar comando de deleção para ' . $endpoint . ': ' . $response->get_error_message() );
        }
    }
}
