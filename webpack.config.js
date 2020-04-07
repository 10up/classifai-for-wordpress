const path = require('path');
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const WebpackBar = require( 'webpackbar' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	entry: {
		'editor': './src/js/editor.js',
		'media': './src/js/media.js',
		'admin': './src/js/admin.js'
	},
	output: {
		filename: '[name].js',
		path: path.resolve( './dist/js')
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /(node_modules)/,
				use: {
					loader: 'babel-loader'
				},
			},
			{
				test: /\.js$/,
				exclude: /(node_modules)/,
				enforce: 'pre',
				loader: 'eslint-loader',
				options: {
					fix: true
				}
			}
		],
	},
	plugins: [
		// Clean the `dist` folder on build.
		new CleanWebpackPlugin(),

		// Fancy WebpackBar.
		new WebpackBar(),

		// Extract dependencies.
		new DependencyExtractionWebpackPlugin( { injectPolyfill: true, combineAssets: true } ),
	]
};
