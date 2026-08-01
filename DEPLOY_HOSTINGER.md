# 🚀 Guia de Deploy no Hostinger

## Preparação

### 1. Pré-requisitos
- Conta no Hostinger com plano que suporte Node.js
- Git instalado no servidor
- Node.js 18+ instalado
- Acesso SSH ao servidor

### 2. Variáveis de Ambiente
Crie um arquivo `.env` na raiz do projeto com as variáveis do `.env.example`:

```bash
VITE_API_URL=https://api.seudominio.com
VITE_PB_URL=https://pocketbase.seudominio.com
NODE_ENV=production
```

## Instalação

### Via SSH no Hostinger:

```bash
# Conectar ao servidor
ssh usuario@seu-ip-servidor.com

# Navegar até a pasta pública
cd ~/public_html

# Clonar o repositório
git clone https://github.com/Giovane-th/InNovaEnviosapp.git .

# Instalar dependências
npm install

# Criar arquivo .env com suas configurações
nano .env

# Fazer build do frontend
npm run build

# Copiar dist para a pasta pública
cp -r dist/apps/web/* .
```

## Estrutura de Arquivos no Hostinger

```
public_html/
├── index.html          (gerado pelo build)
├── assets/             (CSS, JS otimizado)
├── .env                (variáveis - NÃO enviar pro Git)
├── .htaccess           (rewrite rules)
└── (outros arquivos estáticos)
```

## Configuração do Backend (API)

Se usar um subdomínio para a API:

```bash
# Criar pasta para API
mkdir ~/api
cd ~/api

# Clonar apenas a pasta api
git clone https://github.com/Giovane-th/InNovaEnviosapp.git
cp -r InNovaEnviosapp/apps/api/* .

npm install
node src/main.js &
```

## Configuração do PocketBase

O PocketBase precisa rodar como um serviço:

```bash
# Download do PocketBase (se necessário)
wget https://github.com/pocketbase/pocketbase/releases/download/v0.25.0/pocketbase_0.25.0_linux_amd64.zip
unzip pocketbase_0.25.0_linux_amd64.zip

# Dar permissão
chmod +x ./pocketbase

# Rodar como daemon
./pocketbase serve --http=0.0.0.0:8090 &
```

## Verificação

```bash
# Testar se está rodando
curl http://localhost:3000
curl http://localhost:8090/api/

# Ver logs
tail -f /var/log/syslog
```

## Otimizações

### 1. Gzip Compression
O `.htaccess` já tem configurado!

### 2. Cache
Assets (CSS, JS, images) são cacheados por 1 ano.
HTML é cacheado por 1 hora.

### 3. HTTPS
Force HTTPS via `.htaccess`

### 4. CDN (Opcional)
Use Cloudflare para:
- Cache global
- DDOS protection
- Performance

## Troubleshooting

### Erro 404 em rotas
Verifique se `.htaccess` está na pasta pública.

### CORS errors
Configure `VITE_API_URL` corretamente.

### Falha ao conectar ao PocketBase
Verifique firewall e porta 8090.

## Monitoramento

```bash
# Ver processos rodando
ps aux | grep node

# Reiniciar serviço
pkill -f "node src/main.js"
node src/main.js &
```

## Suporte
Para dúvidas, consulte:
- Docs Hostinger: https://support.hostinger.com
- Docs Vite: https://vitejs.dev/guide/ssr.html
- Docs PocketBase: https://pocketbase.io/docs/
