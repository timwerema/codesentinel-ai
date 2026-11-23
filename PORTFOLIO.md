# Portfolio text

Only what is implemented and verified in this repository (see PROJECT_STATUS.md). Update these if the status changes.

## Short description

**CodeSentinel AI** is a code review tool built with Laravel 12, React, TypeScript and PostgreSQL. You paste or upload source code; a queued job scans it with Semgrep and Gitleaks in restricted processes, merges duplicate findings, and shows them in a dashboard with severity, location, explanation and fix. An AI provider layer adds Claude suggestions, always labelled as unverified, with a clearly marked demo mode when no API key is set. Uploaded code is treated as untrusted text and is never executed.

## Resume bullets

- Built a code review web app (Laravel 12, Inertia, React, TypeScript, PostgreSQL, Redis queues) that scans uploaded source with Semgrep and Gitleaks and normalises and de-duplicates findings across tools.
- Wrote 26 offline Semgrep rules covering Python, JavaScript/TypeScript, PHP, Java and C/C++, each with a plain-language explanation and fix.
- Ran third-party scanners on untrusted input in restricted subprocesses: argument-array commands, a clean environment with no application secrets, timeouts, CPU and process limits, and an unprivileged user.
- Designed an AI provider interface with a Claude implementation using structured JSON output; treated uploaded code as untrusted data and validated every model reply against a schema before storing it as an unverified suggestion.
- Enforced per-user isolation with policies and non-guessable ids, rate limits, strict upload validation, and a nonce-based Content-Security-Policy.
- Covered the upload gate, access control, pipeline failure modes and the real scanners with 90 backend and 13 front-end automated tests.

## Do not claim (yet)

- GitHub pull-request integration or test generation: not built.
- "AI-powered" results in production: the live Claude call has not been run.
- Docker deployment: the files are unbuilt.
- Any user numbers, performance figures or accuracy rates: none were measured.
