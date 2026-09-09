# Modelo de domínio e persistência

## Agregados

### VotingQuestion

Representa a definição administrativa da pergunta.

| Campo | Tipo lógico | Regras |
|---|---|---|
| `id` | string | Machine name único, estável e imutável após criação |
| `uuid` | UUID | Identidade técnica Drupal |
| `title` | string | Obrigatório, escapado na saída |
| `status` | bool | Lifecycle obrigatório: aberta ou fechada; nova pergunta inicia fechada |
| `show_results` | bool | Controla exposição pública dos resultados |
| `created` | timestamp | Definido no primeiro save |
| `changed` | timestamp | Atualizado a cada alteração |

A entidade não deve ser um `node`.

### VotingOption

Opção pertencente a uma pergunta. Não é um agregado independente para a API pública.

| Campo | Tipo lógico | Regras |
|---|---|---|
| `id` | inteiro | Chave primária |
| `question_id` | string | Referência obrigatória à pergunta |
| `title` | string | Obrigatório |
| `description` | texto | Opcional; texto filtrado/escapado |
| `image_fid` | inteiro/null | Arquivo validado e com uso registrado |
| `weight` | inteiro | Ordenação determinística |

### VotingRecord

Registro transacional imutável do voto.

| Campo | Tipo lógico | Regras |
|---|---|---|
| `id` | inteiro | Chave primária |
| `question_id` | string | Pergunta votada |
| `option_id` | inteiro | Opção escolhida |
| `uid` | inteiro | Usuário Drupal autenticado |
| `timestamp` | timestamp | Momento do registro |
| `ip_hash` | string/null | Não armazenado por padrão; somente em futura política antiabuso aprovada e com retenção definida |

## Constraints e índices

```text
UNIQUE(question_id, uid)
INDEX(question_id, option_id)
INDEX(uid)
INDEX(question_id, weight) em opções
```

A aplicação deve validar referências de pergunta/opção antes do insert. A constraint de unicidade é a autoridade final contra corrida. Como a Schema API do Drupal não aplica foreign keys físicas para tabelas customizadas, voto, sincronização de opções e exclusão usam a mesma chave de lock por pergunta. Consultas de contagem devem ser centralizadas no `VotingResultsService` para evitar divergência entre CMS, bloco e API.

## Política de exclusão

- Pergunta sem votos pode ser removida com suas opções.
- Pergunta com votos deve ser encerrada/arquivada por padrão.
- Se hard delete for exigido, a operação deve usar transação e política explícita de cascade/auditoria.
- Nunca remover uma pergunta deixando votos órfãos.

## Cache

Tags mínimas sugeridas:

```text
config:simple_voting.settings
config:simple_voting.question.{id}
config:voting_question_list
simple_voting:question:{id}
simple_voting:question-list
```

`config:voting_question_list` é a tag nativa da entidade de configuração e deve ser usada junto da tag customizada para cobrir alterações diretas e importações de configuração.

Contexts relevantes:

```text
user
user.permissions
url
```

Resultados não devem ser cacheados de modo que um usuário sem permissão receba dados de outro usuário.
