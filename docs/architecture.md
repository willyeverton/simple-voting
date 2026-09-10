# Arquitetura e engenharia — Simple Voting

Este documento descreve a arquitetura atual do Simple Voting. A especificação funcional está em [`specification.md`](specification.md).

## 1. Objetivos de engenharia

O sistema foi estruturado para:

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
                                     │                           ├─ Content Entity pergunta
Cliente externo ── API manual ───────┘                           ├─ Content Entity opção
                                                                 └─ votos internos

Operação ── logs, métricas, Lando/CI, backups, runbook
```

O módulo não delega a lógica central ao JSON:API e não usa entidades `node` para perguntas, opções ou votos.

## 3. Fronteiras de domínio e persistência

### 3.1 Pergunta como Content Entity customizada

`VotingQuestion` é uma Content Entity customizada, não `node` nem Config Entity. Possui ID interno e um campo `machine_name` público, único, estável e imutável desde a criação. URLs e API usam `machine_name`; persistência e referências internas usam o ID da entidade.

A entidade contém UUID, `machine_name`, título, status fechado/publicado, `show_results` e timestamps. Uma pergunta nova começa fechada. Conteúdo de perguntas não é configuração deployável e não deve ser promovido por Configuration Management.

### 3.2 Opção como Content Entity customizada

`VotingOption` é uma Content Entity customizada vinculada pelo ID interno da pergunta. Ela permite ordenação e agregação sem embutir alternativas na pergunta. Não é recurso CRUD público da API.

Opções podem ser adicionadas, editadas, reordenadas ou removidas a qualquer momento, mas uma opção com votos não pode ser removida para preservar o histórico; o administrador pode encerrar a pergunta se necessário. Uma nova composição também pode ser criada como nova pergunta com novo `machine_name`.

A camada `OptionStorage` é responsável por:

- leitura ordenada;
- sincronização administrativa;
- validação de pertencimento;
- política de remoção e edição com votos;
- associação e liberação de uso de arquivos;
- invalidação de cache.

Uma opção com votos pode ser editada, mas não removida. A administração deve preservar o histórico ou encerrar a pergunta.

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
- `QuestionPersistenceService`: coordena a persistência das Content Entities e rejeita remoção de opções que já tenham votos; a edição de opções com votos é permitida; operações da File API não possuem rollback transacional automático;
- `VotingResultsService`: único cálculo de contagem e percentual;
- `VotingVisibilityService`: aplica a política de resultados públicos/ocultos;
- `QuestionDeletionService`: impede remoção com votos;
- `VotingApiSerializer`: converte o domínio para o contrato versionado;
- `VotingApiExceptionSubscriber`: transforma falhas somente em rotas API;
- `VotingConfigImportSubscriber`: impede rename de IDs e remoção de perguntas com dados runtime durante importação.

Controllers, Forms e Block fazem composição e apresentação. Não devem criar regras alternativas nem repetir queries de resultados.

Controllers, forms, plugins e services usam dependency injection para as regras de negócio. Hooks de instalação e atualização usam as APIs de ciclo de vida do Drupal.

### Tema global e apresentação

`simple_voting_theme` é um tema frontend opcional do site. Ele fornece o shell público, regiões de header, menus, mensagens, breadcrumb, conteúdo, sidebars e footer, além dos estilos acessíveis usados pelo CMS e pelas páginas de votação. A instalação do módulo instala o tema sem alterar `system.theme:default` ou `system.theme:admin`; a aplicação decide explicitamente se deseja ativá-lo.

O tema não contém regras de votação, validação de payload, autorização, persistência, cálculo de resultados ou decisões de cache de domínio. Login/logout e visibilidade de menus continuam sendo controlados pelos menus e permissões nativas do Drupal. A apresentação usa Twig, render arrays e CSS sem React ou dependência frontend adicional.

A ativação do tema é operacional e reversível por configuração Drupal. Após a instalação/atualização, o cache de descoberta deve ser reconstruído e as regiões/blocos opcionais devem ser revisados no ambiente real.

## 5. Fluxo de administração

1. O administrador acessa uma rota protegida por `administer simple voting`.
2. `VotingQuestionForm` valida título, `machine_name`, opções e upload.
3. O formulário pode reconstruir o wrapper de opções via AJAX, preservando valores no `FormState`.
4. O Entity Storage salva a Content Entity da pergunta.
5. O storage salva as Content Entities de opção.
6. Opções com votos podem ser editadas, mas não removidas; opções sem votos podem ser editadas ou removidas.
8. Arquivos aceitos são enviados para `public://simple_voting/options/`, permanecem temporários até a sincronização da opção e tornam-se permanentes com registro em `file_usage`.
9. A transação do banco não desfaz automaticamente os efeitos da File API; a sincronização mantém um journal de operações e compensa usos anexados/liberados quando a transação falha.
10. Tags da pergunta e da listagem são invalidadas após a alteração.

