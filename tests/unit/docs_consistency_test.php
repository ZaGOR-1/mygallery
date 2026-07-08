<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$roadmap = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'ROADMAP.md');
assert_false(str_contains($roadmap, 'Drag-and-Drop Upload'), 'ROADMAP must not list implemented drag-and-drop upload as future work');
assert_false(str_contains($roadmap, 'Dark/Light Mode'), 'ROADMAP must not list removed dark/light mode as future work');

$aiDocs = [
    'docs/AI_CODE_AUDIT.md',
    'docs/AI_FIX_PLAN.md',
    'docs/AI_DOCS_CONSISTENCY_AUDIT.md',
    'docs/AI_RELEASE_AUDIT.md',
    'docs/AI_DB_MIGRATION_AUDIT.md',
    'docs/AI_MEDIA_STORAGE_AUDIT.md',
    'docs/AI_SECURITY_AUDIT.md',
];

foreach ($aiDocs as $relative) {
    $contents = (string) file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    assert_true(str_contains($contents, 'Historical snapshot'), $relative . ' must be clearly marked as historical');
    assert_false(str_contains($contents, '`PASS`'), $relative . ' must not use PASS as a current verdict');
    assert_false(str_contains($contents, '100% готова'), $relative . ' must not claim 100% readiness');
    assert_false(str_contains($contents, '100% Ready'), $relative . ' must not claim 100% readiness in English');
    assert_false(str_contains($contents, 'без будь-якого ризику'), $relative . ' must not claim risk-free release safety');
    assert_false(str_contains($contents, 'ідеальному стані'), $relative . ' must not overclaim ideal state');
    assert_false(str_contains($contents, 'mygallery_6.4.6_release.zip'), $relative . ' must not use stale release ZIP example');
}

$agents = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'AGENTS.md');
assert_false(str_contains($agents, 'MyGallery v6.4.6'), 'AGENTS.md must not hard-code the old project version');
assert_true(str_contains($agents, 'VERSION'), 'AGENTS.md must point agents to VERSION for the current version');

$gemini = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'GEMINI.md');
assert_false(str_contains($gemini, 'uploaded v6.4.6'), 'GEMINI.md must not refer to a stale uploaded workspace version');

$workflow = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'build_release.yml');
assert_true(str_contains($workflow, '"beta"'), 'release workflow must run for beta branch');
assert_true(str_contains($workflow, 'php tools/self_check.php'), 'release workflow must run self_check');
assert_true(str_contains($workflow, 'php tests/run.php'), 'release workflow must run tests');
assert_true(str_contains($workflow, 'Verify release ZIP contents'), 'release workflow must validate release ZIP contents');

$gitattributes = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.gitattributes');
assert_true(str_contains($gitattributes, '* text=auto eol=lf'), '.gitattributes must define a stable LF text policy');
