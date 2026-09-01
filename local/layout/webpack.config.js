import { CleanWebpackPlugin } from 'clean-webpack-plugin';
import CopyWebpackPlugin from 'copy-webpack-plugin';
import fs from 'fs';
import HtmlWebpackPlugin from 'html-webpack-plugin';
import ImageMinimizerPlugin from 'image-minimizer-webpack-plugin';
import ImageminWebpWebpackPlugin from 'imagemin-webp-webpack-plugin';
import path from 'path';
import TerserPlugin from 'terser-webpack-plugin';
import { fileURLToPath } from 'url';
import webpack from 'webpack';

import BeautifyHtmlWebpackPlugin from '@sumotto/beautify-html-webpack-plugin';
import 'dotenv/config';
import SpriteLoaderPlugin from 'svg-sprite-loader/plugin.js';

import MiniCssExtractPlugin from 'mini-css-extract-plugin';

const getPages = pagesDir => {
	if (!fs.existsSync(pagesDir)) return [];

	const pages = [];

	const entries = fs.readdirSync(pagesDir, { withFileTypes: true });

	for (const entry of entries) {
		if (entry.isFile() && entry.name.endsWith('.pug')) {
			pages.push({
				template: path.join(pagesDir, entry.name),
				filename: entry.name.replace(/\.pug$/, '.html')
			});
		}

		if (entry.isDirectory()) {
			const folderName = entry.name;
			const pugPath = path.join(pagesDir, folderName, `${folderName}.pug`);

			if (fs.existsSync(pugPath)) {
				pages.push({
					template: pugPath,
					filename: `${folderName}.html`
				});
			}
		}
	}

	return pages;
};

const getPugFiles = (dir, files = []) => {
	if (!fs.existsSync(dir)) return files;

	fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
		const fullPath = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			getPugFiles(fullPath, files);
		}

		if (entry.isFile() && entry.name.endsWith('.pug')) {
			files.push(fullPath);
		}
	});

	return files;
};

const envKeys = Object.keys(process.env)
	.filter(key => key.startsWith('APP_'))
	.reduce((acc, key) => {
		acc[`process.env.${key}`] = JSON.stringify(process.env[key]);
		return acc;
	}, {});

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const isDev = process.env.NODE_ENV === 'development';

const PATHS = {
	src: path.resolve(__dirname, 'src'),
	dist: path.resolve(__dirname, 'dist'),
	assets: 'assets'
};

const PAGES_DIR = path.resolve(PATHS.src, 'pages');
const PAGES = getPages(PAGES_DIR);

const MODALS_DIR = path.resolve(PATHS.src, 'shared/ui/modals');
const MODALS = getPugFiles(MODALS_DIR);

