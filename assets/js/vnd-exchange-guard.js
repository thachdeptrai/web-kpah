(function(){'use strict';
var f=document.getElementById('vnd-exchange-form');
var b=document.getElementById('vnd-exchange-button');
if(!f||!b)return;
var busy=false;
f.addEventListener('submit',function(e){
 if(busy){e.preventDefault();return;}
 if(!f.checkValidity())return;
 busy=true;b.disabled=true;b.textContent='Đang đổi mốc…';b.setAttribute('aria-busy','true');
});
})();