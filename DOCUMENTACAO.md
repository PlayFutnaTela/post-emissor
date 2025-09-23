# Documentação do Plugin Post Emissor

## Visão Geral
O Post Emissor é um plugin WordPress cujo objetivo principal é replicar posts (conteúdo, metadados e mídias) para outros sites WordPress configurados como "receptores". A comunicação é feita via REST API, com suporte a:

- Conteúdo bruto do post (preserva shortcodes e estruturas internas);
- Mídias (imagem destacada e anexos);
- Metadados do autor (dados públicos e campos customizados);
- Dados de SEO (quando Yoast está presente);
- Dados do Elementor (meta `_elementor_data`);
- Tradução automática usando a API da OpenAI (opcional);
- Comandos de atualização de status e deleção remota.

Esta documentação descreve o fluxo, mapeia funções, opções e meta keys e lista problemas conhecidos e sugestões de correção.

## Arquitetura e arquivos principais

- `post-emissor.php` — bootstrapping do plugin: defines, load textdomain, includes e inicialização (instancia `Post_Emissor`).
- `includes/class-post-emissor.php` — classe `Post_Emissor`: hooks principais, preparação de dados e lógica de envio/atualização/deleção.
- `includes/rest-api.php` — funções responsáveis por executar as requisições HTTP aos receptores (`post_emissor_send_via_rest`, `post_emissor_update_status_via_rest`, `post_emissor_delete_via_rest`).
- `includes/class-post-emissor-metabox.php` — metabox que permite selecionar receptores por post (meta `_post_emissor_selected_receivers`).
- `includes/admin-settings.php` — UI/admin (onde estão as opções como lista de receptores, chaves e prompts).

## Fluxo de funcionamento (detalhado)

1) Inicialização

- No carregamento do plugin (action `plugins_loaded`) são carregadas as traduções, definidas constantes (`POST_EMISSOR_DIR`, `POST_EMISSOR_URL`) e incluídos os arquivos.
- Em `plugins_loaded` também é instanciada a classe `Post_Emissor` e executado `init()`.

2) Hooks registrados pela classe `Post_Emissor` (método init)

- `transition_post_status( $new, $old, $post )` — usado para detectar quando um post do tipo `post` é publicado (novo publish) ou quando sai do estado `publish` (para propagar mudança de status).
- `save_post( $post_ID, $post, $update )` — usado para capturar atualizações em posts; o handler ignora autosaves e revisões e só age quando `$update === true`.
- `before_delete_post( $post_id )` — usado para enviar comando de deleção para os receptores.

3) Quando um post é publicado (ou atualizado enquanto está publicado)

- `transition_post_status`: se `$post->post_type !== 'post'` a execução é abortada; se `$new_status === 'publish' && $old_status !== 'publish'` chama `$this->send_post_data($post)`.
- `save_post`: se `$update === true` e o post tem `post_status === 'publish'` chama `$this->send_post_data($post)` (atenção: aqui não há verificação explícita de `post_type`, veja seção de problemas conhecidos).

4) Preparação dos dados (`prepare_post_data`)

O método monta um array com os seguintes campos (principais):

- `ID`, `title` (sanitizado), `content` (conteúdo bruto), `excerpt`, `slug`, `status`.
- `categories` (via `wp_get_post_categories(..., fields=>'all')`) e `tags` (via `wp_get_post_tags`).
- `origin_language` (opção `post_emissor_origin_language` ou `get_locale()`), `author` (login do autor) e `author_data` (detalhes completos — nome, email, redes sociais lidas via usermeta, descrição).
- SEO (Yoast): `_yoast_wpseo_focuskw`, `_yoast_wpseo_metadesc` quando presentes.
- Elementor: meta `_elementor_data` quando presente.
- `media`: resultado de `get_post_media($post_id)` contendo `featured_image` e `attachments` (cada item com `ID`, `url`, `alt`, `title`, `caption`, `description`).

5) Envio via REST (`post_emissor_send_via_rest`)

