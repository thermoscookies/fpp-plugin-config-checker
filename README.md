# fpp-plugin-config-checker

Standalone, read-only FPP 10.3 plugin for checking Raspberry Pi composite-video and Projector Control configuration.

It checks `/boot/firmware/config.txt`, `/boot/firmware/cmdline.txt`, Projector Control settings in `/home/fpp/media/config/plugin.FPP-Plugin-Projector-Control`, and `/dev/ttyUSB0`. Expected values are editable in `config/expected.json`.

Run tests with `php tests/run.php`.

The plugin does not edit system configuration, install packages, reboot, change Projector Control, or modify FPP core/Extended File Manager. Save Baseline is the only write operation: it atomically creates `/home/fpp/media/config/plugin.fpp-plugin-config-checker.baseline.json`, requires explicit confirmation, and refuses to overwrite an existing baseline.

Projector Control's current `functions.inc.php` contains a separate upstream behavior where its script-generation path writes the parity value using the `STOP_BITS` key. This checker does not modify that plugin; if that behavior affects a device, the checker will report the resulting settings and the repair remains manual.

## FPP home-page warning limitation

FPP's warning framework is implemented in the daemon through `WarningHolder`, and FPP's documented plugin extension points support plugin pages/menu entries and (in newer plugin API versions) plugin HTTP routes. Inspection of FPP v10.3's home/status page and plugin API found no supported PHP/plugin-page hook for registering a warning there. This project therefore does not inject unsupported JavaScript or modify FPP core. Its dedicated status page displays the limitation and all check results prominently.
