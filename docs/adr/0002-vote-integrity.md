# ADR 0002 — Integridade e concorrência dos votos

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

O requisito de um voto por usuário precisa resistir a duplo clique, retry automático, múltiplos workers PHP e requests simultâneos.

## Decisão

Persistir votos em armazenamento transacional com uma constraint única sobre `(question_id, uid)`. Usar o lock backend do Drupal para serializar requests concorrentes da mesma combinação quando isso melhorar a experiência e reduzir corridas previsíveis, mas nunca depender somente do lock.

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
