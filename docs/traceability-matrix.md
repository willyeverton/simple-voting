# Matriz de rastreabilidade

Cada requisito funcional e não funcional deve apontar para uma evidência verificável.

| Requisito | Especificação | Aceitação | API/ADR | Teste/evidência |
|---|---|---|---|---|
| FR-001 identificador único | functional/domain | AC-001, AC-002 | ADR-0001 | Functional entity |
| FR-002 título da pergunta | functional | AC-001 | domain-model | Functional |
| FR-003 mínimo de uma opção | functional/domain | AC-003 | ADR-0005 | Kernel/functional |
| FR-004 dados da opção | functional/domain | AC-004, AC-005 | ADR-0005 | Kernel/functional |
| FR-005 gestão/ordenação de opções | functional | AC-003, AC-029 | ADR-0005 | Functional Javascript |
| FR-006 lifecycle de pergunta | functional | AC-006 | flows/ADR-0007 | API/functional |
| FR-007 show_results | functional | AC-015, AC-016, AC-017 | ADR-0007 | API/functional |
| FR-008 votação global | functional | AC-007 | specification | API/functional |
| FR-009 administração protegida | functional/permissions | AC-001, AC-017 | permission-matrix | Functional |
| FR-010 perguntas disponíveis autenticadas | functional/API | AC-008, AC-033 | ADR-0003/0006 | API |
| FR-011 voto autenticado/autorizado | functional/permissions | AC-010, AC-018 | ADR-0003 | API/functional |
| FR-012 voto único | functional/domain | AC-010, AC-011, AC-012 | ADR-0002 | Unit/integration |
| FR-013 opção pertencente | functional/errors | AC-009 | error-catalog | API |
| FR-014 bloqueio por estado/global | functional | AC-006, AC-007 | ADR-0007 | API/functional |
| FR-015 resultado pós-voto no CMS | functional/flows | AC-015, AC-016 | ADR-0007 | Functional |
| FR-016 resultado oculto privilegiado | permissions/errors | AC-016, AC-017 | ADR-0003/0007 | API/functional |
| FR-017 listagem API | OpenAPI | AC-008, AC-033 | ADR-0003/0006 | Contract/API |
| FR-018 detalhe API | OpenAPI | AC-008, AC-033 | ADR-0003/0006 | Contract/API |
| FR-019 API usa serviço de domínio | specification | AC-010, AC-011 | ADR-0002/0006 | Unit/API |
| FR-020 resultados API | OpenAPI/domain | AC-015, AC-016, AC-017, AC-035 | ADR-0007/0008 | API/functional |
| FR-021 JSON e erros seguros | error-catalog | AC-018, AC-020 | ADR-0003 | API/security |
| FR-022 AJAX administrativo | functional | AC-029 | — | Functional Javascript |
| FR-023 bloco Drupal | functional | AC-030 | — | Functional |
| FR-024 cache específico | domain-model | AC-021 | ADR-0004/0008 | Kernel/unit |
| FR-025 Postman alinhado | OpenAPI/Postman | AC-024 | ADR-0006 | Manual/collection |
| FR-026 autenticação e permissões da API | permissions/OpenAPI | AC-033 | ADR-0003 | Functional API |
| NFR-001 código customizado isolado | AGENTS/domain | AC-031 | ADR-0001/0005 | Review gate |
| NFR-002 regras em Services | architecture | AC-032 | ADR-0004 | Unit test |
| NFR-003 segurança server-side | threat-model | AC-005, AC-018, AC-019, AC-020, AC-041 | ADR-0003 | Security/functional |
| NFR-004 integridade concorrente | domain/flows | AC-012, AC-013, AC-014, AC-040 | ADR-0002 | Integration |
| NFR-005 observabilidade | operational | AC-020, AC-028 | threat/runbook | Log assertion |
| NFR-006 performance/N+1 | domain/operational | AC-021, AC-035 | ADR-0002/0005/0008 | Query review |
| NFR-007 dependências/gates | quality harness | AC-022, AC-023 | ADR-0004 | CI/Lando |
| NFR-008 Lando/documentação | runbook | AC-025, AC-027 | ADR-0004 | Clean setup |
| NFR-009 OpenAPI | OpenAPI | AC-024 | ADR-0006 | Contract review |
| NFR-010 CI | quality harness | AC-022, AC-023 | ADR-0004 | GitHub Actions |
| NFR-011 GitHub | challenge brief | AC-027 | runbook | Repository review |
| NFR-012 dump restaurável | challenge brief | AC-026 | runbook | Restore test |
| NFR-013 documentação mínima | challenge brief | AC-025 | runbook | Documentation review |
| NFR-014 backend sem exigência visual | challenge brief | — | specification | Scope review |
| NFR-015 privacidade de metadados | security/domain | AC-034 | ADR-0009 | Kernel/security |
