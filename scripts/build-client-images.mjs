#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const WIDTHS = [320, 640, 960, 1280, 1600];
const SUPPORTED_EXTENSIONS = new Set(['.jpg', '.jpeg', '.png', '.webp', '.avif']);
const CLIENT_KEY_PATTERN = /^[a-z0-9][a-z0-9_-]*$/;
const ASSET_SEGMENT_PATTERN = /^[a-z0-9][a-z0-9_-]*$/;

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(scriptDirectory, '..');
const clientKey = process.argv[2] ?? '';

const rawDirectory = path.join(
    projectRoot,
    'clients',
    clientKey,
    'resources',
    'images',
    'raw',
);
const publicMediaDirectory = path.join(projectRoot, 'public', 'media');
const stageDirectory = path.join(
    publicMediaDirectory,
    `.build-${clientKey}-${process.pid}-${Date.now()}`,
);
const stageAssetsDirectory = path.join(stageDirectory, 'assets');
const stageManifestPath = path.join(stageDirectory, 'manifest.json');
const finalAssetsDirectory = path.join(publicMediaDirectory, 'assets');
const finalManifestPath = path.join(publicMediaDirectory, 'manifest.json');

try {
    if (! CLIENT_KEY_PATTERN.test(clientKey)) {
        fail('Usage: npm run media:build -- client-key');
    }

    await assertDirectory(rawDirectory, `Selected client raw image directory does not exist: ${rawDirectory}`);
    await fs.mkdir(publicMediaDirectory, { recursive: true });
    await fs.rm(stageDirectory, { recursive: true, force: true });
    await fs.mkdir(stageAssetsDirectory, { recursive: true });

    try {
        const sourceFiles = await collectSourceFiles(rawDirectory);
        const assets = {};

        for (const source of sourceFiles) {
            if (Object.hasOwn(assets, source.key)) {
                fail(`Raw images collide on media asset key [${source.key}].`);
            }

            assets[source.key] = await processImage(source);
        }

        const manifest = {
            version: 1,
            client: clientKey,
            assets,
        };

        await fs.writeFile(
            stageManifestPath,
            `${JSON.stringify(manifest, null, 2)}\n`,
            'utf8',
        );

        await fs.rm(finalAssetsDirectory, { recursive: true, force: true });
        await fs.rename(stageAssetsDirectory, finalAssetsDirectory);
        await fs.rename(stageManifestPath, finalManifestPath);

        console.log(`Built ${sourceFiles.length} media asset(s) for ${clientKey}.`);
        console.log(`Manifest: ${path.relative(projectRoot, finalManifestPath)}`);
    } finally {
        await fs.rm(stageDirectory, { recursive: true, force: true });
    }
} catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exitCode = 1;
}

async function collectSourceFiles(directory) {
    const results = [];

    async function walk(currentDirectory) {
        const entries = await fs.readdir(currentDirectory, { withFileTypes: true });
        entries.sort((a, b) => a.name.localeCompare(b.name));

        for (const entry of entries) {
            if (entry.name.startsWith('.')) {
                continue;
            }

            const absolutePath = path.join(currentDirectory, entry.name);

            if (entry.isDirectory()) {
                await walk(absolutePath);
                continue;
            }

            if (! entry.isFile()) {
                fail(`Raw image directory contains unsupported entry: ${relativePath(absolutePath)}`);
            }

            const extension = path.extname(entry.name).toLowerCase();
            const relative = slashPath(path.relative(rawDirectory, absolutePath));

            if (! SUPPORTED_EXTENSIONS.has(extension)) {
                fail(`Raw image directory contains unsupported file [${relative}].`);
            }

            const key = relative.slice(0, -extension.length);
            validateAssetKey(key, relative);

            results.push({
                absolutePath,
                relative,
                key,
            });
        }
    }

    await walk(directory);
    results.sort((a, b) => a.key.localeCompare(b.key));

    return results;
}

async function processImage(source) {
    const input = await fs.readFile(source.absolutePath);
    const metadata = await sharp(input).metadata();

    if (! Number.isInteger(metadata.width) || ! Number.isInteger(metadata.height)) {
        fail(`Unable to determine dimensions for raw image [${source.relative}].`);
    }

    const swapsDimensions = [5, 6, 7, 8].includes(metadata.orientation ?? 1);
    const naturalWidth = swapsDimensions ? metadata.height : metadata.width;
    const naturalHeight = swapsDimensions ? metadata.width : metadata.height;
    const outputWidths = responsiveWidths(naturalWidth);
    const outputDirectory = path.join(stageAssetsDirectory, ...source.key.split('/'));

    await fs.mkdir(outputDirectory, { recursive: true });

    const sources = {
        avif: [],
        webp: [],
    };

    for (const width of outputWidths) {
        const avifPath = path.join(outputDirectory, `${width}.avif`);
        const webpPath = path.join(outputDirectory, `${width}.webp`);

        const avifInfo = await sharp(input)
            .rotate()
            .resize({ width, withoutEnlargement: true })
            .avif({ quality: 65, effort: 4 })
            .toFile(avifPath);

        const webpInfo = await sharp(input)
            .rotate()
            .resize({ width, withoutEnlargement: true })
            .webp({ quality: 80 })
            .toFile(webpPath);

        sources.avif.push({
            path: outputPath(source.key, width, 'avif'),
            width: avifInfo.width,
            height: avifInfo.height,
        });

        sources.webp.push({
            path: outputPath(source.key, width, 'webp'),
            width: webpInfo.width,
            height: webpInfo.height,
        });
    }

    const placeholder = await sharp(input)
        .rotate()
        .resize({ width: Math.min(32, naturalWidth), withoutEnlargement: true })
        .webp({ quality: 35 })
        .toBuffer();

    const fallback = sources.webp.at(-1);

    if (! fallback) {
        fail(`No WebP fallback was generated for [${source.relative}].`);
    }

    return {
        source: {
            path: source.relative,
            sha256: createHash('sha256').update(input).digest('hex'),
        },
        width: naturalWidth,
        height: naturalHeight,
        placeholder: `data:image/webp;base64,${placeholder.toString('base64')}`,
        fallback,
        sources,
    };
}

function responsiveWidths(naturalWidth) {
    const maximumWidth = Math.min(naturalWidth, WIDTHS.at(-1));
    const widths = WIDTHS.filter((width) => width < maximumWidth);

    widths.push(maximumWidth);

    return [...new Set(widths)].sort((a, b) => a - b);
}

function outputPath(key, width, extension) {
    return `assets/${key}/${width}.${extension}`;
}

function validateAssetKey(key, sourcePath) {
    const segments = key.split('/');

    if (segments.length === 0 || segments.some((segment) => ! ASSET_SEGMENT_PATTERN.test(segment))) {
        fail(
            `Raw image [${sourcePath}] must use lowercase letters, numbers, hyphens, and underscores in every path segment.`,
        );
    }
}

async function assertDirectory(directory, message) {
    try {
        const stats = await fs.stat(directory);

        if (! stats.isDirectory()) {
            fail(message);
        }
    } catch {
        fail(message);
    }
}

function slashPath(value) {
    return value.split(path.sep).join('/');
}

function relativePath(value) {
    return slashPath(path.relative(projectRoot, value));
}

function fail(message) {
    throw new Error(message);
}