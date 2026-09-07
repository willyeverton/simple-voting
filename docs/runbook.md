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
lando drush cr
```

Conceda as permissões do módulo a roles locais de teste sem versionar credenciais ou dumps contendo dados pessoais.

## Quality gates

```bash
lando phpcs
lando phpstan
lando phpunit
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
