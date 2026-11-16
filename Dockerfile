FROM ubuntu:26.04

ARG TIMEZONE="UTC"
ARG CADDY_VERSION="2.11.9"

RUN apt update && \
	DEBIAN_FRONTEND=noninteractive TZ=${TIMEZONE} apt-get install -qy --no-install-recommends \
    ca-certificates \
    wget \
    tzdata \
    php-fpm \
    php-mbstring \
    php-sqlite3 \
    php-curl \
    php-gd \
    php-xml \
    php-zip \
    php-intl \
    php-ldap \
    ghostscript \
    libreoffice-calc \
    libreoffice-draw \
    libreoffice-impress \
    libreoffice-math \
    libreoffice-writer \
    poppler-utils \
    tesseract-ocr && \
    case "$(uname -m)" in \
      x86_64)  CADDY_ARCH="amd64" ;; \
      aarch64) CADDY_ARCH="arm64" ;; \
      *)       echo "Error: Architecture $(uname -m) is not supported by this container." && exit 1 ;; \
    esac && \
	wget https://github.com/caddyserver/caddy/releases/download/v${CADDY_VERSION}/caddy_${CADDY_VERSION}_linux_${CADDY_ARCH}.tar.gz && \
	tar xzf caddy_${CADDY_VERSION}_linux_${CADDY_ARCH}.tar.gz caddy && \
	chown root:root caddy && \
	mv caddy /usr/bin/caddy && \
    groupadd --system caddy && \
    useradd --system --gid caddy --create-home --home-dir /var/lib/caddy --shell /usr/sbin/nologin caddy && \
    usermod -aG www-data caddy && \
    apt-get purge -y --auto-remove wget && \
    apt-get clean && \
    rm -rf caddy_*.tar.gz /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    mkdir -p /run/php /i-librarian /etc/caddy /var/log/caddy

COPY app /i-librarian/app/
COPY bin /i-librarian/bin/
COPY classes /i-librarian/classes/
COPY config /i-librarian/config/
COPY data /i-librarian/data/
COPY public /i-librarian/public/
COPY Caddyfile /etc/caddy/Caddyfile
COPY start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh && \
    chown -R www-data:www-data /i-librarian/data/ && \
    chown -R caddy:caddy /var/log/caddy

EXPOSE 80
STOPSIGNAL SIGQUIT

CMD ["/usr/local/bin/start.sh"]
