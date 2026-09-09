# ADR 0004 — Harness de qualidade

- **Status:** aceito e validado
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

A direção aprovada é manter as mesmas categorias de validação em PHP 8.5 no ambiente local e no CI. O harness deve falhar com diagnóstico acionável e não deve desabilitar auditorias ou ignorar requisitos de plataforma.

O workflow do CI executa Composer validate, Composer audit, PHPCS, PHPStan e PHPUnit Unit. As suítes Kernel/Functional e as verificações de integração dependentes de banco foram executadas com sucesso no ambiente Lando pelo mantenedor em 2026-09-09.

## Consequências

- A execução local e a execução remota permanecem próximas.
- Dependências e padrões de código são verificados antes da entrega.
- O custo inicial de configuração é pequeno e evita regressões silenciosas.
