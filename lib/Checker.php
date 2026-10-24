<?php
declare(strict_types=1);

function cc_read(string $path): array {
    if (!file_exists($path)) return ['state' => 'missing', 'value' => null, 'error' => 'File does not exist.'];
    if (!is_file($path)) return ['state' => 'unavailable', 'value' => null, 'error' => 'Path exists but is not a regular readable file.'];
    if (!is_readable($path)) return ['state' => 'unavailable', 'value' => null, 'error' => 'File exists but is unreadable.'];
    $value = @file_get_contents($path);
    if ($value === false) return ['state' => 'unavailable', 'value' => null, 'error' => 'File could not be read.'];
    return ['state' => 'available', 'value' => $value, 'error' => null];
}

function cc_result(string $id, string $label, $actual, $expected, string $repair, string $state = ''): array {
    if ($state === '') $state = ((string)$actual === (string)$expected) ? 'passed' : 'mismatched';
    return compact('id', 'label', 'actual', 'expected', 'state', 'repair');
}

function cc_normalize_overlay(string $line): ?array {
    $line = preg_replace('/\s+#.*$/', '', trim($line));
    if (!preg_match('/^dtoverlay\s*=\s*(.+)$/i', $line, $match)) return null;
    $parts = array_values(array_filter(array_map('trim', explode(',', $match[1])), static fn(string $part): bool => $part !== ''));
    if (!$parts) return null;
    $normalized = array_map('strtolower', $parts);
    sort($normalized, SORT_STRING);
    return ['display' => $line, 'tokens' => $normalized];
}

function cc_overlay_matches(string $actual, string $expected): ?string {
    $expectedOverlay = cc_normalize_overlay($expected);
    if ($expectedOverlay === null) return null;
    foreach (preg_split('/\R/', $actual) as $line) {
        $candidate = cc_normalize_overlay($line);
        if ($candidate !== null && $candidate['tokens'] === $expectedOverlay['tokens']) return $candidate['display'];
    }
    return null;
}

function cc_unquote(string $value): string {
    $value = trim($value);
    $length = strlen($value);
    if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"') || ($value[0] === "'" && $value[$length - 1] === "'"))) {
        return substr($value, 1, -1);
    }
    return $value;
}

function cc_settings(string $path): array {
    $read = cc_read($path);
    if ($read['state'] !== 'available') return $read;
    $values = [];
    foreach (preg_split('/\R/', $read['value']) as $line) {
        if (preg_match('/^\s*([^=]+)=(.*)\s*$/', $line, $m)) $values[trim($m[1])] = cc_unquote($m[2]);
    }
    return ['state' => 'available', 'value' => $values, 'error' => null];
}

function cc_save_baseline(string $path, array $checks): array {
    $json = json_encode(['checks' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        return ['saved' => false, 'reason' => file_exists($path) ? 'exists' : 'unavailable'];
    }
    $written = @fwrite($handle, $json);
    $complete = $written === strlen($json) && @fflush($handle);
    @fclose($handle);
    if (!$complete) {
        @unlink($path);
        return ['saved' => false, 'reason' => 'unavailable'];
    }
    return ['saved' => true, 'reason' => 'saved'];
}

function cc_check(array $expected, ?array $overrides = null): array {
    $paths = $expected['paths'];
    if ($overrides) $paths = array_replace($paths, $overrides['paths'] ?? []);
    $overlay = $expected['composite_overlay'];
    $kernel = $expected['kernel_argument'];
    $results = [];

    $config = cc_read($paths['config_txt']);
    if ($config['state'] !== 'available') {
        $results[] = cc_result('composite_overlay', 'Composite KMS overlay', null, $overlay, 'Restore ' . $paths['config_txt'] . ', then add the exact line: ' . $overlay . '.', $config['state']);
    } else {
        $actual = cc_overlay_matches($config['value'], $overlay);
        $results[] = cc_result('composite_overlay', 'Composite KMS overlay', $actual ?? 'Not present', $overlay, 'Edit ' . $paths['config_txt'] . ' and add a dtoverlay line containing vc4-kms-v3d, composite, and cma-256.', $actual === null ? '' : 'passed');
    }

    $cmdline = cc_read($paths['cmdline_txt']);
    if ($cmdline['state'] !== 'available') {
        $results[] = cc_result('kernel_argument', 'Composite video kernel argument', null, $kernel, 'Restore ' . $paths['cmdline_txt'] . ', then add the exact argument to its single command line: ' . $kernel . '.', $cmdline['state']);
    } else {
        $actual = preg_match('/(?:^|\s)' . preg_quote($kernel, '/') . '(?:\s|$)/', trim($cmdline['value'])) ? $kernel : 'Not present';
        $results[] = cc_result('kernel_argument', 'Composite video kernel argument', $actual, $kernel, 'Edit ' . $paths['cmdline_txt'] . ' and add ' . $kernel . ' as a space-separated argument.');
    }

    $settings = cc_settings($paths['fpp_settings']);
    $device = $expected['projector_control_device'];
    $actual = $settings['state'] === 'available' ? ($settings['value']['DEVICE'] ?? null) : null;
    $state = $settings['state'] === 'available' ? ($actual === null ? 'missing' : '') : $settings['state'];
    $results[] = cc_result('projector_device', 'Projector Control DEVICE', $actual, $device, 'Open Projector Control in FPP and set DEVICE to ' . $device . '.', $state);
    return $results;
}
