# Tooling: plugins, skills, servers, hooks and tools

Everything outside the application code that Claude Code uses on this project, what each piece is for, where it comes from and how to put it back. It was checked against the maintainer's computer on 2026-10-11. The step-by-step setup of a new device is in [plan.md](plan.md), "Continue on a new device"; this file is the inventory behind those steps.

When a plugin, skill, server, hook or tool is added or removed, change this file in the same pull request.

## At a glance

| Kind | What | Source | Used for |
|---|---|---|---|
| Plugin | `caveman` | `JuliusBrussee/caveman` | Short replies, to save reading time and tokens |
| Plugin | `ponytail` | `DietrichGebert/ponytail` | Smallest-change discipline, and `/ponytail-audit` for a whole-repository audit |
| Plugin | `watch` | `bradautomates/claude-video` | Looking at a video or a screen recording |
| Plugin | `claude-code-setup` | `anthropics/claude-plugins-official` | Suggests Claude Code automations for a codebase |
| Skills | 38 skills | `mattpocock/skills` | The working loop: audit, plan, ticket, build, review, pull request, retro |
| Skill | `design-taste-frontend` | `Leonxlnx/taste-skill` | Front-end design judgement |
| Skill | `find-skills` | `vercel-labs/skills` | Finding more skills |
| Skill | `apple-design` | `emilkowalski/skills` | Interface design reference |
| Skill | `motion` | `npx motion-ai` | Animation code with the Motion library |
| Server | `motion` | `https://mcp.motion.dev` (HTTP) | Searching Motion's documentation and examples |
| Server | Claude Docs | The Claude account (syncs on sign-in) | Living documents, only when one is asked for |
| Hook | `block-dangerous-git.mjs` | This repository | Stops destructive git commands |
| Hook | `pint-on-edit.mjs` | This repository | Runs Pint on every PHP file Claude edits |

## Plugins

Install inside Claude Code, in this order:

```text
/plugin marketplace add JuliusBrussee/caveman
/plugin install caveman@caveman
/plugin marketplace add DietrichGebert/ponytail
/plugin install ponytail@ponytail
/plugin marketplace add bradautomates/claude-video
/plugin install watch@claude-video
/plugin install claude-code-setup@claude-plugins-official
```

- **caveman** (3.2.0). Answers in short fragments with all the facts kept. Switch it off with "stop caveman" or "normal mode". Anything written into the repository (docs, commits, pull requests, code comments) stays in plain sentences.
- **ponytail** (5.1.0). Makes the change as small as will do the job, and says what was skipped. `/ponytail-audit` reads the whole repository like the developer who will be paged when it breaks and reports numbered findings. The three deep checks in [plan.md](plan.md) used it.
- **watch** (0.3.2). Lets Claude look at a video file or link, for example a screen recording of a bug.
- **claude-code-setup**. Recommends hooks, skills and servers for a project. Optional.

## Skills

Install from a normal terminal (a global install, so every project gets them):

```bash
npx skills add mattpocock/skills -g
npx skills add Leonxlnx/taste-skill -g
npx skills add vercel-labs/skills -g
npx skills add emilkowalski/skills -g
npx motion-ai                          # interactive: run it yourself in a terminal
```

`mattpocock/skills` installs 38 skills. The ones this project's loop relies on (see the phases in [plan.md](plan.md)):

| Step | Skills |
|---|---|
| Set up and record decisions | `setup-matt-pocock-skills` (made `docs/agents/`), `domain-modeling`, `git-guardrails-claude-code` (made the git hook) |
| Find what to improve | `code-review`, `improve-codebase-architecture`, `diagnosing-bugs`, `research` |
| Plan one change | `grill-with-docs`, `to-spec`, `to-tickets`, `triage`, `wayfinder` |
| Build | `implement`, `implement-spec`, `tdd`, `prototype` |
| Ship | `pr` (the pull request body: Summary, Evidence, Merge Danger), `handoff` |
| Look back | `retro` |

The rest are installed with the set and not part of the loop: `ask-matt`, `chief-of-staff`, `claude-handoff`, `codebase-design`, `grill-me`, `grilling`, `loop-me`, `migrate-to-shoehorn`, `scaffold-exercises`, `setup-pre-commit`, `setup-ts-deep-modules`, `teach`, `to-questionnaire`, `wait-what`, `wizard`, `writing-beats`, `writing-for-agents`, `writing-fragments`, `writing-shape`.

The Claude account also syncs its own skills (documents, PDF, spreadsheets and others) by itself when you sign in; nothing to install.

## Servers (MCP)

| Server | How it is added | Notes |
|---|---|---|
| `motion` | `claude mcp add --transport http -s user motion https://mcp.motion.dev` | Available in every project. Remove with `claude mcp remove motion -s user`. Needs no key |
| Claude Docs | The Claude account connector | Appears after signing in to the account; used only when a Claude document is asked for |

`claude mcp list` shows both as connected.

