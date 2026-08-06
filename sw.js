// Service worker mínimo — existe só para o navegador considerar o app
// instalável (ícone na tela inicial). Não faz cache de nada de propósito:
// o app já é servido por PHP dinâmico, cache agressivo aqui só criaria
// risco de mostrar tela desatualizada depois de um deploy.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
