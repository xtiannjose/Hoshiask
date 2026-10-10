# Security policy

## Reporting a problem

Do not open a public issue or pull request for a security problem. Tell the repository owner privately: use the contact on the owner's GitHub profile, or the "Report a vulnerability" button on the repository's Security tab if it is enabled. Include what you found, the page or request involved, and the steps to reproduce it. Do not include real personal data from the system.

## What to expect

- The owner confirms receipt, reproduces the problem, and fixes it on a private branch if needed.
- The fix is described in the pull request without exploit detail.

## Scope

The application in this repository and its deployment scripts. Third-party services (mail provider, hosting) are out of scope.

## For maintainers

- Production settings and virus scanning: [docs/security.md](docs/security.md)
- Before each release: `composer audit --locked` and `npm audit --audit-level=high`
- Dependabot is configured in `.github/dependabot.yml`; the CodeQL workflow exists but is disabled (see [docs/testing.md](docs/testing.md))
