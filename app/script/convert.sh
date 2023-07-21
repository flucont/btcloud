#!/bin/bash

Linux_Version="8.0.1"
Windows_Version="7.9.0"
Btm_Version="2.2.5"

FILES=(
public/install/src/panel6.zip
public/install/update/LinuxPanel-${Linux_Version}.zip
public/install/install_6.0.sh
public/install/update_panel.sh
public/install/update6.sh
public/win/install/panel_update.py
public/win/panel/panel_${Windows_Version}.zip
public/win/panel/data/api.py
public/win/panel/data/setup.py
public/install/src/bt-monitor-${Btm_Version}.zip
public/install/install_btmonitor.sh
public/install/update_btmonitor.sh
)

DIR=$1
SITEURL=$2

if [ ! -d "$DIR" ]; then
	echo "网站目录不存在"
	exit 1
fi
if [ "$SITEURL" = "" ]; then
	echo "网站URL不正确"
	exit 1
fi

function handleFile()
{
	Filename=$1
	if [ "${Filename##*.}" = "zip" ]; then
		handleZipFile $Filename
	else
		handleTextFile $Filename
	fi
}

function handleZipFile()
{
	Filename=$1
	mkdir -p /tmp/package
	unzip -o -q $Filename -d /tmp/package
	grep -rl --include=\*.py --include=\*.sh --include=index.js 'http://www.example.com' /tmp/package | xargs -I @ sed -i "s|http://www.example.com|${SITEURL}|g" @
	Sprit_SITEURK=${SITEURL//\//\\\\\/}
	grep -rl --include=\*.sh 'http:\\\/\\\/www.example.com' /tmp/package | xargs -I @ sed -i "s|http:\\\/\\\/www.example.com|${Sprit_SITEURK}|g" @
	rm -f $Filename
	cd /tmp/package && zip -9 -q -r $Filename * && cd -
	rm -rf /tmp/package
}

function handleTextFile()
{
	sed -i "s|http://www.example.com|${SITEURL}|g" $1
}


echo "=========================="
echo "正在处理中..."
echo "=========================="

for File in ${FILES[@]}
do
	Filename="${DIR}${File}"
	if [ -f "$Filename" ]; then
		handleFile $Filename
		echo -e "成功处理文件：\033[32m${Filename}\033[0m"
	else
		echo -e "文件不存在：\033[33m${Filename}\033[0m"
	fi
done

echo "=========================="
echo "处理完成"
echo "=========================="
