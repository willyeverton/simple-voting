# ADR 0004 — Harness de qualidade

- **Status:** aceito
- **Data:** 2026-09-06

## Contexto

O desafio avalia boas práticas Drupal, manutenção, segurança, concorrência e documentação. A validação precisa ser reproduzível localmente e no CI.

## Decisão

Centralizar comandos no Lando e executar quality gates em cada alteração relevante:

- Composer validate.
- Composer audit.
- PHPCS com Drupal e DrupalPractice.
- PHPStan Drupal.
- PHPUnit.
- Drush status para verificar runtime.

O CI executará as mesmas categorias de validação em PHP 8.5. O harness deve falhar com diagnóstico acionável e não deve desabilitar auditorias ou ignorar requisitos de plataforma.

## Consequências

- A execução local e a execução remota permanecem próximas.
- Dependências e padrões de código são verificados antes da entrega.
- O custo inicial de configuração é pequeno e evita regressões silenciosas.
