# Checklist de entrega do desafio

Este checklist registra o estado validado da entrega. A execução dos gates, testes, cenários manuais, collection Postman, restauração do dump e verificações de integração foi confirmada pelo mantenedor em **2026-09-09**, com resultado bem-sucedido.

Legenda:

- `[x]` implementado, validado ou formalmente aceito como decisão de escopo.

## Repositório e ambiente

- [x] Projeto inicializado com Git.
- [x] Remote GitHub configurado.
- [x] Lando configurado.
- [x] Drupal 11 via Composer.
- [x] PHP 8.5 e MySQL 8.4 declarados no Lando.
- [x] Drush declarado no projeto.
- [x] Dump SQL de demonstração preparado e restaurado em ambiente limpo.
- [x] Instalação limpa reproduzida e verificada.

## Documentação

- [x] Brief original preservado.
- [x] Especificação funcional.
- [x] Requisitos não funcionais.
- [x] Modelo de domínio.
- [x] Matriz de permissões.
- [x] Fluxos do sistema.
- [x] OpenAPI.
- [x] Collection Postman validada.
- [x] Catálogo de erros.
- [x] Threat model.
- [x] Plano de testes automatizados.
- [x] Plano de testes manuais.
- [x] Matriz de rastreabilidade.
- [x] ADRs.
- [x] README de onboarding.
- [x] Documento de arquitetura e limitações conhecidas.
- [x] Runbook.
- [x] Checklist operacional.

## Implementação presente e validada

- [x] Módulo customizado habilitável.
- [x] Entidade customizada sem `node`.
- [x] CRUD administrativo.
- [x] Opções com descrição, imagem e ordenação pela sequência submetida.
- [x] Configuração global.
- [x] Service de votação.
- [x] Constraint de voto único no schema.
- [x] Lock de aplicação e transações.
- [x] Concorrência entre votos validada em banco real.
- [x] Concorrência entre voto e mutação administrativa validada.
- [x] Interface CMS.
- [x] API manual.
- [x] Resultados e política de visibilidade.
- [x] Canal de log e mapeamento de erros.
- [x] Cache tags e invalidação.
- [x] Tema global customizado sem React.
- [x] Shell público com login, menus, mensagens e regiões.
- [x] Ativação e restauração do tema frontend validadas sem alteração indevida do tema administrativo.
- [x] Upload e uso de arquivos validados nos fluxos de sucesso e falha.
- [x] Uninstall protegido pelo validator e procedimento destrutivo verificado.

## Evidências da entrega

- [x] `composer validate --strict` passou.
- [x] `composer audit` passou sem advisory impeditivo.
- [x] PHPCS passou.
- [x] PHPStan passou.
- [x] PHPUnit Unit passou.
- [x] Testes Kernel passaram.
- [x] Testes Functional passaram.
- [x] Testes de tema, menus e permissões passaram.
- [x] Teste de voto bem-sucedido através da API passou.
- [x] Teste de voto duplicado retornando `409` passou.
- [x] Teste de opção pertencente a outra pergunta passou.
- [x] Testes de pergunta fechada e votação global desabilitada passaram.
- [x] Testes de resultados públicos e ocultos passaram.
- [x] Testes de autenticação e CSRF passaram.
- [x] Testes de upload, AJAX, bloco e formulário administrativo passaram.
- [x] Teste de concorrência com banco real passou.
- [x] Teste de concorrência entre voto e alteração de opção passou.
- [x] Testes de cache, privacidade e ausência de IP bruto passaram.
- [x] Testes de cleanup de arquivos e `file_usage` passaram.
- [x] Dump restaurado em ambiente limpo.
- [x] Collection Postman executada.
- [x] Cenários de erro `401`, `403`, `409`, `422`, `500` e `503` validados.
- [x] Revisão de segurança e arquitetura concluída sem findings bloqueadores.

## Limitações de desenho aceitas

- [x] A File API possui efeitos próprios fora do rollback nativo da transação de banco; os fluxos de falha e compensação foram validados e essa fronteira está documentada.
- [x] O workflow GitHub Actions executa gates estáticos e Unit; as suítes dependentes de banco e ambiente Drupal foram executadas no Lando.
- [x] O rate limiting permanece responsabilidade da infraestrutura, conforme o escopo definido no threat model.

Essas notas são decisões de escopo e operação, não pendências da entrega validada.

## Critério de entrega

A entrega está concluída para o escopo atual. Qualquer mudança futura em regras de votação, persistência, API, permissões, uploads, cache, tema ou infraestrutura deve atualizar os testes, a matriz de aceitação, a rastreabilidade e este checklist.
