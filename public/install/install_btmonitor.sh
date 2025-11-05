#!/bin/bash
#########################

# 广东堡塔安全技术有限公司
# author: 赤井秀一
# mail: 1021266737@qq.com

#########################
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH
LANG=en_US.UTF-8

Btapi_Url='http://www.example.com'

is64bit=$(getconf LONG_BIT)
if [ "${is64bit}" != '64' ];then
    echo -e "\033[31m 抱歉, 堡塔云监控系统不支持32位系统, 请使用64位系统! \033[0m"
    exit 1
fi

S390X_CHECK=$(uname -a|grep s390x)
if [ "${S390X_CHECK}" ];then
    echo -e "\033[31m 抱歉, 堡塔云监控系统不支持s390x架构进行安装，请使用x86_64服务器架构 \033[0m"
    exit 1
fi

is_aarch64=$(uname -a|grep aarch64)
if [ "${is_aarch64}" != "" ];then
    echo -e "\033[31m 抱歉, 堡塔云监控系统暂不支持aarch64架构进行安装，请使用x86_64服务器架构 \033[0m"
    exit 1
fi

Command_Exists() {
    command -v "$@" >/dev/null 2>&1
}


GetSysInfo(){
	if [ -s "/etc/redhat-release" ];then
		SYS_VERSION=$(cat /etc/redhat-release)
	elif [ -s "/etc/issue" ]; then
		SYS_VERSION=$(cat /etc/issue)
	fi
	SYS_INFO=$(uname -a)
	SYS_BIT=$(getconf LONG_BIT)
	MEM_TOTAL=$(free -m|grep Mem|awk '{print $2}')
	CPU_INFO=$(getconf _NPROCESSORS_ONLN)

	echo -e ${SYS_VERSION}
	echo -e Bit:${SYS_BIT} Mem:${MEM_TOTAL}M Core:${CPU_INFO}
	echo -e ${SYS_INFO}
	echo -e "请截图以上报错信息发帖至论坛www.bt.cn/bbs求助"

}

Red_Error(){
	echo '=================================================';
	printf '\033[1;31;40m%b\033[0m\n' "$@";
	GetSysInfo
	exit 1;
}


monitor_path="/www/server/bt-monitor"
run_bin="/www/server/bt-monitor/BT-MONITOR"
if [ ! -d "/www/server" ];then 
    mkdir -p /www/server
fi
old_dir="/www/server/old_btmonitor"

cd ~
setup_path="/www"
python_bin=$setup_path/server/bt-monitor/pyenv/bin/python
cpu_cpunt=$(cat /proc/cpuinfo|grep processor|wc -l)

