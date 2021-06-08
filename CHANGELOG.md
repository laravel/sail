# Release Notes

## [Unreleased](https://github.com/laravel/sail/compare/v1.8.1...1.x)


## [v1.8.1 (2021-06-08)](https://github.com/laravel/sail/compare/v1.8.0...v1.8.1)

### Fixed
- Fix if statement in `sail` binary ([414fd19](https://github.com/laravel/sail/commit/414fd19858379fd3c0277194904ffb95617d7ee6)


## [v1.8.0 (2021-06-08)](https://github.com/laravel/sail/compare/v1.7.0...v1.8.0)

### Added
- Add proxy to vendor binaries ([#154](https://github.com/laravel/sail/pull/154))

### Changed
- Use node.js v16.x ([#155](https://github.com/laravel/sail/pull/155))
- Update Sail script to only exit if Main Exits ([#156](https://github.com/laravel/sail/pull/156))

### Fixed
- Append MeiliSearch and MinIO to depends ([#151](https://github.com/laravel/sail/pull/151))
- Append MeiliSearch HealthCheck ([#150](https://github.com/laravel/sail/pull/150))


## [v1.7.0 (2021-05-25)](https://github.com/laravel/sail/compare/v1.6.0...v1.7.0)

### Added
- Add Redis CLI command ([#140](https://github.com/laravel/sail/pull/140))

### Fixed
- Add retries & timeout to healthcheck ([#143](https://github.com/laravel/sail/pull/143))


## [v1.6.0 (2021-05-18)](https://github.com/laravel/sail/compare/v1.5.1...v1.6.0)

### Added
- Add MinIO to sail:install Command ([#128](https://github.com/laravel/sail/pull/128))

### Changed
- Clear pecl caches & tmp files during Swoole extension install ([#134](https://github.com/laravel/sail/pull/134))

### Fixed
- Fix mariaDB Health check ([#126](https://github.com/laravel/sail/pull/126))


## [v1.5.1 (2021-05-11)](https://github.com/laravel/sail/compare/v1.5.0...v1.5.1)

### Changed
- Use MySQL shell when running mariadb ([#119](https://github.com/laravel/sail/pull/119))

### Fixed
- Fix mysql health check ([#125](https://github.com/laravel/sail/pull/125))


## [v1.5.0 (2021-04-20)](https://github.com/laravel/sail/compare/v1.4.12...v1.5.0)

### Added
- MariaDB support ([#111](https://github.com/laravel/sail/pull/111))


## [v1.4.12 (2021-04-13)](https://github.com/laravel/sail/compare/v1.4.11...v1.4.12)

### Fixed
- Load missing PECL package index before installing Swoole ([#94](https://github.com/laravel/sail/pull/94))


## [v1.4.11 (2021-04-06)](https://github.com/laravel/sail/compare/v1.4.10...v1.4.11)

### Changed
- Add Swoole ([9cf7a28](https://github.com/laravel/sail/commit/9cf7a289fbae184f8468188c582ea5a604ac1012), [0706de0](https://github.com/laravel/sail/commit/0706de0c6a80e6f04861ffb875f9e13c63568ccb))


## [v1.4.10 (2021-03-30)](https://github.com/laravel/sail/compare/v1.4.9...v1.4.10)

### Changed
- Database default user name and password ([#84](https://github.com/laravel/sail/pull/84))

### Fixed
- Patch issue with environment database password replacement ([#87](https://github.com/laravel/sail/pull/87))


## [v1.4.9 (2021-03-23)](https://github.com/laravel/sail/compare/v1.4.8...v1.4.9)

### Fixed
- Use different DB user & password for Sail ([#75](https://github.com/laravel/sail/pull/75))


## [v1.4.8 (2021-03-16)](https://github.com/laravel/sail/compare/v1.4.7...v1.4.8)

### Fixed
- Update the publish command to consider PHP 7.4 ([#68](https://github.com/laravel/sail/pull/68))


## [v1.4.7 (2021-03-09)](https://github.com/laravel/sail/compare/v1.4.6...v1.4.7)

### Fixed
- Add missing PostgreSQL clients ([#64(https://github.com/laravel/sail/pull/64))
- Use latest expose container ([cebaebc](https://github.com/laravel/sail/commit/cebaebc0bb3806f4cf7bc71564acbfe8c12a8923))


## [v1.4.6 (2021-03-03)](https://github.com/laravel/sail/compare/v1.4.5...v1.4.6)

### Fixed
- Update share command ([59ee7e2](https://github.com/laravel/sail/commit/59ee7e2b2efeb644eabea719186db91d11666733))


## [v1.4.5 (2021-03-03)](https://github.com/laravel/sail/compare/v1.4.4...v1.4.5)

### Fixes
- Replace `DB_PORT` and `DB_CONNECTION` for pgsql ([#63](https://github.com/laravel/sail/pull/63))
- Update share command ([0348ec8](https://github.com/laravel/sail/commit/0348ec8c13fedc4bafc917b9d65721cd475390bf))


## [v1.4.4 (2021-03-02)](https://github.com/laravel/sail/compare/v1.4.3...v1.4.4)

### Changed
- Re-add memcached ([#62](https://github.com/laravel/sail/pull/62))

### Fixed
- Fix pgsql.stub volumes typo ([#60](https://github.com/laravel/sail/pull/60))


## [v1.4.3 (2021-02-22)](https://github.com/laravel/sail/compare/v1.4.2...v1.4.3)

### Changed
- Update flag name ([0200ce6](https://github.com/laravel/sail/commit/0200ce6e0f697699bce036c42d91f1daab8039a8))


## [v1.4.2 (2021-02-22)](https://github.com/laravel/sail/compare/v1.4.1...v1.4.2)

### Changed
- Removed comments ([a317a1a](https://github.com/laravel/sail/commit/a317a1af337ffc07c63ea5a4e04784fdb58ea9df))


## [v1.4.1 (2021-02-23)](https://github.com/laravel/sail/compare/v1.4.0...v1.4.1)

### Changed
- Back out feature ([87c63c2](https://github.com/laravel/sail/commit/87c63c2956749f66e43467d4a730b917ef7428b7))


## [v1.4.0 (2021-02-23)](https://github.com/laravel/sail/compare/v1.3.1...v1.4.0)

### Added
- Implement interactive choice and Meilisearch ([#58](https://github.com/laravel/sail/pull/58), [b78093b](https://github.com/laravel/sail/commit/b78093b02c328d82e27cdacfb20568c49cd980c4))

### Changed
- Display message after installing Sail ([#56](https://github.com/laravel/sail/pull/56))

### Fixed
- Change supervisord logfile and pidfile settings ([#57](https://github.com/laravel/sail/pull/57))

### Removed
- Remove memcached stub ([3a4fac1](https://github.com/laravel/sail/commit/3a4fac159b92424d2ff3472ce182be14fc1cb080))


## [v1.3.1 (2021-02-09)](https://github.com/laravel/sail/compare/v1.3.0...v1.3.1)

### Changed
- Inform user when running docker-compose down ([#52](https://github.com/laravel/sail/pull/52))
- Cleanup supervisord warnings on start ([#53](https://github.com/laravel/sail/pull/53))


## [v1.3.0 (2021-01-26)](https://github.com/laravel/sail/compare/v1.2.0...v1.3.0)

### Added
- Add support for `dusk:fails` ([#43](https://github.com/laravel/sail/pull/43))

### Fixed
- Append PostgreSQL HealthCheck ([#41](https://github.com/laravel/sail/pull/41))
- Use non-root MySQL password for `sail mysql` ([#45](https://github.com/laravel/sail/pull/45))


## [v1.2.0 (2021-01-19)](https://github.com/laravel/sail/compare/v1.1.0...v1.2.0)

### Added
- PostgreSQL Support ([#28](https://github.com/laravel/sail/pull/28))

### Changed
- Add healthcheck for mysql and redis service in docker-compose ([#36](https://github.com/laravel/sail/pull/36))
- Update Mailhog env variables ([bf10c80](https://github.com/laravel/sail/commit/bf10c804057f8d0be615c71acbc46c7328cd652c))


## [v1.1.0 (2021-01-05)](https://github.com/laravel/sail/compare/v1.0.1...v1.1.0)

### Added
- Yarn Support ([#29](https://github.com/laravel/sail/pull/29))
- root-shell added to bin/sail ([#33](https://github.com/laravel/sail/pull/33))

### Changed
- Add sail bash to Initiate a Bash shell within the application container ([#30](https://github.com/laravel/sail/pull/30))

### Fixed
- Send error messages to STDERR ([#32](https://github.com/laravel/sail/pull/32))


## [v1.0.1 (2020-12-22)](https://github.com/laravel/sail/compare/v1.0.0...v1.0.1)

### Fixed
- Fix a bug with memcached ([7457004](https://github.com/laravel/sail/commit/7457004969dd62fa727fbc596bb2accccb1409a5))


## v1.0.0 (2020-12-22)

Initial stable release.
