# Arquitetura e engenharia — Simple Voting

Este documento explica as decisões de arquitetura do Simple Voting e separa o desenho pretendido do estado atualmente implementado. A especificação funcional continua em [`specification.md`](specification.md); este documento registra responsabilidades, controles presentes no código e limitações que ainda exigem teste ou evolução.

## Estado atual

O módulo implementa o fluxo principal de perguntas, opções, votação, resultados, API manual, permissões, cache e tratamento básico de erros. Em 2026-09-09, o mantenedor confirmou a execução bem-sucedida dos testes Unit, Kernel, Functional, integração, cenários manuais, collection Postman, restauração do dump e verificações de concorrência.

A arquitetura deve ser preservada em futuras alterações: comportamentos de concorrência, restauração, upload, CSRF, limpeza de arquivos e operação fazem parte da validação registrada para a entrega.

## 1. Objetivos de engenharia

O desenho do sistema busca fazer mais do que registrar um voto no caminho feliz. Os objetivos abaixo foram cobertos pela validação executada para a entrega e devem ser preservados em futuras alterações:

- impedir votos duplicados mesmo com duplo clique, retry e workers concorrentes;
- manter a contagem íntegra quando há falhas de banco ou infraestrutura;
- separar configuração deployável de dados transacionais;
- aplicar autenticação e autorização em todos os canais;
- evitar vazamento de resultados por cache ou mensagens de erro;
- permitir que CMS, bloco e API exibam o mesmo read model;
- fornecer sinais operacionais suficientes para investigar falhas;
- permitir instalação, atualização e verificação reproduzíveis.

O foco é um módulo Drupal 11 pequeno, explícito e substituível, não uma abstração genérica de enquete para todos os produtos possíveis.

## 2. Visão de contexto

```text
Administrador ── CMS administrativo ──┐
                                     │
Usuário autenticado ── CMS/bloco ────┼── Serviços de domínio ── Persistência
                                     │                           ├─ Config Entity
Cliente externo ── API manual ───────┘                           ├─ opções
                                                                 └─ votos

Operação ── logs, métricas, Lando/CI, backups, runbook
```

O módulo não delega a lógica central ao JSON:API e não usa entidades `node` para perguntas, opções ou votos.

## 3. Fronteiras de domínio e persistência

### 3.1 Pergunta como Config Entity

`VotingQuestion` usa `ConfigEntityBase` porque a definição administrativa possui machine name estável e precisa ser exportável pelo Configuration Management. A decisão está registrada no [ADR-0001](adr/0001-question-storage.md).

A entidade contém:

- identificador machine name;
- UUID Drupal;
- título;
- status aberto/fechado;
- `show_results`;
- timestamps `created` e `changed`.

O identificador deve usar somente letras minúsculas, números e underscore (`_`), sem hífen (`-`), e não deve mudar depois que integrações ou URLs passarem a referenciá-lo. Uma pergunta nova começa fechada para que a criação e a publicação sejam atos administrativos separados.

Essa escolha é adequada enquanto perguntas são configuração relativamente estável. Se o produto exigir revisão editorial, tradução por campo, workflow ou alterações massivas em produção, a fronteira deve ser reavaliada para `ContentEntityBase` em um novo ADR.

### 3.2 Opções em tabela própria

As opções ficam em `simple_voting_option`, vinculadas por `question_id`. Isso evita serializar dados operacionais dentro de configuração e permite ordenar e agregar sem decodificar blobs.

A camada `OptionStorage` é responsável por:

- leitura ordenada;
- sincronização administrativa;
- validação de pertencimento;
- política de remoção;
- associação e liberação de uso de arquivos;
- invalidação de cache.

Uma opção com votos não é removida. A administração deve preservar o histórico ou encerrar a pergunta.

### 3.3 Votos como dados transacionais

Os votos ficam em `simple_voting_vote` com:

```text
UNIQUE(question_id, uid)
INDEX(question_id, option_id)
INDEX(uid)
INDEX(question_id)
```

O registro contém apenas a identidade Drupal necessária para a regra de unicidade, a opção, a pergunta e o timestamp. IP bruto não é persistido.

A constraint única é a autoridade final. A validação PHP melhora a experiência, mas nunca substitui a proteção do banco.

## 4. Módulo e responsabilidades

