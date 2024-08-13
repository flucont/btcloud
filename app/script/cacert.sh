#!/bin/bash

OPENSSL_CHECK=$(which openssl)
if [ "$?" != "0" ]; then
	echo "未安装OpenSSL"
	exit 1
fi

if [ ! -f ca.key ] && [ ! -f ca.crt ]; then
	openssl genrsa -out ca.key 2048
	openssl req -new -x509 -utf8 -days 3650 -extensions v3_ca -subj "/C=CN/O=宝塔面板/CN=宝塔面板" -key ca.key -out ca.crt
fi

openssl genrsa -out server.key 2048
openssl req -new -nodes -key server.key -subj "/C=CN/O=BTPanel/CN=BTPanel" -out server.csr
openssl x509 -req -in server.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out server.crt -days 3650 -extensions req_ext

cat ca.crt >> server.crt

openssl pkcs12 -export -out baota_root.pfx -inkey server.key -in server.crt -password pass:
if [ "$?" != "0" ]; then
	echo "生成CA根证书失败"
	exit 1
fi

mkdir -p ../../public/ssl
\cp baota_root.pfx ../../public/ssl/baota_root.pfx
\cp ca.crt ../../public/ssl/baota_root.crt
rm -f server.crt server.key server.csr

echo "生成CA根证书成功"
