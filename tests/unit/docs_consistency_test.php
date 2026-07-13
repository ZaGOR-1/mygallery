<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$version = trim((string) file_get_contents($root . DIRECTORY_SEPARATOR . 'VERSION'));

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
assert_false(str_contains($gemini, 'only create or update documentation files under `docs/`'), 'GEMINI audit output rule must not conflict with the root audit prompt');
assert_true(str_contains($gemini, 'FULL_PROJECT_AUDIT.md'), 'GEMINI must point full audits to the canonical root report');

$readme = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'README.md');
assert_false(str_contains($readme, '`audit.md`'), 'README must not reference missing audit.md');
assert_false(str_contains($readme, '`provirka.md`'), 'README must not reference missing provirka.md');

$uiStatus = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'UI_UX_RECOMMENDATIONS.md');
assert_true(str_contains($uiStatus, '## Реалізовано'), 'UI/UX doc must separate implemented work');
assert_false(str_contains($uiStatus, 'Додати кнопку швидкого копіювання'), 'UI/UX doc must not propose the implemented copy button as future work');
assert_false(str_contains($uiStatus, 'Впровадити Drag-and-drop'), 'UI/UX doc must not propose implemented drag-and-drop as future work');

$bugs = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'BUGS.md');
$auditReport = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'AUDIT_REPORT.md');
$securityAudit = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'SECURITY_AUDIT.md');
assert_true(str_contains($bugs, 'MyGallery ' . $version), 'BUGS.md must name the current VERSION');
assert_true(str_contains($auditReport, 'MyGallery ' . $version), 'AUDIT_REPORT.md must name the current VERSION');
assert_true(str_contains($securityAudit, 'MyGallery ' . $version), 'SECURITY_AUDIT.md must name the current VERSION');
$canonicalAuditPath = $root . DIRECTORY_SEPARATOR . 'MYGALLERY_AUDIT.md';
assert_true(is_file($canonicalAuditPath), 'current audit summaries must link to an existing canonical audit source');
$canonicalAudit = (string) file_get_contents($canonicalAuditPath);
$canonicalAuditHash = hash_file('sha256', $canonicalAuditPath);
assert_true(is_string($canonicalAuditHash), 'canonical audit source must be hashable');
assert_true(
    preg_match('/\| Git commit \| `([a-f0-9]{40})` \|/', $canonicalAudit, $auditIdentity) === 1,
    'canonical audit source must declare its Git commit identity'
);
foreach (['AUDIT_REPORT.md' => $auditReport, 'SECURITY_AUDIT.md' => $securityAudit] as $document => $contents) {
    assert_true(str_contains($contents, '[MYGALLERY_AUDIT.md](../MYGALLERY_AUDIT.md)'), $document . ' must link to the canonical audit source');
    assert_true(str_contains($contents, $canonicalAuditHash), $document . ' must contain the current canonical audit SHA-256');
    assert_true(str_contains($contents, $auditIdentity[1]), $document . ' must contain the audited Git commit');
}
assert_true(str_contains($readme, '2026_07_10_add_photo_lock_version.sql'), 'README update commands must include the current lock_version migration');
assert_true(str_contains($readme, '2026_07_10_add_share_target_check.sql'), 'README update commands must include the current share target migration');

$workflow = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'build_release.yml');
assert_true(str_contains($workflow, '"beta"'), 'release workflow must run for beta branch');
assert_true(str_contains($workflow, 'php tools/self_check.php'), 'release workflow must run self_check');
assert_true(str_contains($workflow, 'php tests/run.php'), 'release workflow must run tests');
assert_true(str_contains($workflow, 'Verify release ZIP contents'), 'release workflow must validate release ZIP contents');

$gitattributes = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.gitattributes');
assert_true(str_contains($gitattributes, '* text=auto eol=lf'), '.gitattributes must define a stable LF text policy');
