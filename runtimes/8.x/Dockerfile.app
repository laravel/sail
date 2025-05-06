# ===========================================
# Final Image: Local Application (FPM + Nginx)
# ===========================================

FROM base

WORKDIR /var/www/

# Copy necessary files from builder stage
# COPY --from=php-build /usr/local /usr/local
# COPY --from=base /etc/ssl/certs /etc/ssl/certs
# COPY --from=base /init /init
# COPY --from=base /var/log /var/log
# COPY --from=base /bin/bash /bin/bash
# COPY --from=base /etc/nginx /etc/nginx
# COPY --from=base /usr/share/zoneinfo /usr/share/zoneinfo
# COPY --from=base /usr/bin/composer /usr/bin/composer
# COPY --from=base /usr/local/bin /usr/local/bin

# S6
# COPY --from=base /init /init
# COPY --from=base /command /command
# COPY --from=base /etc/s6-linux-init /etc/s6-linux-init
# COPY --from=runtime ./s6/app /etc/s6-overlay/s6-rc.d/
COPY --from=runtime ./s6/local /etc/s6-overlay/s6-rc.d/

# ARG WWWGROUP=1000
# ARG WWWUSER=1000

# # Finalize the image
# RUN addgroup -g ${WWWGROUP} sail \
#     && adduser -D -u ${WWWUSER} -G sail sail \
#     # Install runtime system dependencies (Alpine packages)
#     # && apk add --no-cache nginx \
#     # libjpeg-turbo \
#     # libpng \
#     # freetype \
#     # libwebp \
#     # libxpm \
#     # libzip \
#     # oniguruma \
#     # icu-libs \
#     # zlib \
#     # libxml2 \
#     # npm \
#     # && rm -rf /var/cache/apk/* \
#     && chown -R sail:sail /var/ \
#     && chmod -R 755 /var/log

EXPOSE 80/tcp

# Start S6 to manage Laravel processes
CMD [ "/init" ]