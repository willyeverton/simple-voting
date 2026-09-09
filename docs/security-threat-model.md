# Controles de segurança

Este documento descreve os controles de segurança presentes no módulo.

## Dados protegidos

- Integridade da contagem de votos.
- Identidade e autorização dos usuários.
- Configuração das perguntas.
- Imagens e arquivos enviados.
- Credenciais e tokens de integração.
- Logs operacionais.

## Controles

| Área | Controle |
|---|---|
| Voto duplicado | Constraint única por `(question_id, uid)`, lock por pergunta e transação de banco. |
| Opção incompatível | A opção é validada junto com o identificador da pergunta antes da persistência. |
| Administração | Permissões Drupal e verificações de acesso protegem rotas, formulários e operações de entidade. |
| CSRF | Form API protege o CMS e requisições de sessão que alteram estado exigem token CSRF. |
| XSS | Títulos e descrições passam por validação e são renderizados com saída segura. |
| Upload | Extensão, tamanho, MIME, destino e uso Drupal do arquivo são verificados. |
| Enumeração | Respostas e regras de autorização não expõem dados de perguntas ou resultados sem permissão. |
| Segredos | Credenciais ficam fora do repositório e não são incluídas em logs, respostas ou dumps. |
| SQL injection | Consultas usam a Database API/Query Builder e parâmetros Drupal. |
| Excesso de carga | Índices e agregações reduzem consultas desnecessárias; rate limiting pertence à infraestrutura. |
| Cache | Cache contexts, tags e invalidação consideram usuário, permissões, pergunta e configuração. |
| Erros | O cliente recebe mensagens estáveis sem SQL, stack trace, tokens ou detalhes internos. |

## Logging

- Usar o canal `simple_voting`.
- Registrar UID e identificador da pergunta somente quando necessário para diagnóstico.
- Nunca registrar senha, Basic Auth, token CSRF, payload completo, IP bruto ou o objeto completo da exceção.
- Associar falhas inesperadas a um `X-Request-ID` seguro sem expor detalhes ao cliente.

## Dependências

- Manter dependências de produção e desenvolvimento no lock file.
- Executar auditoria do Composer sem ignorar advisories ou requisitos de plataforma.
