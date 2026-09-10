# Catálogo de erros e respostas

Mensagens externas são seguras e estáveis; detalhes técnicos ficam no canal `simple_voting`. Todos os erros API podem incluir `X-Request-ID` para correlação.

| Código | HTTP | Situação | Mensagem externa sugerida |
|---|---:|---|---|
| `AUTHENTICATION_REQUIRED` | 401 | Usuário anônimo | Autenticação necessária. |
| `ACCESS_DENIED` | 403 | Sem permissão; resultado antes do voto; ou `show_results=false` sem bypass | Acesso não permitido. |
| `QUESTION_NOT_FOUND` | 404 | `machine_name` público inexistente | Pergunta não encontrada. |
| `OPTION_NOT_FOUND` | 422 | Opção inexistente ou de outra pergunta | Opção inválida para esta pergunta. |
| `QUESTION_CLOSED` | 422 | Pergunta não publicada/fechada | Esta pergunta não está aberta para votação. |
| `OPTION_IN_USE` | 409 | Tentativa de remover opção que já recebeu votos | Esta opção não pode ser removida porque já possui votos. |
| `VOTING_DISABLED` | 503 | Global off em catálogo, detalhe, voto ou resultados | As votações estão temporariamente indisponíveis. |
| `DUPLICATE_VOTE` | 409 | `UNIQUE(question_id, uid)` ou detecção equivalente | Você já registrou um voto nesta pergunta. |
| `INVALID_PAYLOAD` | 400 | JSON ausente/malformado/campos inválidos | Dados da requisição inválidos. |
| `PERSISTENCE_FAILURE` | 500 | Erro inesperado de banco | Não foi possível concluir a operação. |
| `INTERNAL_ERROR` | 500 | Falha não classificada | Ocorreu um erro interno. |

```json
{
  "error": "Você já registrou um voto nesta pergunta.",
  "code": "DUPLICATE_VOTE"
}
```

`LOCK_UNAVAILABLE` não faz parte do hot path planejado: votos não dependem de lock de aplicação. Nunca retornar SQL, stack trace, nomes de tabelas, tokens ou identidade do votante.