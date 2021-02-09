# Release Notes

## [Unreleased](https://github.com/laravel/sail/compare/v1.3.1...1.x)


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
