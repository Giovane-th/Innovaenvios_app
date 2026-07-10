#!/bin/bash

# Script de Setup para Hostinger
# Use: bash HOSTINGER_SETUP.sh

echo "🚀 Iniciando setup para Hostinger..."

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar Node.js
if ! command -v node &> /dev/null; then
    echo -e "${RED}✗ Node.js não encontrado${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Node.js: $(node -v)${NC}"
echo -e "${GREEN}✓ NPM: $(npm -v)${NC}"

# Instalar dependências
echo "📦 Instalando dependências..."
npm install

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Erro ao instalar dependências${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Dependências instaladas${NC}"

# Verificar .env
if [ ! -f .env ]; then
    echo "📝 Criando .env a partir de .env.example..."
    cp .env.example .env
    echo -e "${RED}⚠️  EDITE .env com suas configurações!${NC}"
fi

# Build
echo "🔨 Fazendo build..."
npm run build

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Erro no build${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Build concluído${NC}"

# Copiar para public_html
echo "📋 Copiando arquivos para public_html..."
cp -r dist/apps/web/* .
cp .htaccess .

echo -e "${GREEN}✓ Arquivos copiados${NC}"

echo -e "${GREEN}\n✅ Setup completo! Seu projeto está pronto para deploy.${NC}"
echo -e "${GREEN}Acesse: https://seudominio.com${NC}"
