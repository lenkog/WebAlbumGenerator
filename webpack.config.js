const path = require('path');
const VueLoaderPlugin = require('vue-loader/lib/plugin')

const mode = process.env.NODE_ENV === 'production' ? 'production' : 'development';
console.log("Webpack mode: " + mode);

module.exports = {
    mode: mode,
    entry: path.resolve(__dirname, 'src/main/js/main.ts'),
    output: {
        filename: 'main.js',
        path: path.resolve(__dirname, 'build'),
    },
    resolve: {
        extensions: [".ts", ".js"],
    },
    module: {
        rules: [
            {
                test: /\.vue$/,
                use: 'vue-loader',
            },
            {
                test: /\.tsx?$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: 'ts-loader',
                        options: {
                            appendTsSuffixTo: [/\.vue$/],
                        },
                    },
                ],
            },
            {
                test: /\.less$/,
                use: [
                    {
                        loader: 'style-loader',
                    },
                    {
                        loader: 'css-loader',
                    },
                    {
                        loader: 'less-loader',
                    },
                ],
            },
        ],
    },
    plugins: [
        new VueLoaderPlugin(),
    ],
    devServer: {
        contentBase: path.resolve(__dirname, 'build'),
    },
};

if (mode == 'development') {
    module.exports.devtool = 'cheap-module-eval-sourcemap';
}