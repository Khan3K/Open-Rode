<?php
declare(strict_types=1);

/**
 * Skills: reusable instructions the user can attach to a message so the bot
 * builds the thing they actually want without re-typing the same brief.
 *
 * Two sources:
 *  - built-in catalogue (skill_builtin_catalog) — curated, always available,
 *    never stored, so upgrades reach every project.
 *  - the user's own skills, stored in <project>/skills.json so they can add,
 *    rename or delete anything they want (including overriding a built-in).
 *
 * A skill is just text injected as extra context, so it stays model-agnostic
 * and cheap: only the attached ones are ever sent.
 */

const SKILL_MAX_NAME = 60;
const SKILL_MAX_BODY = 8000;

/** Curated starting points. Order is the order shown in the UI. */
function skill_builtin_catalog(): array
{
    return [
        [
            'id' => 'landing',
            'name' => 'Landing page',
            'icon' => "\u{1F3D7}",
            'body' => "Build a complete landing page: a clear hero with one headline, a short supporting line and one primary call to action, then 2-4 benefit sections with real headings, a social-proof or feature strip, and a closing call to action with a footer. Keep the copy concrete, not marketing filler.",
        ],
        [
            'id' => 'mobile-first',
            'name' => 'Mobile first',
            'icon' => "\u{1F4F1}",
            'body' => "Write responsive CSS mobile-first: start from a single-column layout and add breakpoints with min-width. Make sure tap targets are at least 44px, text stays readable at 320px wide, and nothing overflows horizontally on a phone.",
        ],
        [
            'id' => 'dark-mode',
            'name' => 'Dark mode',
            'icon' => "\u{1F319}",
            'body' => "Use a dark theme driven by CSS custom properties on :root (background, surface, text, muted text, border, accent). Respect prefers-color-scheme and keep contrast at or above 4.5:1 for body text.",
        ],
        [
            'id' => 'seo',
            'name' => 'SEO basics',
            'icon' => "\u{1F50D}",
            'body' => "Add solid SEO basics: a unique <title> under 60 characters, a meta description under 155 characters, one <h1>, correct heading order, descriptive alt text on every image, and Open Graph tags for title, description and image.",
        ],
        [
            'id' => 'accessibility',
            'name' => 'Accessibility',
            'icon' => "\u{267F}",
            'body' => "Make the page accessible: semantic landmarks, a skip link, labelled form controls, visible focus states, keyboard-operable menus, alt text on meaningful images, and no meaning conveyed by colour alone.",
        ],
        [
            'id' => 'performance',
            'name' => 'Performance',
            'icon' => "\u{26F1}",
            'body' => "Keep it fast: no layout shift (set width/height or aspect-ratio on media), minimal CSS, defer non-critical JavaScript, avoid heavy libraries, and keep total page weight small.",
        ],
        [
            'id' => 'forms',
            'name' => 'Form + validation',
            'icon' => "\u{270D}",
            'body' => "Add a working contact form with labelled fields, inline validation messages tied with aria-describedby, a success state, and server-side validation. Never silently swallow a failed submit.",
        ],
        [
            'id' => 'animation',
            'name' => 'Subtle motion',
            'icon' => "\u{2728}",
            'body' => "Add tasteful motion: short fades or small slide-ups on scroll, staggered but subtle, easing with ease-out. Always respect prefers-reduced-motion and disable animation for those users.",
        ],
        [
            'id' => 'comments',
            'name' => 'Clean code',
            'icon' => "\u{1F9F9}",
            'body' => "Keep the code production-ready: meaningful class names, no dead code, no inline styles where CSS will do, comments only where the reason is not obvious, and no placeholder lorem text left behind.",
        ],
        [
            'id' => 'audit',
            'name' => 'Review + fix',
            'icon' => "\u{1F50D}",
            'body' => "Do not rewrite working code. Review what is already there, list the concrete problems you find (broken layout, missing alt text, overflow, slow loading, dead code), and fix the highest-impact ones first.",
        ],
    ];
}

/** @return array<string, array> built-ins keyed by id */
function skill_builtins(): array
{
    $out = [];
    foreach (skill_builtin_catalog() as $s) $out[$s['id']] = $s + ['builtin' => true];
    return $out;
}

function skills_file(string $id): string
{
    return project_dir($id) . '/skills.json';
}

