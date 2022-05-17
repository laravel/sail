# Release Notes

## [Unreleased](https://github.com/laravel/sail/compare/v1.14.5...1.x)

## [v1.14.5](https://github.com/laravel/sail/compare/v1.14.4...v1.14.5) - 2022-05-16

### Changed

- Updated sail helps section by @mehdirajabi59 in https://github.com/laravel/sail/pull/407
- Cleans up deprecated apt-key usage by @tbollinger in https://github.com/laravel/sail/pull/408
- use docker compose (GO) by @erfantkerfan in https://github.com/laravel/sail/pull/405

## [v1.14.4](https://github.com/laravel/sail/compare/v1.14.3...v1.14.4) - 2022-05-12

### Fixed

- Fixes incorrectly referenced distro https://github.com/laravel/sail/commit/0e0e51f19c758c79acbda11e3870641fbad5b7d9

## [v1.14.3](https://github.com/laravel/sail/compare/v1.14.2...v1.14.3) - 2022-05-10

### Changed

- Changed Ubuntu 21.10 to Ubuntu 22.04 LTS by @mehdirajabi59 in https://github.com/laravel/sail/pull/395

## [v1.14.2](https://github.com/laravel/sail/compare/v1.14.1...v1.14.2) - 2022-05-10

### Fixed

- Allow Sail to read from phpunit.xml and phpunit.xml.dist when running the install command by @kylemilloy in https://github.com/laravel/sail/pull/394
- Fix missing usage of POSTGRES_VERSION by @driesvints in https://github.com/laravel/sail/pull/398

## [v1.14.1](https://github.com/laravel/sail/compare/v1.14.0...v1.14.1) - 2022-05-02

### Changed

- Expose 8080 port for hot module replacement by @ryoluo in https://github.com/laravel/sail/pull/391

## [v1.14.0](https://github.com/laravel/sail/compare/v1.13.10...v1.14.0) - 2022-04-27

### Added

- Create a dedicated testing database by @jessarcher in https://github.com/laravel/sail/pull/388

### Fixed

- Fix apt-key for WSL by @Evertt in https://github.com/laravel/sail/pull/389

## [v1.13.10](https://github.com/laravel/sail/compare/v1.13.9...v1.13.10) - 2022-04-14

### Fixed

- Fix apt-key for WSL by @driesvints in https://github.com/laravel/sail/pull/384

## [v1.13.9](https://github.com/laravel/sail/compare/v1.13.8...v1.13.9) - 2022-04-04

### Changed

- Update default PostgreSQL version to v14 by @ariaieboy in https://github.com/laravel/sail/pull/373

## [v1.13.8](https://github.com/laravel/sail/compare/v1.13.7...v1.13.8) - 2022-03-23

### Changed

- Update ondrej/php Repository Details by @amayer5125 in https://github.com/laravel/sail/pull/360
- Shell - display available commands / help section by @WalterWoshid in https://github.com/laravel/sail/pull/359

### Fixes

- Fixes docker-compose not found in non-bash shells by @ribeirobreno in https://github.com/laravel/sail/pull/364

## [v1.13.7](https://github.com/laravel/sail/compare/v1.13.6...v1.13.7) - 2022-03-15

### Fixed

- The input device is not a TTY by @ribeirobreno in https://github.com/laravel/sail/pull/353
- `SAIL_FILE` environment variable prevents using docker-compose.override.yml by @ribeirobreno in https://github.com/laravel/sail/pull/355

## [v1.13.6](https://github.com/laravel/sail/compare/v1.13.5...v1.13.6) - 2022-03-08

### Changed

- Allow overriding docker-compose.yml path using ENV by @prageeth in https://github.com/laravel/sail/pull/352 & @taylorotwell in https://github.com/laravel/sail/commit/6205041336b09b965af1d6af29261584e787bf52

## [v1.13.5](https://github.com/laravel/sail/compare/v1.13.3...v1.13.5) - 2022-02-22

### Changed

- Revert "Install regular PHP packages instead of dev versions" by @taylorotwell in https://github.com/laravel/sail/pull/342

## [v1.13.4](https://github.com/laravel/sail/compare/v1.13.3...v1.13.4) - 2022-02-17

### Changed

