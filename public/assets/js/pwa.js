/* Pendaftaran service worker + tombol "Pasang aplikasi". */
(function () {
  'use strict';
  var me = document.currentScript;
  var swUrl = me && me.getAttribute('data-sw');
  var scope = me && me.getAttribute('data-scope');

  if ('serviceWorker' in navigator && swUrl) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(swUrl, { scope: scope || undefined }).catch(function () {});
    });
  }

  var deferred = null;
  var btn = document.getElementById('installBtn');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (btn) btn.classList.remove('d-none');
  });
  if (btn) {
    btn.addEventListener('click', function () {
      if (!deferred) return;
      deferred.prompt();
      deferred.userChoice.finally(function () { deferred = null; btn.classList.add('d-none'); });
    });
  }
  window.addEventListener('appinstalled', function () { if (btn) btn.classList.add('d-none'); });
})();
