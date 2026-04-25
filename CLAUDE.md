# Project Rules

---

## Active Skills

github-pr

---

## Stack

- **Language**: PHP 8.3
- **HTTP client**: Guzzle 7
- **Config**: vlucas/phpdotenv 5
- **AI**: GitHub Models — gpt-4o-mini via Azure AI Inference
- **Azure services**: AI Language, Document Intelligence, Translator
- **Frontend**: Vanilla JavaScript (ES6), Vanilla CSS
- **Package manager**: Composer

---

## Project Structure

```
forge/
├── src/
│   ├── ContentAnalyzer.php   # Orchestrator — dispatches to services
│   └── Services/
│       ├── ChatService.php
│       ├── DocumentService.php
│       ├── LanguageService.php
│       └── TranslationService.php
├── views/
│   ├── layout.php            # Base HTML shell
│   ├── home.php
│   └── results.php
├── ajax/
│   ├── chat.php              # POST /ajax/chat.php
│   └── translate.php         # POST /ajax/translate.php
├── assets/
│   ├── css/app.css
│   └── js/app.js
├── uploads/                  # Temp uploaded files (gitignored)
└── index.php                 # Front controller + router
```

---

## Code Conventions

### PHP
- `declare(strict_types=1)` at the top of every PHP file
- PSR-12 formatting
- Type hints on all method parameters and return types
- `namespace Forge\Services` for services, `namespace Forge` for core classes
- PHPDoc `@param` / `@return` only for complex types not expressible in hints
- Throw typed exceptions — never return `null` to signal failure
- No `@` error suppressor
- No `var_dump`, `print_r`, or `echo` for debugging in committed code
- No bare `catch (\Throwable $e) {}` — always log or rethrow

### JavaScript
- No `var` — use `const` or `let`
- No `console.log` in committed code
- IIFE wrappers to avoid polluting global scope (existing pattern)
- No external libraries — vanilla only
- `fetch` for AJAX, `async/await` in new code

### CSS
- BEM-style class names matching the existing convention
- CSS custom properties for repeated values
- No inline styles except for truly dynamic runtime values

---

## Patterns

### New Azure service
1. Create `src/Services/XxxService.php` with constructor reading `$_ENV`
2. Inject into `ContentAnalyzer` if it feeds the main analyze flow
3. Expose via a new `ajax/xxx.php` endpoint if it's user-triggered
4. Add env vars to `.env.example` and README table

### Error handling
- Services throw `\RuntimeException` with descriptive messages
- `ContentAnalyzer::analyze()` catches and returns `['type' => ..., 'error' => ...]`
- Ajax endpoints catch `\Throwable` and return `{'success': false, 'error': '...'}`
- Views check `$error` before rendering results

### Session context
- `$_SESSION['result']` carries the analysis result from POST to GET (PRG pattern)
- `ajax/chat.php` reads `$_SESSION['result']` for AI context

---

## Prohibitions

Never do any of the following:

- Hardcoded secrets, API keys, or tokens in code
- `var_dump`, `print_r`, `echo` for debugging in committed code
- `console.log` or `debugger` in committed JS
- Empty `catch` blocks
- `@` error suppressor
- Business logic in view files — views only render
- Direct `$_GET` / `$_POST` / `$_FILES` access outside `index.php` or ajax entry points
- Leaving commented-out code blocks

---

## Workflow

### Before starting a task
1. Read existing service/view for the affected area
2. Check `.env.example` — if adding a new integration, add its vars
3. For non-trivial tasks, outline approach before coding

### Before finishing
- No debug output left behind
- `index.php` router updated if adding a new route
- `.env.example` and README updated if adding env vars

---

## Commits & PRs

### Commit format (Conventional Commits)
```
type(scope): short description

Optional body explaining the why.
- bullet one
- bullet two
```

**Types:** `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `perf`, `ci`

**Rules:**
- Present tense imperatives: "add feature" not "added feature"
- No period at end of subject line
- Subject max 72 chars
- Body bullets in infinitive

### PR guidelines
- One concern per PR
- Description explains *why*, not *what*
- All CI checks must pass before merge
- No force-push to `main`
