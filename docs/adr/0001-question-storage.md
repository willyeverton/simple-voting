# ADR 0001 — Armazenamento da definição da pergunta

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

O desafio exige perguntas identificadas por um identificador único e proíbe representar o domínio com entidades `node`. Perguntas são gerenciadas pelo administrador e referenciadas pela API.

## Decisão

Usar uma entidade customizada Drupal para a definição da pergunta. A primeira implementação deve usar `ConfigEntityBase` como opção pragmática para machine name estável e exportabilidade via Configuration Management. Opções e votos não devem ser serializados; o armazenamento das opções está definido no ADR 0005 e os votos permanecem dados transacionais.

A decisão deve ser revisitada se surgirem requisitos de revisão, tradução, workflow editorial ou grande volume de alterações em produção, casos em que `ContentEntityBase` pode ser mais adequado.

## Consequências

- O domínio não depende de `node`.
- O identificador pode ser estável para rotas e API.
- A definição da pergunta pode ser separada dos dados transacionais de voto.
- A escolha precisa ser refletida em schema, cache tags, update hooks e testes de integração.
