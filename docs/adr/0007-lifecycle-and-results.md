# ADR 0007 — Ciclo de vida, exclusão e visibilidade dos resultados

- **Status:** aceito como direção
- **Data:** 2026-09-06

## Contexto

Perguntas podem ser abertas/encerradas e resultados podem ser públicos ou ocultos. Excluir dados que já possuem votos pode quebrar auditoria e integridade.

## Decisão

- Nova pergunta inicia fechada.
- Somente administrador pode abrir ou encerrar.
- Pergunta fechada nunca aceita novos votos.
- Pergunta sem votos pode ser removida com suas opções.
- Pergunta com votos deve ser encerrada/arquivada; hard delete exige decisão explícita e transação.
- `show_results = true` permite resultados ao usuário elegível após o voto.
- `show_results = false` retorna confirmação no CMS e `403` na API para quem não possui `view voting results`.
- Percentuais usam uma casa decimal; total zero produz percentuais zero.

## Consequências

- A interface não deve confundir pergunta fechada com pergunta inexistente.
- A API precisa distinguir `404`, `409`, `422` e `403`.
- A regra de visibilidade deve ser centralizada para CMS e API.
