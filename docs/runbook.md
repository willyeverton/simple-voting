# Operação local

- Instalação e uso: [`../README.md`](../README.md)
- Arquitetura atual: [`architecture.md`](architecture.md)

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

A instalação/atualização do módulo instala `simple_voting_theme`, mas não o define como tema frontend padrão. A ativação é decisão operacional explícita; depois dela, revise menus e regiões. Para rollback, restaure o tema anterior e reconstrua o cache.

Conceda as permissões do módulo a roles locais de teste sem versionar credenciais ou dumps contendo dados pessoais.

## Quality gates

```bash
lando phpcs
lando phpstan
lando phpunit
lando phpunit-drupal
lando quality
lando quality-drupal
```

O workflow remoto executa os gates estáticos e a suíte Unit. `quality-drupal.sh` executa `quality.sh` e, em seguida, Kernel/Functional no appserver. As suítes Drupal dependem de um site instalado, banco de teste e URL funcional; por isso, continuam como gate local obrigatório e não são adicionadas ao workflow remoto. Execute o script no appserver após exportar `SIMPLETEST_DB` e `SIMPLETEST_BASE_URL` no mesmo shell.

O usuário deve executar `lando quality` para a sequência de código e testes. Com um site Drupal instalado e as variáveis de SimpleTest configuradas, use `lando quality-drupal` para incluir Kernel/Functional. Para o bootstrap completo do ambiente:

```bash
lando quality-drupal && lando drush updb -y && lando drush cr && lando drush status
```

Um erro deve ser investigado; não use flags para ignorar auditorias ou requisitos de plataforma. O `drush cr` é importante porque descobre rotas, entidades e plugins em runtime. Registre execuções concluídas em [`verification.md`](verification.md), identificando que a evidência foi produzida pelo mantenedor e sem incluir secrets.

## Drush

```bash
lando drush status
lando drush cr
lando drush updb -y
```

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

A concorrência deve ser validada em MySQL real com pelo menos dois requests realmente simultâneos para a mesma pergunta e usuário. O esperado é exatamente um `201`, um `409 DUPLICATE_VOTE` e uma única linha para `(question_id, uid)`. Repita com usuários distintos e confirme que ambos podem persistir: o hot path não usa lock Drupal nem serializa a pergunta; a constraint `UNIQUE(question_id, uid)` é a autoridade. Um runner sequencial do Postman não prova concorrência.

Perguntas/opções são conteúdo e não participam de importação de configuração. Limitações honestas: a Schema API não fornece foreign keys físicas; rate limiting/fairness são externos; falha de infraestrutura pode retornar `500`; a aplicação garante no máximo um voto por usuário/pergunta, não disponibilidade ilimitada.

Após uma falha deliberada na sincronização de opções, confirme que as linhas de opção voltaram ao estado anterior e que cada `image_fid` possui o uso `simple_voting/voting_option` correspondente; arquivos anexados apenas pela operação falha devem voltar a temporários sem uso do módulo. Erros da API retornam `X-Request-ID`. Ao investigar uma falha, associe esse valor aos eventos do canal `simple_voting`. Não registre nem solicite senhas, tokens, payloads completos ou IP bruto durante o diagnóstico.

## Tema frontend

A instalação e os updates do módulo instalam `simple_voting_theme`, mas não alteram o tema frontend ou administrativo configurado. Para ativar a apresentação demonstrativa explicitamente:

```bash
lando drush theme:enable simple_voting_theme -y
lando drush config:set system.theme default simple_voting_theme -y
lando drush cr
```

Para restaurar outro tema frontend, confirme antes que ele continua instalado e que a troca não interfere no tema administrativo:

```bash
lando drush config:set system.theme default NOME_DO_TEMA -y
lando drush cr
```

## Dump de demonstração

O arquivo `../dump/simple-voting-demo.sql` é obrigatório, mas a mudança do modelo para Content Entities customizadas torna necessária sua **regeneração**. Não trate o artefato anterior como compatível até aplicar updates, exportar novamente e comprovar um restore limpo. O snapshot final deve incluir configuração mínima, `core.extension`, `key_value`, blocos, tabelas e Content Entities demonstrativas de pergunta/opção, além da estrutura vazia de `simple_voting_vote`. Não deve transportar usuários, sessões, votos, logs, caches, secrets, credenciais locais nem dados temporários de teste.

A restauração deve ser validada somente em um ambiente limpo com o Drupal base instalado. Não importe este arquivo em um banco existente sem backup verificado:

```bash
lando drush en block -y
lando db-import dump/simple-voting-demo.sql
lando drush updb -y
lando drush cr
```

Procedimento de regeneração: parta de ambiente descartável atualizado, cadastre somente perguntas/opções demonstrativas sem votos, exporte de forma determinística e sanitizada, revise o diff SQL para dados pessoais/secrets e restaure o novo arquivo em outro ambiente limpo. Registre commit, data e contagens esperadas. Não use `updb` após o import para mascarar um dump antigo: o artefato entregue já deve representar o schema atual.

Como os usuários não são transportados, crie uma conta administrativa local após o import. As tabelas `voting_question`, `voting_option` e `simple_voting_vote` devem existir; votos devem permanecer em zero. O dump é um artefato de banco; arquivos binários enviados não são substituídos por ele. Após a restauração, confirme que as opções continuam acessíveis e que imagens locais, quando usadas, foram fornecidas separadamente ou removidas do cenário de demonstração.

## Desinstalação destrutiva

O uninstall é bloqueado quando existem opções ou votos runtime. A remoção só pode ocorrer após backup verificado e confirmação operacional explícita:

```bash
lando drush sql:dump --result-file=/app/backup/simple-voting-before-uninstall.sql
lando drush state:set simple_voting.allow_destructive_uninstall 1
lando drush pm:uninstall simple_voting -y
```

O backup deve ser armazenado fora do diretório público e validado antes da confirmação. Não habilite `simple_voting.allow_destructive_uninstall` em uma operação rotineira ou sem aprovação registrada.
