# Threat model e controles de segurança

## Ativos

- Integridade da contagem de votos.
- Identidade e autorização do usuário.
- Configuração das perguntas.
- Imagens e arquivos enviados.
- Credenciais e tokens de integração.
- Logs operacionais.

## Ameaças e controles

| Ameaça | Vetor | Controle obrigatório | Evidência |
|---|---|---|---|
| Voto duplicado | Retry/duplo clique/concorrência | Unique constraint + lock/transaction | Teste de integração |
| Voto em opção de outra pergunta | Manipulação de `option_id` | Query com question_id + option_id | API test |
| Acesso administrativo indevido | Rota/form/API | Permissions + access checks | Functional test |
| CSRF | POST por cookie | Header CSRF e proteção de rota | Functional API test |
| XSS | Título/descrição/imagem | Form API validation + escape/filter | Security test |
| Upload malicioso | Arquivo executável ou excessivo | Extensão, MIME, tamanho, destino e uso | Upload test |
| Enumeração | IDs e resultados | Respostas consistentes e autorização | API/security test |
| Vazamento de segredo | Logs/config/CI | Env vars, redaction, revisão de diff | Manual/CI |
| SQL injection | Payloads de API | DB API/Query Builder e parâmetros | Code review/test |
| Excesso de carga | Flood de votos/resultados | Índices, agregação, limites/infra rate limit | Load/operational test |
| Cache indevido | Conteúdo por permissão/usuário | Cache contexts/tags e invalidation | Kernel test |
| Erro informativo | Stack trace/API | Exception subscriber e mensagens estáveis | API test |

## Regras de logging

- Registrar UID e question ID somente quando operacionalmente necessário.
- Nunca registrar senha, Basic Auth, CSRF token ou payload completo.
- Não retornar identificadores internos do banco sem necessidade.
- Usar canal `simple_voting` e severidade adequada.
- Associar exceções inesperadas ao evento sem expor a exceção ao cliente.

## Dependências

- `composer audit` deve ser executado localmente e no CI.
- Não ignorar advisory para desbloquear instalação sem ADR e revisão explícita.
- Dependências de produção e desenvolvimento devem permanecer no lock file.
