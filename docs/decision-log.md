# Registro resumido de decisões

| Decisão | Escolha atual | Referência |
|---|---|---|
| Entidade da pergunta | `ConfigEntityBase`, sem `node` | ADR-0001 |
| Armazenamento de opções | Tabela customizada vinculada à pergunta | ADR-0005 |
| Armazenamento de votos | Tabela transacional com unique constraint | ADR-0002 |
| Rota de voto | `POST /api/v1/questions/{question_id}/votes` | ADR-0006 |
| API | Manual, versionada, sem JSON:API para a lógica central | ADR-0003/0006 |
| Autenticação API | Obrigatória em todos os endpoints | ADR-0003 |
| Permissão API | `access simple voting API` | ADR-0003 |
| Permissão para votar | `vote in polls` | ADR-0003 |
| Resultado oculto | Exige `view voting results` | ADR-0007 |
| Lifecycle | Aberta/fechada; nova pergunta fechada | ADR-0007 |
| Exclusão | Sem hard delete após votos por padrão | ADR-0007 |
| Resultados | `VotingResultsService` centralizado | ADR-0008 |
| IP | Não persistir IP bruto por padrão | ADR-0009 |
| Discoverability | API lista abertas; CMS pode exibir abertas e fechadas sem liberar voto | ADR-0011 |
| Testes | Unit + Kernel + Functional + Integration concorrente | ADR-0010 |
| Qualidade | Lando, Composer audit, PHPCS, PHPStan, PHPUnit e CI | ADR-0004 |
