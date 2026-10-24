<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/Checker.php';
$tmp = sys_get_temp_dir() . '/fpp-config-checker-' . bin2hex(random_bytes(4));
mkdir($tmp);
$expected = json_decode(file_get_contents(__DIR__ . '/../config/expected.json'), true);
$expected['paths'] = ['config_txt' => "$tmp/config.txt", 'cmdline_txt' => "$tmp/cmdline.txt", 'fpp_settings' => "$tmp/settings", 'baseline' => "$tmp/baseline.json"];
function assert_ok(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function count_state(array $results, string $state): int { return count(array_filter($results, fn(array $result): bool => $result['state'] === $state)); }
file_put_contents($expected['paths']['config_txt'], "# Composite output\ndtoverlay=vc4-kms-v3d,cma-256,composite # active\n");
file_put_contents($expected['paths']['cmdline_txt'], "root=foo video=Composite-1:720x480@60ie\n");
file_put_contents($expected['paths']['fpp_settings'], "DEVICE=\"ttyUSB0\"\n");
$healthy = cc_check($expected);
assert_ok(count($healthy) === 3 && count_state($healthy, 'passed') === 3, 'healthy configuration');
$saved = cc_save_baseline($expected['paths']['baseline'], $healthy);
assert_ok($saved['saved'] === true, 'baseline save');
$baselineContents = file_get_contents($expected['paths']['baseline']);
$savedAgain = cc_save_baseline($expected['paths']['baseline'], cc_check($expected));
assert_ok($savedAgain['saved'] === false && $savedAgain['reason'] === 'exists', 'baseline overwrite protection');
assert_ok(file_get_contents($expected['paths']['baseline']) === $baselineContents, 'baseline remained unchanged');
file_put_contents($expected['paths']['config_txt'], 'dtoverlay=vc4-kms-v3d,composite');
file_put_contents($expected['paths']['cmdline_txt'], 'root=foo');
file_put_contents($expected['paths']['fpp_settings'], 'DEVICE=ttyS0');
assert_ok(count_state(cc_check($expected), 'mismatched') === 3, 'mismatched configuration');
unlink($expected['paths']['config_txt']); unlink($expected['paths']['cmdline_txt']); unlink($expected['paths']['fpp_settings']);
assert_ok(count(cc_check($expected)) === 3 && count_state(cc_check($expected), 'missing') === 3, 'missing configuration');
$expected['paths']['config_txt'] = $tmp . '/unreadable';
// A directory is deliberately used instead of chmod(000): root can bypass
// mode bits, but neither user can safely use a directory as a config file.
mkdir($expected['paths']['config_txt']);
$unreadable = cc_check($expected)[0]['state'];
assert_ok($unreadable === 'unavailable', 'unreadable configuration');
$expected['paths']['fpp_settings'] = $tmp . '/unreadable-settings';
mkdir($expected['paths']['fpp_settings']);
mkdir($tmp . '/unreadable-cmdline');
$expected['paths']['cmdline_txt'] = $tmp . '/unreadable-cmdline';
assert_ok(count_state(cc_check($expected), 'unavailable') === 3, 'unreadable configuration');
echo "All configuration checker tests passed.\n";
