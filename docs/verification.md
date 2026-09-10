# Evidências de verificação

## Evidência histórica informada pelo mantenedor

| Verificação | Resultado informado |
|---|---|
| `composer validate --strict` | `./composer.json is valid` |
| `composer audit --no-interaction` | Sem advisories informados |
| PHPCS / PHPStan | Aprovados no estado anteriormente reportado |
| PHPUnit Unit | 24 testes no ambiente anteriormente reportado |
| Kernel/Functional | Execução anteriormente informada pelo mantenedor |

Essa evidência antecede o alinhamento documental/planejado e **não comprova** que a implementação atual satisfaz os requisitos desta documentação. Nenhuma validação foi executada nesta atualização. Não registrar credenciais, `SIMPLETEST_DB`, tokens ou dados pessoais.

## Evidência requerida para aceite

O mantenedor deve fornecer saídas e artefatos para:

1. metadados Drupal mostrando pergunta e opção como Content Entities customizadas e votos apenas em storage interno;
2. tentativa de rename de `machine_name` rejeitada;
3. edição de pergunta e opção sem votos permitida; edição de opção com votos permitida; remoção de opção com votos rejeitada;
4. matriz CMS/API provando global off em catálogo, detalhe, voto e resultados; quatro endpoints API com `503 VOTING_DISABLED`;
5. resultados: `403` antes do voto, `200` após voto somente com `show_results=true`, e `200` com `view voting results` nos dois estados;
6. dois requests simultâneos do mesmo UID: um sucesso, um `409`, uma linha no banco; UIDs distintos persistem sem serialização por lock;
7. dump obrigatório regenerado depois da mudança para Content Entities, restaurado em ambiente limpo, schema presente, entidades demonstrativas coerentes e zero usuários/sessões/votos/logs/secrets; o dump anterior não é evidência válida;
8. OpenAPI e Postman coerentes com statuses e payloads reais.

## Comandos recomendados — execução exclusiva do mantenedor

```bash
lando quality
lando quality-drupal
lando drush updb -y
lando drush cr
lando drush status
```

A concorrência requer ferramenta capaz de disparo simultâneo e consulta posterior ao MySQL; Newman/Postman sequencial isolado não basta. O restore do dump deve ocorrer em ambiente descartável separado, nunca sobre banco com dados sem backup. Consulte [`runbook.md`](runbook.md) e registre data, commit, comando e saída sanitizada.

## Limitações

- Testes unitários não provam unique constraint nem concorrência real.
- Um `409` sequencial prova duplicidade funcional, não disputa.
- OpenAPI/Postman são contratos, não evidência de implementação.
- A ausência de foreign keys físicas exige inspeção das políticas de lifecycle.
- Qualquer alteração posterior invalida evidências afetadas.