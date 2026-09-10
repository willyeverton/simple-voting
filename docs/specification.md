# Simple Voting — Especificação

## 1. Escopo

Backend Drupal 11 para administração e votação autenticada pelo CMS ou API manual versionada. A lógica central não usa JSON:API nem `node`.

## 2. Modelo obrigatório

- Pergunta: Content Entity customizada, com ID interno e `machine_name` público único, estável e imutável.
- Opção: Content Entity customizada pertencente à pergunta.
- Voto: registro transacional interno e imutável; não é recurso CRUD público.
- Opções podem ser editadas a qualquer momento. Opção com votos não pode ser removida.
- O dump `dump/simple-voting-demo.sql` é entrega obrigatória, restaurável e sanitizada; não inclui secrets, usuários, sessões, votos ou logs.

## 3. Funcionalidade

### Administração

Criar perguntas/opções, definir `show_results`, publicar/fechar perguntas e alternar `voting_enabled`. Opções podem ser editadas a qualquer momento; opção com votos não pode ser removida.

### CMS e API manual

Ambos oferecem catálogo, detalhe, voto e resultados sob as mesmas regras de domínio. O catálogo e o detalhe mostram apenas perguntas publicadas para usuários comuns; administradores com `administer simple voting` veem também perguntas fechadas. Todos os endpoints API exigem autenticação Drupal e `access simple voting API`; voto também exige `vote in polls`, e sessão em escrita exige CSRF.

A API manual expõe:

- `GET /api/v1/questions`;
- `GET /api/v1/questions/{question_id}` (`question_id` contém o `machine_name` público);
- `POST /api/v1/questions/{question_id}/votes`;
- `GET /api/v1/questions/{question_id}/results`.

## 4. Regras de negócio

1. Somente pergunta publicada e votação global ligada recebem voto.
2. Apenas usuário autenticado/autorizado vota.
3. Opção deve existir e pertencer à pergunta.
4. `(question_id, uid)` possui no máximo um voto, garantido por unique constraint.
5. Violação da constraint é `409 DUPLICATE_VOTE`, não erro fatal.
6. Fluxo comum de resultados exige que o usuário já tenha votado **e** `show_results=true`.
7. `view voting results` bypassa voto prévio e `show_results`, sem conceder voto ou administração.
8. Resultados nunca expõem identidade de votantes.
9. Global off bloqueia catálogo, detalhe, voto e resultados no CMS e API. Cada endpoint API responde `503 VOTING_DISABLED`; não há catálogo vazio nem exceção para bypass/admin.
10. Perguntas/opções com histórico não podem ser removidas deixando votos órfãos.

## 5. Concorrência e performance

O hot path não usa lock Drupal. Após validações, tenta o insert; `UNIQUE(question_id, uid)` arbitra requests concorrentes. Transações, quando necessárias, são curtas. A remoção de opções com votos é bloqueada para preservar o histórico; a edição de opções com votos é permitida pois o identificador interno e os votos vinculados se mantêm. Leituras usam agregação centralizada, índices e cache variado por usuário/permissão/configuração.

Garantias honestas: no máximo um voto por usuário/pergunta; usuários diferentes não precisam ser serializados. Não há garantia de fairness, ordenação, disponibilidade sem banco ou rate limiting na aplicação. A Schema API não fornece foreign keys físicas.

## 6. Segurança e observabilidade

Validar input, CSRF, permissões, upload e pertencimento; escapar saída; não logar secrets/payloads/IP bruto; não retornar SQL/stack traces. Canal `simple_voting` registra contexto seguro e `X-Request-ID`. Global off, duplicidade, acesso negado e falha de persistência possuem códigos estáveis.

## 7. Fora do escopo

React, JSON:API para lógica central, voto anônimo/IP, edição de voto, microserviços, event sourcing, rate limiting distribuído e CRUD público de votos.