# ADR 0005 — Armazenamento das opções

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

As opções possuem título, descrição, imagem e ordem. Elas são editadas junto com a pergunta e precisam ser consultadas para votação e resultados. Não há requisito de workflow, revisão ou endpoint independente para opções.

## Decisão

Persistir opções em tabela customizada vinculada à pergunta, com schema explícito, índice de ordenação e referência opcional para arquivo Drupal. Não serializar as opções em um campo arbitrário e não criar uma entidade independente sem necessidade de negócio.

A camada de formulário sincroniza as opções submetidas dentro de uma operação controlada. A camada de serviço e as queries validam `question_id` junto do `option_id`.

A operação de banco e os efeitos da File API não formam uma transação única: alterações em `file_usage` e no estado do arquivo podem exigir compensação quando ocorre falha após o início da sincronização. Essa limitação deve ser considerada antes de uso produtivo.

## Consequências

- Menor overhead para o fluxo de votação e agregação.
- Schema e update hooks precisam ser mantidos manualmente.
- A tabela exige política de limpeza quando uma pergunta sem votos for removida.
- Se opções ganharem workflow, tradução, revisão ou API própria, reavaliar como `ContentEntityBase` em novo ADR.
