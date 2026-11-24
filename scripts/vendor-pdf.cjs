'use strict';
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '..');
const source = path.dirname(require.resolve('pdfmake/package.json'));
const destination = path.join(root, 'public/assets/vendor/pdfmake');
fs.mkdirSync(destination, {recursive: true});
for (const file of ['pdfmake.min.js', 'vfs_fonts.js']) {
  fs.copyFileSync(path.join(source, 'build', file), path.join(destination, file));
}
fs.copyFileSync(path.join(source, 'LICENSE'), path.join(destination, 'LICENSE'));
const version = require(path.join(source, 'package.json')).version;
console.log(`Aset PDF lokal siap: pdfmake ${version}.`);
