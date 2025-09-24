# aapanel面板官方更新包修改记录

查询最新版本号：https://brandnew.aapanel.com/api/panel/getLatestOfficialVersion

官方更新包下载链接：http://download.bt.cn/install/update/LinuxPanel_EN-版本号.zip

假设搭建的宝塔第三方云端网址是 http://www.example.com

- 将class文件夹里面所有的.so文件删除

- 将aapanel/PluginLoader.py复制到class文件夹

- 批量解密模块文件：执行 php think decrypt classdir <面板class文件夹路径>

  php think decrypt classdir <面板class_v2文件夹路径>

- 全局搜索替换 https://wafapi2.aapanel.com => http://www.example.com（需排除task.py、ipsModel.py、js文件），https://wafapi.aapanel.com => http://www.example.com

- 全局搜索替换 https://node.aapanel.com/install/update_7.x_en.sh => http://www.example.com/install/update_7.x_en.sh

  https://node.aapanel.com/install/update_pro_en.sh => http://www.example.com/install/update_7.x_en.sh

- 搜索并删除提交异常报告的代码 bt_error/index.php

- class/ajax.py、class_v2/ajax_v2.py 文件：

  \#是否执行升级程序 下面的 public.get_url() 改成 public.OfficialApiBase()

  __official_url = 'https://www.aapanel.com' 改成 http://www.example.com

  class/jobs.py、class_v2/jobs_v2.py 文件：

  \#尝试升级到独立环境 下面的 public.get_url() 改成 public.OfficialApiBase()

  class/system.py、class_v2/system_v2.py 文件：

  RepPanel和UpdatePro方法内的 public.get_url() 改成 public.OfficialApiBase()

- class/public/common.py

  def OfficialApiBase(): 改成 return 'http://www.example.com'

  def load_soft_list 去除 if force 部分

  plugin_list_data = PluginLoader.get_plugin_list(0) 部分改成 plugin_list_data = PluginLoader.get_plugin_list(force)

  在 def check_domain_cloud(domain): 这一行下面加上 return

  在 def count_wp(): 这一行下面加上 return

  在 def err_collect 这一行下面加上 return

  在 def get_improvement(): 这一行下面加上 return False

  在free_login_area方法内get_free_ips_area替换成get_ips_area

  在login_send_body方法内，free_login_area(login_ip=server_ip_area的server_ip_area改成login_ip

  在 def write_request_log(reques=None): 这一行下面加上 return

- class/panelPlugin.py、class_v2/panel_plugin_v2.py 文件，set_pyenv方法内，temp_file = public.readFile(filename)这行代码下面加上

  ```python
  temp_file = temp_file.replace('http://download.bt.cn/install/public.sh', 'http://www.example.com/install/public.sh')
  temp_file = temp_file.replace('https://download.bt.cn/install/public.sh', 'http://www.example.com/install/public.sh')
  ```
  
- class_v2/btdockerModelV2/flush_plugin.py 文件，删除clear_hosts()一行

- install/install_soft.sh 在. 执行之前加入以下代码

  ```shell
  sed -i "s/http:\/\/download.bt.cn\/install\/public.sh/http:\/\/www.example.com\/install\/public.sh/" $name.sh
  sed -i "s/https:\/\/download.bt.cn\/install\/public.sh/http:\/\/www.example.com\/install\/public.sh/" $name.sh
  ```
  
- install/public.sh 用官网最新版的[public.sh](http://download.bt.cn/install/public.sh)替换，并去除最下面bt_check一行

- 去除无用的定时任务：task.py 文件  删除以下几行

  "check_site_monitor": check_site_monitor,

  "update_software_list": update_software_list,

  "malicious_file_scanning": malicious_file_scanning,

  "check_panel_msg": check_panel_msg,

  "check_panel_auth": check_panel_auth,

  "count_ssh_logs": count_ssh_logs,

  "update_vulnerabilities": update_vulnerabilities,

  "refresh_dockerapps": refresh_dockerapps,

  "submit_email_statistics": submit_email_statistics,

  "submit_module_call_statistics": submit_module_call_statistics,

  "mailsys_domain_blecklisted_alarm": mailsys_domain_blecklisted_alarm,

- [可选]去除各种计算题：将bt.js里面的内容复制到 BTPanel/static/vite/oldjs/public_backup.js 末尾

- [可选]去除创建网站自动创建的垃圾文件：在class/panelSite.py、class_v2/panel_site_v2.py，分别删除

  htaccess = self.sitePath + '/.htaccess'

  index = self.sitePath + '/index.html'

  doc404 = self.sitePath + '/404.html'

  这3行及分别接下来的4行代码

- [可选]关闭未绑定域名提示页面：在class/panelSite.py、class_v2/panel_site_v2.py，root /www/server/nginx/html改成return 400

- [可选]上传文件默认选中覆盖，在BTPanel/static/vite/oldjs/upload-drog.js，id="all_operation"加checked属性

- [可选] BTPanel/static/vite/oldjs/site.js，优化SSL证书配置页面

- [可选]新版vite页面去除需求反馈、各种广告、计算题等，执行 php think cleanvitejs <面板BTPanel/static/js路径>

- 新增简体中文语言：修改BTPanel/languages/settings.json，并增加 zh/server.json、all/zh.json


解压安装包[panel_7_en.zip](http://download.bt.cn/install/src/panel_7_en.zip)，将更新包改好的文件覆盖到里面，然后重新打包，即可更新安装包。（

别忘了删除class文件夹里面所有的.so文件）

