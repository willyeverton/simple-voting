# Checklist de entrega do desafio

Este checklist separa o que já está preparado do que depende da implementação e da configuração final do ambiente.

> As evidências marcadas foram informadas pelo usuário após a execução dos comandos. Como ajustes de código foram aplicados depois dessa execução, os gates devem ser repetidos antes da entrega final. O dump restaurável continua pendente.

## Repositório e ambiente

- [x] Projeto inicializado com Git.
- [x] Remote GitHub configurado.
- [x] Lando configurado.
- [x] Drupal 11 via Composer.
- [x] PHP 8.5 no container.
- [x] MySQL 8.4 no container.
- [x] Drush instalado no projeto.
- [x] Site Drupal instalado com dados de demonstração.
- [ ] Dump restaurável em `dump/`.

## Documentação

- [x] Brief original preservado.
- [x] Especificação funcional.
- [x] Requisitos não funcionais.
- [x] Modelo de domínio.
- [x] Matriz de permissões.
- [x] Fluxos do sistema.
- [x] OpenAPI.
- [x] Collection Postman.
- [x] Catálogo de erros.
- [x] Threat model.
- [x] Plano de testes automatizados.
- [x] Plano de testes manuais.
- [x] Matriz de rastreabilidade.
- [x] ADRs.
- [x] README de onboarding.
- [x] Documento de arquitetura e engenharia.
- [x] Runbook.
- [x] Checklist operacional.

## Implementação

- [x] Módulo customizado habilitável.
- [x] Entidade customizada sem `node`.
- [x] CRUD administrativo.
- [x] Opções com descrição e imagem.
- [x] Configuração global.
- [x] Service de votação.
- [x] Constraint de voto único.
- [x] Proteção contra concorrência.
- [x] Interface CMS.
- [x] API manual.
- [x] Resultados e visibilidade.
- [x] Logs e observabilidade.
- [x] Cache e invalidação.
- [x] Tema global customizado sem React.
- [x] Shell público com login, menus, mensagens e regiões.
- [x] Ativação automática do tema frontend com preservação do admin theme.

## Evidências

- [x] Composer validate.
- [x] Composer audit.
- [x] Quality harness Lando.
- [x] PHPCS com código customizado.
- [x] PHPStan com código customizado.
- [x] PHPUnit com testes do módulo.
- [x] Testes Kernel.
- [x] Testes funcionais.
- [x] Teste funcional do tema global e menus.
- [x] Teste de concorrência com banco real.
- [x] Execução completa da collection Postman.
- [ ] Restauração do dump em ambiente limpo.

## Critério de entrega

O desafio só deve ser considerado entregue quando todos os itens obrigatórios das seções Repositório, Implementação e Evidências estiverem concluídos, a collection estiver alinhada ao OpenAPI e o CI estiver verde.