function skills_user(string $id): array
{
    $f = skills_file($id);
    if (!is_file($f)) return [];
    $raw = json_decode((string)file_get_contents($f), true);
    $list = $raw['skills'] ?? [];
    if (!is_array($list)) return [];
    // tolerate both the keyed form and an older list form
    if ($list && array_keys($list) === range(0, count($list) - 1)) {
        $out = [];
        foreach ($list as $s) if (is_array($s) && !empty($s['id'])) $out[(string)$s['id']] = $s;
        return $out;
    }
    return $list;
}

/** Must be keyed by id — skill_save/skill_delete index by it. */
function skills_save_user(string $id, array $skills): void
{
    file_put_contents(skills_file($id), json_encode(
        ['skills' => (object)$skills], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ));
    @chmod(skills_file($id), 0666);
}

/** Built-ins plus the user's own; a user skill shadows a built-in with the same id. */
function skills_all(string $id): array
{
    $out = skill_builtins();
    foreach (skills_user($id) as $s) {
        if (!is_array($s) || empty($s['id'])) continue;
        $out[$s['id']] = $s + ['builtin' => false];
    }
    return array_values($out);
}

/** Public shape for the UI (no heavy bodies beyond what the panel needs). */
function skills_list(string $id): array
{
    $out = [];
    foreach (skills_all($id) as $s) {
        $body = (string)($s['body'] ?? '');
        $out[] = [
            'id'     => (string)$s['id'],
            'name'   => (string)($s['name'] ?? $s['id']),
            'icon'   => (string)($s['icon'] ?? "\u{1F4DD}"),
            'body'   => $body,
            'preview'=> mb_substr($body, 0, 140),
            'builtin'=> !empty($s['builtin']),
        ];
    }
    return $out;
}

function skill_find(string $id, string $skillId): ?array
{
    foreach (skills_all($id) as $s) if ($s['id'] === $skillId) return $s;
    return null;
}

function skill_save(string $id, array $in): array
{
    $name = trim((string)($in['name'] ?? ''));
    $body = trim((string)($in['body'] ?? ''));
    if ($name === '') throw new RuntimeException('Skill name is required');
    if ($body === '') throw new RuntimeException('Skill instructions are required');
    if (mb_strlen($name) > SKILL_MAX_NAME) throw new RuntimeException('Skill name is too long');
    if (mb_strlen($body) > SKILL_MAX_BODY) throw new RuntimeException('Skill text is too long (max ' . SKILL_MAX_BODY . ' chars)');

    $user = skills_user($id);
    $skillId = trim((string)($in['id'] ?? ''));
    $builtin = skill_builtins();
    if ($skillId === '' || isset($builtin[$skillId]) && empty($in['override'])) {
        // editing a built-in forks it into a user skill with a distinct id
        $base = slugify($name) ?: 'skill';
        $skillId = $base;
        $i = 2;
        while (array_key_exists($skillId, $builtin) || skill_find($id, $skillId)) $skillId = $base . '-' . $i++;
    }

    $rec = [
        'id'     => $skillId,
        'name'   => $name,
        'icon'   => (string)($in['icon'] ?? ($builtin[$skillId]['icon'] ?? "\u{1F4DD}")),
        'body'   => $body,
        'updated_at' => now(),
    ];
    $user[$skillId] = $rec;
    skills_save_user($id, $user);
    return $rec;
}

function skill_delete(string $id, string $skillId): void
{
    $user = skills_user($id);
    if (!isset($user[$skillId])) {
        if (isset(skill_builtins()[$skillId])) {
            throw new RuntimeException('That is a built-in skill — create your own copy to change it');
        }
        throw new RuntimeException('No such skill');
    }
    unset($user[$skillId]);
    skills_save_user($id, $user);
}

/**
 * Turn attached skill ids into a single block of extra context for the model.
 * Unknown ids are ignored so a stale UI never breaks a send.
 */
function skills_context(string $id, array $ids): string
{
    $ids = array_slice(array_values(array_unique(array_map('strval', $ids))), 0, 8);
    if (!$ids) return '';
    $parts = [];
    foreach ($ids as $sid) {
        $s = skill_find($id, $sid);
        if (!$s) continue;
        $parts[] = '- ' . $s['name'] . ': ' . trim((string)$s['body']);
    }
    if (!$parts) return '';
    return "\n\n[Applied skills — follow all of these]\n" . implode("\n", $parts);
}