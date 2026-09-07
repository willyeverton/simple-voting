# Arquitetura e engenharia — Simple Voting

Este documento explica as decisões de arquitetura do Simple Voting sob uma perspectiva de manutenção e operação produtiva. A especificação funcional continua em [`specification.md`](specification.md); este documento registra como o sistema preserva as invariantes, separa responsabilidades e cria pontos de evolução.

## 1. Objetivos de engenharia

O sistema precisa fazer mais do que registrar um voto no caminho feliz. Ele deve:

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
- `VotingResultsService`: único cálculo de contagem e percentual;
- `VotingVisibilityService`: aplica a política de resultados públicos/ocultos;
- `QuestionDeletionService`: impede remoção com votos;
- `VotingApiSerializer`: converte o domínio para o contrato versionado;
- `VotingApiExceptionSubscriber`: transforma falhas somente em rotas API.

Controllers, Forms e Block fazem composição e apresentação. Não devem criar regras alternativas nem repetir queries de resultados.

Todos os serviços são obtidos por dependency injection; consumidores não usam service locator estático para regras de negócio.

## 5. Fluxo de administração

1. O administrador acessa uma rota protegida por `administer simple voting`.
2. `VotingQuestionForm` valida título, machine name, opções e upload.
3. O formulário pode reconstruir somente o wrapper de opções via AJAX, preservando os valores no `FormState`.
4. O `ConfigEntityStorage` salva a definição da pergunta.
5. `OptionStorage` sincroniza as opções em operação controlada.
6. Opções removidas são bloqueadas quando possuem votos.
7. Arquivos aceitos são enviados para `public://simple_voting/options/`, tornam-se permanentes e recebem registro em `file_usage`.
8. Tags da pergunta e da listagem são invalidadas após a alteração.

A remoção de pergunta passa por `QuestionDeletionService`. Perguntas com votos não podem ser removidas; devem ser fechadas/arquivadas. Isso preserva auditoria e evita votos órfãos.

## 6. Fluxo de votação e concorrência

`VotingService::castVote()` aplica as regras na seguinte ordem:

1. exige UID autenticado;
2. verifica `voting_enabled`;
3. carrega a pergunta;
4. exige status aberto;
5. valida `question_id + option_id` em conjunto;
6. adquire lock determinístico por pergunta/usuário;
7. inicia transação;
8. verifica voto existente;
9. insere o voto;
10. converte violação da constraint em `DuplicateVoteException`;
11. invalida cache após sucesso;
12. libera o lock em `finally`.

A proteção possui duas camadas:

- **lock de aplicação:** reduz corridas previsíveis e evita trabalho duplicado;
- **constraint de banco:** continua funcionando em múltiplos workers, hosts ou caminhos alternativos.

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

- a listagem da API contém somente perguntas abertas disponíveis para votação;
- o detalhe da API pode retornar uma pergunta fechada conhecida com `status: closed`;
- o CMS pode exibir perguntas fechadas como somente leitura;
- pergunta fechada nunca aceita voto;
- `voting_enabled` bloqueia novas escritas, mas não apaga resultados históricos autorizados.

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

O módulo usa o canal `simple_voting`. Logs podem registrar UID, question ID, endpoint e código de falha quando operacionalmente necessário, mas não registram senhas, Basic Auth, CSRF tokens, IP bruto ou payload completo.

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

Os testes presentes no repositório são evidência inicial. A aceitação produtiva ainda depende da execução dos comandos Lando, da integração concorrente e da revisão de segurança/arquitetura.

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
