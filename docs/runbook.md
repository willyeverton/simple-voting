# Runbook de desenvolvimento

- Onboarding e instalação: [`../README.md`](../README.md)
- Arquitetura e decisões de engenharia: [`architecture.md`](architecture.md)
- Testes manuais: [`manual-test-plan.md`](manual-test-plan.md)

> **Execução exclusiva do usuário:** os comandos abaixo são documentação para o usuário executar. Devin/modelo não deve executar testes, quality gates, auditorias, Drush status, Docker/Lando checks ou qualquer validação de código/ambiente.

## Ambiente

O projeto usa Drupal 11, PHP 8.5 e MySQL 8.4 dentro do Lando. Execute comandos na raiz do projeto:

```bash
cd /home/willy/Develop/simple-voting
lando start
```

URLs locais normalmente disponíveis:

- `https://simple-voting.lndo.site/`
- `http://simple-voting.lndo.site:8080/`

O certificado local pode exigir uma exceção no navegador se a CA do Lando não estiver instalada no host.

## Dependências

```bash
lando composer install
lando composer validate --strict
lando composer audit
```

## Site Drupal

A instalação do site ainda não é executada pelo bootstrap. Instale o site com credenciais locais fornecidas fora do Git e configure `settings.local.php` sem secrets versionados. Após a instalação, habilite o módulo e limpe o cache:

```bash
lando drush en simple_voting -y
lando drush updb -y
lando drush cr
```

A instalação/atualização do módulo instala `simple_voting_theme` e o define como tema frontend padrão, preservando o tema administrativo. Após o cache rebuild, revise menus e regiões do tema. Para rollback operacional, altere `system.theme:default` para o tema anterior e reconstrua o cache.

Conceda as permissões do módulo a roles locais de teste sem versionar credenciais ou dumps contendo dados pessoais.

## Quality gates

```bash
lando phpcs
lando phpstan
lando phpunit
lando phpunit-drupal
lando quality
```

O usuário deve executar `lando quality` para a sequência de código e testes. Com um site Drupal instalado, use também a sequência completa de bootstrap:

```bash
lando quality && lando drush updb -y && lando drush cr && lando drush status
```

Um erro deve ser investigado; não use flags para ignorar auditorias ou requisitos de plataforma. O `drush cr` é importante porque descobre rotas, entidades e plugins em runtime.

## Drush

```bash
lando drush status
lando drush cr
lando drush updb -y
```

## Fluxo recomendado

1. Ler `docs/specification.md` e a matriz de aceitação.
2. Atualizar ou criar um ADR quando uma decisão arquitetural for tomada.
3. Implementar uma fatia vertical pequena.
4. Adicionar testes correspondentes.
5. O usuário executa `lando quality`.
6. Revisar `git diff` e verificar que `.devin/`, `AGENTS.md`, secrets, `vendor/` e `web/core/` não foram staged.

## Diagnóstico

```bash
lando info
lando logs --service appserver
lando logs --service database
docker ps
```

Não execute `lando destroy`, `lando rebuild`, `git reset --hard` ou limpeza de volumes sem confirmar o impacto e a necessidade.

## Testes Drupal e concorrência

`lando phpunit` executa a suíte Unit configurada em `phpunit.xml.dist`. Com um banco Drupal configurado, execute também a suíte Kernel/Functional:

```bash
lando ssh
export SIMPLETEST_DB='mysql://USUARIO:SENHA@database/NOME_DO_BANCO'
export SIMPLETEST_BASE_URL='https://simple-voting.lndo.site'
vendor/bin/phpunit --configuration=phpunit.drupal.xml.dist
```

A concorrência deve ser validada em banco real com dois requests simultâneos para a mesma pergunta/usuário e com um request de voto concorrente a uma alteração administrativa de opções. O resultado esperado é no máximo um voto e nenhuma referência a opção órfã. O lock Drupal usa uma vida útil de 30 segundos e espera entre tentativas; a verificação deve incluir uma operação deliberadamente lenta o suficiente para demonstrar que a proteção permanece ativa durante a transação.

Erros da API retornam `X-Request-ID`. Ao investigar uma falha, associe esse valor aos eventos do canal `simple_voting`. Não registre nem solicite senhas, tokens, payloads completos ou IP bruto durante o diagnóstico.

## Tema frontend

A instalação e os updates do módulo mantêm a ativação automática de `simple_voting_theme`, sem alterar o tema administrativo. O tema frontend anterior é guardado para rollback. Se for necessário restaurá-lo:

```bash
lando drush config:set system.theme default NOME_DO_TEMA -y
lando drush cr
```

Confirme antes que o tema anterior continua instalado e que a troca não interfere no tema administrativo.

## Dump de demonstração

O arquivo `../dump/simple-voting-demo.sql` contém um snapshot ordenado do ambiente Drupal com configuração, `core.extension`, `key_value`, blocos e schemas necessários para o bootstrap. Para evitar transportar dados pessoais ou credenciais locais, o dump não transporta usuários, sessões, registros de votos, logs, caches nem dados de tabelas temporárias de teste; tabelas voláteis ficam apenas com sua estrutura. O e-mail administrativo foi normalizado para um domínio reservado e a definição vazia de `simple_voting_vote` preserva o schema sem transportar identidades ou votos.

A restauração deve ser validada somente em um ambiente limpo com o Drupal base instalado. Não importe este arquivo em um banco existente sem backup verificado:

```bash
lando drush en block -y
lando db-import dump/simple-voting-demo.sql
lando drush updb -y
lando drush cr
```

Como os usuários não são transportados, crie uma conta administrativa local após o import. A tabela `simple_voting_vote` deve existir e permanecer vazia após a restauração.

O dump é um artefato de banco; arquivos binários enviados não são substituídos por ele. Após a restauração, confirme que as opções continuam acessíveis e que imagens locais, quando usadas, foram fornecidas separadamente ou removidas do cenário de demonstração.

## Desinstalação destrutiva

O uninstall é bloqueado quando existem opções ou votos runtime. A remoção só pode ocorrer após backup verificado e confirmação operacional explícita:

```bash
lando drush sql:dump --result-file=/app/backup/simple-voting-before-uninstall.sql
lando drush state:set simple_voting.allow_destructive_uninstall 1
lando drush pm:uninstall simple_voting -y
```

O backup deve ser armazenado fora do diretório público e validado antes da confirmação. Não habilite `simple_voting.allow_destructive_uninstall` em uma operação rotineira ou sem aprovação registrada.
