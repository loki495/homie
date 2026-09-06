FROM php:8.3-cli-alpine
WORKDIR /mock
COPY router.php .
CMD ["php", "-S", "0.0.0.0:80", "-t", "/mock", "/mock/router.php"]