- A função (em `rest-api.php`) aceita `$post_data` e opcionalmente uma lista `$receivers`. Se `$receivers` estiver vazia, ela carrega `get_option('post_emissor_receivers', array())`.
- Para cada receptor válido monta o endpoint: `trailingslashit( $receiver['url'] ) . 'wp-json/post-receptor/v1/receive'` e executa `wp_remote_post` com headers `Content-Type: application/json` e, opcionalmente, `Authorization: Bearer <auth_token>` quando `auth_token` estiver definido no receptor.
- Timeout padrão usado: 15 segundos.
- A função retorna um array `$results` com a entrada para cada receptor: `['url'=>..., 'status'=>'ok'|'fail', 'message'=>...]`.

6) Atualização de status remoto (`post_emissor_update_status_via_rest`)

- Monta endpoint `.../wp-json/post-receptor/v1/update-status` e envia um POST com `ID` e `status`.
- Registra erros com `error_log` se `wp_remote_post` falhar.

7) Deleção remota (`post_emissor_delete_via_rest`)

- Monta endpoint `.../wp-json/post-receptor/v1/delete` e envia um POST com `{ ID: ..., action: 'delete' }`.
- Registra erros com `error_log`.

## Opções, metas e chaves usadas (mapeamento)

- Opções (get_option):
  - `post_emissor_receivers` — array de receptores configurados (cada receptor contém ao menos `url`, opcional `auth_token`).
  - `post_emissor_origin_language` — idioma de origem padrão usado ao preparar dados.
  - `post_receptor_openai_api_key` — chave para a API da OpenAI (usada por `translate_text_context`).
  - `post_receptor_system_prompt` — prompt base (quando usado) para OpenAI.

- Metas de post (get_post_meta):
  - `_post_emissor_selected_receivers` — array de índices/identificadores que indicam quais receptores daquele post devem receber o conteúdo (usado por `send_post_data`).

## Tradução automática (detalhes técnicos)

- Função: método privado `translate_text_context($text, $source_lang, $target_lang, $context)` dentro de `class-post-emissor.php`.
- Comportamento:
  - Se `source_lang === target_lang` retorna o texto sem alterações.
  - Protege shortcodes do tipo `[video]...[/video]` substituindo-os por placeholders `%%SHORTCODE%%0`, `%%SHORTCODE%%1`, ... antes de enviar à API.
  - (Intenção) limpar spans e tags problemáticas antes da tradução (há código comentado para remoção de `<span>`). Atualmente a limpeza está comentada — ver seção "Problemas conhecidos".
  - Constrói prompt customizado conforme o `context` (`title`, `body`, `default`) e usa um `system prompt` com frase de fluência.
  - Envia requisição para `https://api.openai.com/v1/chat/completions` usando o modelo `gpt-4o-mini` com `temperature` 0.3 e `max_tokens` 2000.
  - Tenta até 3 vezes (retries) em caso de erro.
  - Ao receber texto traduzido, restaura os shortcodes substituindo os placeholders pelos valores originais.

## Logging e tratamento de erros

- As falhas em requisições HTTP são logadas via `error_log()` com mensagens contextuais (envio REST, atualização de status, deleção, e erros na API OpenAI).
- `post_emissor_send_via_rest` também retorna resultados por receptor (ok/fail) quando chamada diretamente.

## Avisos de admin / transients

- O arquivo principal `post-emissor.php` contém uma rotina que, ao abrir a tela de edição de post (`post.php?post=<id>`), verifica um transient `post_emissor_report_<post_id>` e exibe mensagens em admin notices. Porém **a classe `Post_Emissor::send_post_data` atualmente não grava esse transient** (o código comenta que "antes coletávamos $results e set_transient(...). Agora removemos").
- Resultado: existindo ou não o transient depende de versões anteriores ou modificações; hoje o fluxo padrão não cria o transient, logo o aviso de admin normalmente não será mostrado. (Ver recomendações abaixo se quiser restauração do relatório).

## Problemas conhecidos / inconsistências detectadas

1. Tradução: variável `$clean_text` usada quando se monta o prompt não está inicializada porque a rotina de limpeza de spans está comentada. Consequência: Notice de PHP por variável indefinida e possível comportamento incorreto. Recomendação: trocar `$clean_text` por a variável correta (por exemplo `$text` após placeholders) ou reativar/evalidar a rotina de limpeza e inicializar `$clean_text = $text;` antes do loop.

