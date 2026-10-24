# fpp-plugin-config-checker

Standalone, read-only FPP 10.3 plugin for checking Raspberry Pi composite-video and Projector Control configuration.

It checks only `/boot/firmware/config.txt`, `/boot/firmware/cmdline.txt`, and the `DEVICE` setting in `/home/fpp/media/config/plugin.FPP-Plugin-Projector-Control`. Expected values are editable in `config/expected.json`.

Run tests with `php tests/run.php`.

The plugin does not edit system configuration, install packages, reboot, change Projector Control, or modify FPP core/Extended File Manager. Save Baseline is the only write operation: it atomically creates `/home/fpp/media/config/plugin.fpp-plugin-config-checker.baseline.json`, requires explicit confirmation, and refuses to overwrite an existing baseline.

The overlay matcher accepts the same comma-separated overlay parameters in any order and ignores trailing inline comments. Projector Control values are compared after removing matching single or double quotes.

## FPP home-page warning limitation

FPP's warning framework is implemented in the daemon through `WarningHolder`, and FPP's documented plugin extension points support plugin pages/menu entries and (in newer plugin API versions) plugin HTTP routes. Inspection of FPP v10.3's home/status page and plugin API found no supported PHP/plugin-page hook for registering a warning there. This project therefore does not inject unsupported JavaScript or modify FPP core. Its dedicated status page displays the limitation and all check results prominently.
