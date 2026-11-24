# pdfmake 0.3.15

Source: https://www.npmjs.com/package/pdfmake/v/0.3.15
Upstream: https://github.com/bpampuch/pdfmake

Browser builds and bundled Roboto fonts are copied without modifications.
pdfmake is MIT licensed; see LICENSE. Bundled Roboto fonts are licensed under
the SIL Open Font License 1.1; see ROBOTO-LICENSE.txt.

Versions and dependency integrity are pinned in package-lock.json.
To reproduce these files: `npm ci --ignore-scripts` then `npm run vendor:pdf`.
The deployed PHP application serves these files locally; Node.js is not required
in the application container, and PDF generation never uses a third-party API.
