const { spawn } = require('node:child_process');
const { mkdtempSync, realpathSync, rmdirSync, symlinkSync, unlinkSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');

const projectRoot = __dirname;
const viteCli = 'node_modules/vite/bin/vite.js';

function createViteRoot() {
    if (process.platform !== 'win32' || !projectRoot.includes('#')) {
        return projectRoot;
    }

    // Keep a literal # out of the paths that Vite and Rolldown use as module URLs.
    const directory = mkdtempSync(join(realpathSync.native(tmpdir()), 'oqari-vite-'));
    const alias = join(directory, 'app');

    try {
        symlinkSync(projectRoot, alias, 'junction');
    } catch (error) {
        rmdirSync(directory);
        throw error;
    }

    process.once('exit', () => {
        try {
            unlinkSync(alias);
            rmdirSync(directory);
        } catch (error) {
            console.error('Gagal membersihkan jalur sementara Vite:', error.message);
        }
    });

    return alias;
}

async function main() {
    const [mode = 'all', ...viteArgs] = process.argv.slice(2);
    const viteRoot = createViteRoot();
    const aliased = viteRoot !== projectRoot;
    const viteEnv = { OQARI_VITE_ROOT: aliased ? viteRoot : '' };
    const nodeArgs = aliased ? ['--preserve-symlinks', '--preserve-symlinks-main'] : [];

    if (mode === 'all') {
        const { default: concurrently } = await import('concurrently');
        const { result } = concurrently([
            { name: 'APP', command: 'php artisan serve --host=127.0.0.1 --port=8015', cwd: projectRoot, prefixColor: 'green' },
            { name: 'VITE', command: ['node', ...nodeArgs, viteCli].join(' '), cwd: viteRoot, env: viteEnv, prefixColor: 'cyan' },
        ], { prefix: 'name', killOthersOn: ['failure', 'success'] });
        await result.catch(() => { process.exitCode = 1; });
        return;
    }

    const command = mode === 'build' ? ['build'] : [];
    const child = spawn(process.execPath, [...nodeArgs, viteCli, ...command, ...viteArgs], {
        cwd: viteRoot,
        env: { ...process.env, ...viteEnv },
        stdio: 'inherit',
    });
    for (const signal of ['SIGINT', 'SIGTERM']) {
        process.once(signal, () => {
            if (process.platform === 'win32' && signal === 'SIGINT') {
                // Both processes receive Ctrl+C from the console. Give Vite time to finish first.
                setTimeout(() => child.kill(), 1000).unref();
            } else {
                child.kill(signal);
            }
        });
    }
    child.once('error', error => {
        console.error('Gagal menjalankan Vite:', error.message);
        process.exitCode = 1;
    });
    child.once('exit', code => { process.exitCode = code ?? 1; });
}

main().catch(error => {
    console.error('Gagal menjalankan server pengembangan:', error.message);
    process.exitCode = 1;
});

