'use strict';
/* Pure Node fake-browser tests of bulk moderation picker. No DB or network. */
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');
const script=fs.readFileSync(path.join(__dirname,'../assets/js/admin-game-accounts.js'),'utf8');
let lastPrompt='';
function test(condition,label){assert.ok(condition,label);process.stdout.write('PASS: '+label+'\n');}
function makeForm(names,ids) {
  const output={textContent:''};
  const csv={value:''};
  const submitButton={disabled:false,setAttribute(n,v){this[n]=v;}};
  const inputs=[];
  const items=[];
  const selectors=[];
  const nameField={value:'',custom:'',
    getAttribute(){return 'MO BAN TAT CA TAI KHOAN';},
    setCustomValidity(v){this.custom=v;},
    reportValidity(){this.reported=true;},
    addEventListener(event,cb){this['on'+event]=cb;}
  };
  const filter={value:'',addEventListener(event,cb){this['on'+event]=cb;}};
  names.forEach((name,i)=>{
    const item={hidden:false,attrs:{'data-search':name.toLowerCase()+' '+ids[i]},
      getAttribute(n){return this.attrs[n];}};
    const input={checked:false,value:String(ids[i]),name:'selected_ids[]',
      closest(){return item;},removeAttribute(n){if(n==='name')this.name=null;},
      hasAttribute(n){return n==='data-bulk-checkbox';}};
    inputs.push(input);items.push(item);
  });
  ['all','none'].forEach(v=>selectors.push({
    getAttribute(n){return n==='data-bulk-select'?v:'';},
    addEventListener(n,cb){this['on'+n]=cb;}
  }));
  const form={
    getAttribute(n){return n==='data-action-label'?'Mở ban ALL ACC':'';},
    classList:{contains(n){return n==='gam-bulk-form';}},
    addEventListener(event,cb){this['on'+event]=cb;},
    querySelector(selector){
      const map={
        '[data-bulk-count]':output,
        '[data-selected-csv]':csv,
        '[data-bulk-search]':filter,
        'button[type="submit"]':submitButton,
        '[data-exact-name]':nameField
      };
      return map[selector]||null;
    },
    querySelectorAll(selector){
      const map={'[data-bulk-checkbox]':inputs,'[data-bulk-item]':items,
        '[data-bulk-select]':selectors,'[data-bulk-checkbox]:checked':inputs.filter(i=>i.checked)};
      return map[selector]||[];
    }
  };
  return {form,inputs,items,filter,selectors,output,csv,submitButton,nameField};
}
const acc=makeForm(['BannedUserA','KeepBannedUser','BannedUserC'],[123,124,125]);
const chr=makeForm(['BannedPlayer','KeepPlayer'],[456,457]);
vm.runInNewContext(script,{
  document:{querySelectorAll(selector){
    if(selector==='.gam-bulk-form'||selector==='.gam-mod-form')return [acc.form,chr.form];
    return [];
  }},
  window:{confirm(v){lastPrompt=v;return true;}}
},{filename:'admin-game-accounts.js'});
test(acc.submitButton.disabled&&acc.csv.value===''&&acc.output.textContent.includes('Đã chọn 0'),
  'default selection is NONE, no implicit global unban');
test(acc.inputs.every(i=>i.name===null)&&chr.inputs.every(i=>i.name===null),
  'compact CSV submission avoids thousands of named form inputs');
acc.selectors[0].onclick();
test(acc.inputs.every(i=>i.checked)&&acc.csv.value==='123,124,125',
  'select-all adds all currently visible banned accounts');
acc.inputs[1].checked=false;
acc.form.onchange({target:acc.inputs[1]});
test(acc.csv.value==='123,125'&&acc.output.textContent.includes('Giữ ban 1'),
  'unchecking special account excludes its ID from unban while keeping others');
acc.nameField.value='MO BAN TAT CA TAI KHOAN';
let prevented=false;
acc.form.onsubmit({preventDefault(){prevented=true;}});
test(!prevented&&lastPrompt.includes('đúng 2 đối tượng')&&lastPrompt.includes('không tích'),
  'bulk confirmation shows exact selected count and warns unchecked stay banned');
acc.filter.value='keep';
acc.filter.oninput();
test(acc.items[0].hidden&&!acc.items[1].hidden&&acc.items[2].hidden,
  'filter only reveals matching accounts');
acc.selectors[0].onclick();
test(acc.csv.value==='123,124,125',
  'select all visible only adds matching account and does not erase hidden picks');
acc.selectors[1].onclick();
test(acc.csv.value===''&&acc.submitButton.disabled,
  'clear-all removes selected targets and prevents empty bulk submit');
chr.selectors[0].onclick();
test(chr.csv.value==='456,457'&&acc.csv.value==='',
  'character picker is completely independent from account picker');
chr.inputs[1].checked=false;chr.form.onchange({target:chr.inputs[1]});
test(chr.csv.value==='456','unchecked character is excluded from submitted player IDs');
console.log('PASS: selected-only bulk account/character UI controls');
