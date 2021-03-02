# Release Notes

## [Unreleased](https://github.com/laravel/sail/compare/v1.4.4...1.x)


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
