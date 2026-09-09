# Plano de testes manuais — Simple Voting

Este documento descreve a validação manual do Simple Voting após a instalação Drupal, habilitação do módulo e execução dos quality gates. O roteiro foi executado com sucesso pelo mantenedor em 2026-09-09.

O objetivo é comprovar o comportamento integrado pelo navegador, pela API manual e pelo bloco Drupal. Os testes manuais complementam [`test-plan.md`](test-plan.md), [`acceptance-matrix.md`](acceptance-matrix.md) e os testes automatizados.

## 0. Pré-requisitos

O ambiente deve estar instalado e iniciado conforme [`../README.md`](../README.md):

```bash
lando start
lando composer install
lando drush status
lando drush en simple_voting -y
lando drush updb -y
lando drush cr
```

Confirme:

- Drupal responde em `https://simple-voting.lndo.site/`;
- banco está conectado;
- módulo `simple_voting` está instalado;
- cache foi reconstruído;
- `lando quality` passou;
- testes Kernel/Functional foram executados quando o ambiente de testes Drupal estiver configurado.

Se o certificado HTTPS local não for confiável no navegador, instale a CA do Lando ou aceite a exceção apenas no ambiente local.

Abaixo está um roteiro completo para validar o fluxo manualmente.

## 1. Preparar os usuários

### Administrador

Use a conta administrativa criada na instalação do Drupal.

Ela deve possuir:

- `administer simple voting`;
- `access simple voting API`;
- `vote in polls`;
- `view voting results`.

### Usuário votante

Crie outro usuário em:

```text
/admin/people/create
```

Crie, por exemplo:

```text
Usuário: voter
```

Depois crie uma role, por exemplo `Voter`, em:

```text
/admin/people/roles
```

Conceda à role:

- `vote in polls`;
- `access simple voting API`.

Depois de salvar a role, confirme que o usuário `voter` aparece com essa role em `/admin/people`. Se a role foi criada ou alterada enquanto o usuário estava logado, faça logout/login novamente para renovar a sessão e as permissões.

Para diagnosticar rapidamente a sessão, acesse `/user` como `voter` e confirme que a conta correta está logada. A rota `/voting` é protegida exclusivamente pela permissão `vote in polls`; `access simple voting API` sozinho não permite acessar o CMS.

Não conceda:

- `administer simple voting`;
- `view voting results`, inicialmente.

A permissão `view voting results` será usada depois para testar resultados ocultos.

---

## 2. Criar uma pergunta

Logado como administrador, acesse:

```text
https://simple-voting.lndo.site/admin/config/simple-voting/questions
```

Clique em **Add voting question**.

Use valores de exemplo:

```text
Título:
Qual sua cor favorita?

Identificador:
favorite_color
```

O identificador segue a convenção de machine name do Drupal: use letras minúsculas, números e underscore (`_`). Não use hífen (`-`). Portanto, `favorite_color` é válido e `favorite-color` é inválido.

Adicione duas opções:

```text
Opção 1:
Azul

Descrição:
Uma cor fria.

Opção 2:
Verde

Descrição:
Uma cor associada à natureza.
```

Durante esse teste, valide também:

- botão **Add option** adiciona uma nova linha;
- valores já preenchidos permanecem após o AJAX;
- botão **Remove option** remove a linha correta;
- descrição é preservada;
- upload de imagem aceita formatos permitidos;
- upload de arquivo inválido é rejeitado.

Configure:

```text
Show results after voting: marcado
Lifecycle status: Closed
```

Salve a pergunta.

A pergunta deve começar fechada por padrão, conforme o lifecycle definido no projeto. <ref_file file="/home/willy/Develop/simple-voting/docs/adr/0007-lifecycle-and-results.md" />

---

## 3. Abrir a pergunta

Edite a pergunta novamente e altere:

```text
Lifecycle status: Open
```

Salve.

Acesse:

```text
/voting
```

Exemplo:

```text
https://simple-voting.lndo.site/voting
```

A pergunta deve aparecer na listagem como disponível.

## 3.1. Menu e apresentação

Após `drush updb -y` e `drush cr`, o tema `simple_voting_theme` deve estar instalado e definido como tema frontend padrão.

Como visitante anônimo:

- acesse a home (`/`);
- confirme que o nome do site e o link **Log in** aparecem;
- confirme que o tema não exibe links de votação para quem não possui `vote in polls`.

Como usuário autenticado com `vote in polls`:

- confirme que o menu de conta exibe **Log out**;
- confirme que o menu principal exibe **Voting questions**;
- abra `/voting` e valide título, breadcrumb, mensagens e conteúdo;
- confirme que sidebars e footer não quebram quando não houver blocos posicionados.

