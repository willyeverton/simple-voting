# Glossário

| Termo | Definição |
|---|---|
| Pergunta | Enquete administrável que possui opções e recebe votos. |
| Opção | Alternativa selecionável dentro de uma pergunta. |
| Voto | Registro imutável da opção escolhida por um usuário em uma pergunta. |
| Identificador | Machine name estável usado em rotas, API e referências. |
| Votação global | Configuração que habilita ou bloqueia novos votos em todo o sistema. |
| Pergunta aberta | Pergunta que aceita voto quando a votação global está habilitada. |
| Pergunta fechada | Pergunta que não aceita novos votos. |
| Resultado oculto | Configuração que impede usuário comum de ver contagens/percentuais. |
| Usuário autenticado | Conta Drupal identificada por `uid`. |
| Constraint única | Regra de banco que impede duplicação de `(question_id, uid)`. |
| Lock | Mecanismo de sincronização de requests concorrentes. |
| CMS | Interface Drupal usada por administrador e usuário autenticado. |
| API manual | Endpoints implementados no módulo, sem delegar a lógica central ao JSON:API. |
