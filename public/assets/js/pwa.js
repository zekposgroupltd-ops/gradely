/* GRADELY PWA client: service worker registration, install prompt, iOS hint, update prompt. */
(function () {
  'use strict';

  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
    window.navigator.standalone === true;
  if (standalone) document.documentElement.classList.add('is-standalone');

  function setHidden(selector, hidden) {
    document.querySelectorAll(selector).forEach(function (el) { el.hidden = hidden; });
  }
  function store(key, value) {
    try { if (value === undefined) return window.localStorage.getItem(key); window.localStorage.setItem(key, value); } catch (e) { return null; }
    return null;
  }

  /* ---- Install button (Chrome/Edge/Android/desktop) ---- */
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    if (!standalone) setHidden('[data-pwa-install]', false);
  });
  document.addEventListener('click', function (event) {
    var btn = event.target.closest ? event.target.closest('[data-pwa-install]') : null;
    if (!btn || !deferredPrompt) return;
    var prompt = deferredPrompt;
    deferredPrompt = null;
    prompt.prompt();
    prompt.userChoice.then(function () { setHidden('[data-pwa-install]', true); });
  });
  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    setHidden('[data-pwa-install]', true);
    setHidden('[data-pwa-ios]', true);
  });

  /* ---- iPhone / iPad: browsers there cannot show an install prompt, so explain the steps ---- */
  var ua = window.navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(ua) || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  if (isIos && !standalone && store('gradely.iosHint') !== 'dismissed') setHidden('[data-pwa-ios]', false);
  document.addEventListener('click', function (event) {
    if (event.target.closest && event.target.closest('[data-pwa-ios-dismiss]')) {
      store('gradely.iosHint', 'dismissed');
      setHidden('[data-pwa-ios]', true);
    }
  });

  /* ---- Service worker + "update available" prompt ---- */
  function showUpdate(worker) {
    if (document.getElementById('pwa-update')) return;
    var bar = document.createElement('div');
    bar.id = 'pwa-update';
    bar.className = 'toast';
    bar.setAttribute('role', 'status');
    var text = document.createElement('span');
    text.textContent = 'A new version of GRADELY is available.';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-primary btn-sm';
    btn.textContent = 'Refresh';
    btn.addEventListener('click', function () { worker.postMessage({ type: 'SKIP_WAITING' }); });
    bar.appendChild(text);
    bar.appendChild(btn);
    document.body.appendChild(bar);
  }

  var swUrl = document.body ? document.body.getAttribute('data-sw') : null;
  if ('serviceWorker' in navigator && swUrl) {
    var reloading = false;
    // A first-ever install also fires controllerchange (clients.claim). Reloading then would wipe whatever the
    // person is typing, so only reload when a worker was already controlling this page (i.e. a real update).
    var hadController = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (reloading || !hadController) return;
      reloading = true;
      window.location.reload();
    });
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(swUrl).then(function (reg) {
        if (reg.waiting && navigator.serviceWorker.controller) showUpdate(reg.waiting);
        reg.addEventListener('updatefound', function () {
          var nw = reg.installing;
          if (!nw) return;
          nw.addEventListener('statechange', function () {
            if (nw.state === 'installed' && navigator.serviceWorker.controller) showUpdate(nw);
          });
        });
      }).catch(function () { /* the app works without a service worker */ });
    });
  }
})();
