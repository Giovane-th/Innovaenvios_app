# In'Nova Envios - Deploy Guide

## 📋 Checklist antes do Deploy

- [ ] Arquivo `.env` criado e configurado
- [ ] Variáveis de ambiente corretas
- [ ] Build testado localmente (`npm run build`)
- [ ] HTTPS ativado no domínio
- [ ] DNS apontando para Hostinger
- [ ] Acesso SSH configurado
- [ ] Node.js 18+ instalado no servidor

## 🚀 Quick Start Deploy

### 1. Via SSH Hostinger

```bash
ssh seu-usuario@seu-host.com
cd ~/public_html
git clone https://github.com/Giovane-th/InNovaEnviosapp.git .
npm install
npm run build
cp -r dist/apps/web/* .
cp .htaccess .
```

### 2. Via File Manager Hostinger

1. Faça upload de todos os arquivos
2. Via terminal no painel, execute:
   ```bash
   npm install
   npm run build
   ```
3. Copie arquivos de `dist/apps/web` para raiz

### 3. Via GitHub Actions (CI/CD)

Em breve! Configure secrets no GitHub.

## 🔐 Segurança

- ✅ `.env` nunca é commitado (veja `.gitignore`)
- ✅ Senhas/tokens em `.env.example` como placeholders
- ✅ HTTPS forçado via `.htaccess`
- ✅ Headers de segurança configurados
- ✅ CORS configurado apenas para domínios permitidos

## 📊 Performance

- ✅ Gzip compression ativado
- ✅ Cache agressivo para assets (1 ano)
- ✅ Cache inteligente para HTML (1 hora)
- ✅ Build otimizado com Vite
- ✅ Code splitting automático

## 🔧 Troubleshooting

### Erro "Cannot find module"
```bash
rm -rf node_modules package-lock.json
npm install
```

### Erro 404 em rotas SPA
Verifique se `.htaccess` está no root público.

### CORS errors
```
Atualize VITE_API_URL no .env
```

### Permissões
```bash
chmod 755 -R .
chmod 644 *.html *.css *.js
```

## 📞 Suporte

Para problemas:
1. Verifique logs: `tail -f ~/logs/error.log`
2. Teste localmente: `npm run dev`
3. Consulte documentação dos serviços

## 🎉 Próximas Etapas

- [ ] Configurar SSL/TLS
- [ ] Setup de email
- [ ] Backup automático
- [ ] Monitoramento de uptime
- [ ] Analytics/Logs
