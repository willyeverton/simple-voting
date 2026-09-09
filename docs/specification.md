# Simple Voting — Especificação do sistema

**Status:** especificação vigente para a implementação e validação
**Fonte:** desafio técnico de Sistema de Votação Simples
**Escopo atual:** módulo customizado implementado e validado pelo mantenedor em 2026-09-09; esta documentação descreve o comportamento entregue e os critérios que devem ser preservados em futuras alterações.

## 1. Objetivo

Construir um backend Drupal 11 para que administradores cadastrem perguntas com opções de resposta e usuários autenticados votem uma única vez por pergunta, tanto pela interface do Drupal quanto por uma API manual para aplicações externas.

A solução deve ser segura, modular, observável, testável e capaz de preservar a integridade dos votos sob concorrência.

## 2. Atores

- **Administrador:** gerencia perguntas, opções, status e configuração global.
- **Usuário:** consulta perguntas disponíveis; usuário autenticado autorizado registra votos.
- **Cliente externo:** consome a API com autenticação Drupal obrigatória e permissões específicas.
- **Operação:** consulta logs e executa verificações do sistema.

## 3. Escopo funcional

### 3.1 Administração

- Criar, editar e remover ou desativar perguntas.
- Identificar cada pergunta por um identificador único e estável.
- Cadastrar várias opções por pergunta.
- Cada opção pode possuir título, descrição breve e imagem.
- Configurar se os resultados da pergunta serão exibidos após o voto.
- Abrir ou encerrar uma pergunta.
- Habilitar ou desabilitar a votação globalmente.
- Controlar operações por permissões Drupal.

### 3.2 Interface Drupal

- Listar perguntas disponíveis.
- Acessar uma pergunta individualmente.
- Selecionar uma opção e registrar o voto.
- Impedir segundo voto do mesmo usuário na mesma pergunta.
- Confirmar o voto sem revelar resultados quando a pergunta estiver configurada para ocultá-los.
- Exibir resultados quando permitido.
- Bloquear a votação globalmente desabilitada; se o lifecycle aberto/fechado for adotado, bloquear perguntas fechadas.
- Quando a votação global estiver desabilitada, o catálogo CMS fica indisponível e a listagem API retorna `data` vazio.

### 3.3 API manual

A API não deve utilizar JSON:API para a lógica central do desafio. Todos os endpoints exigem autenticação Drupal; leitura, voto e resultados ocultos possuem permissões próprias. O contrato está em [`openapi.yaml`](openapi.yaml).

Capacidades obrigatórias:

- Listar perguntas disponíveis.
- Consultar detalhes de uma pergunta pelo identificador.
- Registrar voto.
- Consultar resultados conforme a política de visibilidade.

## 4. Regras de negócio

1. Perguntas não podem ser representadas por entidades `node`.
2. O identificador da pergunta é único e não deve mudar depois de publicado ou referenciado pela API.
3. Uma pergunta disponível pode receber votos somente quando a votação global estiver habilitada; se o lifecycle for adotado, ela também deve estar aberta.
4. Apenas usuários autenticados e autorizados podem votar.
5. Uma combinação `(question_id, uid)` pode possuir no máximo um voto.
6. A opção enviada deve existir e pertencer à pergunta informada.
7. Uma tentativa duplicada deve produzir um resultado de domínio previsível e não um erro fatal.
8. A regra de unicidade deve ser garantida no banco, não apenas na interface.
9. O resultado público deve respeitar `show_results` e não deve expor a identidade dos votantes.
10. Falhas de lock, banco ou infraestrutura devem ser registradas em canal de log dedicado e mapeadas para respostas seguras.

## 5. Diretrizes arquiteturais

- Entidade customizada para a definição da pergunta; não usar `node`.
- Persistência transacional de votos com constraint única `(question_id, uid)`.
- Services para regras de negócio e Dependency Injection nos consumidores.
- Controllers e Forms responsáveis por orquestração, validação de entrada e resposta.
- Plugins para comportamento configurável, como bloco de votação.
- Event Subscribers somente para preocupações transversais, como normalização de exceções HTTP.
- Cache tags e contexts compatíveis com usuário, permissões, configuração e pergunta.
- Queries agregadas para resultados, evitando N+1.
- Configuração Drupal com schema e update hooks quando houver alterações persistidas.