get_node_url(){
	if [ ! -f /bin/curl ];then
		if [ "${PM}" = "yum" ]; then
			yum install curl -y
		elif [ "${PM}" = "apt-get" ]; then
			apt-get install curl -y
		fi
	fi

	if [ -f "/www/node.pl" ];then
		download_Url=$(cat /www/node.pl)
		echo "Download node: $download_Url";
		echo '---------------------------------------------';
		return
	fi
	
	echo '---------------------------------------------';
	echo "Selected download node...";
	# nodes=(http://dg2.bt.cn http://dg1.bt.cn http://125.90.93.52:5880 http://36.133.1.8:5880 http://123.129.198.197 http://38.34.185.130 http://116.213.43.206:5880 http://128.1.164.196);
	#nodes=(http://dg2.bt.cn http://dg1.bt.cn http://125.90.93.52:5880 http://36.133.1.8:5880 http://123.129.198.197 http://116.213.43.206:5880 http://128.1.164.196);
	nodes=(https://dg2.bt.cn https://dg1.bt.cn https://download.bt.cn);
	tmp_file1=/dev/shm/net_test1.pl
	tmp_file2=/dev/shm/net_test2.pl
	[ -f "${tmp_file1}" ] && rm -f ${tmp_file1}
	[ -f "${tmp_file2}" ] && rm -f ${tmp_file2}
	touch $tmp_file1
	touch $tmp_file2
	for node in ${nodes[@]};
	do
		NODE_CHECK=$(curl --connect-timeout 3 -m 3 2>/dev/null -w "%{http_code} %{time_total}" ${node}/net_test|xargs)
		RES=$(echo ${NODE_CHECK}|awk '{print $1}')
		NODE_STATUS=$(echo ${NODE_CHECK}|awk '{print $2}')
		TIME_TOTAL=$(echo ${NODE_CHECK}|awk '{print $3 * 1000 - 500 }'|cut -d '.' -f 1)
		if [ "${NODE_STATUS}" == "200" ];then
			if [ $TIME_TOTAL -lt 100 ];then
				if [ $RES -ge 1500 ];then
					echo "$RES $node" >> $tmp_file1
				fi
			else
				if [ $RES -ge 1500 ];then
					echo "$TIME_TOTAL $node" >> $tmp_file2
				fi
			fi

			i=$(($i+1))
			if [ $TIME_TOTAL -lt 100 ];then
				if [ $RES -ge 3000 ];then
					break;
				fi
			fi	
		fi
	done

	NODE_URL=$(cat $tmp_file1|sort -r -g -t " " -k 1|head -n 1|awk '{print $2}')
	if [ -z "$NODE_URL" ];then
		NODE_URL=$(cat $tmp_file2|sort -g -t " " -k 1|head -n 1|awk '{print $2}')
		if [ -z "$NODE_URL" ];then
			NODE_URL='https://download.bt.cn';
		fi
	fi
	rm -f $tmp_file1
	rm -f $tmp_file2
	download_Url=$NODE_URL
	echo "Download node: $download_Url";
	echo '---------------------------------------------';
}

Get_Versions(){
	redhat_version_file="/etc/redhat-release"
	deb_version_file="/etc/issue"
	if [ -f $redhat_version_file ];then
		os_type='el'
		is_aliyunos=$(cat $redhat_version_file|grep Aliyun)
		if [ "$is_aliyunos" != "" ];then
			return
		fi
		os_version=$(cat $redhat_version_file|grep CentOS|grep -Eo '([0-9]+\.)+[0-9]+'|grep -Eo '^[0-9]')
		if [ "${os_version}" = "5" ];then
			os_version=""
		fi
		if [ -z "${os_version}" ];then
			os_version=$(cat /etc/redhat-release |grep Stream|grep -oE 8)
		fi
	else
		os_type='ubuntu'
		os_version=$(cat $deb_version_file|grep Ubuntu|grep -Eo '([0-9]+\.)+[0-9]+'|grep -Eo '^[0-9]+')
		if [ "${os_version}" = "" ];then
			os_type='debian'
			os_version=$(cat $deb_version_file|grep Debian|grep -Eo '([0-9]+\.)+[0-9]+'|grep -Eo '[0-9]+')
			if [ "${os_version}" = "" ];then
				os_version=$(cat $deb_version_file|grep Debian|grep -Eo '[0-9]+')
			fi
			if [ "${os_version}" = "8" ];then
				os_version=""
			fi
			if [ "${is64bit}" = '32' ];then
				os_version=""
			fi
		else
			if [ "$os_version" = "14" ];then
				os_version=""
			fi
			if [ "$os_version" = "12" ];then
				os_version=""
			fi
			if [ "$os_version" = "19" ];then
				os_version=""
			fi
			if [ "$os_version" = "21" ];then
				os_version=""
			fi
			if [ "$os_version" = "20" ];then
				os_version2004=$(cat /etc/issue|grep 20.04)
				if [ -z "${os_version2004}" ];then
					os_version=""
				fi
			fi
		fi
	fi
}

Install_Python_Lib(){
	curl -Ss --connect-timeout 3 -m 60 $download_Url/install/pip_select.sh|bash
	pyenv_path="/www/server/bt-monitor"
	if [ -f $pyenv_path/pyenv/bin/python ];then
	 	is_ssl=$($python_bin -c "import ssl" 2>&1|grep cannot)
		$pyenv_path/pyenv/bin/python3.7 -V
		if [ $? -eq 0 ] && [ -z "${is_ssl}" ];then
			chmod -R 700 $pyenv_path/pyenv/bin
			is_package=$($python_bin -m psutil 2>&1|grep package)
			if [ "$is_package" = "" ];then
				wget -O $pyenv_path/pyenv/pip.txt $download_Url/install/pyenv/pip.txt -t 5 -T 10
				$pyenv_path/pyenv/bin/pip install -U pip
				$pyenv_path/pyenv/bin/pip install -U setuptools
				$pyenv_path/pyenv/bin/pip install -r $pyenv_path/pyenv/pip.txt
				$pyenv_path/pyenv/bin/pip install -U flask==2.2.0
				$pyenv_path/pyenv/bin/pip install flask_sock
				$pyenv_path/pyenv/bin/pip install cachelib
				$pyenv_path/pyenv/bin/pip install py7zr
				$pyenv_path/pyenv/bin/pip install backports.lzma
				$pyenv_path/pyenv/bin/pip install pandas
				$pyenv_path/pyenv/bin/pip install msgpack
				$pyenv_path/pyenv/bin/pip install simple-websocket==0.10.0
			fi
			source $pyenv_path/pyenv/bin/activate
			chmod -R 700 $pyenv_path/pyenv/bin
			return
		else
			rm -rf $pyenv_path/pyenv
		fi
	fi

	py_version="3.7.9"
    if [ ! -d "$pyenv_path" ]; then
	    mkdir -p $pyenv_path
    fi
	echo "True" > /www/disk.pl
	if [ ! -w /www/disk.pl ];then
		Red_Error "ERROR: Install python env fielded." "ERROR: /www目录无法写入，请检查目录/用户/磁盘权限！"
	fi
	os_type='el'
	os_version='7'
	is_export_openssl=0
	Get_Versions

	echo "OS: $os_type - $os_version"
	is_aarch64=$(uname -a|grep aarch64)
	if [ "$is_aarch64" != "" ];then
		is64bit="aarch64"
	fi
	
	if [ -f "/www/server/bt-monitor/pymake.pl" ];then
		os_version=""
		rm -f /www/server/bt-monitor/pymake.pl
	fi

    if [[ $os_type =~ "debian" ]] || [[ $os_type =~ "ubuntu" ]]; then
        isbtm="-btm"
    fi

	if [ "${os_version}" != "" ];then
		pyenv_file="/www/pyenv.tar.gz"
		wget -O $pyenv_file $download_Url/install/pyenv/pyenv-${os_type}${os_version}-x${is64bit}${isbtm}.tar.gz -t 5 -T 10

		tmp_size=$(du -b $pyenv_file|awk '{print $1}')
		if [ $tmp_size -lt 703460 ];then
			rm -f $pyenv_file
			echo "ERROR: Download python env fielded."
		else
			echo "Install python env..."
			tar zxvf $pyenv_file -C $pyenv_path/ > /dev/null
			chmod -R 700 $pyenv_path/pyenv/bin
			rm -rf $pyenv_path/pyenv/bin/python
			ln -sf $pyenv_path/pyenv/bin/python3.7 $pyenv_path/pyenv/bin/python
			$pyenv_path/pyenv/bin/python -m pip install --upgrade --force-reinstall pip
			$pyenv_path/pyenv/bin/pip install -U flask==2.2.0
			$pyenv_path/pyenv/bin/pip install flask_sock
			$pyenv_path/pyenv/bin/pip install cachelib
			$pyenv_path/pyenv/bin/pip install py7zr
			$pyenv_path/pyenv/bin/pip install backports.lzma
			$pyenv_path/pyenv/bin/pip install pandas
			$pyenv_path/pyenv/bin/pip install msgpack
			$pyenv_path/pyenv/bin/pip install simple-websocket==0.10.0
			if [ ! -f $pyenv_path/pyenv/bin/python ];then
				rm -f $pyenv_file
				Red_Error "ERROR: Install python env fielded." "ERROR: 下载堡塔云监控主控端运行环境失败，请尝试重新安装！" 
			fi
			$pyenv_path/pyenv/bin/python3.7 -V
			if [ $? -eq 0 ];then
				rm -f $pyenv_file
				ln -sf $pyenv_path/pyenv/bin/pip3.7 /usr/bin/btmpip
				ln -sf $pyenv_path/pyenv/bin/python3.7 /usr/bin/btmpython
				source $pyenv_path/pyenv/bin/activate
				return
			else
				rm -f $pyenv_file
				rm -rf $pyenv_path/pyenv
			fi
		fi
	fi

	cd /www
	python_src='/www/python_src.tar.xz'
	python_src_path="/www/Python-${py_version}"
	wget -O $python_src $download_Url/src/Python-${py_version}.tar.xz -t 5 -T 10
	tmp_size=$(du -b $python_src|awk '{print $1}')
	if [ $tmp_size -lt 10703460 ];then
		rm -f $python_src
		Red_Error "ERROR: Download python source code fielded." "ERROR: 下载堡塔云监控主控端运行环境失败，请尝试重新安装！"
	fi
	tar xvf $python_src
	rm -f $python_src
	cd $python_src_path
	./configure --prefix=$pyenv_path/pyenv
	make -j$cpu_cpunt
	make install
	if [ ! -f $pyenv_path/pyenv/bin/python3.7 ];then
		rm -rf $python_src_path
		Red_Error "ERROR: Make python env fielded." "ERROR: 编译堡塔云监控主控端运行环境失败！"
	fi
	cd ~
	rm -rf $python_src_path
	wget -O $pyenv_path/pyenv/bin/activate $download_Url/install/pyenv/activate.panel -t 5 -T 10
	wget -O $pyenv_path/pyenv/pip.txt $download_Url/install/pyenv/pip-3.7.8.txt -t 5 -T 10
	ln -sf $pyenv_path/pyenv/bin/pip3.7 $pyenv_path/pyenv/bin/pip
	ln -sf $pyenv_path/pyenv/bin/python3.7 $pyenv_path/pyenv/bin/python
	ln -sf $pyenv_path/pyenv/bin/pip3.7 /usr/bin/btmpip
	ln -sf $pyenv_path/pyenv/bin/python3.7 /usr/bin/btmpython
	chmod -R 700 $pyenv_path/pyenv/bin
	$pyenv_path/pyenv/bin/pip install -U pip
	$pyenv_path/pyenv/bin/pip install -U setuptools
	$pyenv_path/pyenv/bin/pip install -U wheel==0.34.2 
	$pyenv_path/pyenv/bin/pip install -r $pyenv_path/pyenv/pip.txt
	$pyenv_path/pyenv/bin/pip install -U flask==2.2.0
	$pyenv_path/pyenv/bin/pip install flask_sock
	$pyenv_path/pyenv/bin/pip install cachelib
	$pyenv_path/pyenv/bin/pip install py7zr
	$pyenv_path/pyenv/bin/pip install backports.lzma
	$pyenv_path/pyenv/bin/pip install pandas
	$pyenv_path/pyenv/bin/pip install msgpack
	$pyenv_path/pyenv/bin/pip install simple-websocket==0.10.0
	source $pyenv_path/pyenv/bin/activate

	is_gevent=$($python_bin -m gevent 2>&1|grep -oE package)
	is_psutil=$($python_bin -m psutil 2>&1|grep -oE package)
	if [ "${is_gevent}" != "${is_psutil}" ];then
		Red_Error "ERROR: psutil/gevent install failed!"
	fi
}

Install_Monitor(){
	ulimit -n 1000001
	tee -a /etc/security/limits.conf << EOF
*               hard    nofile          1000001
*               soft    nofile          1000001
root            hard    nofile          1000001
root            soft    nofile          1000001
EOF
	sysctl -p
	panelPort="806"

	if [ ! -d "/etc/init.d" ];then
		mkdir -p /etc/init.d
	fi

	if [ -f "/etc/init.d/btm" ]; then
		/etc/init.d/btm stop
		sleep 1
	fi
	
	if [ -f "/www/server/bt-monitor/sqlite-server.sh" ]; then
		chmod +x /www/server/bt-monitor/sqlite-server.sh
		/www/server/bt-monitor/sqlite-server.sh stop
		sleep 1
	fi

    version="2.1.7"
    file_name="bt-monitor"
    agent_src="bt-monitor.zip"

	cd ~
	version=`curl -sf ${Btapi_Url}/bt_monitor/latest_version |awk -F '\"version\"' '{print $2}'|awk -F ':' '{print $2}'|awk -F '"' '{print $2}'`
	if [ -z $version ]; then
		version="2.0.6"
	fi
	if [ "$re_install" == "1" ]; then
		new_dir="/www/server/new_btmonitor"
		if [ ! -d "$new_dir" ];then
			mkdir -p $new_dir
		fi
		wget -O $agent_src ${Btapi_Url}/install/src/$file_name-$version.zip -t 5 -T 10
		unzip -o $agent_src -d $new_dir/ > /dev/null
		if [ ! -f $new_dir/BT-MONITOR ];then
			ls -lh $agent_src
			Red_Error "ERROR: Failed to download, please try install again!" "ERROR: 下载堡塔云监控主控端失败，请尝试重新安装！"
		fi

		rm -rf $new_dir/config
		rm -rf $new_dir/data
		rm -rf $new_dir/ssl
		\cp -r $new_dir/* $monitor_path/
		rm -rf $new_dir
	else
		wget -O $agent_src ${Btapi_Url}/install/src/$file_name-$version.zip -t 5 -T 10
		if [ ! -d "$monitor_path" ]; then
			mkdir -p $monitor_path
		fi
		unzip -o $agent_src -d $monitor_path/ > /dev/null
		if [ ! -f $run_bin ];then
			ls -lh $agent_src
			Red_Error "ERROR: Failed to download, please try install again!" "ERROR: 下载堡塔云监控主控端失败，请尝试重新安装！"
		fi
	fi
	rm -rf $agent_src
	chmod +x $monitor_path/BT-MONITOR
	chmod +x $monitor_path/tools.py
	wget -O /etc/init.d/btm ${download_Url}/init/btmonitor.init -t 5 -T 10
	tmp_size=$(du -b "/etc/init.d/btm"|awk '{print $1}')
	if [ ${tmp_size} == 0 ]; then
		\cp -r $monitor_path/init.sh /etc/init.d/btm
	fi
	if [ ! -f "/etc/init.d/btm" ];then
		\cp -r $monitor_path/init.sh /etc/init.d/btm
	fi

	chmod +x /etc/init.d/btm
	ln -sf /etc/init.d/btm /usr/bin/btm

	if [ ! -f $monitor_path/data/user.json ]; then
		echo "{\"uid\":1,\"username\":\"Administrator\",\"ip\":\"127.0.0.1\",\"server_id\":\"1\",\"access_key\":\"test\",\"secret_key\":\"123456\"}" > $monitor_path/data/user.json
	fi
	if [ -f $monitor_path/core/include/c_loader/PluginLoader.so ]; then
		rm -f $monitor_path/core/include/c_loader/PluginLoader.so
	fi
	if [ -f $monitor_path/sqlite_server/PluginLoader.so ]; then
		rm -f $monitor_path/sqlite_server/PluginLoader.so
	fi
	if [ -f $monitor_path/hook_import/PluginLoader.so ]; then
		rm -f $monitor_path/hook_import/PluginLoader.so
	fi
}

Start_Monitor(){
    /etc/init.d/btm start
    if [ "$?" != "0" ]; then
        #echo "堡塔云监控主控端启动失败！"
		tail $monitor_path/logs/error.log
        Red_Error "堡塔云监控主控端启动失败！"
    fi

	echo "正在初始化云监控主控端..."
	if [ "$re_install" == "1" ] || [ "$re_install" == "2" ]; then
		user_pass=`$setup_path/server/bt-monitor/tools.py reset_pwd`
		password=`echo $user_pass |awk '{print $3}'`
	else
		user_pass=`$monitor_path/tools.py create_admin`
		password=`echo $user_pass |awk -F " " '{print $5}'`
		for ((i=1; i<=5; i++));do
			if [ -z "$password" ]; then
				sleep 7
				rm -f /tmp/bt_monitor.lock
				user_pass=`$monitor_path/tools.py create_admin`
				password=`echo $user_pass |awk -F " " '{print $5}'`
			else
				i=5
			fi
		done
	fi
	if [[ "$password" == "" ]];then
		Red_Error "ERROR: 初始化云监控主控端失败，请尝试重新安装！"
	fi

	c_path=$(cat /www/server/bt-monitor/config/config.json |awk -F '\"admin_path\"' '{print $2}'|awk -F ":" '{print $2}'|awk -F '"' '{print $2}')
	adminpath=$(echo $c_path|awk -F ',' '{print $1}')

	if [ -d "/usr/bin/btmonitoragent" ];then
		rm -rf /usr/bin/btmonitoragent
	fi

	date_f=`date '+%Y%m%d_%H%M%S'`
	md5_pl=`echo $date_f | md5sum | head -c 32`
	token_pl=`cat $monitor_path/config/token.pl 2>&1`
	if [ "$token_pl" == ' ' ] || [ ! -f $monitor_path/config/token.pl ]; then
		echo "$md5_pl" > $monitor_path/config/token.pl
	fi

	echo "正在给本机安装云监控被控端,请等待..."
	sleep 15
	curl -sSO ${download_Url}/install/btmonitoragent.sh && bash btmonitoragent.sh https://127.0.0.1:806 $md5_pl
	target_dir="/usr/local/btmonitoragent"
	if [ ! -f "$target_dir/BT-MonitorAgent" ];then
		tail -n 10 ${monitor_path}/logs/error.log
		echo ""
		ps aux|grep -v grep|grep ${monitor_path}
		netstat -tulnp|grep ${panelPort}
		/etc/init.d/btm restart
		if [ "$?" -eq 0 ]; then
			echo -e "\033[31m安装云监控被控端失败，正在尝试重新安装！\033[0m"
			sleep 15
			curl -sSO ${download_Url}/install/btmonitoragent.sh && bash btmonitoragent.sh https://127.0.0.1:806 $md5_pl
			if [ ! -f "$target_dir/BT-MonitorAgent" ];then
				Red_Error "ERROR: 安装云监控被控端失败，请尝试重新安装！"
			fi
		else
			Red_Error "ERROR: 安装云监控被控端失败，请尝试重新安装！"
		fi
	fi
	/etc/init.d/btm restart > /dev/null 2>&1

}

Set_Firewall(){
	sshPort=$(cat /etc/ssh/sshd_config | grep 'Port '|awk '{print $2}')
	if [ "${PM}" = "apt-get" ]; then
		apt-get install -y ufw
		if [ -f "/usr/sbin/ufw" ];then
			ufw allow 22/tcp
			ufw allow ${panelPort}/tcp
			ufw allow ${sshPort}/tcp
			ufw_status=`ufw status`
			echo y|ufw enable
			ufw default deny
			ufw reload
		fi
	else
		if [ -f "/etc/init.d/iptables" ];then
			iptables -I INPUT -p tcp -m state --state NEW -m tcp --dport 22 -j ACCEPT
			iptables -I INPUT -p tcp -m state --state NEW -m tcp --dport ${panelPort} -j ACCEPT
			iptables -I INPUT -p tcp -m state --state NEW -m tcp --dport ${sshPort} -j ACCEPT
			iptables -A INPUT -p icmp --icmp-type any -j ACCEPT
			iptables -A INPUT -s localhost -d localhost -j ACCEPT
			iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
			iptables -P INPUT DROP
			service iptables save
			sed -i "s#IPTABLES_MODULES=\"\"#IPTABLES_MODULES=\"ip_conntrack_netbios_ns ip_conntrack_ftp ip_nat_ftp\"#" /etc/sysconfig/iptables-config
			iptables_status=$(service iptables status | grep 'not running')
			if [ "${iptables_status}" == '' ];then
				service iptables restart
			fi
		else
			AliyunCheck=$(cat /etc/redhat-release|grep "Aliyun Linux")
			[ "${AliyunCheck}" ] && return
			yum install firewalld -y
			[ "${Centos8Check}" ] && yum reinstall python3-six -y
			systemctl enable firewalld
			systemctl start firewalld
			firewall-cmd --set-default-zone=public > /dev/null 2>&1
			firewall-cmd --permanent --zone=public --add-port=22/tcp > /dev/null 2>&1
			firewall-cmd --permanent --zone=public --add-port=${panelPort}/tcp > /dev/null 2>&1
			firewall-cmd --permanent --zone=public --add-port=${sshPort}/tcp > /dev/null 2>&1
			firewall-cmd --reload
		fi
	fi
}

Service_Add(){
    if [ $Command_Exists systemctl ]; then
        wget -O /usr/lib/systemd/system/btm.service ${download_Url}/init/systemd/btmonitor.service -t 5 -T 10
		systemctl daemon-reload
        systemctl enable btm
    else
        if [ "${PM}" == "yum" ] || [ "${PM}" == "dnf" ]; then
            chkconfig --add btm
            chkconfig --level 2345 btm on            
        elif [ "${PM}" == "apt-get" ]; then
            update-rc.d btm defaults
        fi
    fi
}

Service_Del(){
    if [ $Command_Exists systemctl ]; then
        rm -rf /usr/lib/systemd/system/btm.service
        systemctl disable btm
    else
        if [ "${PM}" == "yum" ] || [ "${PM}" == "dnf" ]; then
            chkconfig --del btm
            chkconfig --level 2345 btm off
        elif [ "${PM}" == "apt-get" ]; then
            update-rc.d btm remove
        fi
    fi
}

Get_Ip_Address(){
	getIpAddress=""
	getIpAddress=$(curl -sS --connect-timeout 10 -m 60 https://www.bt.cn/Api/getIpAddress)
	if [ -z "${getIpAddress}" ] || [ "${getIpAddress}" = "0.0.0.0" ]; then
		isHosts=$(cat /etc/hosts|grep 'www.bt.cn')
		if [ -z "${isHosts}" ];then
			echo "" >> /etc/hosts
			echo "116.213.43.206 www.bt.cn" >> /etc/hosts
			getIpAddress=$(curl -sS --connect-timeout 10 -m 60 https://www.bt.cn/Api/getIpAddress)
			if [ -z "${getIpAddress}" ];then
				sed -i "/bt.cn/d" /etc/hosts
			fi
		fi
	fi

	ipv4Check=$($python_bin -c "import re; print(re.match('^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$','${getIpAddress}'))")
	if [ "${ipv4Check}" == "None" ];then
		ipv6Address=$(echo ${getIpAddress}|tr -d "[]")
		ipv6Check=$($python_bin -c "import re; print(re.match('^([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]{0,4}$','${ipv6Address}'))")
		if [ "${ipv6Check}" == "None" ]; then
			getIpAddress="SERVER_IP"
		else
			echo "True" > ${setup_path}/server/bt-monitor/data/ipv6.pl
			sleep 1
			/etc/init.d/btm restart
		fi
	fi

	if [ "${getIpAddress}" != "SERVER_IP" ];then
		echo "${getIpAddress}" > ${setup_path}/server/bt-monitor/data/iplist.txt
	fi
	LOCAL_IP=$(ip addr | grep -E -o '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}' | grep -E -v "^127\.|^255\.|^0\." | head -n 1)
}

System_Check(){
	if [ -f "$monitor_path/BT-MONITOR" ] || [ -f "$monitor_path/tools.py" ] || [ -f "/etc/init.d/btm" ];then
		Install_Check
	elif [ -d "$old_dir" ];then
		Rev_Install_Check
	fi
}

Install_Check(){
	echo -e "----------------------------------------------------"
	echo -e "检测到已存在堡塔云监控系统，请按照选项选择安装方式!"
	echo -e "1) 覆盖安装：保存原有监控配置及数据并安装堡塔云监控"
	echo -e "\033[33m2) 全新安装：清空原有监控配置及数据并安装堡塔云监控\033[0m"
	echo -e "----------------------------------------------------"
	read -p "请输入对应选项[1|2]进行安装或输入任意内容退出安装: " yes;
	if [ "$yes" == "1" ]; then
		re_install="1"
		echo "即将卸载并重装本机的堡塔云监控被控端..."
		Uninstall_agent
	elif [ "$yes" == "2" ]; then
		Backup_Monitor
		echo "即将卸载并重装本机的堡塔云监控被控端..."
		Uninstall_agent
	else
		echo -e "------------"
		echo "取消安装"
		exit;
	fi
}

Rev_Install_Check(){
	echo -e "----------------------------------------------------"
	echo -e "\033[33m检测到上一次卸载云监控时保留的旧数据,请按照选项选择安装方式!\033[0m"
	echo -e "1) 还原以前的备份并安装堡塔云监控系统!"
	echo -e "2) 不使用原有备份,全新安装堡塔云监控系统!"
	echo -e "----------------------------------------------------"
	read -p "请输入对应选项[1|2]进行安装或输入任意内容退出安装: " yes;
	if [ "$yes" == "1" ]; then
		re_install="2"
		echo "开始安装堡塔云监控系统并还原数据..."
	elif [ "$yes" == "2" ]; then
		echo "开始全新安装堡塔云监控系统..."
	else
		echo -e "------------"
		echo "取消安装"
		exit;
	fi
}

Backup_Monitor(){
	if [ -f "/etc/init.d/btm" ]; then
		/etc/init.d/btm stop
		sleep 1
	fi
	if [ ! -d "${old_dir}" ];then
		mkdir -p ${old_dir}
	else
		mv ${old_dir} ${old_dir}_$(date +%Y_%m_%d_%H_%M_%S)
		mkdir -p ${old_dir}
	fi
	
	mv ${monitor_path}/data ${old_dir}/data
	mv ${monitor_path}/config ${old_dir}/config
	mv ${monitor_path}/ssl ${old_dir}/ssl
}

Reinstall_Monitor(){
	rm -rf $monitor_path/data
	rm -rf $monitor_path/config
	rm -rf $monitor_path/ssl
	mv $old_dir/data $monitor_path/data
	mv $old_dir/config $monitor_path/config
	mv $old_dir/ssl $monitor_path/ssl
	rm -rf $old_dir
}

Get_Pack_Manager(){
	if [ -f "/usr/bin/yum" ] && [ -d "/etc/yum.repos.d" ]; then
		PM="yum"
	elif [ -f "/usr/bin/apt-get" ] && [ -f "/usr/bin/dpkg" ]; then
		PM="apt-get"		
	fi
}

Install_RPM_Pack(){
	yumPacks="wget curl unzip gcc gcc-c++ make libcurl-devel openssl-devel xz-devel python-backports-lzma xz crontabs zlib zlib-devel sqlite-devel libffi-devel bzip2-devel lsof net-tools p7zip-full"
	yum install -y ${yumPacks}

	for yumPack in ${yumPacks}
	do
		rpmPack=$(rpm -q ${yumPack})
		packCheck=$(echo ${rpmPack}|grep not)
		if [ "${packCheck}" ]; then
			yum install ${yumPack} -y
		fi
	done
}

Install_Deb_Pack(){
    debPacks="wget curl unzip gcc g++ make cron libcurl4-openssl-dev libssl-dev liblzma-dev xz-utils libffi-dev libbz2-dev libsqlite3-dev libreadline-dev libgdbm-dev python3-bsddb3 tk-dev ncurses-dev uuid-dev zlib1g zlib1g-dev lsof net-tools p7zip-full sqlite3";
	apt-get update -y
	apt-get install -y $debPacks --force-yes

	for debPack in ${debPacks}
	do
		packCheck=$(dpkg -l ${debPack})
		if [ "$?" -ne "0" ] ;then
			apt-get install -y $debPack
		fi
	done
}

Check_Sys_Write(){
    echo "正在检测系统关键目录是否可写"
    if [ ! -d "/etc/init.d" ];then
        mkdir -p /etc/init.d
	fi
	
    Get_Pack_Manager
    if [ "$PM" == "yum" ]; then
        read_dir="/usr/lib/systemd/system/ /etc/init.d/ /var/spool/cron/"
    else
        read_dir="/usr/lib/systemd/system/ /etc/init.d/ /var/spool/cron/crontabs/"
    fi
    
    touch /tmp/btm_install_test_111.pl
    for dir in ${read_dir[@]}
    do
        if [[ -d "$dir" ]]; then
            #touch $dir/btm_install_test_111.pl
            if [[ ! -f "/tmp/btm_install_test_111.pl" ]]; then
                echo "建立测试 /tmp/btm_install_test_111.pl 文件失败"
                state=0
            else
                \cp /tmp/btm_install_test_111.pl $dir/btm_install_test_111.pl
            fi
            state=$(echo $?)
            if [[ "$state" != "0" ]];then
                echo -e "\033[31m错误：检测到系统关键目录不可写! $read_dir \033[0m"
                echo "1、如果安装了[宝塔系统加固]，请先临时关闭"
                echo "2、如果安装了云锁，请临时关闭[系统加固、文件防护]功能"
                echo "3、如果安装了安全狗，请临时关闭[系统防护]功能"
                echo "4、如果使用了其它安全软件，请先卸载 "
                echo -e "5、如果使用了禁止写入命令，请执行命令取消禁止写入：\n   chattr -iaR $read_dir "

                if [ $(whoami) != "root" ];then
                    echo -e "6、检测到非root用户安装，请尝试以下解决方案：\n   1.请切换到root用户安装 \n   2.尝试执行以下安装命令：\n     sudo bash $0 $@"
                fi

                echo ""
                echo -e "\033[31m解决以上问题后，请尝试重新安装！ \033[0m"
                echo -e "如果无法解决请截图以上报错信息发帖至论坛www.bt.cn/bbs求助"
                exit 1
            else
                rm -f $dir/btm_install_test_111.pl
            fi
        fi
    done
}

Check_Sys_Packs(){
    echo "正在检查系统中是否存在必备的依赖包"
    Packs="wget curl unzip gcc make"
    if [ -f /usr/bin/which ];then
        for pack in ${Packs[@]}
        do
            check_pack=$(which $pack)
            #echo $check_pack
            if [[ "$check_pack" == "" ]]; then
                echo -e "\033[31mERROR: $pack 命令不存在，尝试以下解决方法：\033[0m"
                if [ "$PM" == "yum" ]; then
                    echo 1、使用命令重新安装依赖包：yum reinstall -y ${Packs}
                else
                    echo 1、使用命令重新安装依赖包：apt-get reinstall -y ${Packs}
                fi
                echo -e "2、检查系统源是否可用？尝试更换可用的源参考教程：\n   https://www.bt.cn/bbs/thread-58005-1-1.html "
                echo ""
                echo -e "\033[31m解决以上问题后，请尝试重新安装！ \033[0m"
                echo -e "如果无法解决请截图以上报错信息发帖至论坛www.bt.cn/bbs求助"
                exit 1
            fi
        done
    fi
}

Install_Main(){
	startTime=`date +%s`
	Check_Sys_Write "$@"
	System_Check
	Get_Pack_Manager
	get_node_url
	
	if [ "$PM" == "yum" ]; then
		Install_RPM_Pack
	else
		Install_Deb_Pack
	fi
	Check_Sys_Packs
    Install_Python_Lib
    Install_Monitor
    Set_Firewall
	Get_Ip_Address
    Service_Add
	if [ "$re_install" == "2" ]; then
		Reinstall_Monitor
	fi
	Start_Monitor
}

Uninstall_Monitor(){
	pkill BT-MONITOR
	/etc/init.d/btm stop
	
	if [ -f "/www/server/bt-monitor/sqlite-server.sh" ]; then
		chmod +x /www/server/bt-monitor/sqlite-server.sh
		/www/server/bt-monitor/sqlite-server.sh stop
		sleep 1
	fi

	Service_Del

	rm -rf $monitor_path
	rm -rf /usr/bin/btm
	rm -rf /etc/init.d/btm

	echo -e "堡塔云监控主控端卸载成功!"
}

Uninstall_agent(){
	get_node_url
	if [ -f "/tmp/btmonitoragent.sh" ];then
		rm -rf /tmp/btmonitoragent.sh
	fi
	curl -o /tmp/btmonitoragent.sh -sSO ${download_Url}/install/btmonitoragent.sh && bash /tmp/btmonitoragent.sh uninstall
}

action="${1}"
if [ "$action" == "uninstall" ];then
	echo -e "----------------------------------------------------"
	echo -e "\033[33m检测到您正在卸载堡塔云监控系统,请按照选项选择卸载方式!\033[0m"
	echo -e "1) 备份数据后卸载：保存原有监控配置及数据并卸载堡塔云监控系统"
	echo -e "2) 完全卸载：清空原有监控配置及数据并卸载堡塔云监控系统"
	echo -e "----------------------------------------------------"
	read -p "请输入对应选项[1|2]进行卸载或输入任意内容退出卸载: " yes;
	if [ "$yes" == "1" ]; then
		Backup_Monitor
		echo -e "----------------------------------------------------"
		echo -e "\033[33m已备份原有监控数据至: ${old_dir}\033[0m"
	elif [ "$yes" == "2" ]; then
		echo "正在清空堡塔云监控系统数据..."
	else
		echo -e "------------"
		echo "取消卸载"
		exit;
	fi
	Uninstall_agent
	Uninstall_Monitor
	exit 0
else
echo "
+----------------------------------------------------------------------
| Bt-Monitor FOR CentOS/Ubuntu/Debian
+----------------------------------------------------------------------
| Copyright © 2015-2099 BT-SOFT(https://www.bt.cn) All rights reserved.
+----------------------------------------------------------------------
| The Monitor URL will be https://SERVER_IP:806 when installed.
+----------------------------------------------------------------------
"
while [ "$go" != 'y' ] && [ "$go" != 'n' ]
do
	read -p "Do you want to install Bt-Monitor to the $setup_path directory now?(y/n): " go;
done

if [ "$go" == 'n' ];then
  exit;
fi
	Install_Main "$@"
	#curl -o /dev/null -fsSL --connect-time 10 "https://api.bt.cn/bt_monitor/setup_count?cloud_type=1&token=$md5_pl&src_code=$1"
	#curl -o /dev/null -fsSL --connect-time 10 "https://api.bt.cn/bt_monitor/setup_count?cloud_type=1&token=$md5_pl&src_code=$1&status=1"

fi
echo -e "=================================================================="
echo -e "\033[32m堡塔云监控主控端安装完成! Installed successfully!\033[0m"
echo -e "=================================================================="
echo  "外网访问地址: https://${getIpAddress}:${panelPort}${adminpath}"
echo  "内网访问地址: https://${LOCAL_IP}:${panelPort}${adminpath}"
echo -e "username: admin"
echo -e "password: $password"
echo -e "\033[33mIf you cannot access the Monitor,\033[0m"
echo -e "\033[33mrelease the following Monitor port [${panelPort}] in the security group\033[0m"
echo -e "\033[33m若无法访问堡塔云监控主控端，请检查防火墙/安全组是否有放行[${panelPort}]端口\033[0m"
echo -e "=================================================================="

endTime=`date +%s`
((outTime=($endTime-$startTime)/60))
echo -e "Time consumed:\033[32m $outTime \033[0mMinute!"
rm -f install_btmonitor.sh