import * as esbuild from 'esbuild';

const commonConfig = {
    format: 'esm',
    bundle: true,
    sourcemap: true,
    logLevel: 'info',
    external: ["@typo3/*", "interactjs", "lit", "lit/decorators.js", "css-tree", "nprogress"],
};

const javascriptConfig = {
    ...commonConfig,
    entryPoints: ['./Resources/Private/TypeScript/calendar.ts'],
    outdir: 'Resources/Public/JavaScript/',
};

const cssConfig = {
    ...commonConfig,
    entryPoints: ['./Resources/Private/Css/calendar.css'],
    outdir: 'Resources/Public/Css/',
    entryNames: 'calendar',
};

if (process.argv.includes('--build')) {
    await Promise.all([
        esbuild.build({...javascriptConfig, sourcemap: false, minify: true}),
        esbuild.build({...cssConfig, sourcemap: false, minify: true}),
    ]);
} else {
    const [javascriptContext, cssContext] = await Promise.all([
        esbuild.context(javascriptConfig),
        esbuild.context(cssConfig),
    ]);
    await Promise.all([
        javascriptContext.watch(),
        cssContext.watch(),
    ]);
}
