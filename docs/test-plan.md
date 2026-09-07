# Plano de testes

## Pirâmide

### Unitários

Alvo: Services, policies, serializers e funções de domínio sem banco real.

Cobrir:

- Novo voto.
- Duplicidade detectada por leitura.
- Constraint violation convertida em duplicidade.
- Lock indisponível.
- Liberação no `finally`.
- Pergunta fechada/desabilitada.
- Opção incompatível.
- Arredondamento de resultados.
- Mensagens e códigos de domínio.
- Regra de autenticação e permissões da API.
- `VotingResultsService` como ponto único de cálculo.

### Kernel

Alvo: entidades, schema, configuração, queries e cache tags com Drupal bootstrap.

Cobrir:

- Instalação do schema.
- Criação/atualização da entidade.
- Persistência e ordenação das opções.
- Unique constraint real.
- Agregação de resultados.
- Invalidação de cache.

### Functional

Alvo: rotas, permissões, formulários, autenticação e API.

Cobrir:

- CRUD administrativo.
- Acesso por permissão.
- Login/usuário anônimo.
- CSRF.
- Status HTTP e envelope JSON.
- Resultado público e oculto.
- Pergunta fechada e votação global desabilitada.
- Todos os endpoints recusam acesso anônimo.
- Permissões distintas para acessar API, votar e consultar resultado oculto.

### Concorrência/integração

Alvo: banco real e requests simultâneos.

Cobrir:

- Dois inserts concorrentes para o mesmo usuário/pergunta.
- Requests para usuários diferentes.
- Retry após `409`.
- Lock compartilhado quando houver mais de um worker.
- Falha durante insert e liberação de lock.
- Ausência de IP bruto no registro persistido.

## Gates por mudança

| Tipo de mudança | Mínimo obrigatório |
|---|---|
| Serviço/regra | Unit + PHPCS + PHPStan |
| Entity/schema | Unit + Kernel + `drush updb` quando aplicável |
| Rota/permissão | Functional |
| API | Functional API + contrato OpenAPI/Postman |
| Query/resultados | Kernel + performance review |
| Upload/form | Functional + security test |
| Concorrência | Unit + integration real |
| Configuração/CI | Composer validate + audit + quality |

## Critério de conclusão

Uma mudança só está pronta quando os testes relevantes passam, a matriz de aceitação foi atualizada e não existem findings bloqueadores no security/architecture review.
