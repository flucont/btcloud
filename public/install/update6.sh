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

if [ ! -f "/www/server/panel/pyenv/bin/python3" ];then
	echo "============================================="
	echo "错误, 当前面板过旧/py-2.7/无pyenv环境，无法升级至最新版面板"
	echo "请截图发帖至论坛www.bt.cn/bbs求助"
	exit 0;
fi

if [ -f "/etc/redhat-release" ];then
	Centos6Check=$(cat /etc/redhat-release | grep ' 6.' | grep -iE 'centos|Red Hat')
	if [ "${Centos6Check}" ];then
		echo "Centos6不支持升级宝塔面板，建议备份数据重装更换Centos7/8安装宝塔面板"
		exit 1
	fi 
	Centos8Check=$(cat /etc/redhat-release | grep ' 8.' | grep -iE 'centos|Red Hat')
	if [ "${Centos8Check}" ];then
		if [ ! -f "/usr/bin/python" ] && [ -f "/usr/bin/python3" ] && [ ! -d "/www/server/panel/pyenv" ]; then
			ln -sf /usr/bin/python3 /usr/bin/python
		fi
	fi
fi

mypip="pip"
env_path=/www/server/panel/pyenv/bin/activate
if [ -f $env_path ];then
	mypip="/www/server/panel/pyenv/bin/pip"
fi

if [ -f "/www/server/panel/data/down_url.pl" ];then
	D_NODE_URL=$(cat /www/server/panel/data/down_url.pl|grep bt.cn)
fi

if [ -z "${D_NODE_URL}" ];then
	D_NODE_URL="download.bt.cn"
fi

download_Url=$D_NODE_URL


