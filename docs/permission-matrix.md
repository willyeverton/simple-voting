# Matriz de permissões e acesso

A API adota autenticação obrigatória por padrão. A permissão técnica de acesso à API é separada da permissão de registrar votos para aplicar least privilege.

| Capacidade | Anônimo | Usuário autenticado | Admin autorizado | Regra técnica |
|---|---:|---:|---:|---|
| Acessar administração | Não | Não por padrão | Sim | `administer simple voting` |
| Criar/editar pergunta | Não | Não por padrão | Sim | Entity access + route permission |
| Remover pergunta sem votos | Não | Não | Sim | Serviço verifica ausência de votos |
| Encerrar/abrir pergunta | Não | Não | Sim | `administer simple voting` + estado válido |
| Alterar votação global | Não | Não | Sim | Config form + `administer simple voting` |
| Acessar API | Não | Sim | Sim | Autenticação + `access simple voting API` |
| Listar perguntas da API | Não | Sim | Sim | `access simple voting API` |
| Consultar detalhes da API | Não | Sim | Sim | `access simple voting API` |
| Registrar voto na API | Não | Sim | Sim | `access simple voting API` + `vote in polls` + CSRF para sessão |
| Consultar resultados na API | Não | Sim | Sim | `access simple voting API` |
| Consultar resultado oculto | Não | Não por padrão | Sim | `access simple voting API` + `view voting results` |
| Acessar interface CMS de votação | Não | Sim | Sim | `vote in polls` |
| Registrar voto no CMS | Não | Sim | Sim | `vote in polls` + Form API CSRF |
| Consultar resultado no CMS | Não | Sim | Sim | `vote in polls`; resultado oculto mostra confirmação sem números |
| Consultar logs | Não | Não | Operação autorizada | Permissão Drupal de logs/ambiente |

## Regras complementares

1. Permissão de rota não substitui validação no serviço.
2. O controller mapeia autenticação e acesso para HTTP; o serviço protege invariantes.
3. O frontend não concede acesso que o backend negou.
4. Respostas `403` não devem revelar dados além do necessário.
5. Toda requisição de sessão que altera estado exige CSRF; Basic Auth sobre HTTPS segue fluxo stateless documentado.
6. `access simple voting API` não implica permissão para votar.
7. `view voting results` não permite alterar perguntas ou votos.
