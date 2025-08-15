const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'frontend': './assets/js/frontend.js',
        'admin': './assets/js/admin.js',
        'block': './assets/js/block.js',
        'frontend-styles': './assets/css/frontend.css',
        'admin-styles': './assets/css/admin.css',
        'block-editor-styles': './assets/css/block-editor.css'
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'js/[name].js',
        clean: true,
    },
    plugins: [
        ...defaultConfig.plugins,
        new DependencyExtractionWebpackPlugin({
            outputFormat: 'php',
        }),
        new MiniCssExtractPlugin({
            filename: 'css/[name].css',
        }),
    ],
};