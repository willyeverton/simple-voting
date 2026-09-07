# Checklist de entrega do desafio

Este checklist separa o que já está preparado do que depende da implementação e da configuração final do ambiente.

## Repositório e ambiente

- [x] Projeto inicializado com Git.
- [ ] Remote GitHub configurado.
- [x] Lando configurado.
- [x] Drupal 11 via Composer.
- [x] PHP 8.5 no container.
- [x] MySQL 8.4 no container.
- [x] Drush instalado no projeto.
- [ ] Site Drupal instalado com dados de demonstração.
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
- [x] Plano de testes.
- [x] Matriz de rastreabilidade.
- [x] ADRs.
- [x] Runbook.
- [x] Checklist operacional.

## Implementação

- [ ] Módulo customizado habilitável.
- [ ] Entidade customizada sem `node`.
- [ ] CRUD administrativo.
- [ ] Opções com descrição e imagem.
- [ ] Configuração global.
- [ ] Service de votação.
- [ ] Constraint de voto único.
- [ ] Proteção contra concorrência.
- [ ] Interface CMS.
- [ ] API manual.
- [ ] Resultados e visibilidade.
- [ ] Logs e observabilidade.
- [ ] Cache e invalidação.

## Evidências

- [x] Composer validate.
- [x] Composer audit.
- [x] Quality harness Lando.
- [ ] PHPCS com código customizado.
- [ ] PHPStan com código customizado.
- [ ] PHPUnit com testes do módulo.
- [ ] Testes Kernel.
- [ ] Testes funcionais.
- [ ] Teste de concorrência com banco real.
- [ ] Execução completa da collection Postman.
- [ ] Restauração do dump em ambiente limpo.

## Critério de entrega

O desafio só deve ser considerado entregue quando todos os itens obrigatórios das seções Repositório, Implementação e Evidências estiverem concluídos, a collection estiver alinhada ao OpenAPI e o CI estiver verde.
