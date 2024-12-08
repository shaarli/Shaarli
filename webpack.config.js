const path = require('path');
const glob = require('glob');

// Minify JS
const TerserPlugin = require('terser-webpack-plugin');

// This plugin extracts the CSS into its own file instead of tying it with the JS.
// It prevents:
//   - not having styles due to a JS error
//   - the flash page without styles during JS loading
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const extractCss = new MiniCssExtractPlugin({
  filename: "css/[name].min.css",
});

module.exports = [
  {
    mode: 'production',
    entry: {
      shaare_batch: './assets/common/js/shaare-batch.js',
      thumbnails: './assets/common/js/thumbnails.js',
      thumbnails_update: './assets/common/js/thumbnails-update.js',
      metadata: './assets/common/js/metadata.js',
      pluginsadmin: './assets/default/js/plugins-admin.js',
      shaarli: [
        './assets/default/js/base.js',
        './assets/default/scss/shaarli.scss',
      ].concat(glob.sync('./assets/default/img/*', { dotRelative: true })),
      markdown: './assets/common/css/markdown.css',
    },
    output: {
      filename: 'js/[name].min.js',
      path: path.resolve(__dirname, 'tpl/default/')
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
              ]
            }
          }
        },
        {
          test: /\.s?css/,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
            },
            'css-loader',
            'sass-loader',
          ],
        },
        {
          test: /\.(gif|png|jpe?g|svg|ico)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'img/[name][ext]'
          }
        },
        {
          test: /webfont\.svg$/,
          type: 'asset/resource',
          generator: {
            filename: 'fonts/[name][ext]'
          }
        },
        {
          test: /\.(eot|ttf|woff|woff2)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
          type: 'asset/resource',
          generator: {
            filename: 'fonts/[name][ext]'
          }
        },
      ],
    },
    optimization: {
      minimize: true,
      minimizer: [new TerserPlugin()],
    },
    plugins: [
      extractCss,
    ],
  },
  {
    mode: 'production',
    entry: {
      shaarli: [
        './assets/vintage/js/base.js',
        './assets/vintage/css/reset.css',
        './assets/vintage/css/shaarli.css',
      ].concat(glob.sync('./assets/vintage/img/*', { dotRelative: true })),
      markdown: './assets/common/css/markdown.css',
      thumbnails: './assets/common/js/thumbnails.js',
      metadata: './assets/common/js/metadata.js',
      thumbnails_update: './assets/common/js/thumbnails-update.js',
    },
    output: {
      filename: 'js/[name].min.js',
      path: path.resolve(__dirname, 'tpl/vintage/')
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
              ]
            }
          }
        },
        {
          test: /\.css$/,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
            },
            'css-loader',
            'sass-loader',
          ],
        },
        {
          test: /\.(gif|png|jpe?g|svg|ico)$/i,
          type: 'asset/resource',
          generator: {
            filename: 'img/[name][ext]'
          }
        },
      ],
    },
    optimization: {
      minimize: true,
      minimizer: [new TerserPlugin()],
    },
    plugins: [
      extractCss,
    ],
  },
];
