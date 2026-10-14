(function () {
  'use strict';
  var manual = document.getElementById('manual-grant');
  if (manual) {
    manual.addEventListener('submit', function (event) {
      var submit = manual.querySelector('button[type=submit]');
      if (submit && submit.disabled) { event.preventDefault(); return; }
      var character = manual.querySelector('[name=char_id]');
      var currency = manual.querySelector('[name=currency]');
      var mode = manual.querySelector('[name=mode]');
      var amount = manual.querySelector('[name=quantity]');
      var c = character && character.options[character.selectedIndex];
      var m = currency && currency.options[currency.selectedIndex];
      var name = c ? c.text.split(' · ')[0] : '—';
      var msg = 'CỘNG TÀI NGUYÊN CHO NHÂN VẬT\n\n' +
        'Nhân vật: ' + name + '\n' +
        'Tài nguyên: ' + (m ? m.text : '—') + '\n' +
        'Số ' + (mode && mode.value === 'vnd' ? 'VNĐ' : 'tài nguyên') + ': ' + (amount ? amount.value : '—') + '\n\n' +
        'Chỉ tiếp tục khi nhân vật đã THOÁT GAME và bạn đã xác minh chính xác số cộng.';
      if (!window.confirm(msg)) { event.preventDefault(); return; }
      if (submit) { submit.disabled = true; submit.textContent = 'Đang xử lý…'; }
    });
  }
  var card = document.getElementById('card-topup-form');
  var estimate = document.getElementById('tp-estimate');
  if (card && estimate) {
    var amountField = card.querySelector('[name=amount]');
    var currencyField = card.querySelector('[name=currency]');
    var telcoField=card.querySelector('[name=telco]');
    var packages={'10000':{luong:250,luonglock:500},'50000':{luong:1500,luonglock:3000},'100000':{luong:3500,luonglock:7000},'500000':{luong:20000,luonglock:40000},'1000000':{luong:45000,luonglock:90000}};
    var tick=0;
    var recalc=function(){
      var amount=Number(amountField.value),rate=Number(card.getAttribute('data-rate-'+currencyField.value)||0);
      var telco=telcoField.value;
      var current=++tick;
      if(!Number.isSafeInteger(amount)||!packages[String(amount)]||!Number.isSafeInteger(rate)||rate<1){
        estimate.textContent='Nhập mệnh giá để xem phí thẻ thực tế.';return;
      }
      estimate.textContent='Đang lấy phí từ TheCaoSieuToc…';
      fetch('/api/card-fees?telco='+encodeURIComponent(telco)+'&amount='+amount,{credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(quote){
          if(current!==tick)return;
          if(!quote.ok||!Number.isSafeInteger(Number(quote.net))){estimate.textContent='Chưa có báo giá cho loại thẻ này.';return;}
          var units=Math.floor(packages[String(amount)][currencyField.value]*Number(quote.net)/amount);
          estimate.textContent='Chiết khấu đối tác: '+quote.fee+'% · Thực nhận dự kiến: '+Number(quote.net).toLocaleString('vi-VN')+'đ · Dự kiến '+units.toLocaleString('vi-VN')+' '+(currencyField.value==='luonglock'?'Lượng khóa':'Lượng thường')+'.';
        }).catch(function(){if(current===tick)estimate.textContent='Chưa tải được bảng phí từ đối tác.';});
    };
    var timer;
    function scheduled(){clearTimeout(timer);timer=setTimeout(recalc,350);}
    amountField.addEventListener('input',scheduled);
    currencyField.addEventListener('change',scheduled);
    telcoField.addEventListener('change',scheduled);
    recalc();
  }
})();
