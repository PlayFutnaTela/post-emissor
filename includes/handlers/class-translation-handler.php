<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para tradução de texto via OpenAI
 *
 * Fornece métodos para traduzir texto entre diferentes idiomas
 * mantendo a estrutura e protegendo shortcodes
 *
 * @since 2.0.0
 */
class Dashi_Emissor_Translation_Handler {
    
    private $logger;
    
    /**
     * Construtor da classe
     *
     * @param Dashi_Emissor_Logger $logger Instância do logger
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Traduz o texto considerando o idioma de origem e destino, protegendo shortcodes e limpando spans.
     *
     * @param string $text Texto a ser traduzido
     * @param string $source_lang Idioma de origem
     * @param string $target_lang Idioma de destino
     * @param string $context Contexto da tradução (title, body, default)
     * @return string Texto traduzido
     */
    public function translate_text_context($text, $source_lang, $target_lang, $context = 'default') {
        if ($source_lang === $target_lang) {
            return $text;
        }
        
        // Log do texto original para depuração
        $this->logger->debug('Texto original antes da limpeza: ' . substr($text, 0, 500));
        
        // Protege shortcodes com placeholders
        $shortcodes = array();
        $placeholder = '%%SHORTCODE%%';
        $index = 0;
        
        // Função para proteger todos os tipos de shortcodes
        $clean_text = preg_replace_callback(
            '/\[([a-zA-Z0-9_-]+)(.*?)(\](.*?)\[\/\1\]|\])/s',
            function ($matches) use (&$shortcodes, &$index, $placeholder) {
                $shortcodes[$placeholder . $index] = $matches[0];
                return $placeholder . $index++;
            },
            $text
        );
        
        // Limpeza de HTML problemático (se necessário)
        // Remover spans com classes específicas
        $clean_text = preg_replace('/<span\b[^>]*class=[\"\'\[][^\"\']*_fadeIn_m1hgl_8[^\"\']*[\"\']?\b[^>]*>(.*?)<\/span>/is', '$1', $clean_text);
        
        // Remover outros spans genéricos
        $clean_text = preg_replace('/<span\b[^>]*>(.*?)<\/span>/is', '$1', $clean_text);
        
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
        
        $openai_api_key = get_option('dashi_emissor_openai_api_key', '');
        if (empty($openai_api_key)) {
            $this->logger->warning('Chave da API OpenAI não configurada, retornando texto limpo');
            // Restaura shortcodes antes de retornar
            $clean_text = str_replace(array_keys($shortcodes), array_values($shortcodes), $clean_text);
            return $clean_text;
        }
        
        $stored_system_prompt = get_option('dashi_emissor_system_prompt', '');
        $fluency_statement = "You are fluent in both {$source_name} and {$target_name}.";

        switch ($context) {
            case 'title':
                $custom_prompt = "{$fluency_statement} Translate the following title from {$source_name} to {$target_name}, maintaining its tone and intent.";
                break;
            case 'body':
                $custom_prompt = "{$fluency_statement} Translate the following content from {$source_name} to {$target_name}, preserving meaning and context, and ignoring and removing with high priority any HTML markup (e.g. <span>, <p>, <text>, and others) or placeholders like %%SHORTCODE%% in the translation process, and remove any and all existing classes that may prevent full translation into all languages!";
                break;
            default:
                $custom_prompt = "{$fluency_statement} Translate the following text from {$source_name} to {$target_name}.";
                break;
        }
        
        $prompt = $custom_prompt . "\n\n" . $clean_text;
        $max_retries = 3;
        $translated_text = '';

        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            $body_payload = wp_json_encode([
                "model" => "gpt-4o-mini",
                "messages" => [
                    ["role" => "system", "content" => $custom_prompt],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.3,
                "max_tokens" => 2000,
            ]);

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $openai_api_key,
                ],
                'body' => $body_payload,
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                $this->logger->error('Erro na API de tradução (' . $context . ') na tentativa ' . ($attempt+1) . ': ' . $response->get_error_message());
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Verificação robusta da resposta da API
            if (!is_array($data) || !isset($data['choices']) || !is_array($data['choices']) || empty($data['choices']) || !isset($data['choices'][0]['message']['content'])) {
                $this->logger->error('Resposta inválida da API OpenAI (' . $context . ') na tentativa ' . ($attempt+1) . ': ' . print_r($data, true));
                continue;
            }

            $translated_text = trim($data['choices'][0]['message']['content']);
            $this->logger->debug('Texto traduzido: ' . substr($translated_text, 0, 500));
            if (!empty($translated_text) && strtolower(trim($translated_text)) !== strtolower(trim($clean_text))) {
                break;
            }
        }

        // Restaura shortcodes no texto traduzido
        $translated_text = $translated_text ? $translated_text : $clean_text;
        $translated_text = str_replace(array_keys($shortcodes), array_values($shortcodes), $translated_text);
        
        return $translated_text;
    }
}