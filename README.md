# Simple Voting

Backend Drupal 11 para sistema de votação simples. Administradores cadastram perguntas e opções; usuários autenticados votam uma única vez por pergunta pelo CMS ou pela API manual.

## Requisitos

- Docker
- Lando
- Git

## Início rápido

Na raiz do repositório:

```bash
git clone <URL_DO_REPOSITORIO> simple-voting
cd simple-voting
lando start
lando db-import dump/simple-voting-demo.sql
lando drush updb -y
lando drush cr
lando drush status
```

O dump já inclui o site configurado, módulo, tema e dados de demonstração.

## Acesso

- Site: `https://simple-voting.lndo.site/`
- HTTP alternativo: `http://simple-voting.lndo.site:8080/`

## Usuários de teste

O dump contém duas contas de demonstração:

| Usuário | Senha | Função |
|---|---|---|
| `admin` | `admin` | Administrador: cria/edita perguntas, opções e a configuração global. |
| `voter` | `voter` | Eleitor: acessa perguntas, vota e consulta resultados conforme regras. |

> Estas credenciais são apenas para teste local do desafio. Não use em produção.

## Como testar

### CMS

- Administração: `/admin/config/simple-voting/questions`
- Configuração global: `/admin/config/simple-voting/settings`
- Votação pública: `/voting`

### API

A API requer autenticação Drupal (Basic Auth ou sessão). Exemplos com `voter`:

```bash
export DRUPAL_USER='voter'
export DRUPAL_PASSWORD='voter'

# Listar perguntas

curl --user "$DRUPAL_USER:$DRUPAL_PASSWORD" \
  -H 'Accept: application/json' \
  'https://simple-voting.lndo.site/api/v1/questions'

# Detalhe da pergunta

curl --user "$DRUPAL_USER:$DRUPAL_PASSWORD" \
  -H 'Accept: application/json' \
  'https://simple-voting.lndo.site/api/v1/questions/{machine_name}'

# Registrar voto

curl --user "$DRUPAL_USER:$DRUPAL_PASSWORD" \
  -X POST \
  -H 'Content-Type: application/json' \
  -d '{"option_id": 1}' \
  'https://simple-voting.lndo.site/api/v1/questions/{machine_name}/votes'

# Resultados

curl --user "$DRUPAL_USER:$DRUPAL_PASSWORD" \
  -H 'Accept: application/json' \
  'https://simple-voting.lndo.site/api/v1/questions/{machine_name}/results'
```

Substitua `{machine_name}` pelo identificador público da pergunta. Para voto via sessão (cookie), adicione o header `X-CSRF-Token`.

- Especificação completa: [`docs/openapi.yaml`](docs/openapi.yaml)
- Collection Postman: [`postman/simple-voting.postman_collection.json`](postman/simple-voting.postman_collection.json)

## Qualidade

```bash
lando quality
lando quality-drupal   # requer SIMPLETEST_DB e SIMPLETEST_BASE_URL
```

## Estrutura

- `web/modules/custom/simple_voting/` — módulo
- `web/themes/custom/simple_voting_theme/` — tema opcional
- `dump/simple-voting-demo.sql` — banco de demonstração
- `docs/openapi.yaml` — contrato da API
- `postman/` — collection para testes da API

## Observações

- Perguntas e opções são Content Entities customizadas; não usam `node`.
- Votos são registros transacionais com `UNIQUE(question_id, uid)` para evitar duplicidade.
- O dump inclui usuários, sessões e votos de demonstração para agilizar os testes.
