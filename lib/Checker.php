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

function cc_settings(string $path): array {
    $read = cc_read($path);
    if ($read['state'] !== 'available') return $read;
    $values = [];
    foreach (preg_split('/\R/', $read['value']) as $line) {
        if (preg_match('/^\s*([^=]+)=(.*)\s*$/', $line, $m)) $values[trim($m[1])] = trim($m[2]);
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
    $projector = $expected['projector_control'];
    $results = [];

    $config = cc_read($paths['config_txt']);
    if ($config['state'] !== 'available') {
        $results[] = cc_result('composite_overlay', 'Composite KMS overlay', null, $overlay, 'Restore ' . $paths['config_txt'] . ', then add the exact line: ' . $overlay . '.', $config['state']);
    } else {
        $actual = preg_match('/^\s*' . preg_quote($overlay, '/') . '\s*$/m', $config['value']) ? $overlay : 'Not present';
        $results[] = cc_result('composite_overlay', 'Composite KMS overlay', $actual, $overlay, 'Edit ' . $paths['config_txt'] . ' and add the exact line: ' . $overlay . '.');
    }

    $cmdline = cc_read($paths['cmdline_txt']);
    if ($cmdline['state'] !== 'available') {
        $results[] = cc_result('kernel_argument', 'Composite video kernel argument', null, $kernel, 'Restore ' . $paths['cmdline_txt'] . ', then add the exact argument to its single command line: ' . $kernel . '.', $cmdline['state']);
    } else {
        $actual = preg_match('/(?:^|\s)' . preg_quote($kernel, '/') . '(?:\s|$)/', trim($cmdline['value'])) ? $kernel : 'Not present';
        $results[] = cc_result('kernel_argument', 'Composite video kernel argument', $actual, $kernel, 'Edit ' . $paths['cmdline_txt'] . ' and add ' . $kernel . ' as a space-separated argument.');
    }

    $settings = cc_settings($paths['fpp_settings']);
    $keys = ['enabled' => 'ENABLED', 'projector' => 'PROJECTOR', 'device' => 'DEVICE', 'baud_rate' => 'BAUD_RATE', 'char_bits' => 'CHAR_BITS', 'stop_bits' => 'STOP_BITS', 'parity' => 'PARITY'];
    foreach ($keys as $name => $key) {
        $actual = $settings['state'] === 'available'
            ? ($settings['value']['Projector Control_' . $key] ?? $settings['value'][$key] ?? null)
            : null;
        $state = $settings['state'] === 'available' ? ($actual === null ? 'missing' : '') : $settings['state'];
        $results[] = cc_result('projector_' . $name, 'Projector Control ' . $key, $actual, $projector[$name], 'Open Projector Control in FPP and set ' . $key . ' to ' . $projector[$name] . '.', $state);
    }

    $device = $paths['serial_device'];
    $exists = file_exists($device);
    $deviceState = $exists ? 'passed' : (is_readable(dirname($device)) ? 'missing' : 'unavailable');
    $results[] = cc_result('serial_device', 'Serial device', $exists ? $device : 'Not present', $device, 'Connect the USB serial adapter and confirm that ' . $device . ' exists.', $deviceState);
    return $results;
}
