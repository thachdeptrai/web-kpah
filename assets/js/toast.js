'use strict';
(function(){
  // Shared toast placement on every page that loads this script.
  var style=document.createElement('style');
  style.textContent='.kp-toast-stack{position:fixed!important;top:calc(16px + env(safe-area-inset-top,0px))!important;right:calc(16px + env(safe-area-inset-right,0px))!important;bottom:auto!important;left:auto!important;z-index:2147483000!important;display:flex;flex-direction:column;align-items:flex-end;gap:10px;width:min(380px,calc(100vw - 32px));max-height:calc(100dvh - 32px);overflow-y:auto;pointer-events:none}.kp-toast-stack>.kp-toast{box-sizing:border-box;position:relative!important;top:auto!important;right:auto!important;bottom:auto!important;left:auto!important;width:100%;max-width:100%;display:flex;align-items:flex-start;gap:10px;padding:13px 14px;border:1px solid #e0e8f3;border-radius:13px;background:#fff;color:#273850;box-shadow:0 10px 30px rgba(37,59,95,.15);pointer-events:auto}.kp-toast-stack>.kp-toast[data-type=success]{border-left:4px solid #32a87a}.kp-toast-stack>.kp-toast[data-type=error]{border-left:4px solid #e66b6b}.kp-toast-stack>.kp-toast>div{flex:1;min-width:0;overflow-wrap:anywhere}.kp-toast-stack>.kp-toast strong{display:block;font-size:13px;line-height:1.4}.kp-toast-stack>.kp-toast p{margin:3px 0 0;font-size:12px;line-height:1.5}.kp-toast-stack>.kp-toast-icon{display:grid;place-items:center;flex:none;width:22px;height:22px;border-radius:50%;background:#e7f6ee;color:#218a61;font-weight:800}.kp-toast-stack>.kp-toast[data-type=error] .kp-toast-icon{background:#fff0f0;color:#bd4545}.kp-toast-stack>.kp-toast-close{flex:none;border:0;background:transparent;color:#718096;font-size:19px;line-height:1;cursor:pointer}@media(max-width:480px){.kp-toast-stack{top:calc(10px + env(safe-area-inset-top,0px))!important;right:calc(10px + env(safe-area-inset-right,0px))!important;width:min(350px,calc(100vw - 20px))}}';
  document.head.appendChild(style);
  var stack=document.createElement('div');stack.className='kp-toast-stack';
  stack.setAttribute('role','region');stack.setAttribute('aria-label','Thông báo thao tác');
  document.body.appendChild(stack);
  window.kpToast=function(type,title,reason){
    type=type==='error'?'error':'success';
    var el=document.createElement('section');el.className='kp-toast';el.dataset.type=type;
    el.setAttribute('role',type==='error'?'alert':'status');
    var icon=document.createElement('span');icon.className='kp-toast-icon';icon.textContent=type==='error'?'!':'✓';
    var content=document.createElement('div');var bold=document.createElement('strong');bold.textContent=String(title|| (type==='error'?'Thất bại':'Thành công'));
    var p=document.createElement('p');p.textContent=String(reason||'');content.append(bold,p);
    var close=document.createElement('button');close.className='kp-toast-close';close.type='button';close.textContent='×';close.setAttribute('aria-label','Đóng thông báo');
    close.addEventListener('click',function(){el.remove()});el.append(icon,content,close);stack.appendChild(el);
    window.setTimeout(function(){el.remove()},6500);
  };
  document.querySelectorAll('[data-toast-title]').forEach(function(node){
    window.kpToast(node.dataset.toastType,node.dataset.toastTitle,node.dataset.toastMessage);node.remove();
  });
  var search=new URLSearchParams(window.location.search);
  if(search.get('expired')==='1')window.kpToast('error','Phiên đã hết hạn','Vui lòng đăng nhập lại để tiếp tục.');
  // Existing asynchronous form flows can call kpToast() with the server's own result.
})();
