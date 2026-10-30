FROM php:8.3-cli

WORKDIR /app
COPY . .

# Lets the built-in server handle several players at once.
ENV PHP_CLI_SERVER_WORKERS=4

# Railway provides $PORT. Mount a volume at /app/data to keep the database between deploys.
CMD php -S 0.0.0.0:${PORT:-8000} -t public
