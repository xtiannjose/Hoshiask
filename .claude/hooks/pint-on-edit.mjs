// PostToolUse hook (Edit|Write): run Pint on the PHP file Claude just changed, so `pint --test` in CI stays green.
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

let input = '';
process.stdin.on('data', (chunk) => (input += chunk));
process.stdin.on('end', () => {
    let file = '';
    try {
        file = JSON.parse(input).tool_input?.file_path ?? '';
    } catch {
        process.exit(0);
    }
    const root = process.env.CLAUDE_PROJECT_DIR ?? process.cwd();
    const pint = join(root, 'vendor', 'bin', 'pint');
    if (!file.endsWith('.php') || file.endsWith('.blade.php') || !existsSync(pint)) process.exit(0);
    try {
        execFileSync('php', [pint, file], { cwd: root, stdio: 'ignore' });
    } catch {
        // A syntax error makes Pint fail; PHPStan and the tests will report it, so stay quiet here.
    }
    process.exit(0);
});
