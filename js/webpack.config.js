// Deliberately NOT flarum-webpack-config: its runtime prologue references
// flarum.reg unconditionally, which doesn't exist on Flarum 1.x and would
// kill the bundle at load time there. This extension imports nothing from
// flarum/* (it feature-detects the globals at runtime so one artifact runs
// on both majors), so a plain webpack build is all it needs.
//
// output.library commonjs2 matters: Flarum (both majors) wraps each
// extension bundle as `var module={}; <bundle>;
// flarum.extensions['<id>']=module.exports;` and boot reads that object, so
// the bundle must assign module.exports or the extension registers as
// undefined and the whole frontend fails to boot.
const path = require('path');

module.exports = {
  entry: {
    forum: './src/forum.js',
    admin: './src/admin.js',
  },
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: '[name].js',
    library: { type: 'commonjs2' },
  },
  devtool: 'source-map',
};
