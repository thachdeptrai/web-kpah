(function(){
 'use strict';
 var target=document.getElementById('payos-embed');
 if(!target)return;
 var checkoutUrl=target.getAttribute('data-checkout');
 var code=target.getAttribute('data-order');
 var message=document.getElementById('bank-payment-status');
 var completed=false;
 function poll(){
  if(completed)return;
  fetch('/api/payos-status?code='+encodeURIComponent(code),{credentials:'same-origin',cache:'no-store'})
   .then(function(r){return r.json();})
   .then(function(data){
    if(!data.ok||completed)return;
    message.textContent=data.label||'Đang chờ thanh toán';
    if(data.paid){completed=true;window.location.replace('/nap-ngan-hang');}
   }).catch(function(){});
 }
 if(window.PayOSCheckout&&typeof window.PayOSCheckout.usePayOS==='function'){
  try {
   var widget=window.PayOSCheckout.usePayOS({
    RETURN_URL:window.location.origin+'/nap-ngan-hang',
    ELEMENT_ID:'payos-embed',
    CHECKOUT_URL:checkoutUrl,
    embedded:true,
    onSuccess:poll,onCancel:poll,onExit:poll
   });
   widget.open();
  }catch(e){message.textContent='Chưa nhúng được QR. Mở liên kết thanh toán payOS bên dưới.';}
 }else{message.textContent='Không tải được thư viện payOS. Dùng liên kết bên dưới.';}
 poll();
 window.setInterval(poll,5000);
})();
