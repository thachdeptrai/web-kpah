(function(){'use strict';
var form=document.getElementById('bank-create-form');
var button=document.getElementById('bank-create-button');
if(!form||!button)return;
var submitting=false;
form.addEventListener('submit',function(e){
 if(submitting){e.preventDefault();return;}
 if(!form.checkValidity())return;
 submitting=true;
 button.disabled=true;
 button.textContent='Đang tạo QR, vui lòng chờ…';
 button.setAttribute('aria-busy','true');
});
})();