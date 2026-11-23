'use strict';
/**
 * Bundles only the pieces of Motion (the vanilla build of Framer Motion) that the hero uses
 * into a single IIFE exposing the global `Motion`. Output is committed to assets/vendor/motion.min.js
 * so the site stays static (no runtime bundler, no CDN, CSP-safe: no eval/inline).
 *
 * Usage: npm run build:motion
 */
const path = require('path');
const esbuild = require('esbuild');

const ROOT = path.resolve(__dirname, '..');

esbuild
  .build({
    stdin: {
      contents: [
        "export { animate } from 'motion/mini';",
        "export { hover, press } from 'motion';",
      ].join('\n'),
      resolveDir: ROOT,
      sourcefile: 'motion-entry.js',
    },
    bundle: true,
    format: 'iife',
    globalName: 'Motion',
    minify: true,
    target: 'es2019',
    legalComments: 'none',
    outfile: path.join(ROOT, 'assets/vendor/motion.min.js'),
    logLevel: 'info',
  })
  .catch(() => process.exit(1));
