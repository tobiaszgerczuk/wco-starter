// webpack.config.js
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyPlugin = require('copy-webpack-plugin');
const fs = require('fs');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  // === DYNAMICZNE ENTRY DLA BLOKÓW ===
  const blockEntries = {};
  const blocksDir = path.resolve(__dirname, 'views/blocks');

  if (fs.existsSync(blocksDir)) {
    fs.readdirSync(blocksDir).forEach(dir => {
      const dirPath = path.join(blocksDir, dir);
      if (!fs.statSync(dirPath).isDirectory()) return;

      const scssFile = path.join(dirPath, `_${dir}.scss`);
      if (fs.existsSync(scssFile)) {
        blockEntries[`blocks/${dir}/${dir}`] = scssFile;
      }
    });
  }

  // === ŚCIEŻKA DO _block-base.scss ===
  const blockBasePath = path.resolve(__dirname, 'assets/scss/_block-base.scss').replace(/\\/g, '/');

  return {
    entry: {
      front: './assets/js/front.js',
      admin: './assets/js/admin.js',
      style: './assets/scss/style.scss',
      admin_style: './assets/scss/admin.scss',
      ...blockEntries
    },

    output: {
      path: path.resolve(__dirname, 'public'),
      filename: 'js/[name].js',
      clean: true
    },

    module: {
      rules: [
        {
          test: /\.scss$/,
          use: [
            MiniCssExtractPlugin.loader,
            { loader: 'css-loader', options: { url: true, sourceMap: !isProduction } },
            { loader: 'postcss-loader', options: { sourceMap: !isProduction } },
            {
              loader: 'sass-loader',
              options: {
                sourceMap: !isProduction,
                sassOptions: {
                  includePaths: [
                    path.resolve(__dirname, 'assets/scss'),
                    path.resolve(__dirname, 'assets/scss/base'),
                    path.resolve(__dirname, 'assets/scss/components'),
                    path.resolve(__dirname, 'assets/scss/layout')
                  ],
                  quietDeps: true,
                  silenceDeprecations: ['legacy-js-api']
                },
                // DOŁĄCZ _block-base.scss DO KAŻDEGO BLOKU
                additionalData: (content, loaderContext) => {
                  const { resourcePath } = loaderContext;
                  if (resourcePath.includes('views/blocks/') && resourcePath.endsWith('.scss')) {
                    return `@use "${blockBasePath}" as *;\n${content}`;
                  }
                  return content;
                }
              }
            }
          ]
        },
        {
          test: /\.css$/,
          use: [
            MiniCssExtractPlugin.loader,
            { loader: 'css-loader', options: { url: true, sourceMap: !isProduction } },
            { loader: 'postcss-loader', options: { sourceMap: !isProduction } }
          ]
        },
        {
          test: /\.(png|jpe?g|gif|svg|webp|ico)$/i,
          type: 'asset/resource',
          generator: { filename: 'images/[name][ext]' }
        },
        {
          test: /\.(woff|woff2|eot|ttf|otf)$/i,
          type: 'asset/resource',
          generator: { filename: 'fonts/[name][ext]' }
        }
      ]
    },

    plugins: [
      new MiniCssExtractPlugin({
        filename: '[name].css'
      }),
      new CopyPlugin({
        patterns: [
          {
            from: path.resolve(__dirname, 'assets/images'),
            to: path.resolve(__dirname, 'public/images'),
            globOptions: { ignore: ['*.DS_Store', 'Thumbs.db'] },
            noErrorOnMissing: true
          }
        ]
      })
    ],

    devtool: isProduction ? false : 'source-map',
    mode: isProduction ? 'production' : 'development'
  };
};