## 6. Segurança

- Permissões declaradas e verificadas nas rotas e nos limites de negócio.
- CSRF em requisições de sessão que alteram estado.
- Validação server-side de todos os payloads.
- Sanitização/escape de títulos, descrições e mensagens.
- Validação de extensão, tamanho e destino de imagens.
- Erros externos sem stack trace, SQL ou detalhes internos.
- Logs sem senhas, tokens ou payloads sensíveis.
- CORS restrito quando o cliente externo estiver em outra origem.
- Auditoria das dependências via Composer.

## 7. Concorrência e performance

A solução deve suportar duplo clique, retries e workers concorrentes. A implementação deve combinar a constraint única do banco com um lock de aplicação por pergunta para voto e mutações administrativas concorrentes. O lock deve ser liberado em `finally` e uma violação de unicidade deve ser tratada como duplicidade de negócio.

Os endpoints de leitura devem evitar consultas repetidas e podem utilizar cache com invalidação específica por pergunta. A estratégia final deve ser documentada em um ADR e validada por testes de integração.

## 8. Observabilidade

Criar canal de log dedicado para:

- Voto duplicado.
- Pergunta ou opção inexistente.
- Votação global bloqueada.
- Lock indisponível.
- Violação de constraint.
- Falha inesperada de persistência.
- Erros da API.
- Acessos negados aos resultados.

Os eventos devem conter contexto operacional seguro, como UID, identificador da pergunta, endpoint e código de resposta.

## 9. Fora do escopo inicial

- Design visual sofisticado.
- Aplicação React completa.
- JSON:API.
- Microserviços ou event sourcing.
- Gateway externo.
- Mecanismo de votação anônima por IP.
- Alteração de votos já computados, salvo nova exigência.

## 10. Fatias de implementação

1. Decisão final de entidade e armazenamento.
2. Schema, permissões e configuração global.
3. CRUD administrativo de pergunta e opções.
4. Serviço de votação e integridade transacional.
5. Interface Drupal de listagem, voto e resultados.
6. API de leitura.
7. API de registro e resultados.
8. Observabilidade e cache.
9. Testes unitários, Kernel, funcionais e de concorrência.
10. Postman, documentação e validação final.

## 11. Índice de artefatos de implementação

- [`challenge-brief.md`](challenge-brief.md): fonte literal dos requisitos enviados no desafio.
- [`functional-requirements.md`](functional-requirements.md): requisitos numerados e decisões de produto.
- [`domain-model.md`](domain-model.md): agregados, campos, constraints, índices e cache.
- [`permission-matrix.md`](permission-matrix.md): atores, permissões e fronteiras de acesso.
- [`flows.md`](flows.md): fluxos CMS, API, resultados e concorrência.
- [`error-catalog.md`](error-catalog.md): códigos de domínio e respostas HTTP.
- [`security-threat-model.md`](security-threat-model.md): ameaças e controles.
- [`test-plan.md`](test-plan.md): estratégia Unit/Kernel/Functional/Integration.
- [`traceability-matrix.md`](traceability-matrix.md): requisito até evidência.
- [`operational-readiness.md`](operational-readiness.md): checklist de operação e produção.
- [`delivery-checklist.md`](delivery-checklist.md): checklist de entrega do desafio.
- [`decision-log.md`](decision-log.md): resumo das decisões vigentes.
- [`implementation-plan.md`](implementation-plan.md): fatias verticais de implementação.
- [`glossary.md`](glossary.md): vocabulário comum do domínio.
- [`openapi.yaml`](openapi.yaml): contrato da API.
- [`../postman/simple-voting.postman_collection.json`](../postman/simple-voting.postman_collection.json): collection inicial.
- [`adr/`](adr/): decisões arquiteturais registradas.
