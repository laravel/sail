# Sail Build Tests

This directory contains tests for the Sail build functionality.

## Test Structure

- **Feature Tests** (`tests/Feature/`): Unit and feature tests that don't require Docker
- **Integration Tests** (`tests/Integration/`): Full integration tests that require Docker

## Running Tests

### All Tests
```bash
composer test
# or
vendor/bin/phpunit
```

### Feature Tests Only
```bash
vendor/bin/phpunit tests/Feature
```

### Integration Tests Only
```bash
vendor/bin/phpunit tests/Integration
```

## Full Build Test

The `FullBuildTest` performs a complete end-to-end test of the build process:

1. **Prerequisites Validation**: Checks for Docker, Docker Buildx, and Helm
2. **Dry-Run Mode**: Tests the build command in preview mode
3. **Helm Chart Generation**: Validates that Helm charts are created correctly
4. **Docker Build Validation**: (Optional) Actually builds Docker images

### Requirements

- Docker and Docker Buildx installed
- Helm installed
- Sufficient disk space for Docker images

### Running Full Build Test

```bash
# Run with Docker available
vendor/bin/phpunit tests/Integration/FullBuildTest.php

# Or skip if Docker not available (test will auto-skip)
SKIP_DOCKER_TESTS=1 vendor/bin/phpunit tests/Integration/FullBuildTest.php
```

## Test Environment

Tests create temporary directories in the system temp directory and clean them up after execution. The test structure mimics a minimal Laravel application.

## CI/CD Integration

The integration tests are designed to work in CI/CD environments:
- Auto-skip if Docker is not available
- Use dry-run mode by default to avoid long build times
- Clean up resources after tests
