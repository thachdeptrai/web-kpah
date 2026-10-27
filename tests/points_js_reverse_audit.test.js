'use strict';
/**
 * Authorized local reverse/behavior test for public Point JavaScript.
 * Browser JS can always be read and formatted; this test proves only that
 * minification did not insert a privileged request or dynamic execution.
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const src=fs.readFileSync('assets/js/points-guard.js','utf8');
const dist=fs.readFileSync('assets/js-dist/points-guard.js','utf8');
function inspect(content,label){
  assert.ok(!/eval\s*\(|new\s+Function\s*\(|innerHTML\s*=|document\.write\s*\(/i.test(content),label+' unsafe dynamic evaluation');
  assert.ok(!/(?:fetch|XMLHttpRequest|WebSocket)\s*\(/i.test(content),label+' must not issue hidden requests');
  assert.ok(!/api[_-]?key|secret[_-]?key|password\s*[:=]/i.test(content),label+' must not embed secrets');
  let submit=null,prevented=0,button={disabled:false,textContent:'Đổi quà'};
  const form={addEventListener(n,cb){assert.equal(n,'submit');submit=cb;},
    checkValidity(){return true;},querySelector(){return button;}};
  const document={querySelectorAll(selector){assert.equal(selector,'.point-redeem-form');return [form];}};
  vm.runInNewContext(content,{document}, {timeout:1500});
  assert.equal(typeof submit,'function',label+' missing submit listener');
  submit({preventDefault(){prevented++;}});
  assert.equal(button.disabled,true,label+' did not protect repeated click');
  submit({preventDefault(){prevented++;}});
  assert.equal(prevented,1,label+' allowed a duplicate browser submission');
}
inspect(src,'source');
inspect(dist,'reformatted minified build');
console.log('Point JS source/minified behavior comparison and exposed-client-code audit PASS');
