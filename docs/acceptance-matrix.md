# Simple Voting — Matriz de aceitação

Cada requisito deve possuir evidência em teste automatizado, teste manual documentado ou ambos.

| ID | Cenário | Resultado esperado | Evidência planejada |
|---|---|---|---|
| AC-001 | Administrador cria pergunta com identificador único | Pergunta é persistida e aparece na listagem administrativa | Functional test |
| AC-002 | Identificador já existente | Formulário rejeita a duplicidade sem alterar dados | Functional test |
| AC-003 | Administrador cadastra várias opções | Todas as opções são persistidas na ordem definida | Kernel/functional test |
| AC-004 | Opção possui título, descrição e imagem válida | Dados são exibidos com saída segura | Functional test |
| AC-005 | Upload inválido | Extensão/tamanho são rejeitados | Functional test |
| AC-006 | Pergunta é encerrada | Interface e API recusam novos votos | API/functional test |
| AC-007 | Votação global é desabilitada | CMS e API recusam novos votos | API/functional test |
| AC-008 | Usuário autenticado consulta pergunta aberta | Pergunta e opções são retornadas | API test |
| AC-009 | Cliente envia opção de outra pergunta | Request é rejeitado e nenhum voto é persistido | Unit/API test |
| AC-010 | Usuário registra primeiro voto | Voto é criado e resposta indica sucesso | Unit/API test |
| AC-011 | Usuário tenta votar novamente | Nenhum segundo registro é criado e resposta é `409` | Unit/API test |
| AC-012 | Dois requests concorrentes chegam juntos | No máximo um voto existe para `(question_id, uid)` | Integration test |
| AC-013 | Lock falha | Operação não escreve parcialmente e retorna erro transitório seguro | Unit/API test |
| AC-014 | Insert viola unique constraint | Violação vira resultado de voto duplicado, sem stack trace | Unit/integration test |
| AC-015 | Resultados estão habilitados | Usuário autorizado recebe contagens e percentuais | API/functional test |
| AC-016 | Resultados estão ocultos | Usuário comum não recebe dados de resultado | API/functional test |
| AC-017 | Administrador consulta resultado oculto | Permissão específica libera o resultado | API/functional test |
| AC-018 | Usuário anônimo tenta votar | Request é bloqueado com resposta de autenticação adequada | API/functional test |
| AC-019 | Descrição contém markup não permitido | Saída é filtrada ou escapada | Security test |
| AC-020 | Falha inesperada no banco | Cliente recebe erro genérico e log contém contexto seguro | Unit/API test |
| AC-021 | Novo voto é persistido | Cache da pergunta/resultados é invalidado | Kernel/unit test |
| AC-022 | Composer ou código viola padrão | Quality gate falha com diagnóstico acionável | CI/Lando |
| AC-023 | Dependência possui advisory | `composer audit` falha ou exige decisão explícita | CI |
| AC-024 | API é consumida pelo Postman | Collection reproduz os cenários documentados | Postman/manual |
