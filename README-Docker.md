# *I, Librarian* in Docker

## Prepare permanent storage directory

```bash
mkdir -p /var/www/i-librarian-free/library/data
chown -R 33:33 /var/www/i-librarian-free/library/data
```
User 33:33 is the user/group ID (UID/GID) that *I, Librarian* runs as. In an unlikely case of a conflict, you can set
the directory permissions to 0777 instead.

## Prepare config file

```bash
mkdir -p /var/www/i-librarian-free/library/config
tar xf I-Librarian-5.11.3-Linux.tar.xz config/ilibrarian-default.ini --strip-components=1
mv ilibrarian-default.ini /var/www/i-librarian-free/library/config/ilibrarian.ini
```

## Build an image

```bash
docker build -t i-librarian-free:5.11.3 - < I-Librarian-5.11.3-Linux.tar.xz
```

## Run container

```bash
docker run -d --name il-free -p 127.0.0.1:9050:80 -v /var/www/i-librarian-free/library/data:/i-librarian/data \
  -v /var/www/i-librarian-free/library/config:/i-librarian/config i-librarian-free:5.11.3
```

## Docker compose alternative

```YAML
services:
  il-free:
    image: i-librarian-free:5.11.3
    container_name: il-free
    restart: always
    ports:
      - "127.0.0.1:9050:80"
    volumes:
      - type: bind
        source: /var/www/i-librarian-free/library/data
        target: /i-librarian/data
      - type: bind
        source: /var/www/i-librarian-free/library/config
        target: /i-librarian/config
        read_only: true
```

```bash
docker compose up -d
```

## Access

*I, Librarian* now runs at 127.0.0.1:9050. You can use the local address directly, or via reverse proxy. For instance, using Caddy:

* access *I, Librarian* at https://library.example.com. Here, `library` subdomain is just an example. Use whatever you want.
```Caddyfile
library.example.com {
    reverse_proxy 127.0.0.1:9050
}
```
* alternatively, to access *I, Librarian* on a `library` URL path at https://example.com/library.  Here, `library` path is literal.

```Caddyfile
example.com {
    handle /library* {
        reverse_proxy 127.0.0.1:9050
    }
}
```
