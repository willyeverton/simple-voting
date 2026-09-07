# ADR 0009 — Privacidade dos metadados do voto

- **Status:** aceito
- **Data:** 2026-09-07

## Contexto

O desafio exige unicidade por usuário, mas não exige armazenamento de IP. IP bruto é dado pessoal e aumenta a superfície de privacidade sem ser necessário para a regra de negócio.

## Decisão

Não persistir IP bruto no registro de voto. A identidade para unicidade será exclusivamente o `uid` autenticado e a pergunta. Proteção contra abuso deve ser tratada por infraestrutura, rate limiting ou mecanismo separado.

Se uma necessidade futura exigir correlação de abuso, ela deverá ser aprovada em novo ADR e usar metadado anonimizado/hasheado, retenção limitada, finalidade documentada e controles de acesso.

## Consequências

- Menor coleta de dados pessoais.
- O banco de votos não contém IP identificável por padrão.
- Logs não devem reintroduzir IP ou dados sensíveis sem necessidade.
- O mecanismo de rate limiting não faz parte do módulo inicial.