A remoção de pergunta passa por `QuestionDeletionService`. Perguntas com votos não podem ser removidas para preservar auditoria e evitar votos órfãos. Perguntas sem votos podem ser excluídas. O administrador também pode fechar uma pergunta para encerrar novas votações.

## 6. Fluxo de votação e concorrência

`VotingService::castVote()` aplica as regras na seguinte ordem:

1. exige UID autenticado e permissão;
2. verifica `voting_enabled`;
3. resolve a pergunta pelo `machine_name` público e exige publicação;
4. valida que a opção existe e pertence à pergunta;
5. tenta inserir o voto interno;
6. converte violação de `UNIQUE(question_id, uid)` em `DuplicateVoteException`;
7. invalida cache após sucesso.

O hot path não adquire locks Drupal nem serializa votos. A constraint única é a autoridade para duplo clique, retries e múltiplos workers; usuários diferentes podem votar em paralelo. Uma verificação prévia de duplicidade pode melhorar UX, mas não decide integridade. Transações devem ser curtas e restritas à gravação necessária.

A proteção contra edição e remoção de opções com votos preserva a integridade do histórico. A ausência de foreign keys físicas ainda exige validação server-side, bloqueio de exclusão com votos e procedimentos operacionais cuidadosos. Importações de configuração não transportam perguntas/opções, pois ambas são conteúdo.

Falhas são transformadas em resultados seguros:

| Situação | Domínio | API |
|---|---|---:|
| usuário já votou | `DuplicateVoteException` | `409` |
| pergunta fechada | `QuestionClosedException` | `422` |
| opção incompatível | `InvalidOptionException` | `422` |
| votação global desabilitada | `VotingDisabledException` | `503` |
| falha inesperada de persistência | `PersistenceFailureException` | `500` |

Não existe resultado `LOCK_UNAVAILABLE` no caminho de voto planejado: o hot path não adquire lock de aplicação. Indisponibilidade ou falha inesperada do banco é falha de persistência, enquanto uma violação da constraint única é sempre traduzida para duplicidade.

Nenhum caminho de erro retorna SQL, stack trace, token ou detalhes internos.

## 7. Lifecycle e discoverability

O projeto diferencia visibilidade histórica de elegibilidade para mutação:

- com `voting_enabled` habilitado, a listagem e o detalhe CMS e API mostram somente perguntas publicadas para usuários comuns; administradores com `administer simple voting` veem todas as perguntas, inclusive fechadas;
- pergunta fechada nunca aceita voto;
- com `voting_enabled` desabilitado, catálogo, detalhe, voto e resultados ficam indisponíveis em CMS e API; a API responde `503 VOTING_DISABLED` em todos os endpoints, sem retornar catálogo vazio ou dado histórico;
- resultados comuns exigem `show_results=true` e voto prévio do usuário; `view voting results` é bypass explícito para consulta antes do voto ou com resultados ocultos.

Perguntas fechadas retornam `404` para usuários comuns em rotas de votação e detalhe, mas permanecem visíveis para administradores; isso evita expor perguntas encerradas no catálogo público.

## 8. Segurança e autorização

### Permissões

- `administer simple voting`: CRUD, lifecycle e configuração;
- `access simple voting API`: entrada em endpoints API;
- `vote in polls`: CMS e registro de voto;
- `view voting results`: bypass da exigência de voto prévio e de `show_results`.

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

O serviço devolve dados estáveis e metadata de cache. Antes da query, a política comum exige `show_results=true` e voto prévio do usuário; `view voting results` ignora ambas as condições. A resposta varia por usuário e permissão para impedir vazamento entre caches.

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

- perguntas e opções são Content Entities e dados runtime; não são promovidas via Configuration Management;
- `machine_name` é público e não pode ser renomeado;
- opções podem ser editadas a qualquer momento, mas só podem ser removidas enquanto não tiverem votos;
- votos são registros internos, não entidades públicas;
- configuração importável limita-se a settings e metadados apropriados; importação não serializa o hot path de voto;
- o dump obrigatório deve excluir credenciais, tokens, usuários, sessões, votos e dados pessoais desnecessários;
- qualquer mudança de schema, modelo de entidades ou dados demonstrativos torna o dump anterior obsoleto e exige regenerar `dump/simple-voting-demo.sql`, revisar o diff SQL e comprovar restore limpo antes da entrega; a documentação não afirma que o artefato não regenerado representa o modelo atual;
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
- `VOTING_DISABLED` por endpoint;
- taxa de `DUPLICATE_VOTE` e violações da unique constraint;
- HTTP 5xx;
- falhas de conexão com banco;
- latência da agregação de resultados;
- falhas de upload;
- erros de cache ou respostas com conteúdo indevido.

Em incidente, fechar a pergunta é uma contenção segura enquanto a correção é preparada. Não remover votos como procedimento de rollback.

## 13. Estratégia de testes

Os testes do módulo estão organizados nas camadas Unit, Kernel, Functional e integração com banco real. O CI executa os gates estáticos e a suíte Unit; as demais verificações dependem do ambiente Drupal local.
