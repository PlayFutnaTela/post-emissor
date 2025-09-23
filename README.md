# Post Emissor - WordPress Plugin

Plugin WordPress para envio de posts publicados para sites receptores via REST API, com suporte a replicação de conteúdo, mídias, SEO e tradução automática.

## Funcionalidades

- ✅ Replicação automática de posts publicados
- ✅ Envio de mídias (imagem destacada e anexos)
- ✅ Preservação de metadados do autor
- ✅ Suporte a dados de SEO (Yoast)
- ✅ Integração com Elementor
- ✅ Tradução automática via OpenAI
- ✅ Atualização e deleção remota de posts
- ✅ Seleção de receptores por post

## Instalação

1. Baixe o plugin ou clone este repositório:
```bash
git clone https://github.com/[SEU-USUARIO]/post-emissor.git
```

2. Faça upload da pasta `post-emissor` para o diretório `/wp-content/plugins/` do seu WordPress

3. Ative o plugin através do painel administrativo do WordPress

## Configuração

1. Acesse as configurações do plugin no painel administrativo
2. Configure os sites receptores (URL e token de autenticação opcional)
3. Configure a chave da API OpenAI (opcional, para tradução)
4. Configure o idioma de origem

## Uso

1. Ao criar/editar um post, selecione os sites receptores desejados na metabox
2. Publique ou atualize o post
3. O plugin enviará automaticamente o conteúdo para os receptores selecionados

## Estrutura do Plugin

- `post-emissor.php` - Arquivo principal do plugin
- `includes/class-post-emissor.php` - Classe principal com lógica de envio
- `includes/rest-api.php` - Funções de comunicação REST API
- `includes/class-post-emissor-metabox.php` - Metabox de seleção de receptores
- `includes/admin-settings.php` - Configurações administrativas
- `DOCUMENTACAO.md` - Documentação técnica detalhada

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- Sites receptores com plugin "Post Receptor" instalado

## API Endpoints (Sites Receptores)

O plugin comunica-se com os seguintes endpoints nos sites receptores:

- `POST /wp-json/post-receptor/v1/receive` - Recebimento de posts
- `POST /wp-json/post-receptor/v1/update-status` - Atualização de status
- `POST /wp-json/post-receptor/v1/delete` - Deleção de posts

## Desenvolvimento

Para contribuir com o projeto:

1. Fork este repositório
2. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudanças (`git commit -am 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

## Licença

Este plugin é licenciado sob GPL2. Veja o arquivo de licença para mais detalhes.

## Autor

**Alexandre Chaves**

## Suporte

Para suporte técnico ou relato de bugs, abra uma issue neste repositório.

---

**Versão:** 1.0.0