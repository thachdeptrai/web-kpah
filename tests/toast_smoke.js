/* Minimal DOM contract test for the shared toast; no browser or app server required. */
'use strict';
const fs=require('fs');
const vm=require('vm');
const source=fs.readFileSync(__dirname+'/../assets/js/ui-system.js','utf8');
const checks=[];
function assert(ok,label){if(!ok)throw new Error('FAIL '+label);checks.push(label);}
class Element{
  constructor(tag){this.tagName=tag.toUpperCase();this.children=[];this.dataset={};this.attrs={};this.className='';this.classes=new Set();this.classList={add:(name)=>this.classes.add(name),contains:(name)=>this.classes.has(name)};this._text='';this.listeners={};this.parentNode=null;}
  get firstElementChild(){return this.children[0]||null;}
  set textContent(value){this._text=String(value);this.children=[];}
  get textContent(){return this._text+this.children.map(child=>child.textContent).join('');}
  appendChild(el){this.children.push(el);el.parentNode=this;return el;}
  removeChild(el){this.children=this.children.filter(x=>x!==el);el.parentNode=null;return el;}
  setAttribute(k,v){this.attrs[k]=String(v);}
  getAttribute(k){return this.attrs[k]||null;}
  hasAttribute(k){return Object.prototype.hasOwnProperty.call(this.attrs,k);}
  addEventListener(k,fn){this.listeners[k]=fn;}
  remove(){if(this.parentNode)this.parentNode.removeChild(this);}
  prepend(el){this.children.unshift(el);el.parentNode=this;}
  focus(){}
  querySelectorAll(){return [];}
}
const body=new Element('body');
const doc={
  documentElement:new Element('html'),body,readyState:'complete',
  createElement:tag=>new Element(tag),
  querySelector(selector){return selector==='.kp-ui-toast-region'?body.children.find(x=>x.className.split(/\s+/).includes('kp-ui-toast-region'))||null:null;},
  querySelectorAll(){return [];},
  addEventListener(){},dispatchEvent(){},
};
const timers=[];
const context={document:doc,CustomEvent:function(){},URLSearchParams,console,
  window:{matchMedia:()=>({matches:true}),setTimeout:(fn)=>{timers.push(fn);return timers.length;},clearTimeout(){},location:{search:''}},
  setTimeout:(fn)=>{timers.push(fn);return timers.length;},
};
vm.runInNewContext(source,context);
const api=context.window.KpUI;
assert(typeof api.toast==='function','public KpUI.toast API');
const unsafe='<img src=x onerror=alert(1)>';
const first=api.toast({type:'success',title:'Thành công',message:unsafe,duration:1800});
let region=body.children.find(x=>x.className==='kp-ui-toast-region');
assert(first.children[1].children[1].textContent===unsafe,'untrusted text assigned as textContent');
assert(first.children[1].children[1].children.length===0,'message cannot inject markup');
assert(first.children[2].tagName==='BUTTON'&&first.children[2].attrs['aria-label']==='Đóng thông báo','accessible close button');
assert(region.children.length===1,'toast appears in the shared notification region');
assert(typeof context.window.kpToast==='function','legacy AJAX alias');
const compat=context.window.kpToast('error','Thất bại','Mô tả');
assert(compat.className.includes('kp-ui-toast--error'),'legacy alias delegates to shared renderer');
compat.children[2].listeners.click();
assert(compat.dataset.removing==='1','close button dismisses toast');
console.log('PASS toast_smoke.js ('+checks.length+' assertions)');
