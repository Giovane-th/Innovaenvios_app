# Integração Correios — InnovaEnvios

## Objetivo
Conectar o InnovaEnvios à nova API dos Correios (gateway `api.correios.com.br`) usando as credenciais do seu contrato, entregando três recursos: cálculo de frete (preço + prazo), rastreamento de encomendas e pré-postagem com geração de etiqueta em PDF.

## O que será entregue

### 1. Cálculo de frete (preço e prazo)
- Formulário para informar CEP de origem, CEP de destino, peso e dimensões (comprimento, largura, altura) da encomenda.
- Retorno com valor do frete e prazo de entrega estimado para os serviços do seu contrato (ex.: SEDEX, PAC).
- Possibilidade de comparar mais de um serviço lado a lado.

### 2. Rastreamento de encomendas
- Campo para digitar o código de rastreio (formato AA123456789BR).
- Exibição do histórico de eventos (data, local, status), do mais recente ao mais antigo.

### 3. Pré-postagem + etiqueta
- Formulário com dados do remetente, destinatário, serviço, peso e declaração de conteúdo.
- Criação da pré-postagem nos Correios e registro do ID e do código do objeto.
- Solicitação e download da etiqueta em PDF (geração assíncrona: a etiqueta fica "pendente" por alguns segundos e depois disponível para baixar).
- Tela de listagem das pré-postagens criadas, com status e link para a etiqueta.

## Decisões adotadas
- **Ambiente inicial: Homologação (testes).** Toda a integração será construída e validada em `apihom.correios.com.br`. A troca para produção (`api.correios.com.br`) será feita depois, apenas alterando as credenciais/ambiente — sem reescrever funcionalidades.
- **Autenticação por Cartão de Postagem**, já que você possui cartão + contrato. O token é gerado e renovado automaticamente nos bastidores (validade ~24h), nunca exposto no navegador.
- **Segurança das credenciais:** usuário, código de acesso, contrato e cartão ficam somente no servidor; a tela nunca recebe esses dados nem o token dos Correios.
- **Etiquetas em PDF** serão armazenadas para download; não ficarão embutidas no banco.

## O que preciso de você (de forma segura) antes de conectar de verdade
Para a integração funcionar com o seu contrato, precisarei confirmar estes valores do ambiente de **homologação** (peça a replicação do contrato para homologação no portal CWS, se ainda não fez):
1. **Usuário (idCorreios)** do login Meu Correios.
2. **Código de acesso às APIs** (gerado no CWS). *Você enviou uma chave — vou confirmar com você se é esse o código de acesso.*
3. **Número do Cartão de Postagem.**
4. **Número do Contrato.**
5. **Código da DR** (Diretoria Regional) associado ao contrato.

Enquanto esses valores de homologação não estiverem confirmados, as telas serão construídas e testadas; a conexão real com os Correios é ativada assim que as credenciais forem inseridas.

## Fora do escopo (por enquanto)
- Rastreamento em lote de muitos objetos de uma vez.
- Serviços internacionais.
- Logística reversa e agendamento de coleta.
- Emissão fiscal (NF-e) — a declaração de conteúdo dos Correios será suportada, mas não integração fiscal externa.

## Pontos para você confirmar ou ajustar
1. Confirmar que a lista de recursos acima (frete, rastreio, pré-postagem/etiqueta) está completa e na prioridade certa.
2. Confirmar que a chave enviada é o **código de acesso às APIs** (e não a senha do portal).
3. Confirmar se você já tem acesso replicado em **homologação** ou se prefere que eu prepare tudo para produção direto (você indicou homologação primeiro).
