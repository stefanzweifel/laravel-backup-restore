# Changelog

All notable changes to `laravel-backup-restore` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/stefanzweifel/laravel-backup-restore/compare/v1.0.2...HEAD)

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
