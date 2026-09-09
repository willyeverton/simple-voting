# Operational readiness checklist

A validação operacional foi concluída pelo mantenedor em **2026-09-09**. Os gates, testes Drupal, cenários manuais, integração, concorrência, restauração do dump e collection Postman passaram com sucesso.

## Antes de aceitar a implementação

- [x] `composer validate --strict` passa.
- [x] `composer audit` não possui advisory impeditivo.
- [x] Schema de instalação e update hooks foram testados.
- [x] Permissões foram exportadas/documentadas.
- [x] Configuração possui schema.
- [x] Tema frontend padrão e tema administrativo foram revisados.
- [x] Blocos opcionais do tema e regiões foram reconstruídos após o cache rebuild.
- [x] Login, menus, mensagens e páginas sem conteúdo foram validados no tema global.
- [x] Logs usam canal dedicado.
- [x] Erros externos não expõem stack trace.
- [x] Cache tags/contexts foram revisados.
- [x] Query de resultados possui índices e não faz N+1 nos cenários validados.
- [x] Constraint de voto único existe no banco.
- [x] Lock é liberado mesmo em exceção.
- [x] Postman e OpenAPI estão sincronizados.
- [x] README/runbook possuem setup reproduzível.
- [x] Gates locais foram executados com sucesso.
- [x] Suítes Kernel/Functional foram executadas com banco configurado.
- [x] Concorrência de voto e mutação administrativa foi verificada em banco real.
- [x] Uninstall com dados runtime é bloqueado pelo validator.
- [x] Procedimento de destruição confirmada possui backup verificado e aprovação registrada.
- [x] Tema frontend anterior pode ser restaurado sem alterar o tema administrativo.

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
- Restaurar `system.theme:default` para o tema anterior se o shell global apresentar regressão.
- Registrar incidentes e decisão de recuperação.

## Privacidade

Se o IP for armazenado, definir finalidade, retenção e anonimização antes da implementação. Não incluir IP ou identidade do votante em resultados públicos.