2. Filtro de tipo de post inconsistente:
   - `transition_post_status` verifica `post_type === 'post'` e só age para posts do tipo `post`.
   - `handle_save_post` não verifica o `post_type` antes de chamar `send_post_data` — portanto, atualizações em outros tipos de post que passem pelos checks (não-autosave, não-revision, `$update === true` e `post_status === 'publish'`) também irão disparar envio.
   - Recomendação: adicionar a mesma checagem `if ( $post->post_type !== 'post' ) return;` em `handle_save_post` para manter comportamento consistente.

3. Admin notices vs retorno de envio:
   - `post_emissor_send_via_rest` retorna um relatório `$results`. A classe `send_post_data` chama essa função mas **não armazena nem usa** esse resultado para criar o transient exibido no admin. Se o objetivo for mostrar relatórios de envio no admin, deve-se armazenar o resultado com `set_transient( 'post_emissor_report_' . $post_id, $results, $expiration )` e garantir que o transient seja definido antes de redirecionar/abrir a página do post.

4. Robustez do regex de shortcodes:
   - Atualmente só protege shortcodes do tipo `[video]...[/video]`. Outros shortcodes presentes no conteúdo podem ser afetados pela tradução; se necessário é melhor generalizar a proteção para qualquer shortcode (padrão: `/\[[^\]]+\]/` ou usar parser de shortcodes do WP para mais segurança).

5. Timeouts e retries:
   - Requests REST usam timeout 15s; OpenAI usa timeout 20s e 3 retries. Dependendo do volume de conteúdos grandes pode ser necessário ajustar limites e tratar chamadas em background (WP-Cron ou action queue) para evitar timeouts HTTP no fluxo síncrono.

## Recomendações práticas

- Corrigir a inicialização de `$clean_text` em `translate_text_context` ou usar `$text` diretamente quando a limpeza estiver desativada.
- Uniformizar checagem de `post_type` em todos os handlers (`transition_post_status`, `save_post`, `before_delete_post`).
- Se desejar exibir relatório de envio no admin, reintroduzir a lógica que grava o resultado em `set_transient('post_emissor_report_' . $post_id, $results, 60)` logo após o envio (e.g., em `send_post_data`) e ajustar o fluxo para que o editor do post seja redirecionado para `post.php?post=<id>` após o envio, permitindo a leitura do transient pelo aviso.
- Considerar mover envios longos/“pesados” para fila assíncrona (Action Scheduler, WP-Cron, ou background process) para não bloquear requests de publicação/edição.
- Generalizar a proteção de shortcodes antes de tradução para reduzir risco de corromper o conteúdo.

## Mapeamento rápido (funções / ações / opções)

- `init_post_emissor()` — função chamada em `plugins_loaded` para instanciar `Post_Emissor`.
- `Post_Emissor::init()` — registra hooks: `transition_post_status`, `save_post`, `before_delete_post`.
- `Post_Emissor::prepare_post_data($post)` — monta o array com todos os dados do post.
- `Post_Emissor::send_post_data($post)` — recupera receptores selecionados (`_post_emissor_selected_receivers`) e chama `post_emissor_send_via_rest($post_data, $receivers)`.
- `post_emissor_send_via_rest($post_data, $receivers)` — executa `wp_remote_post` para o endpoint `/wp-json/post-receptor/v1/receive` para cada receptor e retorna `$results`.
- `post_emissor_update_status_via_rest($update_data)` — envia para `/wp-json/post-receptor/v1/update-status`.
- `post_emissor_delete_via_rest($delete_data)` — envia para `/wp-json/post-receptor/v1/delete`.

## Conclusão

O plugin implementa a maioria das funcionalidades esperadas para replicação: coleta rica de dados, suporte a SEO e Elementor, estrutura para tradução e uso de tokens de autenticação nos receptores. Encontrei algumas inconsistências e um bug prático na função de tradução e na coerência da verificação do tipo de post. Corrigindo esses pontos a confiabilidade do plugin aumenta bastante.

Se quiser, posso:

1. Aplicar as correções de código sugeridas (inicializar `$clean_text`, uniformizar checagem de `post_type`, reintroduzir gravação de transient com opção configurável).
2. Implementar proteção generalizada de shortcodes antes da chamada à API de tradução.
3. Mover os envios para um sistema assíncrono (ex.: Action Scheduler).

Diga qual(es) mudança(s) prefere que eu aplique agora e eu edito o código e testo localmente.

---

**Autor do plugin (conforme cabeçalho):** Alexandre Chaves
**Versão detectada:** 1.0.0
**Licença:** GPL2
