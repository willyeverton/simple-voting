# Matriz de permissões e acesso

A API manual exige autenticação e `access simple voting API`. A configuração global é um gate anterior à exposição funcional: desligada, bloqueia catálogo, detalhe, voto e resultados no CMS e retorna `503 VOTING_DISABLED` nos quatro endpoints API.

| Capacidade | Anônimo | Autenticado comum | Com `view voting results` | Admin | Regra técnica |
|---|---:|---:|---:|---:|---|
| Administrar perguntas/opções | Não | Não | Não | Sim | `administer simple voting`; administrador pode editar perguntas/opções; edição e remoção de opções com votos são bloqueadas |
| Alterar global off | Não | Não | Não | Sim | `administer simple voting` |
| Listar/detalhar via API, global on | Não | Sim | Sim | Sim | `access simple voting API` |
| Listar/detalhar via API, global off | Não | Não (`503`) | Não (`503`) | Não (`503`) | Global off prevalece inclusive para bypass/admin |
| Votar via API, global on | Não | Sim | Sim | Sim | `access simple voting API` + `vote in polls`; CSRF para sessão |
| Votar via CMS, global on | Não | Sim | Sim | Sim | `vote in polls` + Form API CSRF |
| Ver resultados comuns | Não | Após votar, se `show_results=true` | Sim | Conforme permissões | API também exige `access simple voting API`; CMS exige acesso à votação |
| Ver antes de votar | Não | Não | Sim | Somente com bypass | `view voting results` |
| Ver quando `show_results=false` | Não | Não | Sim | Somente com bypass | `view voting results` |
| Ver qualquer superfície, global off | Não | Não | Não | Não | CMS bloqueado; API `503 VOTING_DISABLED` |
| Alterar/remover voto | Não | Não | Não | Não | Votos internos e imutáveis; sem CRUD público |

## Ordem de decisão

1. Autenticação e entrada técnica da rota continuam obrigatórias.
2. O gate global impede catálogo, detalhe, voto e resultados; não retorna catálogo vazio.
3. Permissões específicas são avaliadas sem inferência entre elas.
4. Para resultados, `view voting results` bypassa voto prévio e `show_results`; sem bypass, ambas as condições são obrigatórias.
5. `view voting results` não permite administrar entidades nem alterar votos.
6. Respostas `403` não revelam contagens, opção vencedora ou se outro usuário votou.