Valide também foco de teclado, contraste, viewport móvel e ausência de dependências React ou JavaScript obrigatórias para registrar o voto. Confirme que o tema administrativo continua sendo o configurado anteriormente.

---

# Teste pelo navegador

## 4. Primeiro voto

1. Faça logout do administrador.
2. Faça login como `voter`.
3. Acesse:

```text
https://simple-voting.lndo.site/voting
```

4. Clique em **Qual sua cor favorita?**
5. Selecione `Azul`.
6. Clique em **Register vote**.

Resultado esperado:

- o voto é registrado;
- o usuário é redirecionado para resultados, pois `show_results` está habilitado;
- os resultados aparecem com total e percentual;
- a opção escolhida aparece contabilizada.

A contagem e os percentuais devem ser produzidos pelo `VotingResultsService`, usado pelo CMS, bloco e API. <ref_file file="/home/willy/Develop/simple-voting/docs/architecture.md" />

## 5. Tentar votar novamente pelo CMS

Ainda logado como `voter`:

1. Volte para:

```text
/voting
```

2. Acesse novamente a pergunta.

Resultado esperado:

- o formulário aparece bloqueado ou em estado somente leitura;
- o usuário não consegue registrar um segundo voto;
- nenhuma nova contagem é criada.

A proteção real também deve ocorrer no serviço e no banco, não apenas na interface.

## 6. Testar pergunta fechada

Como administrador:

1. Edite a pergunta;
2. altere o status para `Closed`;
3. salve.

Como `voter`, acesse a pergunta novamente.

Resultado esperado:

- a pergunta aparece marcada como fechada;
- nenhum controle de voto ativo é exibido;
- nenhum novo voto é aceito.

Depois, reabra a pergunta para continuar os demais testes.

## 7. Testar votação global desabilitada

Como administrador, acesse:

```text
/admin/config/simple-voting/settings
```

Desmarque:

```text
Enable voting
```

Salve.

Como `voter`, acesse:

```text
/voting
```

Resultado esperado:

- a interface informa que a votação está temporariamente desabilitada;
- a listagem não exibe perguntas nem links de votação;
- o formulário não permite novo voto quando uma pergunta é acessada diretamente;
- resultados históricos autorizados continuam sujeitos à política de visibilidade.

Depois do teste, habilite novamente a votação.

---

# Teste da API com Postman

A API deve ser validada usando a collection Postman versionada em:

- <ref_file file="/home/willy/Develop/simple-voting/docs/openapi.yaml" />
- <ref_file file="/home/willy/Develop/simple-voting/postman/simple-voting.postman_collection.json" />

A collection possui assertions para respostas de sucesso. Os cenários de erro (`401`, `403`, `409`, `422` e `503`) foram executados pelas requisições manuais descritas abaixo e passaram, sem reutilizar credenciais ou tokens em arquivos versionados. A execução foi confirmada pelo mantenedor em 2026-09-09.

## 8. Importar e configurar a collection

No Postman:

1. clique em **Import**;
2. selecione `postman/simple-voting.postman_collection.json`;
3. abra a collection **Simple Voting API**;
4. confirme a variável `base_url`:

```text
https://simple-voting.lndo.site
```

5. configure a autenticação **Basic Auth** da collection;
6. informe o usuário `voter` e a senha local correspondente;
7. defina `question_id` como `favorite_color`;
8. deixe `option_id` com o ID real de uma opção retornada pelo endpoint de detalhe;
9. não salve senhas, tokens ou credenciais em arquivos exportados da collection.

Se o certificado HTTPS local não for confiável, configure temporariamente a opção de certificado SSL do Postman apenas para o ambiente local. Não desabilite validação TLS em produção.

A collection usa a mesma autenticação nas requisições. Para testar um usuário anônimo, desabilite a autenticação somente na requisição específica ou crie uma cópia local não exportada.

## 9. Listar perguntas disponíveis

No Postman, execute a requisição **List questions**.

Confirme:

- método `GET`;
- URL `{{base_url}}/api/v1/questions`;
- autenticação Basic Auth herdada da collection;
- header `Accept: application/json`.

Resultado esperado:

- HTTP `200`;
- envelope `data`;
- somente perguntas abertas e disponíveis;
- nenhuma identidade de votante.

Com `Enable voting` desabilitado, repita a requisição e confirme HTTP `200` com `data` vazio. Uma pergunta fechada não deve aparecer na listagem da API, embora continue consultável diretamente pelo identificador.

## 10. Consultar uma pergunta

No Postman, execute **Get question**.

Confirme:

- método `GET`;
- URL `{{base_url}}/api/v1/questions/{{question_id}}`;
- autenticação Basic Auth herdada;
- header `Accept: application/json`.

Resultado esperado:

