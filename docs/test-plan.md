# Plano de testes

Este documento descreve a estratégia e os critérios de validação. Todos os cenários aplicáveis foram executados e passaram conforme confirmação do mantenedor em 2026-09-09.

## Estado atual da evidência

O projeto possui testes Unit, Kernel e Functional, além das verificações de integração e manuais previstas no plano. A validação combinada cobriu guards de votação, lock, serialização, visibilidade, acesso, tratamento de exceções, schema, constraint, rotas, formulários, API, tema, uploads, cache, CSRF, concorrência, restauração do dump e limpeza operacional.

## Pirâmide

### Unitários

Alvo: Services, policies, serializers e funções de domínio sem banco real.

Cobrir:

- Novo voto.
- Duplicidade detectada por leitura.
- Constraint violation convertida em duplicidade somente quando o voto já existe.
- Falha inesperada de insert convertida em falha de persistência.
- Lock indisponível, espera entre tentativas e vida útil suficiente para a seção crítica.
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
- Home anônima com link de login e usuário autenticado com link de logout.
- Menu público de perguntas visível somente para usuários com `vote in polls`.
- Regiões globais, mensagens, breadcrumb, título, sidebars e footer sem regressões.
- Tema administrativo preservado após a ativação do tema frontend.

### Concorrência/integração

Alvo: banco real e requests simultâneos.

Cobrir:

- Dois inserts concorrentes para o mesmo usuário/pergunta.
- Requests para usuários diferentes.
- Retry após `409`.
- Lock compartilhado por pergunta quando houver mais de um worker ou mutação administrativa concorrente.
- Falha durante insert e liberação de lock.
- Voto concorrente com remoção/alteração de opção não cria referência órfã.
- Falha de persistência de opção não deixa `file_usage` ou arquivo em estado incompatível com o banco.
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
| Tema/apresentação | Functional + revisão manual acessível + `drush cr` |

## Critério de conclusão

A entrega foi declarada pronta após a execução dos testes relevantes, o registro dos resultados, a atualização da matriz de aceitação e a ausência de findings bloqueadores no security/architecture review. Futuras mudanças devem repetir esse critério.
