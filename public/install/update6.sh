#!/bin/bash
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH
LANG=en_US.UTF-8

Btapi_Url='http://www.example.com'

if [ ! -d /www/server/panel/BTPanel ];then
	echo "============================================="
	echo "错误, 5.x不可以使用此命令升级!"
	echo "5.9平滑升级到6.0的命令：curl http://download.bt.cn/install/update_to_6.sh|bash"
	exit 0;
fi

public_file=/www/server/panel/install/public.sh
publicFileMd5=$(md5sum ${public_file} 2>/dev/null|awk '{print $1}')
md5check="f6ccaaec227577b87a22bf162004667b"
if [ "${publicFileMd5}" != "${md5check}"  ]; then
	wget -O Tpublic.sh $Btapi_Url/install/public.sh -T 20;
	publicFileMd5=$(md5sum Tpublic.sh 2>/dev/null|awk '{print $1}')
	if [ "${publicFileMd5}" == "${md5check}"  ]; then
		\cp -rpa Tpublic.sh $public_file
	fi
	rm -f Tpublic.sh
fi
. $public_file

Centos8Check=$(cat /etc/redhat-release | grep ' 8.' | grep -iE 'centos|Red Hat')
if [ "${Centos8Check}" ];then
	if [ ! -f "/usr/bin/python" ] && [ -f "/usr/bin/python3" ] && [ ! -d "/www/server/panel/pyenv" ]; then
		ln -sf /usr/bin/python3 /usr/bin/python
	fi
fi

mypip="pip"
env_path=/www/server/panel/pyenv/bin/activate
if [ -f $env_path ];then
	mypip="/www/server/panel/pyenv/bin/pip"
fi

download_Url=$NODE_URL
setup_path=/www
version=$(curl -Ss --connect-timeout 5 -m 2 $Btapi_Url/api/panel/get_version)
if [ "$version" = '' ];then
	version='7.9.2'
fi
armCheck=$(uname -m|grep arm)
if [ "${armCheck}" ];then
	version='7.7.0'
fi
wget -T 5 -O /tmp/panel.zip $Btapi_Url/install/update/LinuxPanel-${version}.zip
dsize=$(du -b /tmp/panel.zip|awk '{print $1}')
if [ $dsize -lt 10240 ];then
	echo "获取更新包失败，请稍后更新或联系宝塔运维"
	exit;
fi
unzip -o /tmp/panel.zip -d $setup_path/server/ > /dev/null
rm -f /tmp/panel.zip
cd $setup_path/server/panel/
check_bt=`cat /etc/init.d/bt`
if [ "${check_bt}" = "" ];then
	rm -f /etc/init.d/bt
	wget -O /etc/init.d/bt $download_Url/install/src/bt6.init -T 20
	chmod +x /etc/init.d/bt
fi
rm -f /www/server/panel/*.pyc
rm -f /www/server/panel/class/*.pyc
#pip install flask_sqlalchemy
#pip install itsdangerous==0.24

pip_list=$($mypip list)
request_v=$(echo "$pip_list"|grep requests)
if [ "$request_v" = "" ];then
	$mypip install requests
fi
openssl_v=$(echo "$pip_list"|grep pyOpenSSL)
if [ "$openssl_v" = "" ];then
	$mypip install pyOpenSSL
fi

#cffi_v=$(echo "$pip_list"|grep cffi|grep 1.12.)
#if [ "$cffi_v" = "" ];then
#	$mypip install cffi==1.12.3
#fi

pymysql=$(echo "$pip_list"|grep pymysql)
if [ "$pymysql" = "" ];then
	$mypip install pymysql
fi

pymysql=$(echo "$pip_list"|grep pycryptodome)
if [ "$pymysql" = "" ];then
	$mypip install pycryptodome
fi

#psutil=$(echo "$pip_list"|grep psutil|awk '{print $2}'|grep '5.7.')
#if [ "$psutil" = "" ];then
#	$mypip install -U psutil
#fi

if [ -d /www/server/panel/class/BTPanel ];then
	rm -rf /www/server/panel/class/BTPanel
fi
rm -f /www/server/panel/class/*.so
if [ ! -f /www/server/panel/data/not_workorder.pl ]; then
	echo "True" > /www/server/panel/data/not_workorder.pl
fi
if [ ! -f /www/server/panel/data/userInfo.json ]; then
	echo "{\"uid\":1,\"username\":\"Administrator\",\"address\":\"127.0.0.1\",\"serverid\":\"1\",\"access_key\":\"test\",\"secret_key\":\"123456\",\"ukey\":\"123456\",\"state\":1}" > /www/server/panel/data/userInfo.json
fi

chattr -i /etc/init.d/bt
chmod +x /etc/init.d/bt
echo "====================================="
rm -f /dev/shm/bt_sql_tips.pl
kill $(ps aux|grep -E "task.pyc|main.py"|grep -v grep|awk '{print $2}')
/etc/init.d/bt restart
echo 'True' > /www/server/panel/data/restart.pl
pkill -9 gunicorn &
echo "已成功升级到[$version]${Ver}";


