# Controles de segurança e threat model

## Ativos e fronteiras

Protegem-se integridade/segredo da contagem, identidade e autorização Drupal, Content Entities de pergunta/opção, votos internos, uploads, configuração global, logs e credenciais. CMS e API manual são fronteiras não confiáveis; todo input é validado server-side.

| Ameaça | Controle | Limitação residual |
|---|---|---|
| Voto duplicado por retry/concorrência | `UNIQUE(question_id, uid)` e tradução para `409 DUPLICATE_VOTE`; hot path sem locks | Disponibilidade do banco continua necessária |
| Troca de opção entre perguntas | Validação conjunta por IDs internos antes do insert | Sem foreign keys físicas na Schema API |
| Alteração histórica de alternativa | Opção com votos pode ser editada, mas não removida; alterações em opções sem votos são permitidas | Correções textuais são possíveis mesmo após votos; a remoção continua bloqueada; nova composição exige nova pergunta/novo `machine_name` |
| Rename/quebra de integrações | `machine_name` público único e imutável | Migração de identificador não é suportada |
| Vazamento de resultados | Fluxo comum exige voto prévio e `show_results=true`; bypass somente por `view voting results`; cache varia por usuário/permissão | Admin com bypass vê resultados por definição |
| Bypass do global off | Gate comum bloqueia catálogo, detalhe, voto e resultados; API `503` | Não substitui indisponibilidade de infraestrutura |
| CSRF | Form API no CMS; cookie + POST API exige `X-CSRF-Token` | Basic Auth somente sobre HTTPS |
| XSS/upload malicioso | Escape/filter de saída e validação de extensão, tamanho, MIME, destino e file usage | Scanner antimalware depende da infraestrutura |
| Enumeração | Autenticação/permissões e respostas mínimas | `503` revela apenas indisponibilidade global |
| SQL injection | Database API/Query Builder com parâmetros | Queries novas exigem revisão |
| Abuso/carga | Índices e agregações | Rate limiting, quotas, fairness e proteção DDoS são externos |
| Segredos/dados pessoais no dump | Dump obrigatório sanitizado, sem usuários, sessões, votos, tokens ou logs | Binários e restore precisam validação separada |

## Logging

Usar `simple_voting` e contexto mínimo: UID quando necessário, `machine_name`, endpoint, código e `X-Request-ID` validado. Nunca registrar senha, Basic Auth, CSRF token, payload completo, IP bruto, SQL ou objeto integral da exceção.

## Concorrência

A garantia é **no máximo um voto por usuário/pergunta**, decidida pelo banco. Não se promete ordenação global, fairness, ausência de latência nem funcionamento sem banco. Usuários diferentes não devem ser serializados. Teste sequencial não comprova concorrência; a evidência requer requests simultâneos contra MySQL real.

## Dependências

Manter lock file e auditoria Composer sem ignorar advisories ou requisitos de plataforma.