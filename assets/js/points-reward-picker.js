(function(){
  'use strict';
  var source=document.getElementById('point-reward-catalog');
  var dialog=document.getElementById('point-reward-picker');
  if(!source||!dialog)return;
  var catalog;
  try{catalog=JSON.parse(source.textContent||'{}');}catch(e){return;}
  var items=Array.isArray(catalog.items)?catalog.items:[];
  var shopItems=Array.isArray(catalog.shop_items)?catalog.shop_items:[];
  var options=Array.isArray(catalog.options)?catalog.options:[];
  var editors=Array.prototype.slice.call(document.querySelectorAll('[data-point-editor]'));
  var search=document.getElementById('point-picker-search');
  var itemContainer=document.getElementById('point-picker-items');
  var optionContainer=document.getElementById('point-picker-options');
  var counter=document.getElementById('point-picker-count');
  var more=document.getElementById('point-picker-more');
  var paramBox=document.getElementById('point-picker-selected-option');
  var optionTitle=document.getElementById('point-picker-option-title');
  var paramInput=document.getElementById('point-picker-param-value');
  var addOption=document.getElementById('point-picker-add-option');
  var active=null;
  var option=null;
  var selectedOptionButton=null;
  var tab='items';
  var matches=[];
  var shown=0;
  var pageSize=64;
  function elem(tag,cl,text){
    var n=document.createElement(tag);
    if(cl)n.className=cl;
    if(text!==undefined)n.textContent=String(text);
    return n;
  }
  function norm(value){
    var s=String(value===undefined?'':value).toLowerCase();
    if(s.normalize)s=s.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    return s.replace(/đ/g,'d').trim();
  }
  function field(editor,name){return editor.querySelector('[name="'+name+'"]');}
  function selections(editor){
    var input=editor.querySelector('[data-point-options]');
    try{
      var parsed=JSON.parse(input.value||'[]');
      return Array.isArray(parsed)?parsed:[];
    }catch(e){return [];}
  }
  function updateSelected(editor){
    var container=editor.querySelector('[data-point-selection]');
    container.textContent='';
    var itemId=field(editor,'item_id').value;
    var iconId=field(editor,'icon_id').value;
    var itemSource=field(editor,'item_source')?field(editor,'item_source').value:'data_item';
    var item=(itemSource==='data_shop'?shopItems:items).find(function(entry){return String(entry.id)===String(itemId);});
    var first=elem('div','point-picker-meta');
    var image=elem('span','point-picker-small-icon');
    if(iconId!==''&&/^\d{1,6}$/.test(iconId)){
      var img=elem('img');
      img.src='/res/icon/'+Number(iconId)+'.png';
      img.alt='';
      img.loading='lazy';
      img.decoding='async';
      img.width=38;img.height=38;
      image.appendChild(img);
    }else{image.textContent='✦';}
    first.appendChild(image);
    first.appendChild(elem('span','',item?(itemSource==='data_shop'?'Data Shop · ':'data_item · ')+item.name+' (#'+item.id+')':'Item #'+(itemId||'—')+' · Icon #'+(iconId||'—')));
    container.appendChild(first);
    var lines=selections(editor);
    lines.forEach(function(entry){
      var info=options.find(function(o){return o.id===entry.id;});
      var span=elem('span','point-picker-tag',(info?info.name:'Option #'+entry.id)+' +'+entry.param+(info&&info.percent?'%':''));
      var remove=elem('button','', '×');
      remove.type='button';remove.setAttribute('aria-label','Xóa option '+entry.id);
      remove.addEventListener('click',function(){
        editor.querySelector('[data-point-options]').value=JSON.stringify(lines.filter(function(x){return x.id!==entry.id;}));
        var area=field(editor,'option_lines');
        if(area)area.value=displayLines(selections(editor));
        updateSelected(editor);
      });
      span.appendChild(remove);
      container.appendChild(span);
    });
  }
  function displayLines(entries){
    return entries.map(function(entry){
      var info=options.find(function(o){return o.id===entry.id;});
      if(!info)return '';
      var value=entry.param*(Number(info.display_multiplier)||1);
      return '#'+entry.id+' '+info.name+' +'+value.toLocaleString('vi-VN')+(info.percent?'%':'');
    }).filter(Boolean).join('\n');
  }
  function openPicker(editor){
    active=editor;option=null;paramBox.hidden=true;search.value='';
    chooseTab('items');
    if(typeof dialog.showModal==='function')dialog.showModal();
    else dialog.setAttribute('open','');
    search.focus();
  }
  function closePicker(){
    if(typeof dialog.close==='function')dialog.close();
    else dialog.removeAttribute('open');
    active=null;
  }
  function chooseTab(name){
    if(name!=='items'&&name!=='shop'&&name!=='options')return;
    if(tab!==name)search.value=''; // Do not reuse the item's filter on Options.
    tab=name;shown=0;option=null;paramBox.hidden=true;
    if(selectedOptionButton){
      selectedOptionButton.setAttribute('aria-pressed','false');
      selectedOptionButton=null;
    }
    itemContainer.hidden=name==='options';
    optionContainer.hidden=name!=='options';
    search.placeholder=name==='items'?'Tìm data_item hoặc #ID':(name==='shop'?'Tìm Data Shop hoặc #ID':'Tìm chỉ số / option hoặc #ID');
    dialog.querySelectorAll('[data-point-tab]').forEach(function(button){
      var selected=button.getAttribute('data-point-tab')===tab;
      button.setAttribute('aria-pressed',String(selected));
      button.setAttribute('aria-selected',String(selected));
      button.tabIndex=selected?0:-1;
    });
    render(false);
  }
  function itemRow(item){
    var root=elem('div','point-picker-item');
    var visual=elem('span','point-picker-icon');
    if(item.image&&/^\/res\/icon\/\d+\.png$/.test(item.image)){
      var pic=elem('img');pic.src=item.image;pic.width=44;pic.height=44;
      pic.alt='';pic.loading='lazy';visual.appendChild(pic);
    }else{visual.textContent='✦';}
    root.appendChild(visual);
    var heading=elem('div','point-picker-item-heading');
    heading.appendChild(elem('strong','',item.name));
    heading.appendChild(elem('small','', (tab==='shop'?'data_shop · tab '+item.tab:'data_item')+' · #'+item.id+' · '+item.type_name+' · Icon #'+item.icon_id));
    root.appendChild(heading);
    var actions=elem('div','point-picker-item-actions');
    var icon=elem('button','','Chọn icon');
    icon.type='button';
    icon.addEventListener('click',function(){
      field(active,'icon_id').value=item.icon_id;
      updateSelected(active);closePicker();
    });
    var use=elem('button','','Chọn item');
    use.type='button';
    use.addEventListener('click',function(){
      field(active,'item_id').value=item.id;
      field(active,'icon_id').value=item.icon_id;
      if(field(active,'item_source'))field(active,'item_source').value=tab==='shop'?'data_shop':'data_item';
      active.querySelector('[data-point-options]').value='[]';
      var lines=field(active,'option_lines');if(lines)lines.value='';
      updateSelected(active);closePicker();
    });
    actions.appendChild(icon);actions.appendChild(use);root.appendChild(actions);
    return root;
  }
  function optionRow(meta){
    var button=elem('button','point-picker-option','');
    button.type='button';
    button.setAttribute('aria-pressed','false');
    var labels=elem('span','point-picker-item-heading');
    labels.appendChild(elem('strong','',meta.name));
    labels.appendChild(elem('small','','#'+meta.id+(meta.percent?' · %':'')+' · '+(meta.description||'Chỉ số')));
    button.appendChild(labels);
    button.addEventListener('click',function(){
      if(selectedOptionButton)selectedOptionButton.setAttribute('aria-pressed','false');
      selectedOptionButton=button;
      button.setAttribute('aria-pressed','true');
      option=meta;paramBox.hidden=false;
      optionTitle.textContent=meta.name+' (#'+meta.id+')';
      paramInput.min=meta.min;
      paramInput.max=meta.max;
      paramInput.value=String(meta.min);
      paramInput.focus();
    });
    return button;
  }
  function render(append){
    if(!append){
      if(selectedOptionButton){
        selectedOptionButton.setAttribute('aria-pressed','false');
        selectedOptionButton=null;
      }
      option=null;paramBox.hidden=true;
      var q=norm(search.value);
      var id=q.match(/^#?(\d+)$/);
      var set=tab==='items'?items:(tab==='shop'?shopItems:options);
      matches=set.filter(function(x){
        return !q||norm(x.name).indexOf(q)>=0||String(x.id).indexOf(q.replace(/^#/,''))>=0;
      });
      if(id){
        var number=Number(id[1]);
        matches.sort(function(a,b){return Number(b.id===number)-Number(a.id===number);});
      }
      shown=0;
      (tab==='options'?optionContainer:itemContainer).textContent='';
    }
    var view=tab==='options'?optionContainer:itemContainer;
    matches.slice(shown,shown+pageSize).forEach(function(entry){
      view.appendChild(tab==='options'?optionRow(entry):itemRow(entry));
    });
    shown=Math.min(matches.length,shown+pageSize);
    counter.textContent='Hiển thị '+shown+' / '+matches.length+' '+(tab==='options'?'option':(tab==='shop'?'ShopTemplate':'vật phẩm'));
    more.hidden=shown>=matches.length;
  }
  editors.forEach(function(editor){
    var launch=editor.querySelector('[data-point-open]');
    launch.addEventListener('click',function(){openPicker(editor);});
    updateSelected(editor);
  });
  dialog.querySelectorAll('[data-point-close]').forEach(function(b){
    b.addEventListener('click',closePicker);
  });
  dialog.addEventListener('close',function(){active=null;});
  dialog.querySelectorAll('[data-point-tab]').forEach(function(b){
    b.addEventListener('click',function(){chooseTab(b.getAttribute('data-point-tab'));});
    b.addEventListener('keydown',function(e){
      if(e.key!=='ArrowLeft'&&e.key!=='ArrowRight')return;
      e.preventDefault();
      var order=['items','shop','options'];var next=order[(order.indexOf(b.getAttribute('data-point-tab'))+(e.key==='ArrowRight'?1:2))%3];
      chooseTab(next);
      dialog.querySelectorAll('[data-point-tab]').forEach(function(t){
        if(t.getAttribute('data-point-tab')===next)t.focus();
      });
    });
  });
  search.addEventListener('input',function(){render(false);});
  more.addEventListener('click',function(){render(true);});
  addOption.addEventListener('click',function(){
    if(!active||!option)return;
    var param=Number(paramInput.value);
    if(!Number.isSafeInteger(param)||param<Number(option.min)||param>Number(option.max)){
      paramInput.setCustomValidity('Giá trị phải từ '+option.min+' đến '+option.max);
      paramInput.reportValidity();paramInput.setCustomValidity('');return;
    }
    if(field(active,'item_source')&&field(active,'item_source').value==='data_shop'){
      optionTitle.textContent='ShopTemplate không hỗ trợ option data_item.';return;
    }
    if(!field(active,'item_id').value){
      optionTitle.textContent='Cần chọn vật phẩm trước khi thêm option.';
      return;
    }
    var entries=selections(active);
    var found=entries.find(function(x){return x.id===option.id;});
    if(!found&&entries.length>=12){
      optionTitle.textContent='Tối đa 12 option cho mỗi quà.';return;
    }
    if(found)found.param=param;
    else entries.push({id:Number(option.id),param:param});
    active.querySelector('[data-point-options]').value=JSON.stringify(entries);
    field(active,'option_lines').value=displayLines(entries);
    updateSelected(active);
    optionTitle.textContent='Đã thêm '+option.name+'.';
  });
  document.addEventListener('keydown',function(event){
    if(event.key==='Escape'&&dialog.hasAttribute('open'))closePicker();
  });
})();
