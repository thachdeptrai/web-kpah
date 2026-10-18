#!/usr/bin/env node
'use strict';
/**
 * Build public, minified JavaScript with Terser.
 * This is obfuscation/minification, NOT encryption or a security boundary.
 * Browser users can always inspect executed JavaScript.
 */
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const terser = require('terser');

const root = path.resolve(__dirname, '..');
const sources = path.join(root, 'assets', 'js');
const output = path.join(root, 'assets', 'js-dist');
const files = fs.readdirSync(sources).filter(n => /^[a-z0-9-]+\.js$/i.test(n)).sort();
if (files.length < 10) throw new Error('Expected public JS sources missing; refusing partial output');
fs.mkdirSync(output, { recursive: true });

async function build() {
  for (const name of files) {
    const original = fs.readFileSync(path.join(sources, name), 'utf8');
    const result = await terser.minify(original, {
      module: false,
      toplevel: false,
      compress: { defaults: true, passes: 1 },
      mangle: { toplevel: false },
      format: { comments: /@license|@preserve|^!/i },
      sourceMap: false
    });
    if (!result.code || result.code.length < 10) throw new Error('Unsafe empty minification result: '+name);
    const temp = path.join(output, name+'.tmp.js');
    fs.writeFileSync(temp, result.code+'\n', 'utf8');
    execFileSync(process.execPath, ['--check', temp], { stdio:'pipe' });
    fs.renameSync(temp, path.join(output, name));
    console.log(name, original.length, '=>', result.code.length, 'bytes');
  }
  for (const obsolete of fs.readdirSync(output)) {
    if (obsolete.endsWith('.js') && !files.includes(obsolete)) fs.unlinkSync(path.join(output, obsolete));
  }
  console.log('Built', files.length, 'public JavaScript assets; no source maps.');
}
build().catch(e => { console.error(e.message); process.exitCode = 1; });
