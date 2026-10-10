# Bug Fixes & Known Issues

This document records the bugs found and fixed during development, their root causes, and how to verify the fixes.

---

## 1. Auto Mode Silent Failure (Critical)

### Symptom
When using **Auto mode**, the bot would report success (`applied: []`, `changes: []`) while actually writing **no files** to disk. The user would see "1 file written" toast but no changes on disk.

### Root Cause
The AI model's response was **truncated mid-code-block** (hit `max_tokens` limit). The `extract_files()` function in `lib/applier.php` only parsed **closed** fenced blocks (`` ```...``` ``). An unterminated block was silently discarded, so `applied` was empty while the response claimed success.

### Fix
**Files changed:**
- `lib/applier.php` — `extract_files()` now salvages unterminated trailing fences, marks `truncated=true`, and preserves the partial code.
- `index.php` chat route — surfaces `truncated` flag in SSE `files` and `done` events.
- `assets/app.js` — UI shows *"ran out of room — ask it to continue"* instead of false success; if no files applied, warns *"No files were changed — ask it to reply with the full code block"*.
- `lib/ai.php` — raised default `max_tokens` from **8000 → 16000** to reduce truncation frequency.
- Diff payload capped at 400 lines in `files` SSE event to keep UI responsive.

**Verification:**
- Send a multi-file request in auto mode that exceeds token budget.
- Observe: `index.php` updated, `extra.txt` NOT created, toast shows *"ran out of room — ask it to continue"*.
- Ask "continue the file" → second response completes the second file.

---

## 2. Model Dropdown Silent Failure (Critical)

### Symptom
The model dropdown (`#model-switch`) had **no `onchange` handler**. Selecting a model did nothing — no API call, no toast, no model change.

### Root Cause
The `renderSwitcher()` function populated the dropdown but never attached an `onchange` listener. The `wireModelSwitcher()` function was missing entirely.

### Fix
**Files changed:**
- `assets/app.js` — added `wireModelSwitcher()` that registers the `onchange` handler, auto-adds unconfigured models to the provider, and calls `setActive(provider, model)`.
- `loadProviders()` now calls `wireModelSwitcher()` and `warmModelCatalog()` on startup.
- Added `warmModelCatalog()` (server-side) to fetch the full model catalogue from configured providers **on the server** (avoids browser CORS issues and IP leakage).

**Verification:**
- Open Settings → Models dropdown → select a catalogue model (e.g., `gpt-5-nano`).
- Toast: *"Using gpt-5-nano"*.
- Model dropdown now shows 87 models (10 configured + 77 from catalogue).

---

## 3. Model Catalogue CORS Leak (Security)

### Symptom
On page load, the browser made **27 CORS-blocked requests** to 31 third-party AI providers, leaking the user's IP and failing silently.

### Root Cause
`warmModelCatalog()` fetched `/models` from each provider **from the browser**. Most providers block cross-origin requests.

### Fix
**Files changed:**
- `assets/app.js` — `warmModelCatalog()` now calls `GET /api/catalog` (server-side).
- `lib/providers.php` — added `provider_catalog()` with 15-minute file cache (`data/.catalog-cache.json`), using each provider's own key.
- `index.php` — new `GET /api/catalog` endpoint.

**Verification:**
- Open project page → zero console errors, 87 models in dropdown, zero third-party requests.

---

## 4. Version Restore Bug (Logic Error)

### Symptom
"Restore" button in history dialog restored the **wrong version**. Clicking version N restored version N+1 (or shifted after undo/redo).

### Root Cause
The "Restore" button executed `index + 1` undo operations blindly. This only worked if you had **never** undone/redone before. After any undo/redo, the index shifted and the wrong version was restored.

### Fix
**Files changed:**
- `lib/versions.php` — new `version_restore($id, $rel, $index)` computes exact steps from current depth (derived from redo stack size) to target index, then steps in the correct direction (undo or redo).
- `index.php` `/revert` endpoint accepts `index` parameter for exact restore.
- `assets/app.js` — history dialog sends `{path, index}` instead of walking steps manually.

