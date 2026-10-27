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
nodes['point-reward-catalog'].textContent=JSON.stringify({items,options});
const editor=new FakeNode('form');
const fields={};
for(const name of ['item_id','icon_id','option_lines']){fields[name]=new FakeNode('input');}
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
const tabs=['items','options'].map(name=>{
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
const program=fs.readFileSync('assets/js/points-reward-picker.js','utf8');
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
tabs[1].fire('click');
assert.equal(nodes['point-picker-options'].children.length,1);
nodes['point-picker-options'].children[0].fire('click');
nodes['point-picker-param-value'].value='3';
nodes['point-picker-add-option'].fire('click');
assert.equal(JSON.parse(optionHidden.value).length,1,'option stored in hidden structured payload');
assert.match(fields.option_lines.value,/\+3\.000%/);
assert.match(fields.option_lines.value,/<svg\/onload=alert\(1\)>/,'hostile game text remains plain text');
assert.equal(program.includes('innerHTML'),false,'no HTML execution sink');
assert.equal(program.includes('eval('),false);
assert.equal(chips.children.some(n=>n.tagName==='span'&&n._text.includes('svg')),true,
 'hostile string shown through textContent only');
console.log('PASS: Point picker 501 items ID/icon/option choices and script-injection safety');
