# InnovaEnvios — Integração Correios CWS

## Problema original
Usuário tem a página https://innovaenvios.app/ ativa mas não conseguiu integrar a API do seu contrato com os Correios.

## Arquitetura
- Frontend: React (CRA/craco), Tailwind + shadcn/ui, react-router, sonner, lucide-react, framer-motion. pt-BR.
- Backend: FastAPI + Motor (MongoDB). httpx para chamadas aos Correios.
- Integração Correios CWS: token via cartão de postagem (Basic Auth -> Bearer), hosts apihom/api.correios.com.br. Endpoints: preço, prazo, SRO rastreamento, pré-postagem, etiqueta.
- Modo demo (padrão): dados SIMULADOS de alta fidelidade. Modo real ativado ao salvar credenciais e desligar demo.

## Personas
- Lojista/e-commerce brasileiro que despacha via Correios com contrato comercial.

## Requisitos core (estáticos)
- Cálculo de frete (preço + prazo) comparando serviços.
- Rastreamento de objetos (timeline + stepper).
- Pré-postagem + geração de etiqueta (PDF/visual).
- Lista/gestão de pré-postagens.
- Configuração e diagnóstico do contrato CWS (credenciais seguras no servidor).

## Implementado (2026-06)
- Backend endpoints: /correios/settings (GET/POST), /correios/test-connection, /frete/calcular, /rastreamento/{codigo}, /prepostagem (criar/etiqueta/cancelar), /prepostagens, /prepostagem/{id}, /dashboard/stats.
- Token real Correios com cache em memória (obter_token) + fallback simulado.
- Frontend: Layout com sidebar+header (status conexão, tema, ambiente), páginas Dashboard, Frete, Rastreamento, PrePostagem (+EtiquetaModal com código de barras), ListaPostagens, Contrato (guia passo-a-passo, dados de exemplo, diagnóstico).
- Testes: 11/11 backend pytest, 6/6 fluxos frontend — 100%.

## Backlog priorizado
- P1: Ativar conexão real com credenciais de homologação do usuário (aguardando usuário/DR).
- P1: Persistir etiqueta PDF real (object storage) quando em modo real (fluxo assíncrono de rótulo).
- P2: Rastreamento em lote; exportar PLP; logística reversa; agendamento de coleta.
- P2: Serviços internacionais; emissão fiscal (NF-e).
- P2: Criptografia/hash do código de acesso em repouso; migrar on_event shutdown para lifespan.

## Próximas tarefas
- Coletar credenciais de homologação (usuário, código de acesso, contrato, cartão, DR) e validar token real.
- Implementar download real de etiqueta PDF assíncrona.
