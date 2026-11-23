# Project status

Last updated 23 Nov 2026. "Tested" means an automated test or a real browser run exists and passed on that date. Anything not listed as tested was not verified.

## Implemented and tested

| Feature | Evidence |
|---|---|
| Accounts, login, password reset, profile settings (Laravel React starter kit) | Starter kit tests |
| Paste code or upload up to 10 files, eight extensions, strict size and type limits | `SubmissionTest`, `SourceIntakeTest`, browser run |
| Binary and disguised files refused; file names sanitised; private storage | `SourceIntakeTest`, `SubmissionTest` |
| Queued analysis with queued / running / completed / failed states, retry, delete | `PipelineTest`, `AccessTest`, browser run with a real Redis worker |
| Semgrep with 26 bundled offline rules (Python, JS/TS, PHP, Java, C/C++) | `RealScannersTest`, manual run on samples in all five languages |
| Gitleaks secret scanning with redaction | `RealScannersTest` |
| Restricted scanner processes: argument arrays, clean environment, timeout, CPU/process limits, unprivileged user | `RealScannersTest` |
| Findings normalised (file, line, severity, category, rule, explanation, fix) and de-duplicated across tools | `DeduplicatorTest`, `PipelineTest` |
| AI provider interface with a labelled, deterministic demo mode | `ClaudeReviewProviderTest`, `PipelineTest` |
| Per-user isolation of analyses, findings, lists and totals | `AccessTest` |
| Rate limits on analyses and AI reviews | `SubmissionTest`, `PipelineTest` |
| Security headers and nonce-based Content-Security-Policy | `AccessTest`, browser run with no policy violations |
| Dashboard: real counts, severity breakdown, recent analyses | `AccessTest`, browser run |
| History with search, status filter and pagination | `AccessTest`, browser run |
| Analysis page: tool status, severity summary, filters, code snippets, live status polling, empty/error/loading states | browser run at 1440 px and 390 px, `findings.test.ts`, `badges.test.tsx` |
| Demo seeder: labelled demo account and analysis of a bundled vulnerable sample | run manually: 7 scanner findings |

Totals on 23 Nov 2026: 90 backend tests (393 assertions) and 13 front-end tests passing; Pint, Prettier, ESLint and TypeScript clean.

## Implemented, only partly verified

| Feature | What was and was not verified |
|---|---|
| Claude review (official Anthropic PHP SDK, structured JSON output, schema validation, token usage, cost estimate, refusal handling, retries, timeout) | Everything around the API call is tested with a fake gateway. **The live API call itself has never been run**: no key was available. |
| Side-by-side original/suggested view | The line-replacement logic is unit tested. It only appears for real AI suggestions with replacement code, so it has not been seen with live data. |
| Docker Compose and Dockerfile | Written, **never built or run** (no Docker daemon available). |
| GitHub Actions CI | Runs on every push. The first run caught a real bug (scanners installed outside /usr/bin were not found), fixed the same day. |
| macOS setup instructions | Written from documentation, **not run on a Mac**. |

## Planned (not started)

- **Phase 4, test generation:** suggested pytest and Vitest tests with assumptions and download. Running generated tests needs an isolated runner (no network, non-root, read-only filesystem, CPU/memory/time limits) and will not be added without one.
- **Phase 6, GitHub App:** installation tokens, webhook signature verification, replay and duplicate protection, pull-request diff review, summary comments. Optional; the app works without it.
- Two-step login and required email verification.
- Automatic deletion of stored code after a retention period.
- More Semgrep rules, and per-rule documentation links.
- Line-level syntax highlighting in code panes.

## Blocked

| Item | Blocked by |
|---|---|

| Verifying the Claude call | An Anthropic API key in the server's `.env`. |
| Verifying Docker | A machine with Docker. |
