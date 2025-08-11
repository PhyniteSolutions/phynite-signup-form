const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'frontend': './assets/js/frontend.js',
        'admin': './assets/js/admin.js',
        'block': './assets/js/block.js'
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
    plugins: [
        ...defaultConfig.plugins,
        new DependencyExtractionWebpackPlugin({
            outputFormat: 'php',
        }),
    ],
};