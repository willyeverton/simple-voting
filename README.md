# Simple Voting

Backend Drupal 11 para o desafio técnico de votação simples. Administradores cadastram perguntas e opções; usuários autenticados votam uma única vez por pergunta pelo CMS ou por uma API manual versionada.

> O projeto é focado em backend, integridade transacional, segurança, observabilidade e qualidade operacional. O tema global Drupal fornece a apresentação do site sem uma aplicação React ou dependências frontend adicionais.

## Requisitos locais

- Docker funcionando;
- Lando instalado;
- Git;
- acesso aos containers do projeto;
- PHP 8.5 e MySQL 8.4 fornecidos pelo Lando;
- Composer executado dentro do container Lando.

As versões do ambiente estão definidas em [`.lando.yml`](.lando.yml): Drupal 11, PHP 8.5, Apache 2.4 e MySQL 8.4.

## Instalação limpa

Execute os comandos a partir da raiz do repositório:

```bash
git clone <URL_DO_REPOSITORIO> simple-voting
cd simple-voting
lando start
lando composer install
```

Verifique o estado do ambiente:

```bash
lando info
lando drush status
```

### Instalar o site Drupal

O repositório não versiona `settings.php` com secrets nem um site já instalado. Para uma instalação limpa, abra:

```text
https://simple-voting.lndo.site/core/install.php
```

No instalador do Drupal:

1. selecione o perfil de instalação desejado;
2. use MySQL/MariaDB como banco;
3. use `database` como host do banco;
4. use porta `3306`;
5. use o nome, usuário e senha exibidos por `lando info` para o serviço `database`;
6. crie uma conta administrativa local;
7. não committe credenciais, `settings.local.php` ou dados pessoais.

Depois da instalação, habilite o módulo:

```bash
lando drush en simple_voting -y
lando drush updb -y
lando drush cr
```

Se o site já estiver instalado, pule o instalador e execute apenas a habilitação/atualização do módulo.

### Restaurar o dump em ambiente limpo

O dump de demonstração fica em [`dump/simple-voting-demo.sql`](dump/simple-voting-demo.sql). Ele foi preparado para uma instalação Drupal nova e contém configuração, `core.extension`, `key_value`, blocos e schemas, sem usuários, sessões, votos, logs, caches ou dados temporários de teste.

Use uma cópia separada do projeto e um nome Lando diferente do ambiente de desenvolvimento. Depois da instalação limpa do Drupal, execute **nesta ordem**:

```bash
lando drush en block -y
lando db-import dump/simple-voting-demo.sql
lando drush updb -y
lando drush cr
```

O dump preserva o estado de módulos/configuração do ambiente de demonstração e contém uma definição vazia de `simple_voting_vote`, sem registros de votos. Depois do import, crie um administrador local, pois dados de usuários não são transportados:

```bash
read -r -s DEMO_PASSWORD
lando drush user:create demo_admin --mail=demo@example.invalid --password="$DEMO_PASSWORD"
lando drush user:role:add administrator demo_admin
unset DEMO_PASSWORD
```

Confirme também os dados demonstrativos:

```bash
lando drush sql:query "SELECT COUNT(*) AS questions FROM config WHERE name LIKE 'simple_voting.question.%';"
lando drush sql:query "SELECT COUNT(*) AS options FROM simple_voting_option;"
lando drush sql:query "SELECT COUNT(*) AS votes FROM simple_voting_vote;"
```

O conteúdo esperado do dump atual é `3` perguntas, `6` opções e `0` votos. Esses valores descrevem o artefato SQL; confirme-os somente após restaurar o dump em uma instalação nova. Se `simple_voting_vote` não existir depois do import, interrompa o procedimento e não prossiga com um banco parcialmente restaurado.

O uninstall do módulo é bloqueado quando existem opções ou votos. Uma remoção destrutiva exige backup verificado e confirmação operacional explícita por meio do procedimento documentado em [`docs/runbook.md`](docs/runbook.md).

Ao instalar o módulo em um site novo, ou ao aplicar `updb` em um site existente, o tema `simple_voting_theme` é instalado e definido como tema frontend padrão. O tema administrativo configurado não é alterado.

