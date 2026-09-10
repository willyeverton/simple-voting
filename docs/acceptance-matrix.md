# Matriz de aceite

Esta matriz separa requisito, comportamento esperado e evidência necessária. Documentação não substitui implementação nem execução pelo mantenedor.

| ID | Requisito explícito | Critério de aceite | Evidência requerida |
|---|---|---|---|
| AC-01 | Pergunta customizada | `VotingQuestion` é Content Entity customizada; não `node`/Config Entity | Definição da entidade + teste Kernel |
| AC-02 | Opção customizada | `VotingOption` é Content Entity customizada ligada à pergunta | Definição, schema e teste Kernel |
| AC-03 | `machine_name` público estável | API/URLs resolvem por valor único; rename sempre rejeitado | Functional/API + tentativa administrativa de rename |
| AC-04 | Votos internos | Voto existe somente em tabela/storage interno, sem CRUD/entity/API pública | Inspeção de schema/rotas |
| AC-05 | Proteção de opções em uso | Opção pode ser editada a qualquer momento; opção com votos não pode ser removida | Testes por remoção e mutação com/sem votos |
| AC-06 | Resultado comum pós-voto | Sem bypass: antes do voto `403`; após voto `200` apenas com `show_results=true` | Matriz CMS/API por usuário |
| AC-07 | Bypass | `view voting results` retorna resultados antes do voto e com `show_results=false` | Testes de acesso CMS/API |
| AC-08 | Global off CMS | Catálogo, detalhe, voto e resultados ficam bloqueados, inclusive bypass/admin funcional | Functional tests das quatro superfícies |
| AC-09 | Global off API | Cada um dos quatro endpoints retorna `503` e `code=VOTING_DISABLED` | OpenAPI, Postman e Functional tests |
| AC-10 | API manual | Somente rotas manuais versionadas implementam o contrato; JSON:API não contém lógica central | Inspeção de rotas/controllers |
| AC-11 | Hot path sem locks | Voto não adquire lock; insert e `UNIQUE(question_id, uid)` arbitram duplicidade | Inspeção estática + instrumentação concorrente |
| AC-12 | Concorrência mesmo UID | Dois requests simultâneos: um sucesso, um `409`, uma linha | Log sanitizado e query de contagem em MySQL real |
| AC-13 | Concorrência UIDs distintos | Requests simultâneos de usuários distintos persistem sem serialização por pergunta | Evidência temporal + duas linhas |
| AC-14 | Dump obrigatório | `dump/simple-voting-demo.sql` é regenerado após a mudança para Content Entities, restaura em ambiente limpo, inclui schema/entidades demo e zero votos/secrets/dados pessoais | Diff SQL revisado, restore documentado e queries sanitizadas; dump anterior não aceita |
| AC-15 | Limitações honestas | Documentação declara ausência de FK física, rate limiting/fairness externos, dependência do banco e limite da evidência sequencial | Revisão de README, arquitetura, threat model, runbook e verificação |
| AC-16 | Erros seguros | `401/403/404/409/422/500/503` seguem catálogo, sem SQL/stack/secrets; correlação segura | Functional tests e amostra sanitizada de logs |
| AC-17 | Contratos alinhados | OpenAPI declara `503` em todos endpoints e pós-voto; Postman testa `VOTING_DISABLED` sem secrets | Revisão estática + execução pelo mantenedor |

## Regra de conclusão

Um item só está aceito com evidência produzida sobre o mesmo commit. Postman sequencial não aceita AC-12/AC-13; documentação isolada não aceita requisitos de implementação. Nenhuma evidência de validação foi produzida nesta atualização.