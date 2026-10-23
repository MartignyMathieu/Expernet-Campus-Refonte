# Nginx de production : seuls les fichiers publics de Laravel sont copiés (index.php, openapi.json, robots.txt).
FROM nginx:1.27-alpine
RUN apk add --no-cache curl
COPY docker/prod/nginx.conf /etc/nginx/conf.d/default.conf
COPY backend/public /var/www/backend/public