- Install regular PHP packages instead of dev versions by @bramdevries in https://github.com/laravel/sail/pull/340
- Update Ubuntu by @taylorotwell in https://github.com/laravel/sail/commit/57d2942d5edd89b2018d0a3447da321fa35baac7

## [v1.13.3](https://github.com/laravel/sail/compare/v1.13.2...v1.13.3) - 2022-02-15

### Changed

- Support Newer Docker Compose Exit Statuses by @amayer5125 in https://github.com/laravel/sail/pull/331

### Fixed

- Typo in replace when checking for ARM for Seleium by @aprat84 in https://github.com/laravel/sail/pull/330

## [v1.13.2](https://github.com/laravel/sail/compare/v1.13.1...v1.13.2) - 2022-02-08

### Fixed

- Fix a typo in the "phpunit" command ([#329](https://github.com/laravel/sail/pull/329))

## [v1.13.1 (2022-01-20)](https://github.com/laravel/sail/compare/v1.13.0...v1.13.1)

### Changed

- Update for Meilisearch ARM support ([#315](https://github.com/laravel/sail/pull/315))

### Fixed

- Fix php8.0-dev depending on php8.1-cli ([#316](https://github.com/laravel/sail/pull/316))

## [v1.13.0 (2022-01-18)](https://github.com/laravel/sail/compare/v1.12.12...v1.13.0)

### Added

- Add phpunit alias to sail binary ([#310](https://github.com/laravel/sail/pull/310))

### Changed

- Add separator between volume names ([#312](https://github.com/laravel/sail/pull/312))

## [v1.12.12 (2021-12-16)](https://github.com/laravel/sail/compare/v1.12.11...v1.12.12)

### Fixed

- Revert "Set meilisearch data path" ([#301](https://github.com/laravel/sail/pull/301))

## [v1.12.11 (2021-12-14)](https://github.com/laravel/sail/compare/v1.12.10...v1.12.11)

### Added

- Set meilisearch data path ([#299](https://github.com/laravel/sail/pull/299))

## [v1.12.10 (2021-12-07)](https://github.com/laravel/sail/compare/v1.12.9...v1.12.10)

### Fixed

- ARM based container on Apple Silicon for Selenium ([#294](https://github.com/laravel/sail/pull/294))

## [v1.12.9 (2021-11-30)](https://github.com/laravel/sail/compare/v1.12.8...v1.12.9)

### Changed

- Make PHP 8.1 the default runtime ([#292](https://github.com/laravel/sail/pull/292))

## [v1.12.8 (2021-11-26)](https://github.com/laravel/sail/compare/v1.12.7...v1.12.8)

## Changed

- Revert "Switch to PHP 8.1" ([#291](https://github.com/laravel/sail/pull/291))

## [v1.12.7 (2021-11-26)](https://github.com/laravel/sail/compare/v1.12.6...v1.12.7)

### Changed

- Make PHP 8.1 the default runtime ([#289](https://github.com/laravel/sail/pull/289))

## [v1.12.6 (2021-11-23)](https://github.com/laravel/sail/compare/v1.12.5...v1.12.6)

### Changed

- Add npm update to Dockerfile ([#285](https://github.com/laravel/sail/pull/285))

## [v1.12.5 (2021-11-16)](https://github.com/laravel/sail/compare/v1.12.4...v1.12.5)

### Changed

- Re-enable previously disabled PHP 8.1 extensions ([#278](https://github.com/laravel/sail/pull/278))
- Add platform setting to Meilisearch config ([1286886](https://github.com/laravel/sail/commit/1286886ec04f9101b756221c90ec766741459db4))

## [v1.12.4 (2021-11-09)](https://github.com/laravel/sail/compare/v1.12.3...v1.12.4)

### Fixed

- Fix `NODE_VERSION` on build ([#274](https://github.com/laravel/sail/pull/274))

## [v1.12.3 (2021-11-05)](https://github.com/laravel/sail/compare/v1.12.2...v1.12.3)

### Changed

- Update MySQL stub for Apple Silicon ([#272](https://github.com/laravel/sail/pull/272))

## [v1.12.2 (2021-10-26)](https://github.com/laravel/sail/compare/v1.12.1...v1.12.2)

### Fixed

- Revert "Adds a check and error for APP_SERVICE being accurate." ([#264](https://github.com/laravel/sail/pull/264))

## [v1.12.1 (2021-10-26)](https://github.com/laravel/sail/compare/v1.12.0...v1.12.1)

### Changed

- Adds a check and error for `APP_SERVICE` being accurate ([#258](https://github.com/laravel/sail/pull/258))
- Allow `NODE_VERSION` variable ([#261](https://github.com/laravel/sail/pull/261))

## [v1.12.0 (2021-10-12)](https://github.com/laravel/sail/compare/v1.11.0...v1.12.0)

### Added

- PHP 8.1 support ([#254](https://github.com/laravel/sail/pull/254))

## [v1.11.0 (2021-10-01)](https://github.com/laravel/sail/compare/v1.10.1...v1.11.0)

### Added

- Added support for "docker compose" command syntax

## [v1.10.2 (2021-09-28)](https://github.com/laravel/sail/compare/v1.10.1...v1.10.2)

### Changed

- Environment variable for share subdomain ([#239](https://github.com/laravel/sail/pull/239))

## [v1.10.1 (2021-08-24)](https://github.com/laravel/sail/compare/v1.10.0...v1.10.1)

### Changed

- Adding extra_hosts to the compose file stubs ([#222](https://github.com/laravel/sail/pull/222))
- Allow skip of sail checks ([#224](https://github.com/laravel/sail/pull/224))

## [v1.10.0 (2021-08-17)](https://github.com/laravel/sail/compare/v1.9.0...v1.10.0)

### Added

- Add devcontainer to install command ([#218](https://github.com/laravel/sail/pull/218))

### Changed

- Removes hardcoded service name from `APP_URL` in `dusk` and `dusk:fails` command ([#219](https://github.com/laravel/sail/pull/219))

## [v1.9.0 (2021-08-03)](https://github.com/laravel/sail/compare/v1.8.6...v1.9.0)

### Added

- Xdebug 3.0 support ([#209](https://github.com/laravel/sail/pull/209))

### Changed

- Make sail script publishable ([#201](https://github.com/laravel/sail/pull/201), [#202](https://github.com/laravel/sail/pull/202))
- Pass additional arguments to shell / root-shell commands ([#208](https://github.com/laravel/sail/pull/208))

### Fixed

- Call source `.env` before exporting bash environment variables ([#207](https://github.com/laravel/sail/pull/207))

## [v1.8.6 (2021-07-15)](https://github.com/laravel/sail/compare/v1.8.5...v1.8.6)

### Fixed

- Fixes missing backslash ([#196](https://github.com/laravel/sail/pull/196))

## [v1.8.5 (2021-07-13)](https://github.com/laravel/sail/compare/v1.8.4...v1.8.5)

### Changed

- Minio Console Port ([#188](https://github.com/laravel/sail/pull/188))

## [v1.8.4 (2021-07-06)](https://github.com/laravel/sail/compare/v1.8.3...v1.8.4)

### Changed

- Update to Ubuntu 21.04 ([#177](https://github.com/laravel/sail/pull/177))
- Add pcov to php 8.0 runtime ([#183](https://github.com/laravel/sail/pull/183))

### Fixed

- Append random subdomain by default ([#175](https://github.com/laravel/sail/pull/175))

### Removed

- Remove Unused SEDCMD ([#179](https://github.com/laravel/sail/pull/179))

## [v1.8.3 (2021-06-29)](https://github.com/laravel/sail/compare/v1.8.2...v1.8.3)

### Fixed

- Revert Ubuntu 21.04 changes ([#174](https://github.com/laravel/sail/pull/174))

## [v1.8.2 (2021-06-29)](https://github.com/laravel/sail/compare/v1.8.1...v1.8.2)

### Changed

- Share/Expose options and cleanup on exit ([#168](https://github.com/laravel/sail/pull/168), [44c7087](https://github.com/laravel/sail/commit/44c7087026a0637471e544237d608a2e1173dc77))
- Update to Ubuntu 21.04 ([#169](https://github.com/laravel/sail/pull/169), [0df641d](https://github.com/laravel/sail/commit/0df641dd2d7f2f42d24aef638e2e579f6ac7e57c), [484b928](https://github.com/laravel/sail/commit/484b9284d46bfe3e1e6a2ed71477bb4b70166070))

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
