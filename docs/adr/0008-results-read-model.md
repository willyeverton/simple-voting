# ADR 0008 — Serviço central de resultados

- **Status:** aceito
- **Data:** 2026-09-07

## Contexto

Os resultados são consumidos pelo controller CMS, pelo bloco e pela API. Duplicar a query de agregação pode produzir percentuais, filtros, cache e regras diferentes entre canais.

## Decisão

Criar um `VotingResultsService` responsável por:

- Validar a existência da pergunta para a consulta.
- Aplicar a política de visibilidade recebida do domínio.
- Executar uma query agregada com `LEFT JOIN`, `COUNT`, `GROUP BY` e ordenação.
- Calcular total e percentual com a mesma regra em todos os canais.
- Retornar um DTO/array de leitura estável para apresentação e serialização.
- Declarar/informar metadados de cache necessários ao consumidor.

Controllers, Forms e Block não devem repetir a query nem calcular percentuais independentemente.

## Consequências

- CMS, API e bloco apresentam os mesmos números.
- A query pode ser otimizada e testada em um único ponto.
- A autorização continua sendo verificada no limite de acesso; o serviço de leitura não deve presumir que todo chamador é público.
- A invalidação da pergunta ocorre após alteração de opções ou persistência de voto.
