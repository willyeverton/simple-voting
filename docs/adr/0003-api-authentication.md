# ADR 0003 — Autenticação e contrato da API

- **Status:** proposto
- **Data:** 2026-09-06

## Contexto

A API será consumida por uma aplicação externa, mas a regra de unicidade é definida por usuário. O desafio exige uma implementação manual e segura, sem JSON:API.

## Decisão

Usar autenticação Drupal para identificar o usuário. Permitir Basic Auth sobre HTTPS para clientes externos e sessão Drupal para clientes no mesmo contexto. Requests de sessão que alteram estado devem exigir `X-CSRF-Token`. O contrato inicial está em `docs/openapi.yaml` e deve ser mantido alinhado à collection Postman e aos testes de contrato.

A API deve retornar JSON consistente, códigos HTTP semânticos e mensagens externas que não revelem detalhes internos.

## Consequências

- O cliente externo não pode votar anonimamente por IP se a regra continuar sendo por usuário.
- CORS, cookies e headers precisam ser configurados somente quando o frontend estiver em origem separada.
- A autenticação e o CSRF devem ser cobertos por testes funcionais.
