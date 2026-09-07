# ADR 0003 — Autenticação e contrato da API

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

A API será consumida por uma aplicação externa, mas a regra de unicidade é definida por usuário. O desafio exige uma implementação manual e segura, sem JSON:API.

## Decisão

Exigir autenticação Drupal em todos os endpoints da API para reduzir enumeração, manter uma política uniforme de identidade e permitir autorização consistente. Usar a permissão `access simple voting API` para acesso à API, `vote in polls` para registro de voto e `view voting results` adicionalmente para resultados ocultos. Permitir Basic Auth sobre HTTPS para clientes externos e sessão Drupal para clientes no mesmo contexto. Requests de sessão que alteram estado devem exigir `X-CSRF-Token`. O contrato está em `docs/openapi.yaml` e deve permanecer alinhado à collection Postman e aos testes de contrato.

A API deve retornar JSON consistente, códigos HTTP semânticos e mensagens externas que não revelem detalhes internos.

## Consequências

- O cliente externo não pode votar anonimamente por IP se a regra continuar sendo por usuário.
- A leitura da API exige uma permissão própria e não concede autorização para votar.
- CORS, cookies e headers precisam ser configurados somente quando o frontend estiver em origem separada.
- A autenticação e o CSRF devem ser cobertos por testes funcionais.
