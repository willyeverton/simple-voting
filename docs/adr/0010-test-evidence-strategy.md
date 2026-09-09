# ADR 0010 — Estratégia de evidência de testes

- **Status:** aceito e validado
- **Data:** 2026-09-07

## Contexto

A unicidade do voto combina lógica de domínio e integridade do banco. Mocks comprovam decisões do serviço, mas não comprovam concorrência real, schema ou comportamento de rotas Drupal.

## Decisão

Adotar uma pirâmide mínima:

- Unit tests para Services, políticas, serialização e exceções.
- Kernel tests para entidade, schema, queries, configuração e cache.
- Functional tests para permissões, formulários, CMS e API.
- Pelo menos um teste de integração com banco real para a constraint `(question_id, uid)` e concorrência.

O usuário executará os comandos de teste e validação. O agente somente criará testes, recomendará comandos e interpretará output fornecido.

## Estado atual

Os testes Unit, Kernel, Functional e as verificações de integração com banco real previstas nesta decisão foram executados com sucesso pelo mantenedor em 2026-09-09.

## Consequências

- O happy path não é a única evidência de qualidade.
- A constraint e o tratamento da corrida são validados no ambiente correto.
- A matriz de aceitação deve apontar para testes específicos.
- A entrega atual possui evidência Unit, Kernel, Functional e Integration; futuras alterações devem preservar essa pirâmide.
