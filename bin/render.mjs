#!/usr/bin/env node

import { readFileSync, writeFileSync } from 'fs';
import * as vl from 'vega-lite';
import * as vega from 'vega';
import { Resvg } from '@resvg/resvg-js';

const [,, inputFile, outputFile, widthArg] = process.argv;

if (!inputFile || !outputFile) {
    process.stderr.write('Usage: render.mjs <input.json> <output.png> [width]\n');
    process.exit(1);
}

const width = parseInt(widthArg || '800', 10);

try {
    const spec = JSON.parse(readFileSync(inputFile, 'utf-8'));
    const vgSpec = vl.compile(spec).spec;
    const view = new vega.View(vega.parse(vgSpec), { renderer: 'none' });

    const svg = await view.toSVG();
    const resvg = new Resvg(svg, {
        fitTo: { mode: 'width', value: width },
    });
    const pngData = resvg.render();
    const pngBuffer = pngData.asPng();
    writeFileSync(outputFile, pngBuffer);
} catch (err) {
    process.stderr.write(String(err.message || err) + '\n');
    process.exit(1);
}
