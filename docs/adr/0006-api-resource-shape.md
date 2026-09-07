# ADR 0006 — Formato das rotas da API

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

O desafio exige API manual, mas não define nomes de rotas. O contrato precisa ser estável para Postman e futuros clientes headless.

## Decisão

Usar o identificador da pergunta como recurso da URL:

```text
GET  /api/v1/questions
GET  /api/v1/questions/{question_id}
POST /api/v1/questions/{question_id}/votes
GET  /api/v1/questions/{question_id}/results
```

O payload de voto contém somente `option_id`, pois `question_id` já está no path. Todos os endpoints retornam JSON e usam envelopes `data` para leitura e `error`/`message` para resultados de comando.

## Consequências

- Evita duplicar a identidade da pergunta no body.
- Facilita autorização e validação de pertencimento.
- Uma mudança de rota exige atualização simultânea do OpenAPI, Postman e testes.
