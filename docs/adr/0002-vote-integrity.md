# ADR 0002 — Integridade e concorrência dos votos

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

O requisito de um voto por usuário precisa resistir a duplo clique, retry automático, múltiplos workers PHP e requests simultâneos.

## Decisão

Persistir votos em armazenamento transacional com uma constraint única sobre `(question_id, uid)`. Usar o lock backend do Drupal para serializar mutações concorrentes da mesma pergunta, incluindo votação e alterações administrativas que possam remover ou alterar opções. A constraint continua sendo necessária e o sistema nunca depende somente do lock.

O parâmetro de tempo de `LockBackendInterface::acquire()` é a vida útil do lock. A implementação usa uma vida útil de 30 segundos e `wait()` entre tentativas, em vez de interpretar esse parâmetro como tempo de espera. Operações que excedam esse limite devem renovar o lock ou ser redesenhadas para manter a seção crítica curta.

A camada de serviço deve:

1. Validar a pergunta, a opção e a disponibilidade antes da escrita.
2. Adquirir lock com chave determinística quando aplicável.
3. Verificar voto existente.
4. Inserir dentro da fronteira transacional adequada.
5. Converter violação da constraint única em resultado de voto duplicado.
6. Liberar lock em `finally`.
7. Invalidar cache somente após persistência bem-sucedida.

## Consequências

- A constraint continua protegendo múltiplos servidores e caminhos alternativos.
- Testes unitários cobrem decisões do serviço; testes de integração validam o banco real.
- Falhas de lock e constraint precisam de logs e códigos HTTP coerentes.
- Operações administrativas que removem ou alteram opções usam a mesma chave de lock por pergunta do fluxo de voto. Isso serializa mutações concorrentes da mesma pergunta e evita que uma opção seja removida entre sua validação e a persistência do voto.
- A API de schema do Drupal documenta foreign keys, mas não as cria nem as aplica no banco; por isso, a proteção contra referências órfãs depende do lock compartilhado, das transações e da política de não remover opções já utilizadas.
