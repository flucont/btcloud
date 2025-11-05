#!/bin/bash
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH
LANG=en_US.UTF-8

Btapi_Url='http://www.example.com'

monitor_path="/www/server/bt-monitor"
run_bin="/www/server/bt-monitor/BT-MONITOR"
is64bit=$(getconf LONG_BIT)

if [ ! -d $monitor_path ]; then
	echo "没有安装云监控,请执行下面的命令安装堡塔云监控!"
	echo "curl -sSO ${Btapi_Url}/install/install_btmonitor.sh && bash install_btmonitor.sh"
	exit 1
fi

cd ~
setup_path="/www"

if [ -f "/etc/init.d/btm" ]; then
	/etc/init.d/btm stop
	sleep 1
fi

if [ -f "/www/server/bt-monitor/sqlite-server.sh" ]; then
	chmod +x /www/server/bt-monitor/sqlite-server.sh
	/www/server/bt-monitor/sqlite-server.sh stop
	sleep 1
fi

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
	#nodes=(http://dg2.bt.cn http://dg1.bt.cn http://125.90.93.52:5880 http://36.133.1.8:5880 http://123.129.198.197 http://116.213.43.206:5880);
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
			if [ ! -f $pyenv_path/pyenv/bin/python ];then
				rm -f $pyenv_file
				Red_Error "ERROR: Install python env fielded." "ERROR: 下载堡塔云监控运行环境失败，请尝试重新安装！" 
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
		Red_Error "ERROR: Download python source code fielded." "ERROR: 下载堡塔云监控运行环境失败，请尝试重新安装！"
	fi
	tar xvf $python_src
	rm -f $python_src
	cd $python_src_path
	./configure --prefix=$pyenv_path/pyenv
	make -j$cpu_cpunt
	make install
	if [ ! -f $pyenv_path/pyenv/bin/python3.7 ];then
		rm -rf $python_src_path
		Red_Error "ERROR: Make python env fielded." "ERROR: 编译堡塔云监控运行环境失败！"
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
	source $pyenv_path/pyenv/bin/activate

	is_gevent=$($python_bin -m gevent 2>&1|grep -oE package)
	is_psutil=$($python_bin -m psutil 2>&1|grep -oE package)
	if [ "${is_gevent}" != "${is_psutil}" ];then
		Red_Error "ERROR: psutil/gevent install failed!"
	fi
}

Install_Monitor(){
    version="2.3.0"
    file_name="bt-monitor"
    agent_src="bt-monitor.zip"

	cd ~
	version=`curl -sf ${Btapi_Url}/bt_monitor/latest_version |awk -F '\"version\"' '{print $2}'|awk -F ':' '{print $2}'|awk -F '"' '{print $2}'`
	if [ -z $version ]; then
		version="2.3.0"
	fi
	new_dir="/www/server/new_btmonitor"
	if [ ! -d "$new_dir" ];then
		mkdir -p $new_dir
	fi
	if [ ! -z "$action" ]; then
		# 例如：sh update_btmonitor.sh /root/demo.zip
		if [[ "$action" =~ "zip" ]]; then
			version="指定版本"
			unzip -o $action -d $new_dir/
		else
			wget -O $agent_src ${Btapi_Url}/install/src/$file_name-$version.zip -t 5 -T 10
			unzip -o $agent_src -d $new_dir/ > /dev/null
		fi
	else
    	wget -O $agent_src ${Btapi_Url}/install/src/$file_name-$version.zip -t 5 -T 10
		unzip -o $agent_src -d $new_dir/ > /dev/null
	fi
	if [ ! -f $new_dir/BT-MONITOR ];then
		ls -lh $agent_src
		Red_Error "ERROR: Failed to download, please try install again!" "ERROR: 下载堡塔云监控失败，请尝试重新安装！"
	fi

	rm -rf $new_dir/config
	rm -rf $new_dir/data
	rm -rf $new_dir/ssl
	\cp -r $new_dir/* $monitor_path/
	rm -rf $agent_src
	rm -rf $new_dir
	chmod +x $monitor_path/BT-MONITOR
	chmod +x $monitor_path/tools.py
	wget -O /etc/init.d/btm ${download_Url}/init/btmonitor.init -t 5 -T 10
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

Start_Monitor(){
	/etc/init.d/btm start
    if [ "$?" != "0" ]; then
        echo "堡塔云监控启动失败！"
		tail $monitor_path/logs/error.log
		exit 1
    fi
	echo "已成功升级到[$version]${Ver}";
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

Install_RPM_Pack(){
	yumPacks="wget curl unzip gcc gcc-c++ make libcurl-devel openssl-devel xz-devel python-backports-lzma xz"
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
	apt-get update -y
    debPacks="wget curl unzip gcc g++ make libcurl4-openssl-dev libssl-dev liblzma-dev xz-utils libffi-dev libbz2-dev libsqlite3-dev libreadline-dev libgdbm-dev python3-bsddb3 tk-dev ncurses-dev uuid-dev";
	apt-get install -y $debPacks --force-yes

	for debPack in ${debPacks}
	do
		packCheck=$(dpkg -l ${debPack})
		if [ "$?" -ne "0" ] ;then
			apt-get install -y $debPack
		fi
	done
}

Get_Pack_Manager(){
	if [ -f "/usr/bin/yum" ] && [ -d "/etc/yum.repos.d" ]; then
		PM="yum"
	elif [ -f "/usr/bin/apt-get" ] && [ -f "/usr/bin/dpkg" ]; then
		PM="apt-get"		
	fi
}

Update_Monitor(){
	Get_Pack_Manager
	get_node_url
	if [ $PM = "yum" ]; then
		Install_RPM_Pack
	else
		Install_Deb_Pack
	fi
	if [ "$action" == "update_py" ]; then
		Install_Python_Lib
	fi
	Install_Monitor
	Service_Add
	Start_Monitor
}

action=${1}
Update_Monitor