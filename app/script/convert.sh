#!/bin/bash

Linux_Version="11.2.0"
Windows_Version="8.2.2"
Aapanel_Version="7.0.25"
Btm_Version="2.3.3"

FILES=(
public/install/src/panel6.zip
public/install/update/LinuxPanel-${Linux_Version}.zip
public/install/install_panel.sh
public/install/update_panel.sh
public/install/update6.sh
public/win/install/panel_update.py
public/win/panel/panel_${Windows_Version}.zip
public/win/panel/data/api.py
public/win/panel/data/setup.py
public/install/src/bt-monitor-${Btm_Version}.zip
public/install/install_btmonitor.sh
public/install/update_btmonitor.sh
public/install/src/panel_7_en.zip
public/install/update/LinuxPanel_EN-${Aapanel_Version}.zip
public/install/install_7.0_en.sh
public/install/update_7.x_en.sh
)
PL_FILE="public/install/update/LinuxPanel-${Linux_Version}.pl"

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

HASH=$(sha256sum "${DIR}public/install/update/LinuxPanel-${Linux_Version}.zip" | awk '{print $1}')
TIMESTAMP=$(date +%s)
printf '{"hash": "%s", "update_time": "%s"}' "$HASH" "$TIMESTAMP" > "${DIR}${PL_FILE}"

echo "=========================="
echo "处理完成"
echo "=========================="