Set_Centos7_Repo(){
    if [ -f "/etc/yum.repos.d/docker-ce.repo" ];then
        mv /etc/yum.repos.d/docker-ce.repo /etc/yum.repos.d/docker-ce.repo_backup
    fi
	MIRROR_CHECK=$(cat /etc/yum.repos.d/CentOS-Base.repo |grep "[^#]mirror.centos.org")
	if [ "${MIRROR_CHECK}" ] && [ "${is64bit}" == "64" ];then
		\cp -rpa /etc/yum.repos.d/ /etc/yumBak
		sed -i 's/mirrorlist/#mirrorlist/g' /etc/yum.repos.d/CentOS-*.repo
		sed -i 's|#baseurl=http://mirror.centos.org|baseurl=http://vault.epel.cloud|g' /etc/yum.repos.d/CentOS-*.repo
	fi

	TSU_MIRROR_CHECK=$(cat /etc/yum.repos.d/CentOS-Base.repo |grep "tuna.tsinghua.edu.cn")
	if [ "${TSU_MIRROR_CHECK}" ];then
		\cp -rpa /etc/yum.repos.d/ /etc/yumBak
		sed -i 's/mirrorlist/#mirrorlist/g' /etc/yum.repos.d/CentOS-*.repo
		sed -i 's|#baseurl=https://mirrors.tuna.tsinghua.edu.cn|baseurl=http://vault.epel.cloud|g' /etc/yum.repos.d/CentOS-*.repo
		sed -i 's|#baseurl=http://mirrors.tuna.tsinghua.edu.cn|baseurl=http://vault.epel.cloud|g' /etc/yum.repos.d/CentOS-*.repo
	fi
	
	ALI_CLOUD_CHECK=$(grep Alibaba /etc/motd)
	Tencent_Cloud=$(cat /etc/hostname |grep -E VM-[0-9]+-[0-9]+)
	if [ "${ALI_CLOUD_CHECK}" ] || [ "${Tencent_Cloud}" ];then
		return
	fi
	
	yum install tree -y
	if [ "$?" != "0" ] ;then
		TAR_CHECK=$(which tree)
		if [ "$?" == "0" ] ;then
			\cp -rpa /etc/yum.repos.d/ /etc/yumBak
			if [ -z "${download_Url}" ];then
				download_Url="http://download.bt.cn"
			fi
			curl -Ss --connect-timeout 5 -m 60 -O ${download_Url}/src/el7repo.tar.gz
			rm -f /etc/yum.repos.d/*.repo
			tar -xvzf el7repo.tar.gz -C /etc/yum.repos.d/
		fi
	fi

	yum install tree -y
	if [ "$?" != "0" ] ;then
		sed -i "s/vault.epel.cloud/mirrors.cloud.tencent.com/g" /etc/yum.repos.d/*.repo
	fi
}
if [ -f "/etc/redhat-release" ];then
	Centos7Check=$(cat /etc/redhat-release | grep ' 7.' | grep -iE 'centos|Red Hat')
	if [ "${Centos7Check}" ];then
		Set_Centos7_Repo
	fi
fi
setup_path=/www
version=$(curl -Ss --connect-timeout 5 -m 2 $Btapi_Url/api/panel/get_version)
if [ -z "$version" ];then
	version='9.5.0'
fi
armCheck=$(uname -m|grep arm)
if [ "${armCheck}" ];then
	version='7.7.0'
fi

if [ "$1" ];then
	version=$1
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
	wget -O /etc/init.d/bt $download_Url/install/src/bt7.init -T 20
	chmod +x /etc/init.d/bt
fi
echo "=============================================================="
echo "正在修复面板依赖问题"
rm -f /www/server/panel/*.pyc
rm -f /www/server/panel/class/*.pyc
#pip install flask_sqlalchemy
#pip install itsdangerous==0.24

if [ -f "/www/server/panel/pyenv/bin/pip3" ];then
	btpip_path="/www/server/panel/pyenv/bin/pip3"
	FlaskV=$($btpip_path list 2>/dev/null|grep "Flask " |awk '{print $2}'|cut -f 1 -d .)
	piplist_count=$($btpip_path list 2>/dev/null|wc -l)
	if [ "${FlaskV}" -le "2" ] && [ "${piplist_count}" -le "118" ];then
		echo "检测到面板运行环境过老，正常尝试修复面板依赖"
		pyenv_path="/www/server/panel"
		wget -O $pyenv_path/pyenv/pip.txt $download_Url/install/pyenv/pip-3.7.16.txt -T 5
		$pyenv_path/pyenv/bin/pip install -U pip
		$pyenv_path/pyenv/bin/pip install -U setuptools==65.5.0
		$pyenv_path/pyenv/bin/pip install -U wheel==0.34.2 
		$pyenv_path/pyenv/bin/pip install -r $pyenv_path/pyenv/pip.txt
		echo "依赖修复完成，如面板仍无法正常访问，请联系宝塔官方人员进行求助"
	fi

	#wget -O pip-packs.txt $download_Url/install/pyenv/pip-packs.txt
	#PIP_PACKS=$(cat pip-packs.txt)
	#for P_PACK in ${PIP_PACKS};
	#do
	#	btpip show ${P_PACK} > /dev/null 2>&1
	#	if [ "$?" == "1" ];then
	#		btpip install ${P_PACK}
	#	fi 
	#done
fi


pip_list=$($mypip list 2>&1)
request_v=$(btpip list 2>/dev/null|grep "requests "|awk '{print $2}'|cut -d '.' -f 2)
if [ "$request_v" = "" ] || [ "${request_v}" -gt "28" ];then
	$mypip install requests==2.27.1
	$mypip install chardet==4.0.0
fi

NATSORT_C=$(echo $pip_list|grep natsort)
if [ -z "${NATSORT_C}" ];then
	btpip install natsort
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
GEVENT_V=$(btpip list 2> /dev/null|grep "gevent "|awk '{print $2}'|cut -f 1 -d '.')
if [ "${GEVENT_V}" -le "1" ];then
    /www/server/panel/pyenv/bin/pip3 install -I gevent
fi

BROTLI_C=$(btpip list 2> /dev/null |grep Brotli)
if [ -z "$BROTLI_C" ]; then
    btpip install brotli
fi

PYMYSQL_C=$(btpip list 2> /dev/null |grep PyMySQL)
if [ -z "$PYMYSQL_C" ]; then
    btpip install PyMySQL
fi


PY_CRPYT=$(btpip list 2> /dev/null |grep cryptography|awk '{print $2}'|cut -f 1 -d '.')
if [ "${PY_CRPYT}" -le "10" ];then
    btpip install pyOpenSSL==24.1.0
    btpip install cryptography==42.0.5
fi

PYMYSQL_SSL_CHECK=$(btpython -c "import pymysql" 2>&1|grep "AttributeError: module 'cryptography.hazmat.bindings._rust.openssl'")
if [ "${PYMYSQL_SSL_CHECK}" ];then
    btpip uninstall pyopenssl cryptography -y
    btpip install pyopenssl cryptography
fi

btpip uninstall enum34 -y

GEOIP_C=$(echo $pip_list|grep geoip2)
if [ -z "${GEOIP_C}" ];then
	btpip install geoip2==4.7.0
fi

# PANDAS_C=$(echo $pip_list|grep pandas)
# if [ -z "${PANDAS_C}" ];then
# 	btpip install pandas
# fi

pymysql=$(echo "$pip_list"|grep pycryptodome)
if [ "$pymysql" = "" ];then
	$mypip install pycryptodome
fi

echo "修复面板依赖完成！"
echo "==========================================="

RE_UPDATE=$(cat /www/server/panel/data/db/update)
if [ "$RE_UPDATE" -ge "4" ];then
    echo "2" > /www/server/panel/data/db/update
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
	echo "{\"uid\":1,\"username\":\"Administrator\",\"address\":\"127.0.0.1\",\"access_key\":\"test\",\"secret_key\":\"123456\",\"ukey\":\"123456\",\"state\":1}" > /www/server/panel/data/userInfo.json
fi
if [ ! -f /www/server/panel/data/panel_nps.pl ]; then
	echo "" > /www/server/panel/data/panel_nps.pl
fi
if [ ! -f /www/server/panel/data/btwaf_nps.pl ]; then
	echo "" > /www/server/panel/data/btwaf_nps.pl
fi
if [ ! -f /www/server/panel/data/tamper_proof_nps.pl ]; then
	echo "" > /www/server/panel/data/tamper_proof_nps.pl
fi
if [ ! -f /www/server/panel/data/total_nps.pl ]; then
	echo "" > /www/server/panel/data/total_nps.pl
fi

echo "==========================================="
echo "正在更新面板文件..............."
sleep 1
echo "更新完成！"
echo "==========================================="

chattr -i /etc/init.d/bt
chmod +x /etc/init.d/bt
echo "====================================="
rm -f /dev/shm/bt_sql_tips.pl
kill $(ps aux|grep -E "task.pyc|main.py"|grep -v grep|awk '{print $2}')
/etc/init.d/bt restart
echo 'True' > /www/server/panel/data/restart.pl
pkill -9 gunicorn &
echo "已成功升级到[$version]${Ver}";


