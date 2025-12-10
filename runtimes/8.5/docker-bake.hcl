variable "APP_DIR" {
    default = "."
}

variable "RUNTIME_DIR" {
    default = "./vendor/reyemtech/sail/runtimes/8.x"
}

variable "PHP_VERSION" {
    default = "8.5"
}

variable "PUSH" {
    default = true
}

variable "REGISTRY" {
    default = "ghcr.io"
}

variable "ORG" {
    default = "your-org"
}

variable "APP_NAME" {
    default = "your-app"
}

variable "VERSION" {
    default = "1.0.0"
}

variable "ARCHS" {
    default = "linux/amd64,linux/arm64"
}

group "default" {
    targets = ["app", "production-cli", "production-fpm"]
}

target "base" {
    name = "base-${tgt}"
    context = "${RUNTIME_DIR}"
    contexts = {
        "runtime" = "${RUNTIME_DIR}"
    }
    matrix = {
        tgt = ["cli", "fpm"]
    }
    platforms = PUSH ? split(",", ARCHS) : ["local"]
    dockerfile= "Dockerfile.base"
    args = {
        BASE_IMAGE = "php:${PHP_VERSION}-${tgt}-alpine"
    }
}

target "app-build" {
    name = "app-build-${tgt}"
    context = "${RUNTIME_DIR}"
    contexts = {
        "base" = "target:base-${tgt}"
        "app" = "${APP_DIR}"
        "runtime" = "${RUNTIME_DIR}"
        "package" = "${RUNTIME_DIR}/../../"
    }
    dockerfile= "Dockerfile.app-build"
    platforms = PUSH ? split(",", ARCHS) : ["local"]
    matrix = {
        tgt = ["cli", "fpm"]
    }
}

target "production" {
    name = "production-${tgt}"
    context = "${RUNTIME_DIR}"
    contexts = {
        "base" = "target:base-${tgt}"
        "app-build" = "target:app-build-${tgt}"
        "runtime" = "${RUNTIME_DIR}"
    }
    dockerfile= "Dockerfile.production"
    matrix = {
        tgt = ["cli", "fpm"]
    }
    output = [{
        type = PUSH == true ? "registry" : "docker"
    }]
    labels = {
        "maintainer" = "Reyem Tech"
        "version" = "${VERSION}"
        "description" = "Production image for ${APP_NAME} (${tgt == "cli" ? "worker" : "web"})"
        "org.opencontainers.image.created" = "${timestamp()}"
        "org.opencontainers.image.authors" = "Reyem Tech"
        "org.opencontainers.image.url" = "https://reyem.tech"
        "org.opencontainers.image.documentation" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.source" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.version" = "${VERSION}"
        "org.opencontainers.image.vendor" = "Reyem Tech"
        "org.opencontainers.image.licenses" = "MIT"
        "org.opencontainers.image.title" = "${APP_NAME}-${tgt == "cli" ? "worker" : "web"}"
        "org.opencontainers.image.description" = "Production image for ${APP_NAME} (${tgt == "cli" ? "worker" : "web"})"
    }
    platforms = PUSH ? split(",", ARCHS) : ["local"]
    tags = [
        "${REGISTRY}/${ORG}/${APP_NAME}-${tgt == "cli" ? "worker" : "web"}:${VERSION}",
        "${REGISTRY}/${ORG}/${APP_NAME}-${tgt == "cli" ? "worker" : "web"}:latest",
    ]
}

target "app" {
    context = "${RUNTIME_DIR}"
    contexts = {
        "base" = "target:base-fpm"
        "runtime" = "${RUNTIME_DIR}"
    }
    dockerfile= "Dockerfile.app"
    output = [{
        type = "docker"
    }]
    tags = [
        "${ORG}/${APP_NAME}:${VERSION}",
        "${ORG}/${APP_NAME}:latest",
    ]
    labels = {
        "maintainer" = "Reyem Tech"
        "version" = "${VERSION}"
        "description" = "Local image for ${APP_NAME}"
        "org.opencontainers.image.created" = "${timestamp()}"
        "org.opencontainers.image.authors" = "Reyem Tech"
        "org.opencontainers.image.url" = "https://reyem.tech"
        "org.opencontainers.image.documentation" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.source" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.version" = "${VERSION}"
        "org.opencontainers.image.vendor" = "Reyem Tech"
        "org.opencontainers.image.licenses" = "MIT"
        "org.opencontainers.image.title" = "${APP_NAME}"
        "org.opencontainers.image.description" = "Local image for ${APP_NAME}"
    }
}
