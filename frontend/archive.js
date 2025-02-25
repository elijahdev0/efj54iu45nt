// require modules
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const wpPluginsFolder = path.resolve('../../');
const outputPath = path.join(wpPluginsFolder, 'fs-poster.zip');

console.log('wp plugins folder:', wpPluginsFolder);
console.log('output path:', outputPath);

if (fs.existsSync(outputPath)) {
    fs.rmSync(outputPath);
    console.log('deleted old zip archive');
}

console.log('creating zip archive');

const t0 = performance.now();

const output = fs.createWriteStream(outputPath);
const archive = archiver('zip', {
    zlib: { level: 9 } // Sets the compression level.
});

output.on('close', function() {
    const t1 = performance.now();
    const secs = ((t1 - t0) / 1000).toFixed(2);
    console.log('done. ' + archive.pointer() + ' total bytes written, in ' + secs + ' seconds');
});

output.on('end', function() {
    console.log('Data has been drained');
});

archive.on('warning', function(err) {
    console.log('warning:', err);
});

archive.on('error', function(err) {
    console.log('error:', err);
});

// pipe archive data to the file
archive.pipe(output);

archive.glob('fs-poster/**', {
    cwd: wpPluginsFolder,
    dot: false,
    ignore: [
        '**/node_modules/**',
        'fs-poster/frontend/src/**',
        '**/.*',
        '**/.DS_Store',
        '**/__MACOSX',
        '*.zip'
    ]
});

archive.finalize();