# Teste de Sistema de Votação Simples
O sistema de votação simples permitirá que os usuários votem em perguntas cadastradas
pelo administrador.

## Interface Administrativa
• Deve permitir o cadastro de perguntas, cada uma identificada por um identificador único.
• As perguntas podem conter várias opções de resposta, as quais podem incluir uma imagem, um título e uma breve descrição.
• Deve permitir configurar se o total de votos para cada pergunta deve ser exibido ou ocultado no Drupal, após o voto do usuário.
• Deve permitir desabilitar a votação de forma geral, restringindo todo o fluxo de votação, tanto no CMS quanto no acesso externo.

## Interface de Interação com o Usuário no Próprio CMS
• Os usuários devem ser capazes de acessar cada pergunta de forma independente e votar selecionando uma das opções de resposta.
• Cada voto deve ser identificável e único por usuário para cada pergunta, garantindo que um usuário não vote mais de uma vez na mesma pergunta.
• Após a votação, os resultados devem ser mostrados conforme a configuração individual para cada questão.

## API para Aplicação Externa
A API deve permitir:
• Obtenção das Perguntas: Acesso às perguntas disponíveis para que os usuários possam selecionar em qual querem votar.
• Exibição da Pergunta Selecionada: Mostrar a pergunta escolhida com base no identificador único, permitindo a visualização das opções de resposta para votação.
• Registro dos Votos.
• Exibição dos Resultados: Apresentar os resultados após a votação, de acordo com as configurações de visualização definidas.

## Requisitos Não Funcionais
• O sistema deve ser fácil de usar e intuitivo para o administrador cadastrar as perguntas e opções de resposta.
• O sistema deve ser seguro, garantindo a proteção dos dados dos votos e evitando manipulações indevidas.

## Requisitos Técnicos
• É necessário implementar a lógica central para os endpoints da API para aplicação externa manualmente, sem o uso de módulos como o JSON:API.
• Não utilizar "node" para as entidades.
• O código deve ser entregue em um repositório do GitHub, com um dump do banco de dados e o ambiente configurado via Lando.
• Uma documentação mínima é necessária.
• Collection do Postman para testes dos endpoints de uso externo.

## Avaliação
• Funcionalidades implementadas conforme os requisitos.
• A estrutura do código deve ser cuidadosamente planejada e organizada para garantir clareza e facilidade de manutenção alinhadas com boas práticas de desenvolvimento, que promovem a separação de responsabilidades e a modularidade.
• O código deve ser otimizado para garantir alta performance e eficiência, utilizando técnicas que minimizem a carga sobre os recursos do sistema.
• Uso das melhores práticas do Drupal, garantindo conformidade com os padrões de desenvolvimento.
• A implementação deve estar preparada para processar um alto volume de votações concorrentes, garantindo que a integridade dos dados seja mantida.
• Uma observabilidade efetiva para identificação de problemas.
• Este teste é focado exclusivamente no desenvolvimento e funcionamento do backend do sistema. A interface leva em consideração os requisitos funcionais sem considerar a aplicabilidade de guias de estilos.