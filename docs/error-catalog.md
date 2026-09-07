# Catálogo de erros e respostas

A mensagem externa deve ser segura e estável; detalhes técnicos ficam no log dedicado.

| Código de domínio | HTTP | Situação | Mensagem externa sugerida |
|---|---:|---|---|
| `AUTHENTICATION_REQUIRED` | 401 | Usuário anônimo | Autenticação necessária. |
| `ACCESS_DENIED` | 403 | Sem permissão ou resultado oculto | Acesso não permitido. |
| `QUESTION_NOT_FOUND` | 404 | ID inexistente | Pergunta não encontrada. |
| `OPTION_NOT_FOUND` | 422 | Opção inexistente | Opção inválida para esta pergunta. |
| `QUESTION_CLOSED` | 422 | Pergunta fechada | Esta pergunta não está aberta para votação. |
| `VOTING_DISABLED` | 503 | Votação global desabilitada | As votações estão temporariamente indisponíveis. |
| `DUPLICATE_VOTE` | 409 | Usuário já votou | Você já registrou um voto nesta pergunta. |
| `LOCK_UNAVAILABLE` | 503 | Lock não obtido após política de retry | Tente novamente em instantes. |
| `INVALID_PAYLOAD` | 400 | JSON ausente/malformado/campos inválidos | Dados da requisição inválidos. |
| `PERSISTENCE_FAILURE` | 500 | Erro inesperado de banco | Não foi possível concluir a operação. |
| `INTERNAL_ERROR` | 500 | Falha não classificada | Ocorreu um erro interno. |

## Formato mínimo

```json
{
  "error": "Você já registrou um voto nesta pergunta.",
  "code": "DUPLICATE_VOTE"
}
```

Não retornar stack trace, SQL, nomes de tabelas, tokens ou dados do votante.
