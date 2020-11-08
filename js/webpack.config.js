const path = require('path');

module.exports = {
	mode: 'development',
	entry: {
		simple: './index.mjs'
	},
	output: { 
		path: path.resolve(__dirname, 'dist'),
		filename: '[name].bundle.js'
	},
	/*
	optimization: {
		minimize: true 
	}*/
};