Para restaurar outro tema frontend posteriormente:

```bash
lando drush config:set system.theme default NOME_DO_TEMA -y
lando drush cr
```

## Configuração inicial

O módulo cria as seguintes permissões:

- `administer simple voting`: administrar perguntas, opções, lifecycle e configuração;
- `access simple voting API`: acessar a API manual;
- `vote in polls`: acessar o CMS e registrar votos;
- `view voting results`: consultar resultados ocultos.

Conceda as permissões a roles locais pela interface administrativa ou por procedimento operacional equivalente. Não conceda `administer simple voting` a usuários comuns.

Para criar a primeira pergunta:

1. acesse `/admin/config/simple-voting/questions`;
2. crie uma pergunta com identificador estável;
3. informe ao menos uma opção;
4. defina `show_results`;
5. mantenha a pergunta fechada até terminar a revisão;
6. abra a pergunta somente quando ela estiver pronta para receber votos.

A votação global pode ser controlada em:

```text
/admin/config/simple-voting/settings
```

## Executar a aplicação

Com o Lando iniciado, a aplicação fica normalmente disponível em:

- `https://simple-voting.lndo.site/`;
- `http://simple-voting.lndo.site:8080/`.

O certificado HTTPS local pode exigir a instalação da CA do Lando ou uma exceção no navegador local.

A interface CMS de votação fica em:

```text
/voting
```

## API

A API manual não usa JSON:API para a lógica central. Todos os endpoints exigem autenticação Drupal e a permissão `access simple voting API`.

| Método | Endpoint | Finalidade |
|---|---|---|
| `GET` | `/api/v1/questions` | Lista perguntas abertas disponíveis; retorna catálogo vazio quando a votação global está desabilitada |
| `GET` | `/api/v1/questions/{question_id}` | Consulta uma pergunta conhecida, inclusive fechada |
| `POST` | `/api/v1/questions/{question_id}/votes` | Registra um voto |
| `GET` | `/api/v1/questions/{question_id}/results` | Consulta resultados conforme a política de visibilidade |

O body do voto contém somente:

```json
{
  "option_id": 1
}
```

### Autenticação

- Basic Auth sobre HTTPS pode ser usado por clientes externos stateless;
- sessão Drupal pode ser usada por clientes no mesmo contexto;
- requisições de sessão que alteram estado precisam do header `X-CSRF-Token`;
- `access simple voting API` não concede automaticamente `vote in polls`;
- resultados ocultos exigem também `view voting results`.

Exemplo de leitura com Basic Auth, usando variáveis locais não versionadas:

```bash
export DRUPAL_USER='usuario-local'
export DRUPAL_PASSWORD='senha-local'

curl --fail-with-body \
  --user "$DRUPAL_USER:$DRUPAL_PASSWORD" \
  -H 'Accept: application/json' \
  'https://simple-voting.lndo.site/api/v1/questions'
```

A especificação está em [`docs/openapi.yaml`](docs/openapi.yaml), e a collection está em [`postman/simple-voting.postman_collection.json`](postman/simple-voting.postman_collection.json).

## Executar testes e quality gates

Os comandos abaixo são executados pelo usuário dentro do Lando. O projeto possui scripts que ignoram a etapa quando ainda não há arquivos do tipo correspondente, mas isso não substitui a execução depois da implementação.

### Testes PHPUnit unitários

```bash
lando phpunit
```

A configuração `phpunit.xml.dist` executa somente os testes Unit, que não dependem de um site Drupal bootstrapped nem de banco de testes. Para executar diretamente:

```bash
lando php vendor/bin/phpunit --configuration=phpunit.xml.dist
```

### Testes Kernel e Functional Drupal

Os testes Kernel/Functional usam a configuração separada `phpunit.drupal.xml.dist`, que carrega o bootstrap de testes do Drupal. Eles precisam de uma instalação funcional e de uma URI de banco fornecida localmente.

Obtenha os valores com:

```bash
lando info
```

Depois entre no appserver:

```bash
lando ssh
```

