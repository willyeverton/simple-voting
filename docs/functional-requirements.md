# Requisitos funcionais e não funcionais

## Convenções

- **Must:** obrigatório para a entrega.
- **Should:** recomendado para uma solução sênior, salvo conflito com o desafio.
- **Out:** fora do escopo atual.

## Requisitos funcionais

| ID | Prioridade | Requisito |
|---|---|---|
| FR-001 | Must | O administrador deve criar uma pergunta com identificador único, estável e validado como machine name. |
| FR-002 | Must | A pergunta deve possuir título obrigatório e tamanho máximo documentado. |
| FR-003 | Must | Uma pergunta deve possuir pelo menos uma opção de resposta. |
| FR-004 | Must | Cada opção deve possuir título obrigatório; descrição e imagem são opcionais. |
| FR-005 | Must | O administrador deve conseguir adicionar, editar, remover e ordenar opções. |
| FR-006 | Must | O administrador deve conseguir abrir ou encerrar uma pergunta; uma nova pergunta inicia fechada até ser explicitamente publicada. |
| FR-007 | Must | O administrador deve configurar `show_results` por pergunta. |
| FR-008 | Must | O administrador deve habilitar ou desabilitar a votação globalmente. |
| FR-009 | Must | Ações administrativas devem exigir `administer simple voting`. |
| FR-010 | Must | O sistema deve disponibilizar perguntas abertas e disponíveis para usuários autenticados autorizados. |
| FR-011 | Must | Usuário autenticado com `vote in polls` deve selecionar exatamente uma opção e registrar um voto. |
| FR-012 | Must | O sistema deve impedir mais de um voto por `(question_id, uid)`. |
| FR-013 | Must | A opção escolhida deve pertencer à pergunta solicitada. |
| FR-014 | Must | Pergunta fechada ou votação global desabilitada não pode aceitar votos. |
| FR-015 | Must | Após o voto, o CMS deve exibir resultados somente quando permitido; caso contrário, deve exibir confirmação sem números. |
| FR-016 | Must | Usuário com `view voting results` pode consultar resultados ocultos; demais usuários recebem acesso negado. |
| FR-017 | Must | A API deve listar perguntas disponíveis. |
| FR-018 | Must | A API deve retornar detalhes de uma pergunta e suas opções. |
| FR-019 | Must | A API deve registrar votos usando o mesmo serviço de domínio do CMS. |
| FR-020 | Must | A API deve retornar resultados com votos e percentual por opção. |
| FR-021 | Must | A API deve responder JSON consistente para sucesso e erro, sem stack trace. |
| FR-022 | Should | O formulário administrativo deve adicionar e remover opções via AJAX sem perder valores já preenchidos. |
| FR-023 | Should | Um bloco Drupal deve permitir incorporar o formulário de uma pergunta em uma região. |
| FR-024 | Should | O sistema deve invalidar cache específico da pergunta após alteração ou voto. |
| FR-025 | Should | A API deve possuir collection Postman alinhada ao OpenAPI. |
| FR-026 | Must | Toda a API deve exigir usuário autenticado; permissões específicas devem proteger leitura, votação e resultados privilegiados. |

## Requisitos não funcionais

| ID | Prioridade | Requisito |
|---|---|---|
| NFR-001 | Must | Código customizado deve estar em módulo/tema customizado e não alterar core ou contrib. |
| NFR-002 | Must | Regras de negócio devem ser testáveis fora da camada HTTP por meio de Services. |
| NFR-003 | Must | Inputs, uploads, permissões, CSRF e saídas devem ser tratados no servidor. |
| NFR-004 | Must | A unicidade do voto deve ser garantida por constraint no banco e protegida contra concorrência. |
| NFR-005 | Must | Falhas relevantes devem possuir logs estruturados em canal dedicado. |
| NFR-006 | Must | A implementação deve evitar N+1 e consultas desnecessárias em listagem/resultados. |
| NFR-007 | Must | Dependências devem ser auditáveis e o projeto deve possuir quality gates reproduzíveis. |
| NFR-008 | Must | Ambiente deve funcionar via Lando com documentação de setup. |
| NFR-009 | Should | A API deve possuir contrato OpenAPI versionado. |
| NFR-010 | Should | CI deve executar Composer audit, PHPCS, PHPStan e PHPUnit. |
| NFR-011 | Must | O código deve ser entregue em repositório GitHub. |
| NFR-012 | Must | O repositório deve conter ou documentar um dump restaurável do banco para demonstração. |
| NFR-013 | Must | Deve existir documentação mínima de instalação, uso, API e testes. |
| NFR-014 | Must | A avaliação deve priorizar backend e funcionamento; guia visual sofisticado não é requisito. |
| NFR-015 | Should | O sistema não deve persistir IP bruto por padrão; prevenção de abuso deve ficar na infraestrutura ou usar metadado anonimizado com retenção definida. |

## Decisões aprovadas antes do código

1. Hard delete só será permitido quando não houver votos; perguntas com votos devem ser encerradas/arquivadas.
2. Percentuais serão arredondados para uma casa decimal; pergunta sem votos retorna zero para todas as opções.
3. Resultados ocultos retornam `403` na API para usuários sem permissão e uma confirmação sem números no CMS.
4. Toda a API exige autenticação Drupal. Leitura exige `access simple voting API`; registro exige `vote in polls`; resultados ocultos exigem adicionalmente `view voting results`.
5. O voto não pode ser alterado depois de registrado.
6. Status aberto/fechado é obrigatório, com nova pergunta fechada por padrão.
7. O endpoint de voto usa `POST /api/v1/questions/{question_id}/votes` e recebe somente `option_id` no body.
8. Resultados são calculados por `VotingResultsService`, evitando duplicação de queries entre CMS, bloco e API.
9. IP bruto não será persistido por padrão. Qualquer mecanismo antiabuso futuro deve ter justificativa, anonimização e retenção documentadas.
