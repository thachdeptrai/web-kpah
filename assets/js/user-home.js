(function () {
  'use strict';
  var form=document.getElementById('profile-form');
  if (!form) return;
  var feedback=document.getElementById('profile-feedback');
  var sendOldButton=document.getElementById('send-old-code');
  var sendNewButton=document.getElementById('send-code');
  var emailInput=document.getElementById('email');
  var oldCodeInput=document.getElementById('old_email_code');
  var newCodeInput=document.getElementById('email_code');
  var phoneInput=document.getElementById('phone');
  var originalEmail=emailInput.value.trim().toLowerCase();
  var firstLink=!originalEmail;
  function show(message,good){feedback.textContent=message;feedback.className='form-feedback '+(good?'is-good':'is-bad');if(window.KpUI)window.KpUI.toast({type:good?'success':'error',title:good?'Cập nhật thành công':'Cập nhật chưa thành công',message:message});}
  function send(data){return fetch(form.dataset.endpoint,{method:'POST',credentials:'same-origin',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json().then(function(j){if(!r.ok&&!j.message)j.message='Không xử lý được yêu cầu.';return j;});});}
  function busy(button,loading,label,on){button.disabled=on;button.textContent=on?loading:label;}
  sendOldButton.addEventListener('click',function(){
    var data=new FormData(form);data.set('action','send_old_email_code');
    busy(sendOldButton,'Đang gửi…','Gửi mã Gmail cũ',true);
    send(data).then(function(r){show(r.message||'Đã xử lý yêu cầu.',!!r.ok);if(r.ok){oldCodeInput.value='';newCodeInput.value='';}})
      .catch(function(){show('Không kết nối được máy chủ. Hãy thử lại.',false);})
      .finally(function(){busy(sendOldButton,'Đang gửi…','Gửi mã Gmail cũ',false);});
  });
  sendNewButton.addEventListener('click',function(){
    var email=emailInput.value.trim().toLowerCase();
    if(!email||!emailInput.checkValidity()){show('Hãy nhập Gmail mới hợp lệ trước.',false);emailInput.focus();return;}
    if(email===originalEmail){show('Gmail mới phải khác Gmail đang liên kết.',false);return;}
    if(!/^\d{6}$/.test(oldCodeInput.value.trim())){show('Nhập mã 6 chữ số đã gửi đến Gmail hiện tại trước.',false);oldCodeInput.focus();return;}
    var data=new FormData(form);data.set('action','send_email_code');
    busy(sendNewButton,'Đang xác minh…',firstLink?'Gửi mã email':'Xác minh mã cũ & gửi mã mới',true);
    send(data).then(function(r){show(r.message||'Đã xử lý yêu cầu.',!!r.ok);})
      .catch(function(){show('Không kết nối được máy chủ. Hãy thử lại.',false);})
      .finally(function(){busy(sendNewButton,'Đang xác minh…',firstLink?'Gửi mã email':'Xác minh mã cũ & gửi mã mới',false);});
  });
  form.addEventListener('submit',function(event){
    event.preventDefault();
    var email=emailInput.value.trim().toLowerCase();
    if(email!==originalEmail&&!/^\d{6}$/.test(newCodeInput.value.trim())){show(firstLink?'Nhập mã xác minh đã gửi về email để liên kết.':'Để đổi Gmail, cần xác minh Gmail cũ và nhập mã xác minh Gmail mới.',false);newCodeInput.focus();return;}
    var data=new FormData(form);data.set('action','update');
    send(data).then(function(r){show(r.message||'Đã xử lý yêu cầu.',!!r.ok);if(r.ok){originalEmail=(r.email||'').toLowerCase();emailInput.value=r.email||emailInput.value;if(phoneInput)phoneInput.value=r.phone||'';oldCodeInput.value='';newCodeInput.value='';window.setTimeout(function(){window.location.reload();},900);}})
      .catch(function(){show('Không kết nối được máy chủ. Hãy thử lại.',false);});
  });
  var passwordForm=document.getElementById('change-password-form');
  if(passwordForm){
    var passwordFeedback=document.getElementById('password-feedback');
    var passwordSubmit=document.getElementById('change-password-submit');
    passwordForm.addEventListener('submit',function(event){
      event.preventDefault();
      var currentPassword=document.getElementById('current_password'),newPassword=document.getElementById('new_password'),confirmPassword=document.getElementById('confirm_password');
      function passwordMessage(message,good){passwordFeedback.textContent=message;passwordFeedback.className='form-feedback '+(good?'is-good':'is-bad');if(window.KpUI)window.KpUI.toast({type:good?'success':'error',title:good?'Đổi mật khẩu thành công':'Đổi mật khẩu chưa thành công',message:message});}
      if(newPassword.value!==confirmPassword.value){passwordMessage('Mật khẩu mới và phần xác nhận không trùng nhau.',false);confirmPassword.focus();return;}
      if(currentPassword.value===newPassword.value){passwordMessage('Mật khẩu mới phải khác mật khẩu hiện tại.',false);newPassword.focus();return;}
      passwordSubmit.disabled=true;var label=passwordSubmit.querySelector('span:first-child');if(label)label.textContent='Đang cập nhật…';
      fetch(passwordForm.dataset.endpoint,{method:'POST',credentials:'same-origin',body:new FormData(passwordForm),headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json().then(function(j){if(!r.ok&&!j.message)j.message='Không xử lý được yêu cầu.';return j;});})
        .then(function(r){passwordMessage(r.message||'Đã xử lý yêu cầu.',!!r.ok);if(r.ok)passwordForm.reset();})
        .catch(function(){passwordMessage('Không kết nối được máy chủ. Hãy thử lại.',false);})
        .finally(function(){passwordSubmit.disabled=false;if(label)label.textContent='Cập nhật mật khẩu';});
    });
  }
})();
