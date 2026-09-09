# Fluxos principais

Os diagramas abaixo representam o fluxo implementado no código. Os cenários integrados, incluindo o fluxo de voto concorrente, foram executados e validados pelo mantenedor em 2026-09-09.

## Cadastro administrativo

```mermaid
sequenceDiagram
  actor Admin
  participant Form as QuestionForm
  participant Entity as VotingQuestion
  participant Store as OptionStorage
  participant Cache as CacheTags

  Admin->>Form: Envia pergunta e opções
  Form->>Form: Valida machine name, campos e uploads
  Form->>Entity: Salva definição
  Form->>Store: Sincroniza opções ordenadas
  Store-->>Form: Resultado da sincronização
  Form->>Cache: Invalida lista/pergunta
  Form-->>Admin: Mensagem e redirect
```

## Voto pelo CMS

```mermaid
sequenceDiagram
  actor User
  participant CMS as VoteForm
  participant Service as VotingService
  participant Lock as LockBackend
  participant DB as Database
  participant Cache as CacheTags

  User->>CMS: Seleciona opção e envia
  CMS->>Service: castVote(question, option, uid)
  Service->>Service: Valida disponibilidade e pertencimento
  Service->>Lock: Adquire chave uid+question
  Service->>DB: Verifica voto existente
  Service->>DB: Insere voto com unique constraint
  DB-->>Service: Sucesso ou constraint violation
  Service->>Lock: Libera em finally
  Service->>Cache: Invalida pergunta após sucesso
  Service-->>CMS: Resultado de domínio
  CMS-->>User: Confirmação ou resultados permitidos
```

## Voto concorrente

```mermaid
sequenceDiagram
  participant A as Request A
  participant B as Request B
  participant S as VotingService
  participant DB as Database

  par Requests simultâneos
    A->>S: castVote(uid, question)
    B->>S: castVote(uid, question)
  end
  S->>DB: Primeiro insert
  DB-->>S: Commit
  S->>DB: Segundo insert
  DB-->>S: Unique constraint violation
  S-->>B: DuplicateVoteException / HTTP 409
```

## API de resultado oculto

```mermaid
sequenceDiagram
  actor User
  participant API
  participant Access as Access Policy
  participant DB

  User->>API: GET /results
  API->>Access: Verifica show_results/permissão
  alt Resultado público ou permissão elevada
    Access-->>API: Permitido
    API->>DB: Agrega votos
    DB-->>API: Contagens
    API-->>User: 200 JSON
  else Sem autorização
    Access-->>API: Negado
    API-->>User: 403 JSON sem dados
  end
```
