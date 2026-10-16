'use strict';
(function(){
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
