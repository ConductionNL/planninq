const path = require('path')
const fs = require('fs')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const { VueLoaderPlugin } = require('vue-loader')
const NodePolyfillPlugin = require('node-polyfill-webpack-plugin')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

const appId = 'planix'
webpackConfig.entry = {
	main: {
		import: path.join(__dirname, 'src', 'main.js'),
		filename: appId + '-main.js',
	},
	adminSettings: {
		import: path.join(__dirname, 'src', 'settings.js'),
		filename: appId + '-settings.js',
	},
}

// Use local source when available (monorepo dev), otherwise fall back to npm package
const localLib = path.resolve(__dirname, '../nextcloud-vue/src')
const useLocalLib = fs.existsSync(localLib)

webpackConfig.resolve = webpackConfig.resolve || {}
webpackConfig.resolve.modules = [path.resolve(__dirname, 'node_modules'), 'node_modules']
webpackConfig.resolve.alias = {
	...(webpackConfig.resolve.alias || {}),
	'@': path.resolve(__dirname, 'src'),
	...(useLocalLib ? { '@conduction/nextcloud-vue': localLib } : {}),
	// Deduplicate shared packages so the aliased library source uses
	// the same instances as the app (prevents dual-Pinia / dual-Vue bugs).
	'vue$': path.resolve(__dirname, 'node_modules/vue'),
	'pinia$': path.resolve(__dirname, 'node_modules/pinia'),
	'@nextcloud/vue$': path.resolve(__dirname, 'node_modules/@nextcloud/vue'),
}

// The base @nextcloud/webpack-vue-config already registers VUE, CSS, SCSS, JS
// and ASSET rules. Re-pushing .vue/.css rules here stacked a second loader
// chain on top of the base one (css-loader!style-loader!css-loader!), so
// style-loader's JS output was fed back into css-loader and every stylesheet
// failed with "SyntaxError: Unknown word import". Keep the base rules; only
// replace plugins below to avoid a duplicate VueLoaderPlugin.
webpackConfig.plugins = [
	new VueLoaderPlugin(),
	new webpack.DefinePlugin({ appName: JSON.stringify(appId) }),
	new webpack.DefinePlugin({ appVersion: JSON.stringify(process.env.npm_package_version) }),
	new NodePolyfillPlugin({ additionalAliases: ['process'] }),
]

// Force @nextcloud/dialogs to resolve from this app's node_modules,
// preventing the nextcloud-vue submodule's nested deps (Vue 3) from leaking in.
webpackConfig.resolve.alias['@nextcloud/dialogs'] = path.resolve(__dirname, 'node_modules/@nextcloud/dialogs')

// Override publicPath so dynamic webpack chunks load from the standard
// Nextcloud user-installed-app layout. The webpack-vue-config default of
// /apps/{appName}/js/ is PHP-routed and returns 401 for static asset
// requests; /apps-extra/ doesn't exist in the dev container. Every other
// ConductionNL app is served from /custom_apps/<slug>/js/.
webpackConfig.output = {
	...webpackConfig.output,
	publicPath: '/custom_apps/planix/js/',
}

module.exports = webpackConfig