**Verification:**
- Write 4 versions (a, b, c, d).
- Undo twice → at "b".
- Click history row for "a" (index 2) → exact restore to "a".
- Redo once → back to "b".
- Click "c" (index 1) → exact restore to "c".
- Restore current version → no-op (0 steps).

---

## 5. History Button Visibility (UX Bug)

### Symptom
History button (⌚) appeared on **every file**, even those with no version history. Clicking it opened an empty dialog.

### Root Cause
`renderTree()` always rendered the history button (⌚) regardless of whether the file had versions.

### Fix
**Files changed:**
- `assets/app.js` `renderTree()` — history button (`.hist`) now only rendered when `canUndo || canRedo`.

---

## 6. History Dialog "Current" Marker (UX)

### Symptom
No visual indication of which version is currently active in the history dialog.

### Fix
- Added `.live` class to the currently active row (last redo entry, or newest undo entry if no redos).
- Shows "current" badge next to the label.

---

## 7. Model Switcher Catalogue CORS (Security)

### Symptom
(Already covered in #2) — first implementation fetched from browser.

### Fix
Already covered — moved to server-side catalogue endpoint.

---

## 8. Catalogue Cache Staleness (Intermittent)

### Symptom
Adding a new server/provider → its models **did not appear** in the dropdown for 15 minutes (cache TTL).

### Root Cause
Catalogue cached in `data/.catalog-cache.json` with 15-min TTL. No invalidation on provider add/edit/delete.

### Fix
**Files changed:**
- `lib/providers.php` — `providers_save()` now calls `provider_catalog_invalidate()` which deletes `.catalog-cache.json`.

**Verification:**
- Add a new server → its models appear **instantly** in the header dropdown.

---

## 9. Auto Mode Truncation UX (UX Improvement)

### Symptom
No indication that the model's answer was cut off. User saw empty result.

### Fix
- `truncated` flag in SSE `files` and `done` events.
- Frontend shows toast: *"The answer ran out of room before the code finished — ask it to continue"*.
- Context meter in chat header shows `ctx Xk/Yk` with progress bar (amber at 70%, red at 90%).

---

## 9. Diff Payload Size (Performance)

### Symptom
Large diffs (entire file) sent over SSE, slowing UI and consuming memory.

### Fix
- `index.php` caps `publicChanges` diffs at **400 lines**, adds `diff_capped: true`.
- Frontend shows *"diff shortened for speed — open the file to see it all"*.

---

## 10. File Click in Preview Overlay (Test Bug)

### Symptom
Test "file click in overlay still adds a mention" failed.

### Root Cause
Test clicked the **first `.file-item`**, which was a **directory** (toggles open/close), not a file. The first 4 items were directories.

### Fix
Updated test to click first **non-directory** `.file-item`:
```js
const fileBtn = $$('#panes .file-item').find(el => !el.classList.contains('dir'));
```

---

## 11. History Dialog "Already Current" No-op (Logic)

### Symptom
Clicking "Restore" on the currently active version showed an error instead of a no-op.

### Fix
`version_restore()` now returns `result: 'already-current', steps: 0` when target depth equals current depth. Frontend shows *"Already showing that version"*.

---

## 11. Preview Close/Stop Separation (UX)

### Symptom
Closing preview **stopped the server**, so reopening required a slow restart.

### Fix
- Closing preview (× button or Esc) now **keeps server running**.
- Dedicated **■ Stop** button in preview header explicitly stops the server.
- Toolbar **Preview** button: if closed → opens; if open → closes (server stays); if server running but closed → opens.

---

## 12. History Button Visibility on Phone (Mobile UX)

### Symptom
Undo/redo/history/delete icons were `opacity: 0` (hidden until hover). **Phones have no hover** → invisible and untappable.

### Fix
```css
@media (hover:none),(pointer:coarse) {
  .file-item .undo, .file-item .redo, .file-item .hist, .file-item .del {
    opacity: .9; display: inline-flex; min-width: 36px; min-height: 36px;
    background: var(--panel2); border: 1px solid var(--border2);
  }
  .btn { min-height: 44px }  /* 44px minimum tap target */
}
```

All toolbar buttons now ≥ 44px tall on touch devices.

---

## 13. Chat Input Variable Scope Bug (Intermittent)

### Symptom
`onFileClick` sometimes failed with "input is not defined" when clicking a file in the preview overlay.

### Root Cause
`onFileClick()` defined **before** `const input = $('#chat-input')` in source order. In JavaScript, `const` is hoisted but in the Temporal Dead Zone until the declaration is reached. The function worked after full load, but if called during initialization (rare), it threw.

### Fix
Moved `const input = $('#chat-input')` **above** `onFileClick()` definition.

---

## 12. Skill Delete Bug (Data Corruption)

### Symptom
Deleting a custom skill failed with "No such skill" even though it existed.

### Root Cause
`skills_save_user()` wrote skills as a JSON **array** (`array_values()`), but `skills_user()` and `skill_delete()` indexed by `id` (object key). Lookups always failed.

### Fix
`skills_save_user()` now writes skills as a **keyed object** (`(object)$skills`). Reader `skills_user()` tolerates both formats for backward compatibility.

---

## 13. Preview Element Picker Scratch File Leak (Data Hygiene)

### Symptom
`.picked.html` appeared in the file tree and project zip download.

### Fix
- New `/api/projects/{id}/pick` endpoint stores scratch HTML in `project_dir/.picked.html` (outside `files/`).
- `expand_mentions()` resolves `@picked-element.html` from `.picked.html`.
- Removed from file tree and zip.

---

## 14. Context Window Meter (Observability)

### Feature
Live context meter in chat header: `ctx 29.6k/128k` with progress bar (amber ≥70%, red ≥90%). Tooltip shows exact counts.

### Implementation
- `ai_context_usage()` in `lib/ai.php` estimates tokens from system prompt + history (chars/4).
- Emitted in SSE `start` event as `context: {chars, est_tokens, limit, pct}`.
- Frontend `renderContext()` updates meter and tooltip.

---

## 14. Button Busy States (UX)

### Feature
All async buttons show animated "working" shimmer while busy:
- Send button: `aria-busy="true"` + shimmer
- Preview button: `aria-busy="true"` + shimmer
- Toolbar Undo/Redo: `btn.classList.add('busy')`
- Apply/Reject buttons on change cards

---

## 15. Code Block Rendering (UX)

### Improvement
- Code blocks **collapsed by default** (show header + "Show" button).
- Short blocks (<25 lines) auto-expand.
- Header shows `language:filename`, line count, KB size.
- **Copy** button copies raw code.
- Streaming blocks show blinking caret.
- Long blocks fade with CSS mask when collapsed.

---

## 15. Skill System (New Feature)

### Feature
- **10 built-in skills** (Landing page, Mobile-first, Dark mode, SEO, Accessibility, Performance, Forms, Motion, Clean code, Review).
- **Custom skills** — add name + instructions, saved per project.
- **Chips above composer** show active skills, auto-cleared after send.
- Gated skills (need Zen key) show 🔑 badge; clicking opens Add Server pre-filled with Zen URL.

---

## Regression Test Suite

Run with:
```bash
cd /tmp/opencode && node wstest.js      # 143 workspace checks
node hometest.js                        # 14 home page checks
/opt/lampp/bin/php undo_test.php        # undo/redo API
```

All suites must pass with **0 console errors**.

---

## Known Limitations

| Area | Limitation |
|---|---|
| Nested fences | A markdown file containing a fenced block will be truncated at the inner fence. |
| Free Zen models | Only `space-bunny-free` works keyless; others need a Zen key (one-click setup in Skills panel). |
| Context estimate | Rough (`chars/4`), not tokenizer-accurate. |
| Preview server | In-process (`mode: sandbox`), no real ports. |
| Auto mode truncation | Inherent to model token limit; mitigated by 16k budget and truncation notice. |

---

## Verification Checklist (Pre-Release)

- [ ] `php -l` on all `.php` files
- [ ] `node --check assets/app.js`
- [ ] CSS braces balanced
- [ ] `node wstest.js` → 143/143
- [ ] `node hometest.js` → 14/14
- [ ] `php undo_test.php` → all pass
- [ ] Real browser (desktop + phone) — preview open/close, skills, undo/redo, chat, element picker
- [ ] `curl` API smoke tests (providers, catalog, chat, revert, skills)
- [ ] No stray preview server, no pending changes, clean project state