<?php
// FPP's documented plugin menu contract: $menu is the current section and
// $plugin is the current plugin directory/repoName.
$menuEntries = [
    ['text' => 'Configuration Checker', 'type' => 'status', 'page' => 'status.php', 'wrap' => 1],
];
foreach ($menuEntries as $entry) {
    if ($entry['type'] !== $menu) continue;
    $nopage = (isset($entry['wrap']) && $entry['wrap'] === 0) ? '&nopage=1' : '';
    printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n", $plugin, $entry['page'], $nopage, $entry['text']);
}
?>
