(() => {
  'use strict';
  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js', {scope: '/'}).catch(error => console.warn('App setup unavailable:', error));
    });
  }
  const button = document.getElementById('installApp');
  const panel = document.querySelector('.docs-install');
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  if (standalone && panel) panel.hidden = true;
  let installPrompt = null;
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
    if (button && !standalone) button.hidden = false;
  });
  if (button) button.addEventListener('click', async () => {
    if (!installPrompt) return;
    const prompt = installPrompt;
    installPrompt = null;
    button.disabled = true;
    try { await prompt.prompt(); await prompt.userChoice; }
    catch (error) { console.warn('App installation unavailable:', error); }
    finally { button.disabled = false; button.hidden = true; }
  });
  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    if (panel) panel.hidden = true;
  });
})();
