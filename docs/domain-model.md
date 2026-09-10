# Modelo de domínio e persistência

## Agregados

### VotingQuestion

Pergunta é uma **Content Entity customizada**, nunca `node` nem Config Entity. Conteúdo editorial e dados runtime não são promovidos pelo Configuration Management.

| Campo | Tipo lógico | Regras |
|---|---|---|
| `id` | inteiro | Chave interna da entidade |
| `uuid` | UUID | Identidade técnica Drupal |
| `machine_name` | string | Identificador público único, estável e imutável após criação; usado em URLs e API |
| `title` | string | Obrigatório, escapado na saída |
| `status` | bool | Fechada ou publicada/aberta; nova pergunta inicia fechada |
| `show_results` | bool | Autoriza resultados comuns somente depois que o usuário votou |
| `created` / `changed` | timestamp | Auditoria editorial |

`machine_name` é contrato público e não deve ser confundido com o ID interno. Rename não é suportado, inclusive após publicação ou integração.

### VotingOption

Opção é uma **Content Entity customizada**, filha da pergunta e não um recurso independente da API pública.

| Campo | Tipo lógico | Regras |
|---|---|---|
| `id` | inteiro | Chave interna e identificador público da opção no payload de voto |
| `question_id` | inteiro | Referência obrigatória à entidade de pergunta |
| `title` | string | Obrigatório |
| `description` | texto | Opcional; saída filtrada/escapada |
| `image_fid` | inteiro/null | Arquivo validado e com uso registrado |
| `weight` | inteiro | Ordenação determinística |

Opções podem ser adicionadas, editadas, reordenadas ou removidas. Uma opção com votos pode ser editada, mas não removida, para preservar o histórico. Uma nova composição de alternativas também pode ser criada como nova pergunta com novo `machine_name`.

### VotingRecord

Voto é dado transacional interno, imutável, armazenado em tabela própria e não exposto como Content Entity ou recurso CRUD público.

| Campo | Tipo lógico | Regras |
|---|---|---|
| `id` | inteiro | Chave primária interna |
| `question_id` | inteiro | ID interno da pergunta |
| `option_id` | inteiro | ID interno da opção escolhida |
| `uid` | inteiro | Usuário Drupal autenticado |
| `timestamp` | timestamp | Momento do registro |

Não se armazena IP bruto. Identidade de votantes nunca integra resultados públicos.

## Constraints e índices

```text
UNIQUE(question_id, uid)
INDEX(question_id, option_id)
INDEX(uid)
UNIQUE(voting_question.machine_name)
```

O hot path de voto não usa lock de aplicação: valida votação global, pergunta publicada, opção pertencente e autorização; tenta o `INSERT`; e traduz violação de `UNIQUE(question_id, uid)` para `DUPLICATE_VOTE`. A constraint é a autoridade sob duplo clique, retry e workers concorrentes. Transação curta pode delimitar a gravação, mas não substitui a constraint nem serializa usuários distintos.

A ausência de foreign keys físicas na Schema API exige validação de referências e políticas de lifecycle. A edição de opções com votos é permitida e preserva o identificador interno; a remoção de opções com votos é bloqueada para preservar o histórico. Consultas de contagem devem ser centralizadas no serviço de resultados.

## Política de resultados

- Fluxo comum: `show_results=true` **e** o usuário autenticado já votou na pergunta.
- Bypass: `view voting results` permite consultar antes de votar e também quando `show_results=false`.
- Sem uma dessas condições, CMS/API respondem acesso negado sem revelar contagens.
- O estado global desligado precede catálogo, detalhe, voto e resultados: CMS bloqueia as páginas e a API responde `503 VOTING_DISABLED`.

## Política de exclusão

- Pergunta e opções podem ser editadas a qualquer momento.
- Opção com votos pode ser editada, mas não removida.
- Pergunta com votos deve ser fechada para novas votações; hard delete é bloqueado por padrão.
- Nunca remover pergunta ou opção deixando voto órfão.

## Cache

Tags mínimas: configuração global, lista de perguntas, entidade de pergunta e suas opções. Respostas variam por usuário, permissões e estado de voto; o global off deve invalidar catálogo, detalhe e resultados. Cache compartilhado não pode revelar resultados a quem ainda não votou.