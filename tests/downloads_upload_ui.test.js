/**
 * Node-only browser harness for release upload UX, with no network or live VPS.
 * Run: node tests/downloads_upload_ui.test.js
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/admin-downloads.js'), 'utf8');
let passed = 0;
function check(ok, label) {
  assert.ok(ok, label);
  process.stdout.write('PASS: ' + label + '\n');
  passed++;
}
function boot(opts = {}) {
  const panel = { hidden: true };
  const progress = { value: 0 };
  const label = { textContent: '' };
  const value = { textContent: '' };
  const message = { textContent: '' };
  const button = { disabled: false };
  const sourceSelector = { value: opts.source || 'url' };
  const upload = {
    name: 'upload[java]',
    files: [{ name: opts.name || 'Nghien_KPAH.jar', size: opts.size == null ? 3000 : opts.size }],
    addEventListener(event, cb) { this['on' + event] = cb; }
  };
  const elements = { 'source[java]': sourceSelector };
  const form = {
    elements,
    action: '/quan-tri/tai-game',
    getAttribute(key) {
      return {
        'data-upload-max-bytes': String(opts.uploadLimit == null ? 2 * 1048576 : opts.uploadLimit),
        'data-post-max-bytes': String(opts.postLimit == null ? 8 * 1048576 : opts.postLimit),
        'data-app-max-bytes': String(512 * 1048576)
      }[key] || '';
    },
    querySelector(key) { return key === 'button[type="submit"]' ? button : null; },
    querySelectorAll(key) { return key === 'input[type="file"]' ? [upload] : []; },
    addEventListener(event, cb) { this['on' + event] = cb; }
  };
  const dom = {
    'dl-form': form, 'dl-progress': panel, 'dl-progress-bar': progress,
    'dl-progress-label': label, 'dl-progress-value': value, 'dl-progress-message': message
  };
  const xhrs = [];
  class FakeXHR {
    constructor() {
      this.listeners = {};
      this.upload = { addEventListener: () => {} };
      xhrs.push(this);
    }
    open(method, url) { this.method = method; this.url = url; }
    setRequestHeader(name, v) { this.headers = this.headers || {}; this.headers[name] = v; }
    addEventListener(event, cb) { this.listeners[event] = cb; }
    getResponseHeader(name) { return name === 'cf-ray' ? (this.ray || null) : null; }
    send(payload) { this.sent = payload; }
  }
  let reloads = 0;
  const windowMock = {
    FormData: function FormData(formRef) { this.form = formRef; },
    XMLHttpRequest: FakeXHR,
    location: { reload() { reloads++; } }
  };
  vm.runInNewContext(source, {
    document: { getElementById(id) { return dom[id] || null; } },
    window: windowMock,
    FormData: windowMock.FormData,
    XMLHttpRequest: FakeXHR
  }, {filename:'admin-downloads.js'});
  return {
    upload, sourceSelector, message, progress, panel, button, xhrs,
    submit() { form.onsubmit({preventDefault(){}}); },
    respond({status, json, ray, text, responseURL}) {
      const xhr = xhrs[xhrs.length - 1];
      xhr.status = status;
      xhr.responseText = json ? JSON.stringify(json) : (text || '<html>Proxy error</html>');
      xhr.ray = ray || null;
      xhr.responseURL = responseURL || 'https://game.example/quan-tri/tai-game';
      xhr.listeners.load();
    },
    get reloads(){return reloads;}
  };
}

let env = boot();
env.upload.onchange();
check(env.sourceSelector.value === 'file', 'Selecting JAR auto-selects VPS file source');
env.submit();
check(env.xhrs.length === 1 && env.xhrs[0].method === 'POST'
  && env.xhrs[0].headers.Accept === 'application/json', 'AJAX sends JSON preference and multipart data');
env.respond({status:422,json:{ok:false,message:'File JAR không hợp lệ.'}});
check(env.message.textContent === 'File JAR không hợp lệ.' && !env.button.disabled, 'Server JSON error is shown verbatim and submit re-enabled');

env = boot({size:3*1048576});
env.upload.onchange();env.submit();
check(env.xhrs.length === 0 && env.message.textContent.includes('upload_max_filesize'),
      'Client blocks file bigger than XAMPP upload_max_filesize');

env = boot({size:6*1048576,uploadLimit:8*1048576,postLimit:5*1048576});
env.upload.onchange();env.submit();
check(env.xhrs.length === 0 && env.message.textContent.includes('post_max_size'),
      'Client blocks oversized multipart post before request');

env = boot({name:'Nghien_KPAH.apk'});
env.upload.onchange();env.submit();
check(env.xhrs.length === 0 && env.message.textContent.includes('sai định dạng'),
      'Wrong extension for Java JAR rejected before request');

env = boot();
env.upload.onchange();env.submit();
env.respond({status:400,ray:'ray123',text:'<html>Cloudflare block</html>'});
check(env.message.textContent.includes('Ray ID ray123'), 'Non-JSON HTTP 400 includes Cloudflare diagnostic ID');

env = boot();
env.upload.onchange();env.submit();
env.respond({status:400,text:'<html>Bad Request</html>'});
check(env.message.textContent.includes('F12') && env.message.textContent.includes('Apache/PHP'),
      'Non-JSON HTTP 400 gives Network/Apache debugging instructions');

env = boot();
env.upload.onchange();env.submit();
env.respond({status:200,responseURL:'https://game.example/quan-tri/dang-nhap'});
check(env.message.textContent.includes('Phiên quản trị'), 'Redirected login page is not mistaken for successful upload');

env = boot();
env.upload.onchange();env.submit();
env.respond({status:200,json:{ok:true}});
check(env.reloads === 1, 'Successful upload refreshes catalog');

console.log('TOTAL UPLOAD UI CHECKS:', passed);
