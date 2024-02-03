# Changelog

All notable changes to `laravel-backup-restore` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.2.0...HEAD)

## [v1.2.0](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.1.5...v1.2.0) - 2024-01-31

### Added

- Add Support to import bz2 compressed database dumps ([#61](https://github.com/stefanzweifel/laravel-backup-restore/pull/61))

### Changed

- Better unpacked dump detection ([#62](https://github.com/stefanzweifel/laravel-backup-restore/pull/62))

## [v1.1.5](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.1.4...v1.1.5) - 2024-01-09

### Changed

- Upgrade Pest to v2 ([#50](https://github.com/stefanzweifel/laravel-backup-restore/pull/50))

### Fixed

- Disable Process Timeout when importing Database backup ([#55](https://github.com/stefanzweifel/laravel-backup-restore/pull/55))

## [v1.1.4](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.1.3...v1.1.4) - 2023-11-13

### Fixed

- Add --host Option to psql command ([#46](https://github.com/stefanzweifel/laravel-backup-restore/pull/46))

## [v1.1.3](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.1.2...v1.1.3) - 2023-11-01

### Changed

- Update MySQL Importer to use CLI Arguments instead of Credentials File ([#42](https://github.com/stefanzweifel/laravel-backup-restore/pull/42))

### Fixed

- Respect dump_binary_path setting when importing database ([#40](https://github.com/stefanzweifel/laravel-backup-restore/pull/40))

## [v1.1.2](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.1.1...v1.1.2) - 2023-10-27

### Fixed

- Use DIRECTORY_SEPARATOR to support Windows ([#38](https://github.com/stefanzweifel/laravel-backup-restore/pull/38))

## [v1.1.1](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.1.0...v1.1.1) - 2023-10-17

### Fixed

- Use Database Connection name when generating import shell command ([#34](https://github.com/stefanzweifel/laravel-backup-restore/pull/34))

## [v1.1.0](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.0.2...v1.1.0) - 2023-10-16

### Added

- Add Support for PHP 8.3 ([#31](https://github.com/stefanzweifel/laravel-backup-restore/pull/31))

### Changed

- Replace Symfony Process with Illuminate Process ([#30](https://github.com/stefanzweifel/laravel-backup-restore/pull/30))
- Update Artisan Command to use Laravel Prompts ([#19](https://github.com/stefanzweifel/laravel-backup-restore/pull/19))

### Removed

- Drop Support for Laravel 9 ([#29](https://github.com/stefanzweifel/laravel-backup-restore/pull/29))

### Fixed

- Check if CLI Dependencies are available before starting restore process ([#28](https://github.com/stefanzweifel/laravel-backup-restore/pull/28))

## v1.0.2 - 2023-08-22

### Fixed

- Use Driver Name when creating DbImporter instead of Connection Name ([#24](https://github.com/stefanzweifel/laravel-backup-restore/pull/24))

## v1.0.1 - 2023-08-12

### Changed

- Show Connection Details in Confirmation Prompt ([#20](https://github.com/stefanzweifel/laravel-backup-restore/pull/20))

## v1.0.0 - 2023-06-15

First stable release

## v0.3.1 - 2023-05-27

### Fixed

- Update version constraint of `spatie/temporary-directory` for better compatibility with other packages.

## v0.3.0 - 2023-05-20

### Added

- Add Health Checks ([#11](https://github.com/stefanzweifel/laravel-backup-restore/pull/11))

## v0.2.0 - 2023-05-10

### Added

- Add `--reset` option to wipe database before importing backup [#8](https://github.com/stefanzweifel/laravel-backup-restore/pull/8)

## v0.1.0 - 2023-03-22

### Added

- Initial Version
