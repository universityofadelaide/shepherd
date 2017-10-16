let webpack = require('webpack');
let path = require('path');

module.exports = {
  // Entry point to create the dependency graphs.
  entry: {
    // Add components here .. Drupal handles each separately through its libraries
    table: './js/src/table/app.js',
    vendor: ['vue', 'axios']
  },
  output: {
    path: path.resolve(__dirname, 'js'),
    filename: '[name].js',
    publicPath: './js'
  },
  module: {
    // Apply rules to file extensions ( for *.vue files etc. )
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        // ES6 to ES5
        loader: 'babel-loader'
      },
      {
        test: /\.vue$/,
        loader: 'vue-loader'
      }
    ]
  },
  // npm version of vue only ships with the runtime
  // @see - https://vuejs.org/v2/guide/installation.html
  resolve: {
    extensions: ['.js', '.vue', '.ts'],
    alias: {
      'vue$': 'vue/dist/vue.esm.js' // 'vue/dist/vue.common.js' for webpack 1
    }
  },
  plugins: [
    new webpack.optimize.CommonsChunkPlugin({
      names: ['vendor']
    })
  ]
};

// Compress files if production.
if(process.env.NODE_ENV === 'production') {
  module.exports.plugins.push(
    new webpack.optimize.UglifyJsPlugin({
      sourcemap: true,
      compress: {
        warnings: false
      }
    })
  );

  module.exports.plugins.push(
    new webpack.DefinePlugin({
      'process.env': {
        NODE_ENV: '"production"'
      }
    })
  );
}
