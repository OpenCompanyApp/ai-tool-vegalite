#!/usr/bin/env node
'use strict';

const fs = require('fs');
const vl = require('vega-lite');
const vega = require('vega');
const { Resvg } = require('@resvg/resvg-js');

const [,, inputFile, outputFile, widthArg] = process.argv;

if (!inputFile || !outputFile) {
    process.stderr.write('Usage: render.cjs <input.json> <output.png> [width]\n');
    process.exit(1);
}

const width = parseInt(widthArg || '800', 10);

try {
    const spec = JSON.parse(fs.readFileSync(inputFile, 'utf-8'));
    const vgSpec = vl.compile(spec).spec;
    const view = new vega.View(vega.parse(vgSpec), { renderer: 'none' });

    view.toSVG().then(svg => {
        const resvg = new Resvg(svg, {
            fitTo: { mode: 'width', value: width },
        });
        const pngData = resvg.render();
        const pngBuffer = pngData.asPng();
        fs.writeFileSync(outputFile, pngBuffer);
        process.exit(0);
    }).catch(err => {
        process.stderr.write(String(err.message || err) + '\n');
        process.exit(1);
    });
} catch (err) {
    process.stderr.write(String(err.message || err) + '\n');
    process.exit(1);
}
