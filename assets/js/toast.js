'use strict';
/* Compatibility shim for older integrations. The shared renderer lives in ui-system.js. */
(function (window) {
  if (window.KpUI && typeof window.KpUI.toast === 'function') {
    window.kpToast = function (type, title, message, duration) {
      return typeof type === 'object'
        ? window.KpUI.toast(type)
        : window.KpUI.toast({type:type,title:title,message:message,duration:duration});
    };
  }
}(window));
