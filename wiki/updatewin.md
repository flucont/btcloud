# Windows面板官方更新包修改记录

查询最新版本号：https://www.bt.cn/api/wpanel/get_version?is_version=1

官方更新包下载链接：http://download.bt.cn/win/panel/panel_版本号.zip

- 使用16进制编辑器打开btPanel.exe，将 https://api.bt.cn 替换成 http://api.bt.cn/ ，将 https://www.bt.cn 替换成 http://www.bt.cn/ ，然后将api.bt.cn替换成任意其他域名，将第二个www.bt.cn替换成任意其他域名。
- 新版vite页面去除需求反馈、各种广告、计算题等，执行 php think cleanvitejs <面板assets/static/js路径>

