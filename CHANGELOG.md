# Changelog

## 2026-06-29

### Fixed
- Admin push-to-Git: prioritize PHP `execGitPush()` when `GIT_TOKEN` is set in `.env` (more reliable on XAMPP/Apache than batch file)
- `execGitPush()`: use `findGitDir(__DIR__)` to run `git add -A` from the correct working directory
- `execGitPush()` push command: changed `git push $escaped_branch` → `git push origin $branch` (Windows `escapeshellarg` wraps `main` in quotes, causing "not a git repository" error)
- `backupPhotosToGit()`: now reads `.env` for `GIT_TOKEN` (same pattern as `backupToGit()`)
- `push_admin.bat`: simplified (no token logic, uses git config credential.helper)

### Added
- `backend/.env`: stores `GIT_TOKEN` for token-based git authentication via HTTPS

### Changed
- `config.php`: `backupToGit()` attempts PHP `execGitPush()` first when token is available, falls back to batch file
- AGENTS.md: updated with session progress, key decisions, and relevant file references

### In Progress
- Photo upload after admin save: user reports photo appears "not found" in grid despite successful upload. Verified upload works programmatically (file saved, API returns correct URL, HTTP 200). Issue persists on user's end — needs browser-side debugging.
