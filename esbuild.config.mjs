import * as esbuild from 'esbuild';

const buildConfig = {
    entryPoints: ['./Resources/Private/TypeScript/calendar.ts'],
    format: 'esm',
    bundle: true,
    sourcemap: true,
    outdir: 'Resources/Public/JavaScript/',
    logLevel: 'info',
};

if (process.argv.includes('--build')) {
    buildConfig.sourcemap = false;
    buildConfig.minify = true;
    await esbuild.build(buildConfig);
} else {
    const ctx = await esbuild.context(buildConfig);
    await ctx.watch();
}
