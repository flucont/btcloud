# Linux面板官方更新包修改记录

查询最新版本号：https://www.bt.cn/api/panel/get_version?is_version=1

官方更新包下载链接：http://download.bt.cn/install/update/LinuxPanel-版本号.zip

假设搭建的宝塔第三方云端网址是 http://www.example.com

- 将class文件夹里面所有的.so文件删除

- 将linux/PluginLoader.py复制到class文件夹

- 批量解密模块文件：执行 php think decrypt classdir <面板class文件夹路径>

- 全局搜索替换 https://api.bt.cn => http://www.example.com

- 全局搜索替换 https://www.bt.cn/api/ => http://www.example.com/api/（需排除clearModel.py、scanningModel.py、ipsModel.py、domainMod.py、js文件）

- 全局搜索替换 http://www.bt.cn/api/ => http://www.example.com/api/（需排除js文件）

- 全局搜索替换 https://download.bt.cn/install/update6.sh => http://www.example.com/install/update6.sh

  http://download.bt.cn/install/update6.sh => http://www.example.com/install/update6.sh

  http://download.bt.cn/install/update/ => http://www.example.com/install/update/

- 搜索并删除提交异常报告的代码 bt_error/index.php

- class/ajax.py 文件 \# 是否执行升级程序 下面的 public.get_url() 改成 public.GetConfigValue('home')

  class/jobs.py 文件 \#尝试升级到独立环境 下面的 public.get_url() 改成 public.GetConfigValue('home')

  class/system.py 文件 RepPanel和UpdatePro方法内的 public.get_url() 改成 public.GetConfigValue('home')

- class/public.py 在 

  ```python
  def GetConfigValue(key):
  ```

  这一行下面加上

  ```python
  if key == 'home': return 'http://www.example.com'
  ```

  在 def is_bind(): 这一行下面加上 return True

  在 def check_domain_cloud(domain): 这一行下面加上 return

  在 def err_collect 这一行下面加上 return

  在 def get_improvement(): 这一行下面加上 return False

  在free_login_area方法内get_free_ips_area替换成get_ips_area

  在get_free_ip_info方法内，获取IP的部分改成res = get_ips_area([address])

  在login_send_body方法内，free_login_area(login_ip=server_ip_area的server_ip_area改成login_ip

- class/panelPlugin.py 文件

  __set_pyenv方法内，temp_file = public.readFile(filename)这行代码下面加上

  ```python
  temp_file = temp_file.replace('http://download.bt.cn/install/public.sh', 'http://www.example.com/install/public.sh')
  temp_file = temp_file.replace('https://download.bt.cn/install/public.sh', 'http://www.example.com/install/public.sh')
  ```
  
  def check_status(self, softInfo): 方法最后一行加上
  
  ```python
  if 'endtime' in softInfo:
              softInfo['endtime'] = time.time() + 86400 * 3650
  ```
  
  plugin_bin.pl 改成 plugin_list.json
  
  删除 public.total_keyword(get.query)
  
  删除 public.run_thread(self.get_cloud_list_status, args=(get,))
  
  删除 public.run_thread(self.is_verify_unbinding, args=(get,))
  
- class/plugin_deployment.py 文件，__setup_php_environment方法和GetJarPath方法内替换 public.GetConfigValue('home') => 'https://www.bt.cn'

- class/config.py 文件，get_nps方法内data['nps'] = False改成True，get_nps_new方法下面加上 return public.returnMsg(False, "获取问卷失败")

  def err_collection(self, get): 这一行下面加上 return public.returnMsg(True, "OK")

- class/push/site_push.py 文件，'https://www.bt.cn' => 'http://www.example.com'

- script/flush_plugin.py 文件，删除clear_hosts()一行

- script/reload_check.py 文件，在第2行插入sys.exit()

- script/local_fix.sh 文件，${D_NODE_URL}替换成www.example.com

- script/upgrade_panel_optimized.py 文件，def get_home_node(url): 下面加上return url

- tools.py 文件，u_input == 16下面的public.get_url()替换成public.GetConfigValue('home')

- install/install_soft.sh 在. 执行之前加入以下代码

  ```shell
  sed -i "s/http:\/\/download.bt.cn\/install\/public.sh/http:\/\/www.example.com\/install\/public.sh/" $name.sh
  sed -i "s/https:\/\/download.bt.cn\/install\/public.sh/http:\/\/www.example.com\/install\/public.sh/" $name.sh
  ```
  
- install/public.sh 用官网最新版的[public.sh](http://download.bt.cn/install/public.sh)替换，并去除最下面bt_check一行

- 去除无用的定时任务：task.py 文件  删除以下几行

  check_panel_msg,

  refresh_domain_cache,

  task_ssh_error_count,

- [可选]去除各种计算题：复制bt.js到 BTPanel/static/ ，在 BTPanel/templates/default/software.html 的 \<script\>window.vite_public_request_token 前面加入

  ```javascript
  <script src="/static/bt.js"></script>
  ```

- [可选]去除创建网站自动创建的垃圾文件：在class/panelSite.py，分别删除

  htaccess = self.sitePath + '/.htaccess'

  index = self.sitePath + '/index.html'

  doc404 = self.sitePath + '/404.html'

  这3行及分别接下来的4行代码

  def get_view_title_content(self, get): 下面加上 return public.returnMsg(True, '')

- [可选]关闭未绑定域名提示页面：在class/panelSite.py，root /www/server/nginx/html改成return 400

- [可选]关闭自动生成访问日志：在 BTPanel/\_\_init\_\_.py  删除public.write_request_log这一行

- [可选]新版vite页面去除需求反馈、各种广告、计算题等，执行 php think cleanvitejs <面板BTPanel/static/js路径>


解压安装包[panel6.zip](http://download.bt.cn/install/src/panel6.zip)，将更新包改好的文件覆盖到里面，然后重新打包，即可更新安装包。（

别忘了删除class文件夹里面所有的.so文件）

