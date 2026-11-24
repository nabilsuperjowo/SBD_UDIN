(function (root) {
  'use strict';

  function createDefinition(report, colors) {
    const weights = [3, 14, 12, 11, 8, 8, 10, 5, 8, 8, 6, 7];
    const padding = 3;
    const lineWidth = 0.5;
    const contentWidth = 841.89 - 48;
    const cellsWidth = contentWidth - padding * 24 - lineWidth * 13;
    const headers = report.headers.map(text => ({text, bold: true, color: colors.heading}));
    const rows = report.rows.length ? report.rows : [
      [{text: report.emptyMessage, colSpan: 12, alignment: 'center', color: colors.muted}, ...Array(11).fill('')],
    ];
    const content = [
      {text: report.title, fontSize: 17, bold: true, margin: [0, 0, 0, 4]},
      {text: report.address, fontSize: 9, color: colors.muted, margin: [0, 0, 0, 17]},
      {text: report.summary, fontSize: 9, margin: [0, 0, 0, 10]},
    ];
    if (report.filter) content.push({text: report.filter, fontSize: 8, color: colors.muted, margin: [0, 0, 0, 8]});
    content.push({
      table: {
        headerRows: 1,
        dontBreakRows: true,
        keepWithHeaderRows: 1,
        widths: weights.map(weight => cellsWidth * weight / 100),
        body: [headers, ...rows],
      },
      layout: {
        fillColor: row => row === 0 ? colors.headBackground : row % 2 === 0 ? colors.stripe : colors.surface,
        hLineWidth: () => lineWidth,
        vLineWidth: () => lineWidth,
        hLineColor: () => colors.line,
        vLineColor: () => colors.line,
        paddingLeft: () => padding,
        paddingRight: () => padding,
        paddingTop: () => 7,
        paddingBottom: () => 7,
      },
    });
    content.push({text: report.footer, fontSize: 8, color: colors.muted, margin: [0, 13, 0, 0]});

    return {
      pageSize: 'A4',
      pageOrientation: 'landscape',
      pageMargins: [24, 24, 24, 24],
      info: {title: report.title, creator: 'Smart Sport Reservation'},
      defaultStyle: {font: 'Roboto', fontSize: 8.5, color: colors.text, lineHeight: 1.15},
      background: (_page, size) => ({canvas: [{type: 'rect', x: 0, y: 0, w: size.width, h: size.height, color: colors.background}]}),
      content,
    };
  }

  root.SsrReservationPdf = {createDefinition};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.SsrReservationPdf;
})(typeof window === 'undefined' ? globalThis : window);
