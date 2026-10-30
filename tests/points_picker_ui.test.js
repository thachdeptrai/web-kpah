'use strict';
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
class FakeNode{
 constructor(tag='div'){
   this.tagName=tag;this.children=[];this.listeners={};this.attrs={};this.value='';
   this.hidden=false;this.className='';this._text='';this.disabled=false;
 }
 set textContent(text){this._text=String(text);this.children=[];}
 get textContent(){return this._text;}
 appendChild(node){this.children.push(node);return node;}
 setAttribute(k,v){this.attrs[k]=String(v);}
 getAttribute(k){return this.attrs[k]??null;}
 hasAttribute(k){return Object.hasOwn(this.attrs,k);}
 removeAttribute(k){delete this.attrs[k];}
 addEventListener(k,fn){this.listeners[k]=fn;}
 fire(k){assert.equal(typeof this.listeners[k],'function','missing '+k+' listener');this.listeners[k]({key:k});}
 focus(){}
 showModal(){this.attrs.open='';}
 close(){delete this.attrs.open;if(this.listeners.close)this.listeners.close();}
 setCustomValidity(){}
 reportValidity(){}
}
function exercise(filename){
const nodes={};
const ids=['point-reward-catalog','point-reward-picker','point-picker-search','point-picker-items',
 'point-picker-options','point-picker-count','point-picker-more','point-picker-selected-option',
 'point-picker-option-title','point-picker-param-value','point-picker-add-option'];
ids.forEach(id=>nodes[id]=new FakeNode());
const items=Array.from({length:501},(_,i)=>({
 id:i+1,name:i===490?'Kiếm K huyền thoại':'Vật phẩm '+(i+1),
 type:1,type_name:'Vũ khí',icon_id:i+2,image:'/res/icon/'+(i+2)+'.png'
}));
const options=[{id:33,name:'Tăng sức mạnh <svg/onload=alert(1)>',description:'Tăng chỉ số',
 min:1,max:127,percent:true,display_multiplier:1000}];
const shopItems=[{id:491,name:'Đạo cụ Shop thử',type:2,type_name:'Shop · Loại 2',tab:3,icon_id:999,image:'/res/icon/999.png'}];
nodes['point-reward-catalog'].textContent=JSON.stringify({items,shop_items:shopItems,options});
const editor=new FakeNode('form');
const fields={};
for(const name of ['item_id','icon_id','item_source','option_lines']){fields[name]=new FakeNode('input');}
const optionHidden=new FakeNode('input');
const chips=new FakeNode('div');
const launch=new FakeNode('button');
editor.querySelector=function(selector){
 if(selector==='[data-point-options]')return optionHidden;
 if(selector==='[data-point-selection]')return chips;
 if(selector==='[data-point-open]')return launch;
 let match=selector.match(/^\[name="([^"]+)"\]$/);
 if(match)return fields[match[1]];
 throw Error('unexpected editor selector '+selector);
};
const tabs=['items','shop','options'].map(name=>{
 const el=new FakeNode('button');el.attrs['data-point-tab']=name;return el;
});
const close=new FakeNode('button');
const dialog=nodes['point-reward-picker'];
dialog.querySelectorAll=function(selector){
 if(selector==='[data-point-tab]')return tabs;
 if(selector==='[data-point-close]')return [close];
 throw Error('unexpected modal selector '+selector);
};
const document={
 getElementById(id){return nodes[id]??null;},
 querySelectorAll(sel){if(sel==='[data-point-editor]')return [editor];return [];},
 createElement(tag){return new FakeNode(tag);},
 addEventListener(){},
};
const program=fs.readFileSync(filename,'utf8');
assert.ok(!/eval\s*\(|new\s+Function\s*\(|innerHTML\s*=|document\.write\s*\(/i.test(program),filename+' executes unsafe HTML');
assert.ok(!/(?:fetch|XMLHttpRequest|WebSocket)\s*\(/i.test(program),filename+' unexpected requests');
assert.ok(!/api[_-]?key|secret[_-]?key|password\s*[:=]/i.test(program),filename+' exposes embedded secrets');
vm.runInNewContext(program,{document,console},{filename:'points-reward-picker.js'});
launch.fire('click');
assert.equal(nodes['point-picker-items'].children.length,64,'first page renders only 64');
assert.equal(nodes['point-picker-more'].hidden,false);
for(let i=0;i<7;i++)nodes['point-picker-more'].fire('click');
assert.equal(nodes['point-picker-items'].children.length,501,'catalog is not truncated');
nodes['point-picker-search'].value='#491';
nodes['point-picker-search'].fire('input');
assert.equal(nodes['point-picker-items'].children.length,1,'ID after 180 remains searchable');
const itemRow=nodes['point-picker-items'].children[0];
itemRow.children[2].children[1].fire('click');
assert.equal(fields.item_id.value,491,'selected catalog item');
assert.equal(fields.icon_id.value,492,'icon matches item');
launch.fire('click');
// A previous item search must not hide options when the tab changes.
nodes['point-picker-search'].value='Kiếm';
nodes['point-picker-search'].fire('input');
tabs[2].fire('click');
assert.equal(nodes['point-picker-search'].value,'','search resets on category change');
assert.equal(nodes['point-picker-search'].placeholder,'Tìm chỉ số / option hoặc #ID');
assert.equal(nodes['point-picker-items'].hidden,true,'items panel must be hidden on Options');
assert.equal(nodes['point-picker-options'].hidden,false,'options panel must be visible');
assert.equal(tabs[2].getAttribute('aria-selected'),'true','Options tab selected');
assert.equal(tabs[0].getAttribute('aria-selected'),'false','Items tab deselected');
assert.equal(nodes['point-picker-options'].children.length,1,'options catalog is rendered, not items');
const optionButton=nodes['point-picker-options'].children[0];
optionButton.fire('click');
assert.equal(optionButton.getAttribute('aria-pressed'),'true','chosen option has active styling');
assert.equal(nodes['point-picker-selected-option'].hidden,false,'param editor appears on option selection');
// Returning to items must restore just the items pane.
tabs[0].fire('click');
assert.equal(nodes['point-picker-options'].hidden,true,'Options panel hides on Items');
assert.equal(nodes['point-picker-items'].hidden,false,'Items panel reappears');
assert.equal(nodes['point-picker-selected-option'].hidden,true,'old option editor resets');
tabs[2].fire('click');
assert.equal(nodes['point-picker-options'].children.length,1);
nodes['point-picker-options'].children[0].fire('click');
nodes['point-picker-param-value'].value='3';
nodes['point-picker-add-option'].fire('click');
assert.equal(JSON.parse(optionHidden.value).length,1,'option stored in hidden structured payload');
assert.match(fields.option_lines.value,/\+3\.000%/);
assert.match(fields.option_lines.value,/<svg\/onload=alert\(1\)>/,'hostile game text remains plain text');
assert.equal(program.includes('innerHTML'),false,'no HTML execution sink');
assert.equal(program.includes('eval('),false);
assert.ok(program.includes("aria-selected"),'tab ARIA state must be synchronized');
let prevented=false;
tabs[2].listeners.keydown({key:'ArrowLeft',preventDefault(){prevented=true;}});
assert.equal(prevented,true,'tab arrow key prevents page scroll');
assert.equal(tabs[1].getAttribute('aria-selected'),'true','left arrow opens Data Shop');
assert.equal(nodes['point-picker-items'].children.length,1,'Data Shop has distinct catalogue');
nodes['point-picker-items'].children[0].children[2].children[1].fire('click');
assert.equal(fields.item_source.value,'data_shop','shop source persisted with overlapping ID');
assert.equal(fields.item_id.value,491);
assert.equal(fields.icon_id.value,999,'Shop idImg is selected');
assert.equal(optionHidden.value,'[]','Shop selection clears item options');
tabs[1].listeners.keydown({key:'ArrowLeft',preventDefault(){}});
assert.equal(tabs[0].getAttribute('aria-selected'),'true','left arrow returns to Items');
tabs[0].listeners.keydown({key:'ArrowRight',preventDefault(){}});
assert.equal(tabs[1].getAttribute('aria-selected'),'true','right arrow opens Shop');
tabs[1].listeners.keydown({key:'ArrowRight',preventDefault(){}});
assert.equal(tabs[2].getAttribute('aria-selected'),'true','right arrow opens Options');
assert.equal(chips.children.some(n=>n.tagName==='span'&&n._text.includes('svg')),true,
 'hostile string shown through textContent only');
}
// CSS used to override [hidden] with display:grid, exposing both panels.
const css=fs.readFileSync('assets/css/point-reward-picker.css','utf8');
assert.match(css, /#point-reward-picker\s+\[hidden\]\s*\{\s*display:\s*none\s*!important\s*\}/,
 'hidden panels must override the grid layout');
assert.match(css,/point-picker-option\[aria-pressed="true"\]/,'selected Option receives a visible state');
const template=fs.readFileSync('app/topup/admin/points.php','utf8');
assert.match(template,/id="point-picker-options"[^>]*hidden/,'Options pane starts hidden');
assert.match(template,/role="tablist"/,'picker uses accessible tabs');
exercise('assets/js/points-reward-picker.js');
exercise('assets/js-dist/points-reward-picker.js');
console.log('PASS: original AND reverse-inspected minified Point picker, 501 items, options, JS injection safety');
