#!/bin/bash

# Path to the letsencrypt-auto tool
LETSENCRYPT_BIN=/usr/bin/certbot

# Directory where the acme client puts the generated certs
LETSENCRYPT_CERT_OUTPUT=/etc/letsencrypt/live

# Create or renew certificate for the domain(s) supplied for this tool
$LETSENCRYPT_BIN certonly \
    --authenticator standalone \
    --keep-until-expiring \
    --standalone \
    --http-01-port 80 \
    --text \
    --expand \
    --agree-tos \
    -d $DOMAINNAME

# Cat the certificate chain and the private key together for haproxy
for path in $(find $LETSENCRYPT_CERT_OUTPUT/* -type d -exec basename {} \;); do
  cat $LETSENCRYPT_CERT_OUTPUT/$path/{fullchain.pem,privkey.pem} > /etc/yiimp/haproxy/ssl/${path}.pem
done
