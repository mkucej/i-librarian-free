FROM ubuntu:22.04

ARG TIMEZONE="UTC"
ARG CADDY_VERSION="2.10.2"

RUN apt update && \
	DEBIAN_FRONTEND=noninteractive TZ=${TIMEZONE} apt install -qy \
    wget \
    tzdata \
    php-fpm \
    php-mbstring \
    php-sqlite3 \
    php-curl \
    php-gd \
    php-xml \
    php-json \
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
	apt-get clean && \
	rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
	wget https://github.com/caddyserver/caddy/releases/download/v${CADDY_VERSION}/caddy_${CADDY_VERSION}_linux_amd64.tar.gz && \
	tar xzf caddy_${CADDY_VERSION}_linux_amd64.tar.gz caddy && \
	chown root:root caddy && \
	mv caddy /usr/bin/caddy && \
    groupadd --system caddy && \
    useradd --system --gid caddy --create-home --home-dir /var/lib/caddy --shell /usr/sbin/nologin caddy && \
    usermod -aG www-data caddy && \
    rm caddy_${CADDY_VERSION}_linux_amd64.tar.gz

# User www-data.
ENV UID="33"
ENV GID="33"

RUN mkdir /i-librarian
RUN mkdir /run/php
RUN mkdir /etc/caddy
RUN mkdir /var/log/caddy

COPY app /i-librarian/app/
COPY bin /i-librarian/bin/
COPY classes /i-librarian/classes/
COPY config /i-librarian/config/
COPY data /i-librarian/data/
COPY public /i-librarian/public/
COPY Caddyfile /etc/caddy/Caddyfile
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN chown -R "$UID":"$GID" /i-librarian/data/
RUN chown -R caddy:caddy /var/log/caddy

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]

STOPSIGNAL SIGQUIT