- HTTP `200`;
- `id`, `title`, `status`, `show_results` e `options`;
- opções pertencentes à pergunta;
- descrições seguras;
- `image_url` somente para arquivo válido.

Copie o ID de uma opção retornada e atualize a variável `option_id` da collection. Uma pergunta conhecida fechada deve continuar consultável com `status: closed`, mas não deve aceitar votos.

## 11. Registrar o primeiro voto

No Postman, abra **Create vote**.

Confirme:

- método `POST`;
- URL `{{base_url}}/api/v1/questions/{{question_id}}/votes`;
- autenticação Basic Auth herdada;
- header `Content-Type: application/json`;
- body JSON usando a variável `option_id`:

```json
{
  "option_id": {{option_id}}
}
```

O header `X-CSRF-Token` pode permanecer desabilitado quando a requisição usa Basic Auth stateless. Para sessão Drupal via cookie, habilite-o e preencha `csrf_token`.

Clique em **Send**.

Resultado esperado:

- HTTP `201`;
- body com `message`;
- exatamente um registro para `(question_id, uid)`;
- nenhum IP bruto persistido.

## 12. Tentar registrar o segundo voto

Sem alterar a requisição **Create vote**, clique em **Send** novamente.

Resultado esperado:

- HTTP `409`;
- código `DUPLICATE_VOTE`;
- nenhum segundo registro;
- nenhuma stack trace na resposta.

## 13. Consultar resultados

No Postman, execute **Get results**.

A requisição deve usar:

- método `GET`;
- URL `{{base_url}}/api/v1/questions/{{question_id}}/results`;
- autenticação Basic Auth herdada.

Com `show_results` habilitado, o usuário deve receber HTTP `200`.

O body deve conter:

- `question_id`;
- `total_votes`;
- cada opção;
- quantidade de votos;
- percentual.

## 14. Testar resultado oculto

Como administrador:

1. edite a pergunta;
2. desmarque `Show results after voting`;
3. salve;
4. mantenha a pergunta aberta.

Como `voter`, execute novamente **Get results** sem a permissão `view voting results`.

Resultado esperado:

- HTTP `403`;
- código `ACCESS_DENIED`;
- nenhum total ou percentual no body.

Depois, conceda `view voting results` à role do usuário, atualize a sessão no Postman e execute **Get results** novamente.

Resultado esperado:

- HTTP `200`;
- resultados disponíveis para o usuário autorizado.

## 15. Testar opção de outra pergunta

Crie uma segunda pergunta, por exemplo:

```text
Identificador: favorite_food
```

Copie o ID de uma opção dessa segunda pergunta. No Postman:

1. abra **Create vote**;
2. mantenha `question_id` como `favorite_color`;
3. altere `option_id` para o ID pertencente a `favorite_food`;
4. clique em **Send**.

Resultado esperado:

- HTTP `422`;
- código `OPTION_NOT_FOUND`;
- nenhum voto persistido.

## 16. Testar pergunta fechada pela API

Feche `favorite_color` no CMS. No Postman, execute **Create vote** novamente sem alterar o body.

Resultado esperado:

- HTTP `422`;
- código `QUESTION_CLOSED`;
- nenhum voto persistido.

## 17. Testar acesso anônimo

No Postman, duplique **List questions** ou desabilite a autenticação herdada da collection somente nessa requisição. Execute:

```text
GET {{base_url}}/api/v1/questions
```

Resultado esperado:

- HTTP `401`;
- código `AUTHENTICATION_REQUIRED`;
- nenhum dado de negócio.

---

# Testar o bloco Drupal

## 18. Colocar o bloco

Como administrador, acesse:

```text
/admin/structure/block
```

1. Clique em **Place block** na região desejada;
2. procure por:

```text
Simple Voting: question form
```

3. selecione a pergunta;
4. escolha uma região, como `Content`;
5. salve o bloco.

## 19. Validar o bloco como usuário votante

Faça login como `voter` e acesse uma página que use a região onde o bloco foi colocado.

Verifique:

- pergunta aberta exibe o formulário;
- usuário sem `vote in polls` não vê o formulário;
- pergunta fechada não permite voto;
- votação global desabilitada bloqueia o formulário;
- usuário que já votou recebe confirmação;
- resultados públicos aparecem quando permitido;
- resultados ocultos não aparecem sem `view voting results`;
- CMS, bloco e API exibem os mesmos números.

## 20. Limpar o ambiente depois dos testes

Depois de alterar configurações:

```bash
lando drush cr
```

Para conferir as tabelas criadas:

```bash
lando drush sql:query "SHOW TABLES LIKE 'simple_voting_%';"
```

Não remova votos manualmente para repetir cenários. Para novos testes, use outra pergunta ou outro usuário.