## Hooks (this repository, `.claude/settings.json`)

Both load when Claude Code starts inside the project folder.

- **`block-dangerous-git.mjs`** runs before every Bash or PowerShell command. It blocks force pushes, any push to `main` or `master` (also a bare `git push` while on one of them), `reset --hard`, `clean -f`, `branch -D` and `checkout .` / `restore .`. Feature branches can be pushed. The maintainer merges every pull request. `node .claude/hooks/block-dangerous-git.mjs --self-test` runs its 22 cases and must print `all 22 passed`.
- **`pint-on-edit.mjs`** runs after every Edit or Write of a `.php` file (not Blade) and formats it with Laravel Pint, so `vendor/bin/pint --test` in CI stays green. A syntax error makes it stay quiet; PHPStan and the tests report it.

To protect every project on a device and not only this one, copy `block-dangerous-git.mjs` to `~/.claude/hooks/` and add the same `PreToolUse` entry to `~/.claude/settings.json`.

## Command-line tools

| Tool | Version | Needed for |
|---|---|---|
| PHP | 8.3 or newer | The app, the tests, Pint, PHPStan |
| Composer | 2 | `composer install`, `composer qa` |
| Node.js and npm | 22.12 or newer | The build, ESLint, Vitest, Playwright |
| Git and the GitHub CLI (`gh`) | any recent | Branches and pull requests (`gh auth login` once) |
| Playwright's Chromium | `npx playwright install chromium` | The browser tests |
| PostgreSQL | 16 or newer, with `psql` and `pg_dump` | The PostgreSQL test run and PostgreSQL backups (the suite itself needs no server: it uses SQLite in memory) |
| Python 3 | optional | Small one-off scripts that edit files, see the quirks below |
| ClamAV | optional | Virus scanning of uploads on a server, see [security.md](../security.md) |

## Quirks worth knowing

These each cost a failed run once.

**Windows**

- `composer` is not on the Git Bash path. In PowerShell call `composer.bat`, `npm.cmd` and `npx.cmd`.
- Windows PowerShell 5.1 prints native commands' error output in red (PHPStan's "Using configuration file" line). The result is the `[OK]` line or the exit code, not the red text.
- Git Bash turns an argument that starts with `/` (a URL path such as `/admin/tickets`) into a Windows path. Run such commands from PowerShell, or set `MSYS_NO_PATHCONV=1`.
- `php -r '...'` loses double quotes in PowerShell. Put the code in a script file.
- A script pasted as a here-document loses its backslashes (`'\\'` becomes `'\'`, a `\U` in a namespace is a Python error). Write scripts with the editor's Write tool and run the file.
- A script that rewrites repository files in text mode turns LF into CRLF, and `vendor/bin/pint --test` then fails. Open files with `newline=''`, or use the Edit and Write tools. Check with `git ls-files --eol -- <file>`.

**Editor hooks**

- `pint-on-edit.mjs` deletes an import that nothing uses yet. When adding `use X;` together with the code that uses `X`, edit the code first, or check `git diff` for the `use` line afterwards.
- The same hook formats scratch PHP files too and can move `use` lines below the code, so scripts that boot the app should use plain string class names.

**Tests**

- Do not start two separate test runs at once (for example the SQLite suite and the PostgreSQL suite). Their fake disks share one folder under `storage/framework/testing/disks`, and the backup test fails when the runs overlap. `php artisan test --parallel` is safe: it gives each worker its own folder.
- The browser tests start their own server and **refuse to use port 8000**, which another project's dev server often holds. Give them a database of their own: [testing.md](../testing.md).
- A PostgreSQL run needs an empty database whose name ends in `_test` (`tests/Support/TestDatabaseGuard` refuses anything else), with `DB_CONNECTION=pgsql`, the `DB_*` variables and `CI_EXPECT_DB_DRIVER=pgsql` set in the shell. Drop it afterwards.
- `php artisan test --parallel` runs the PHP suite in about a minute; `composer qa` stays serial (about three minutes).
- To look at a generated PDF (poppler is not installed): `pip install --target <folder>/pylibs pypdfium2 pillow`, then render pages to PNG with `pypdfium2` and open the PNGs.

## What lives only on one computer

None of these are in the repository, so a new device starts without them:

- `.env` and any `.env.e2e`.
- Claude Code's private notes for the project (`~/.claude/projects/.../memory`). Anything a future session must know belongs in this file, [plan.md](plan.md) or [security.md](../security.md) instead.
- The globally installed plugins, skills and servers above, which the steps in [plan.md](plan.md) put back.

## Check that everything is in place

```text
/plugin                                                   # inside Claude Code: the four plugins, enabled
claude mcp list                                           # motion and Claude Docs, connected
Get-ChildItem $HOME\.claude\skills                        # PowerShell: the skills of the sections above
node .claude/hooks/block-dangerous-git.mjs --self-test    # all 22 passed
composer qa ; npm run qa                                  # the full quality gate
```
