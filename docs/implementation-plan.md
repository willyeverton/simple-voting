# Plano de implementação por fatias

Este documento registra o plano executado. As saídas de cada fase foram validadas; o registro consolidado está em [`delivery-checklist.md`](delivery-checklist.md).

## Fase 0 — decisões e contrato

- Revisar esta documentação.
- Usar `ConfigEntityBase` para a pergunta e storage customizado para opções/votos.
- Usar API versionada com `POST /api/v1/questions/{question_id}/votes`.
- Exigir autenticação em toda a API e permissões específicas por capacidade.
- Usar status aberto/fechado com nova pergunta fechada.
- Não persistir IP bruto por padrão.
- Centralizar resultados no `VotingResultsService`.

**Saída:** ADRs aprovados, OpenAPI e matriz estáveis.

## Fase 1 — fundação do módulo

- Criar `simple_voting.info.yml`.
- Criar permissões, schema/config schema e services.
- Criar entidade customizada sem `node`.
- Criar instalação/update hooks.

**Saída:** módulo habilitável e schema instalado.

## Fase 2 — administração

- List builder.
- Entity form.
- Formulário de opções.
- Upload seguro.
- Configuração global.
- Status e política de delete.

**Saída validada:** AC-001 a AC-007, com teste funcional executado com sucesso.

## Fase 3 — domínio de votação

- `VotingService`.
- Validação de disponibilidade e pertencimento.
- Lock e unique constraint.
- Exceções de domínio.
- `VotingResultsService` para queries agregadas e cache metadata.
- Logs e cache invalidation.

**Saída validada:** AC-009 a AC-014 e testes unitários/integrados executados com sucesso.

## Fase 4 — CMS

- Listagem CMS para usuários autenticados com permissão.
- Página/formulário de voto.
- Página de resultados.
- Bloco opcional.
- Controle de cache e permissões.

**Saída:** AC-006, AC-007, AC-010, AC-015 a AC-017.

## Fase 5 — API

- Controllers finos.
- Serialização manual conforme OpenAPI.
- Auth/CSRF/access.
- Exception subscriber JSON.
- Collection Postman.

**Saída:** AC-008, AC-009, AC-011, AC-015 a AC-020 e AC-024.

## Fase 6 — robustez

- Kernel/functional/integration tests.
- Query/performance review.
- Observabilidade.
- Security review.
- Atualização de runbook e traceability.

**Saída validada:** checklist operacional completo e quality gates concluídos com sucesso.
