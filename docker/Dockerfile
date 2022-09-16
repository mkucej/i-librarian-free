# docker build . -t librarian-dev -f docker/Dockerfile 
# docker run --rm --name libra-dev -p 8082:80 -v $(pwd)/app:/usr/share/i-librarian/app -v $(pwd)/classes:/usr/share/i-librarian/classes -v $(pwd)/public:/usr/share/i-librarian/public -it librarian-dev

FROM ubuntu:kinetic

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Europe/London
ENV INSTALLDIR="/usr/share/i-librarian"
ENV DATADIR="/var/lib/i-librarian"
ENV CONFDIR="/etc/i-librarian"
ENV APACHEOWNER="www-data"
ENV APACHEGROUP="www-data"
ENV CONF='/etc/apache2/sites-available/i-librarian.conf'

# Install apache, php & project dependencies
RUN apt-get update && \ 
    apt-get -y install apache2 libapache2-mod-php php php-sqlite3 \
    php-gd php-curl php-xml php-intl php-json php-mbstring php-zip \
    ghostscript poppler-utils tesseract-ocr tesseract-ocr-ell libreoffice    

COPY . /tmp/libra/
WORKDIR /tmp/libra

# develop locally
COPY docker/docker-php-ext-enable /usr/local/bin/
RUN apt-get -y install php-xdebug && \
    docker-php-ext-enable xdebug && \
    cp ./docker/php/conf.d/xdebug.ini /etc/php/8.1/apache2/conf.d/docker-php-ext-xdebug.ini && \
    cp ./docker/php/conf.d/error_reporting.ini /etc/php/8.1/apache2/conf.d/error_reporting.ini && \
    # config LIBRARIA
    mkdir -vp "$INSTALLDIR" && \
    mkdir -vp "$DATADIR/data" && \
    mkdir -vp "$CONFDIR" && \
    cp -r ./bin $INSTALLDIR  && \   
   # cp -r ./public $INSTALLDIR  && \
    cp -r ./config $CONFDIR  && \
    cp -r ./data $DATADIR && \
    chown -R root:root "$INSTALLDIR"  && \ 
    chown -R root:root "$CONFDIR"  && \ 
    chown -R $APACHEOWNER:$APACHEGROUP "$DATADIR/data" && \ 
    sed -e "s=<DIR>=$INSTALLDIR/public=" <"$PWD/docker/.i-librarian" >"$PWD/.i-librarian2"  && \ 
    mv "$PWD/.i-librarian2" "$CONF"  && \ 
    a2ensite i-librarian

CMD apachectl -D FOREGROUND