```text
web/modules/custom/simple_voting/
├── config/
│   ├── install/simple_voting.settings.yml
│   └── schema/simple_voting.schema.yml
├── simple_voting.install
├── simple_voting.permissions.yml
├── simple_voting.routing.yml
├── simple_voting.services.yml
└── src/
    ├── Access/             # acesso de entidade administrativa
    ├── Controller/         # orquestração HTTP/CMS
    ├── Entity/             # VotingQuestion e list builder
    ├── EventSubscriber/    # normalização transversal de exceções API
    ├── Exception/          # resultados de domínio
    ├── Form/               # entrada administrativa e voto CMS
    ├── Plugin/Block/       # integração configurável com regiões Drupal
    └── Service/            # regras, storage, read model e serialização
```

### Serviços principais

- `QuestionReadService`: carrega perguntas e delega opções;
- `OptionStorage`: persiste opções e File API usage;
- `VoteStorage`: persiste e consulta votos;
- `VotingService`: único caminho para registrar voto;
- `QuestionPersistenceService`: coordena a persistência da pergunta e a sincronização das opções sob lock e transação de banco; operações da File API não possuem rollback transacional automático;
- `VotingMutationLock`: lock compartilhado por pergunta para voto e mutações administrativas;
- `VotingResultsService`: único cálculo de contagem e percentual;
- `VotingVisibilityService`: aplica a política de resultados públicos/ocultos;
- `QuestionDeletionService`: impede remoção com votos;
- `VotingApiSerializer`: converte o domínio para o contrato versionado;
- `VotingApiExceptionSubscriber`: transforma falhas somente em rotas API;
- `VotingConfigImportSubscriber`: impede rename de IDs e remoção de perguntas com dados runtime durante importação.

Controllers, Forms e Block fazem composição e apresentação. Não devem criar regras alternativas nem repetir queries de resultados.

Os controllers, forms, plugins e services usam dependency injection para as regras de negócio. Hooks de instalação/atualização usam as APIs procedurais de ciclo de vida do Drupal; a entidade ainda possui uma chamada estática ao serviço de tempo que deve ser revista em uma evolução futura.

### Tema global e apresentação

`simple_voting_theme` é o tema frontend global do site. Ele fornece o shell público, regiões de header, menus, mensagens, breadcrumb, conteúdo, sidebars e footer, além dos estilos acessíveis usados pelo CMS e pelas páginas de votação. A instalação do módulo instala o tema e define apenas `system.theme:default`; o tema administrativo configurado em `system.theme:admin` permanece inalterado.

O tema não contém regras de votação, validação de payload, autorização, persistência, cálculo de resultados ou decisões de cache de domínio. Login/logout e visibilidade de menus continuam sendo controlados pelos menus e permissões nativas do Drupal. A apresentação usa Twig, render arrays e CSS sem React ou dependência frontend adicional.

A mudança do tema padrão é operacional e reversível por configuração Drupal. O módulo guarda o tema frontend anterior em State somente quando troca um valor diferente e não altera o tema administrativo. Após a instalação/atualização, o cache de descoberta deve ser reconstruído e as regiões/blocos opcionais devem ser revisados no ambiente real.

## 5. Fluxo de administração

1. O administrador acessa uma rota protegida por `administer simple voting`.
2. `VotingQuestionForm` valida título, machine name, opções e upload.
3. O formulário pode reconstruir somente o wrapper de opções via AJAX, preservando os valores no `FormState`.
4. O `ConfigEntityStorage` salva a definição da pergunta.
5. `OptionStorage` sincroniza as opções em operação controlada.
6. Opções removidas são bloqueadas quando possuem votos.
7. Arquivos aceitos são enviados para `public://simple_voting/options/`, permanecem temporários até a sincronização da opção e tornam-se permanentes com registro em `file_usage`.
8. A transação do banco não desfaz automaticamente os efeitos da File API. A implementação mantém essa fronteira explícita e o fluxo de falha/limpeza foi validado nos cenários operacionais da entrega.
9. Tags da pergunta e da listagem são invalidadas após a alteração.

A remoção de pergunta passa por `QuestionDeletionService`. Perguntas com votos não podem ser removidas; devem ser fechadas/arquivadas. Isso preserva auditoria e evita votos órfãos.

## 6. Fluxo de votação e concorrência

`VotingService::castVote()` aplica as regras na seguinte ordem:

