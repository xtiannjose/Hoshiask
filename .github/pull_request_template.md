## Summary

<!-- Before / now, in plain words. Link the issue: Closes #N -->

## Evidence

<!-- Tests run and their results, counts, screenshots of UI changes. For a bug: the test that fails without the fix. -->

## Merge Danger

<!-- Door: two-way (cheap to roll back) or one-way (data destroyed, a migration that cannot be undone, an email that cannot be recalled). Blast Radius: what could break and for whom (a screen, a report number, an email, a setting). -->

## Checklist

- [ ] `composer qa` and `npm run qa` pass (add `npm run qa:full` for UI changes)
- [ ] New settings are in `.env.example` and `docs/configuration.md`
- [ ] Docs updated where behaviour changed (and the guide's PDF rebuilt if `docs/ticket-system-workflow.md` changed)
- [ ] Permission is checked on the server, and security-relevant actions are in the audit trail
