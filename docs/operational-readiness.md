# Operational readiness checklist

## Antes de aceitar a implementação

- [ ] `composer validate --strict` passa.
- [ ] `composer audit` não possui advisory sem decisão registrada.
- [ ] Schema de instalação e update hooks foram testados.
- [ ] Permissões foram exportadas/documentadas.
- [ ] Configuração possui schema.
- [ ] Logs usam canal dedicado.
- [ ] Erros externos não expõem stack trace.
- [ ] Cache tags/contexts foram revisados.
- [ ] Query de resultados possui índices e não faz N+1.
- [ ] Constraint de voto único existe no banco.
- [ ] Lock é liberado mesmo em exceção.
- [ ] Postman e OpenAPI estão sincronizados.
- [ ] README/runbook possui setup limpo.
- [ ] CI executa os mesmos gates do Lando.

## Sinais e alertas

Observar aumento de:

- `DUPLICATE_VOTE` acima do comportamento esperado.
- `LOCK_UNAVAILABLE`.
- Violações de unique constraint inesperadas.
- HTTP 5xx na API.
- Falhas de banco/conexão.
- Latência de agregação de resultados.

## Rollback e recuperação

- Não apagar dados de voto para corrigir erro de aplicação.
- Usar update hook reversível quando possível.
- Fazer backup antes de alterações destrutivas de schema.
- Fechar a pergunta para conter comportamento incorreto enquanto a correção é preparada.
- Registrar incidentes e decisão de recuperação.

## Privacidade

Se o IP for armazenado, definir finalidade, retenção e anonimização antes da implementação. Não incluir IP ou identidade do votante em resultados públicos.