1. exige UID autenticado;
2. adquire lock determinístico por pergunta, compartilhado com mutações administrativas;
3. verifica `voting_enabled`;
4. carrega a pergunta;
5. exige status aberto;
6. valida `question_id + option_id` em conjunto sob o lock;
7. inicia transação;
8. verifica voto existente;
9. insere o voto;
10. converte violação da constraint em `DuplicateVoteException`;
11. confirma a transação e invalida cache após sucesso;
12. libera o lock em `finally`.

A proteção possui duas camadas:

- **lock de aplicação por pergunta:** serializa voto, sincronização de opções e exclusão para evitar que uma opção seja removida entre a validação e a persistência do voto;
- **constraint de banco:** a chave única continua funcionando em múltiplos workers, hosts ou caminhos alternativos.

O segundo argumento de `LockBackendInterface::acquire()` é a vida útil do lock, não um timeout de espera. `VotingMutationLock` usa uma vida útil de 30 segundos e chama `wait()` entre tentativas não bloqueantes. O limite deve cobrir a operação transacional; caso uma operação administrativa passe a executar trabalho mais longo, a política deve ser revisada ou o lock renovado explicitamente.

A Schema API do Drupal não cria foreign keys físicas para tabelas customizadas. Por isso, a integridade entre opções e votos depende do lock compartilhado, das transações e da política de não remover opções que já possuem votos.

Falhas são transformadas em resultados seguros:

| Situação | Domínio | API |
|---|---|---:|
| usuário já votou | `DuplicateVoteException` | `409` |
| pergunta fechada | `QuestionClosedException` | `422` |
| opção incompatível | `InvalidOptionException` | `422` |
| votação global desabilitada | `VotingDisabledException` | `503` |
| lock indisponível | `VoteLockUnavailableException` | `503` |
| falha inesperada de persistência | `PersistenceFailureException` | `500` |

Nenhum caminho de erro retorna SQL, stack trace, token ou detalhes internos.

## 7. Lifecycle e discoverability

O projeto diferencia visibilidade histórica de elegibilidade para mutação, conforme o [ADR-0011](adr/0011-question-discoverability.md):

- a listagem da API contém somente perguntas abertas disponíveis para votação quando `voting_enabled` está habilitado; quando desabilitado, retorna catálogo vazio;
- o detalhe da API pode retornar uma pergunta fechada conhecida com `status: closed`;
- o CMS pode exibir perguntas fechadas como somente leitura;
- pergunta fechada nunca aceita voto;
- `voting_enabled` bloqueia novas escritas; a listagem CMS exibe somente uma mensagem de indisponibilidade e a listagem API retorna catálogo vazio enquanto a configuração estiver desabilitada.
- A configuração não apaga nem oculta resultados históricos autorizados.

Essa separação evita usar `404` para representar um recurso que existe, mas não aceita mutação.

## 8. Segurança e autorização

### Permissões

- `administer simple voting`: CRUD, lifecycle e configuração;
- `access simple voting API`: entrada em endpoints API;
- `vote in polls`: CMS e registro de voto;
- `view voting results`: resultados ocultos.

`access simple voting API` não implica permissão de voto. A rota API garante a capacidade técnica; o controller e o serviço validam a capacidade de negócio.

### Autenticação e CSRF

Toda a API requer autenticação Drupal via Basic Auth sobre HTTPS ou sessão Drupal. POST via cookie exige `X-CSRF-Token`. O CMS usa a proteção nativa do Form API.

### Entrada, saída e upload

- JSON é decodificado com `JSON_THROW_ON_ERROR`;
- o body de voto aceita somente `option_id` inteiro positivo;
- question ID é restringido ao formato machine name na rota;
- opções são sempre verificadas com o question ID;
- títulos e descrições são renderizados como texto seguro;
- uploads têm extensão, tamanho, MIME e destino verificados;
- arquivos recebem uso Drupal e não ficam abandonados por remoção de opção;
- resultados nunca incluem identidade dos votantes.

### Logs

O módulo usa o canal `simple_voting`. Logs podem registrar UID, question ID, endpoint, código de falha e um `X-Request-ID` validado quando operacionalmente necessário, mas não registram senhas, Basic Auth, CSRF tokens, IP bruto, payload completo ou objetos de exceção.

## 9. Read model de resultados

`VotingResultsService` é o único ponto de agregação para CMS, bloco e API. A query usa:

- `LEFT JOIN`, para preservar opções com zero votos;
- `COUNT` e `GROUP BY`;
- ordenação por `weight` e ID;
- percentual arredondado para uma casa decimal;
- percentuais iguais a zero quando o total é zero.

O serviço devolve dados estáveis e metadata de cache. A autorização fica no limite do consumidor, enquanto a query permanece única para evitar divergência entre canais.

## 10. Cache

Tags principais:

```text
config:simple_voting.settings
config:simple_voting.question.{id}
config:voting_question_list
simple_voting:question:{id}
simple_voting:question-list
```

Contexts usados conforme a resposta:

```text
user
user.permissions
url
```

Resultados e estados de voto variam por usuário/permissão. Após persistência de voto ou alteração de opções, as tags da pergunta são invalidadas. A revisão operacional deve confirmar que proxies externos não armazenam respostas autenticadas sem a política correta.

## 11. API e fronteira HTTP

As rotas manuais estão em `simple_voting.routing.yml` e seguem o contrato de [`docs/openapi.yaml`](openapi.yaml). O controller:

- valida autenticação e capacidade adicional de voto;
- decodifica o request;
- chama serviços;
- monta resposta de sucesso.

O subscriber de exceção só atua em rotas cujo nome começa com `simple_voting.api.`. Isso impede que uma falha API transforme páginas HTML em JSON ou que uma mensagem interna chegue ao cliente.

A collection Postman é parte do contrato de integração e deve ser atualizada junto com OpenAPI e testes sempre que uma rota/payload/status mudar.

## 12. Operação produtiva

### Configuração versus dados

- perguntas são configuração e podem ser promovidas via Configuration Management;
- identificadores de perguntas não podem ser renomeados durante importação;
- perguntas com opções ou votos runtime não podem ser removidas durante importação;
- opções e votos são dados runtime e não devem ser tratados como configuração deployável;
- dumps devem excluir credenciais, tokens e dados pessoais desnecessários;
- `settings.php`, `settings.local.php`, senhas e variáveis de ambiente não entram no Git.

### Atualizações

- `hook_schema()` cria tabelas no primeiro install;
- `hook_update_N()` é usado para alterações persistidas posteriores;
- update hooks devem ser reversíveis quando possível;
- alterações destrutivas exigem backup e decisão operacional;
- nunca corrigir contagens apagando votos.

### Sinais operacionais

Monitorar:

- crescimento de `DUPLICATE_VOTE`;
- `LOCK_UNAVAILABLE`;
- falhas de unique constraint inesperadas;
- HTTP 5xx;
- falhas de conexão com banco;
- latência da agregação de resultados;
- falhas de upload;
- erros de cache ou respostas com conteúdo indevido.

Em incidente, fechar a pergunta é uma contenção segura enquanto a correção é preparada. Não remover votos como procedimento de rollback.

## 13. Estratégia de testes

A pirâmide está detalhada em [`docs/test-plan.md`](test-plan.md):

- **Unit:** regras, guards, serialização, visibilidade e exceções;
- **Kernel:** schema, configuração, queries e cache;
- **Functional:** rotas, permissões, formulários, autenticação, CSRF e envelopes JSON;
- **Integração:** constraint real e requests concorrentes no banco.

As configurações Unit, Kernel e Functional estão disponíveis no Lando. O CI executa os gates estáticos e a suíte Unit; as suítes Kernel/Functional e as verificações de integração dependentes de banco foram executadas no ambiente Lando pelo mantenedor. A aceitação da entrega foi concluída com os gates, a verificação de concorrência, a collection/manual plan e a revisão de segurança/arquitetura.

## 14. Evolução além do desafio

Antes de ampliar o volume ou o risco operacional, considerar:

- rate limiting na borda ou serviço dedicado;
- métricas estruturadas para votos aceitos, duplicados e rejeitados;
- tracing/correlation ID sem registrar dados sensíveis;
- estratégia de retenção e backup para votos;
- índices e particionamento conforme volume real;
- job assíncrono para agregados pré-calculados somente se a query deixar de atender a latência;
- migração para entidade de conteúdo se houver tradução, revisão ou workflow editorial;
- política formal de arquivamento para perguntas antigas.

Essas extensões não devem ser adicionadas prematuramente: primeiro medir o comportamento real e registrar a decisão em novo ADR.