export default {
	mode: isDev ? 'development' : 'production',

	entry: {
		main: path.resolve(PATHS.src, 'app/index.js')
	},

	output: {
		path: PATHS.dist,
		filename: `${PATHS.assets}/js/[name].js`,
		assetModuleFilename: `${PATHS.assets}/img/[name][ext]`,
		publicPath: isDev ? '/' : './',
		clean: false
	},

	resolve: {
		alias: {
			'@': PATHS.src
		},
		extensions: ['.js'],
		mainFiles: ['index']
	},

	module: {
		rules: [
			// JS
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: '/node_modules/',
				resolve: {
					fullySpecified: false
				}
			},

			// SCSS
			{
				test: /\.(scss|sass)$/i,
				use: [
					isDev
						? 'style-loader'
						: {
								loader: MiniCssExtractPlugin.loader,
								options: { publicPath: '../../' }
							},
					{
						loader: 'css-loader',
						options: {
							sourceMap: isDev,
							url: true
						}
					},
					{
						loader: 'sass-loader',
						options: {
							sourceMap: isDev
						}
					}
				]
			},
			// PUG
			{
				test: /\.pug$/,
				loader: '@webdiscus/pug-loader',
				options: {
					pretty: true,
					basedir: PATHS.src
				}
			},

			// Fonts
			{
				test: /\.(woff2?|eot|ttf|otf)$/i,
				type: 'asset/resource',
				generator: {
					filename: `${PATHS.assets}/fonts/[name][ext]`
				}
			},

			// Images
			{
				test: /\.(png|jpg|jpeg|webp)$/i,
				type: 'asset/resource',
				generator: {
					filename: `${PATHS.assets}/img/[name][ext]`
				}
			},

			// SVG sprite
			{
				test: /\.svg$/,
				include: path.resolve(PATHS.src, 'shared/sprite'),
				use: [
					{
						loader: 'svg-sprite-loader',
						options: {
							extract: true,
							spriteFilename: `${PATHS.assets}/img/sprite.svg`
						}
					}
				]
			},

			// SVG (обычные)
			{
				test: /\.svg$/,
				exclude: path.resolve(PATHS.src, 'shared/sprite'),
				type: 'asset/resource',
				generator: {
					filename: `${PATHS.assets}/img/[name][ext]`
				}
			},

			// Files (pdf, video, etc)
			{
				test: /\.(pdf|mp4|webm|mov)$/i,
				type: 'asset/resource',
				generator: {
					filename: `${PATHS.assets}/files/[name][ext]`
				}
			}
		]
	},

	plugins: [
		!isDev && new CleanWebpackPlugin(),

		new MiniCssExtractPlugin({
			filename: `${PATHS.assets}/css/[name].css`,
			chunkFilename: '[id].css'
		}),

		new CopyWebpackPlugin({
			patterns: [
				{
					from: path.resolve(PATHS.src, 'shared/static'),
					to: `${PATHS.assets}/static`,
					noErrorOnMissing: true
				},
				{
					from: path.resolve(PATHS.src, 'shared/fonts'),
					to: `${PATHS.assets}/fonts`,
					noErrorOnMissing: true
				},
				{
					from: path.resolve(PATHS.src, 'shared/img'),
					to: `${PATHS.assets}/img`,
					noErrorOnMissing: true
				},
				{
					from: path.resolve(PATHS.src, 'shared/files'),
					to: `${PATHS.assets}/files`,
					noErrorOnMissing: true
				}
			]
		}),

		new SpriteLoaderPlugin(),

		new webpack.DefinePlugin({
			'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV)
		}),
		...PAGES.map(
			page =>
				new HtmlWebpackPlugin({
					template: page.template,
					filename: page.filename,
					inject: 'body'
				})
		),
		...MODALS.map(modalPath => {
			const name = path.basename(modalPath, '.pug');

			return new HtmlWebpackPlugin({
				template: modalPath,
				filename: `modals/${name}.html`,
				inject: false,
				minify: false
			});
		}),

		new ImageminWebpWebpackPlugin(),
		new webpack.DefinePlugin({
			'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV),
			...envKeys
		}),
		new BeautifyHtmlWebpackPlugin({
			indent_size: 4,
			indent_char: ' ',
			indent_level: 0,
			indent_with_tabs: false,
			preserve_newlines: true,
			max_preserve_newlines: 10,
			jslint_happy: false,
			space_after_named_function: false,
			space_after_anon_function: false,
			brace_style: 'collapse',
			keep_array_indentation: false,
			keep_function_indentation: false,
			space_before_conditional: true,
			break_chained_methods: false,
			eval_code: false,
			unescape_strings: false,
			wrap_line_length: 0,
			indent_empty_lines: false,
			templating: ['auto']
		})
	].filter(Boolean),

	optimization: {
		minimize: !isDev,
		minimizer: [
			new TerserPlugin({
				extractComments: false
			}),
			new ImageMinimizerPlugin({
				minimizer: {
					implementation: ImageMinimizerPlugin.imageminMinify,
					options: {
						plugins: [
							['imagemin-mozjpeg', { progressive: true }],
							['imagemin-optipng', { optimizationLevel: 5 }]
						]
					}
				}
			})
		]
	},

	devServer: {
		port: 7777,
		open: true,
		static: PATHS.dist,
		hot: false,
		liveReload: true,
		watchFiles: [
			path.resolve(PATHS.src, 'pages/**/*.pug'),
			path.resolve(PATHS.src, 'shared/ui/modals/**/*.pug')
		]
	}
};