Dentro do container, defina a URI usando os valores exibidos por `lando info` e execute:

```bash
export SIMPLETEST_DB='mysql://USUARIO:SENHA@database/NOME_DO_BANCO'
export SIMPLETEST_BASE_URL='https://simple-voting.lndo.site'
vendor/bin/phpunit --configuration=phpunit.drupal.xml.dist
```

A tooling `lando phpunit-drupal` executa a mesma configuração Kernel/Functional diretamente no appserver. Em ambos os casos, `SIMPLETEST_DB` e `SIMPLETEST_BASE_URL` precisam estar definidos no ambiente. Não substitua `USUARIO`, `SENHA` e `NOME_DO_BANCO` por valores versionados em arquivos do projeto.

Os testes estão organizados em:

- `tests/src/Unit`: regras isoladas, visibilidade, serialização e guards do serviço;
- `tests/src/Kernel`: schema e bootstrap Drupal;
- `tests/src/Functional`: rotas e acesso HTTP;
- integração e concorrência real: executadas no banco do ambiente Drupal.

### Quality gates

```bash
lando composer validate --strict
lando composer audit
lando phpcs
lando phpstan
lando phpunit
lando quality
```

`lando quality` executa Composer validate, Composer audit, PHPCS, PHPStan e PHPUnit Unit em sequência. O workflow remoto mantém essa mesma sequência. Kernel/Functional e concorrência dependem de um Drupal instalado e devem ser executados localmente com `lando phpunit-drupal` e banco real; não são executados no workflow remoto atual. Não use flags para ignorar auditorias, requisitos de plataforma ou falhas de segurança.

### Verificação completa do ambiente instalado

Depois que o site Drupal estiver instalado e o módulo habilitado, execute a sequência abaixo para combinar quality gates com o smoke test de bootstrap, rotas, plugins e cache do Drupal:

```bash
lando quality && \
  lando drush updb -y && \
  lando drush cr && \
  lando drush status
```

`lando quality` verifica código e testes; `lando drush cr` é necessário para detectar erros de carregamento de classes, rotas, entidades e plugins que não aparecem necessariamente em uma análise estática isolada.

### Verificações Drupal

```bash
lando drush status
lando drush updb -y
lando drush cr
```

## Estrutura do projeto

```text
.
├── docs/                         # Especificação, contratos e operação
├── postman/                      # Collection da API
├── scripts/                      # Scripts Lando/CI de qualidade
├── web/
│   ├── core/                     # Drupal core gerenciado por Composer
│   └── modules/custom/
│       └── simple_voting/        # Módulo pertencente ao projeto
├── .lando.yml                    # Ambiente local
├── composer.json                 # Dependências e caminhos Drupal
├── phpunit.xml.dist              # Suite PHPUnit
├── phpcs.xml.dist                # Drupal/DrupalPractice
└── phpstan.neon                  # PHPStan sobre código customizado
```

## Documentação

- [`docs/specification.md`](docs/specification.md): comportamento e regras do sistema;
- [`docs/architecture.md`](docs/architecture.md): componentes e responsabilidades;
- [`docs/openapi.yaml`](docs/openapi.yaml): contrato da API;
- [`docs/permission-matrix.md`](docs/permission-matrix.md): autorização e permissões;
- [`docs/domain-model.md`](docs/domain-model.md): entidades, tabelas, índices e cache;
- [`docs/security-threat-model.md`](docs/security-threat-model.md): controles de segurança;
- [`docs/error-catalog.md`](docs/error-catalog.md): códigos e respostas de erro;
- [`docs/runbook.md`](docs/runbook.md): operação local;
- [`docs/verification.md`](docs/verification.md): evidências fornecidas e critérios de verificação;
- [`postman/simple-voting.postman_collection.json`](postman/simple-voting.postman_collection.json): collection da API.

## Operação e limites conhecidos

- votos são imutáveis e perguntas com votos não devem ser apagadas;
- rate limiting é responsabilidade da infraestrutura;
- IP bruto não é persistido;
- alterações destrutivas de schema exigem backup e update hook;
- o dump de demonstração não deve conter credenciais nem dados pessoais.
