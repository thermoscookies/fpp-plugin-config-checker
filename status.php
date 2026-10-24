<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/Checker.php';
$expected = json_decode((string)file_get_contents(__DIR__ . '/config/expected.json'), true, 512, JSON_THROW_ON_ERROR);
$baselineSetting = (string)$expected['paths']['baseline'];
$baseline = ($baselineSetting !== '' && $baselineSetting[0] === '/') ? $baselineSetting : __DIR__ . '/' . ltrim($baselineSetting, '/');
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'baseline') {
    if ((string)($_POST['confirm'] ?? '') !== 'SAVE BASELINE') $message = 'Baseline was not saved: type SAVE BASELINE to confirm.';
    else {
        $save = cc_save_baseline($baseline, cc_check($expected));
        $message = $save['saved'] ? 'Baseline saved. It will not be overwritten by this plugin.' : ($save['reason'] === 'exists' ? 'Baseline already exists; it was not overwritten.' : 'Baseline could not be saved.');
    }
}
$checks = cc_check($expected);
function cc_h($value): string { return htmlspecialchars((string)($value ?? 'Unavailable'), ENT_QUOTES, 'UTF-8'); }
?>
<div class="settings">
<h2>Configuration Checker</h2>
<div class="alert alert-warning"><strong>Read-only checker.</strong> It never edits system configuration. FPP v10.3 has no documented supported plugin hook for a warning on the core home/status dashboard; this page is the supported integration point.</div>
<?php if ($message): ?><div class="alert alert-info"><?=cc_h($message)?></div><?php endif; ?>
<form method="get"><button class="buttons" type="submit">Re-run Checks</button></form>
<table class="table table-striped"><thead><tr><th>Check</th><th>Status</th><th>Current</th><th>Expected</th><th>Manual repair</th></tr></thead><tbody>
<?php foreach ($checks as $check): ?><tr><td><?=cc_h($check['label'])?></td><td><span class="label label-<?=cc_h($check['state'] === 'passed' ? 'success' : ($check['state'] === 'unavailable' ? 'default' : 'warning'))?>"><?=cc_h($check['state'])?></span></td><td><code><?=cc_h($check['actual'])?></code></td><td><code><?=cc_h($check['expected'])?></code></td><td><?=cc_h($check['repair'])?></td></tr><?php endforeach; ?>
</tbody></table>
<h3>Baseline</h3><p>Saving a baseline is optional and never overwrites an existing baseline.</p>
<form method="post" onsubmit="return confirm('Save a baseline now? An existing baseline will never be overwritten.');"><input type="hidden" name="action" value="baseline"><label for="cc-confirm">Type <code>SAVE BASELINE</code> to confirm:</label> <input id="cc-confirm" name="confirm" required pattern="SAVE BASELINE" autocomplete="off"> <button class="buttons" type="submit">Save Baseline</button></form>
</div>
