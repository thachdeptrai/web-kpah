/* Progressive mobile account navigation; desktop nav remains server rendered. */
(function(){
 'use strict';
 var button=document.querySelector('.portal-nav-toggle');
 var nav=document.getElementById('portal-nav-list');
 if(!button||!nav)return;
 function setOpen(open){
  nav.classList.toggle('is-open',!!open);
  button.setAttribute('aria-expanded',open?'true':'false');
 }
 button.addEventListener('click',function(){setOpen(button.getAttribute('aria-expanded')!=='true');});
 document.addEventListener('keydown',function(e){
  if(e.key==='Escape'&&button.getAttribute('aria-expanded')==='true'){
   setOpen(false);button.focus();
  }
 });
 document.addEventListener('pointerdown',function(e){
  if(button.getAttribute('aria-expanded')==='true'&&!button.contains(e.target)&&!nav.contains(e.target))setOpen(false);
 });
 nav.addEventListener('click',function(e){if(e.target.closest('a'))setOpen(false);});
 window.addEventListener('resize',function(){if(window.innerWidth>900)setOpen(false);});
})();
