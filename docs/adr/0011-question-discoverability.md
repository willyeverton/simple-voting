# ADR 0011 — Discoverability e elegibilidade de perguntas

- **Status:** aceito
- **Data:** 2026-09-07

## Contexto

O brief original define “perguntas disponíveis” para a API, mas não define se uma pergunta encerrada deve ser tratada como inexistente ou permanecer visível para consulta. O lifecycle aberta/fechada foi adotado posteriormente, e a referência apresenta comportamentos diferentes no catálogo da API e na interface CMS.

## Decisão

Separar visibilidade histórica de elegibilidade para voto:

- `GET /api/v1/questions` retorna somente perguntas abertas quando a votação global está habilitada; quando `voting_enabled` está desabilitado, retorna um catálogo vazio;
- `GET /api/v1/questions/{question_id}` pode retornar uma pergunta conhecida fechada, com `status: closed`, sem permitir mutação;
- a listagem CMS pode mostrar perguntas abertas e fechadas, sempre marcando as fechadas e sem renderizar controles de voto;
- resultados continuam protegidos por `show_results` e pela permissão `view voting results`;
- `voting_enabled` bloqueia escritas no CMS e na API e oculta o catálogo de novas votações no CMS e na API, mas não apaga nem oculta automaticamente resultados históricos autorizados;
- `VotingService` é a autoridade final para rejeitar votos em perguntas fechadas ou quando a votação global está desabilitada.

## Consequências

- clientes externos não precisam tratar pergunta fechada como recurso inexistente;
- o CMS conserva transparência e acesso histórico sem enfraquecer a regra de escrita;
- as diferenças entre catálogo da API e listagem CMS ficam explícitas e testáveis;
- cache, OpenAPI, Postman e testes devem refletir os dois conceitos separadamente.
