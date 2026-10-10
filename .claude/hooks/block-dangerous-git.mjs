// PreToolUse hook (Bash + PowerShell): blocks destructive git commands.
// Plain `git push` of a feature branch is allowed; force pushes and pushes to main/master are not.
// Exit 2 + stderr = blocked, Claude sees the message.
import { execSync } from 'node:child_process';

const RULES = [
    [/\bgit\b[^;&|\n]*\bpush\b[^;&|\n]*(\s--force(-with-lease)?\b|\s-f\b|\s\+\S)/, 'force push'],
    [/\bgit\b[^;&|\n]*\bpush\b[^;&|\n]*[\s:](main|master)(\s|$)/, 'push to main/master'],
    [/\bgit\b[^;&|\n]*\breset\b[^;&|\n]*--hard\b/, 'reset --hard'],
    [/\bgit\b[^;&|\n]*\bclean\b[^;&|\n]*\s-[a-zA-Z]*f/, 'clean -f'],
    [/\bgit\b[^;&|\n]*\bbranch\b[^;&|\n]*(\s-D\b|--delete\s+--force|--force\s+--delete)/, 'branch -D'],
    [/\bgit\b[^;&|\n]*\b(checkout|restore)\b(\s+--)?\s+\.(\s|$)/, 'discard all changes'],
];

function currentBranch() {
    try {
        return execSync('git rev-parse --abbrev-ref HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
    } catch {
        return '';
    }
}

export function check(command, branch = currentBranch) {
    for (const [pattern, name] of RULES) {
        if (pattern.test(command)) return name;
    }
    // `git push` with no branch named pushes the current branch.
    if (/\bgit\b[^;&|\n]*\bpush\b/.test(command) && ['main', 'master'].includes(branch())) {
        return 'push while on main/master';
    }
    return null;
}

if (process.argv[2] === '--self-test') {
    const onMain = () => 'main';
    const onFeature = () => 'fix/x';
    const cases = [
        ['git push -u origin fix/x', onFeature, null],
        ['git push origin feature/main-menu', onFeature, null],
        ['git status && git push', onFeature, null],
        ['git push', onMain, 'push while on main/master'],
        ['git push origin main', onFeature, 'push to main/master'],
        ['git push origin HEAD:main', onFeature, 'push to main/master'],
        ['git push --force origin fix/x', onFeature, 'force push'],
        ['git push --force-with-lease', onFeature, 'force push'],
        ['git push origin +fix/x', onFeature, 'force push'],
        ['git reset --hard HEAD~1', onFeature, 'reset --hard'],
        ['git reset --soft HEAD~1', onFeature, null],
        ['git clean -fd', onFeature, 'clean -f'],
        ['git clean -n', onFeature, null],
        ['git branch -D old', onFeature, 'branch -D'],
        ['git branch -d old', onFeature, null],
        ['git checkout .', onFeature, 'discard all changes'],
        ['git checkout -- .', onFeature, 'discard all changes'],
        ['git restore .', onFeature, 'discard all changes'],
        ['git checkout ./app/Models/Ticket.php', onFeature, null],
        ['git switch -c fix/y', onFeature, null],
        ["git commit -F - <<'EOF'\nKeep the plan\nEOF\ncat > pr.md <<'EOF'\nno push to main here\n+├── .claude/\nEOF", onFeature, null],
        ['git status\ngit push --force', onFeature, 'force push'],
    ];
    let failed = 0;
    for (const [command, branch, expected] of cases) {
        const got = check(command, branch);
        if (got !== expected) {
            failed++;
            console.error(`FAIL: ${command} -> ${got}, expected ${expected}`);
        }
    }
    console.log(failed ? `${failed} failed` : `all ${cases.length} passed`);
    process.exit(failed ? 1 : 0);
}

let input = '';
process.stdin.on('data', (chunk) => (input += chunk));
process.stdin.on('end', () => {
    let command = '';
    try {
        command = JSON.parse(input).tool_input?.command ?? '';
    } catch {
        process.exit(0);
    }
    const hit = check(command);
    if (hit) {
        console.error(`BLOCKED (${hit}): '${command}'. The user has not allowed this; ask them to run it themselves.`);
        process.exit(2);
    }
    process.exit(0);
});
