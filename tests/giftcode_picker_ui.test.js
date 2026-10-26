'use strict';
/* Execute the actual picker script with a minimal DOM. No network/DB access. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class FakeNode {
  constructor(tag = 'div') {
    this.tagName = tag;
    this.children = [];
    this.listeners = {};
    this.attributes = {};
    this.hidden = false;
    this.value = '';
    this.className = '';
    this.style = {};
    this._text = '';
    this.classList = {add(){},remove(){},toggle(){}};
  }
  set textContent(value) { this._text = String(value); this.children = []; }
  get textContent() { return this._text; }
  appendChild(child) { this.children.push(child); return child; }
  addEventListener(event, cb) { this.listeners[event] = cb; }
  setAttribute(k,v) { this.attributes[k] = String(v); }
  fire(event) { assert.equal(typeof this.listeners[event], 'function'); this.listeners[event]({currentTarget:this}); }
}
const names = ['gc-reward-catalog','gc-item-picker','gc-option-picker','gc-item-search',
  'gc-item-type','gc-item-grid','gc-item-result','gc-item-more','gc-option-search',
  'gc-option-group','gc-option-list','gc-option-result'];
const nodes = Object.fromEntries(names.map(name => [name,new FakeNode()]));
const items = Array.from({length:501}, (_,i) => ({
  id:i+1, name:i===492?'Đồ Quý':'Vật phẩm #'+(i+1),
  type:1,type_name:'Quần',level:1,icon_id:0,image:null,default_options:[]
}));
nodes['gc-reward-catalog'].textContent=JSON.stringify({items,options:[],dbo_enabled:false});
const document = {
  body:new FakeNode('body'),
  getElementById:(id)=>nodes[id] || null,
  createElement:(tag)=>new FakeNode(tag),
  querySelectorAll:()=>[],
  querySelector:()=>null,
  addEventListener:()=>{}
};
vm.runInNewContext(fs.readFileSync('assets/js/admin-giftcode-builder.js','utf8'),{
  document, window:{setTimeout(fn){fn();}}, navigator:{},
  console, Blob, Intl
},{filename:'admin-giftcode-builder.js'});

const grid=nodes['gc-item-grid'];
const more=nodes['gc-item-more'];
const search=nodes['gc-item-search'];
const counter=nodes['gc-item-result'];
search.value='';
search.fire('input');
assert.equal(grid.children.length,80,'First page renders only 80 items');
assert.equal(more.hidden,false,'Load-more button should be visible');
assert.match(counter.textContent,/80\/501/);
for(let n=0;n<6;n++)more.fire('click');
assert.equal(grid.children.length,501,'Every item must become selectable');
assert.equal(more.hidden,true,'All results loaded');
assert.match(counter.textContent,/501\/501/);
search.value='#480';
search.fire('input');
assert.equal(grid.children.length,1,'Exact ID #480 must work even beyond first 180');
assert.match(grid.children[0].children[1].children[2].textContent,/#480/);
search.value='do quy';
search.fire('input');
assert.equal(grid.children.length,1,'Accent-insensitive name search must work');
assert.equal(grid.children[0].children[1].children[1].textContent,'Đồ Quý');
search.value='khong-co';
search.fire('input');
assert.equal(grid.children.length,0);
assert.equal(more.hidden,true);
console.log('Giftcode picker full-catalog paging and name/ID search PASS');
