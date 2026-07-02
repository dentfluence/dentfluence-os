# =============================================================================
# Dentfluence — nginx web server image
# Lightweight: just nginx + our site config. The actual public files are
# mounted in at runtime from the shared `public_data` volume (populated by the
# app container's entrypoint), so this image stays tiny and never goes stale.
# =============================================================================
FROM nginx:1.27-alpine

# Remove the default site, add ours
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
