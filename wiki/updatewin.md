# Windows面板官方更新包修改记录

查询最新版本号：https://www.bt.cn/api/wpanel/get_version?is_version=1

官方更新包下载链接：http://download.bt.cn/win/panel/panel_版本号.zip

假设搭建的宝塔第三方云端网址是 http://www.example.com

Windows版宝塔由于加密文件太多，无法全部解密，因此无法做到全开源。

- 删除PluginLoader.pyd，将win/PluginLoader.py复制到class文件夹

- 全局搜索替换 https://api.bt.cn => http://www.example.com

- 全局搜索替换 https://www.bt.cn/api/ => http://www.example.com/api/（需排除ipsModel.py）

- 全局搜索替换 http://www.bt.cn/api/ => http://www.example.com/api/

- 全局搜索替换 http://download.bt.cn/win/panel/data/setup.py => http://www.example.com/win/panel/data/setup.py

- class/panel_update.py 文件 public.get_url() =>  'http://www.example.com'

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

  在 get_update_file() 方法里面 get_url() => GetConfigValue('home')

- class/plugin_deployment.py 文件 get_icon 和 SetupPackage 方法内，替换 public.GetConfigValue('home') => 'https://www.bt.cn'

- 去除无用的定时任务：task.py 文件

  删除 p = threading.Thread(target=check_files_panel) 以及下面2行

  删除 p = threading.Thread(target=check_panel_msg) 以及下面2行

  删除 p = threading.Thread(target=update_software_list) 以及下面2行

- 去除面板日志上报：script/site_task.py 文件

  - 删除最下面 logs_analysis() 这1行

- 去除首页广告：BTPanel/static/js/index.js 文件删除最下面index.recommend_paid_version()这一行以及index.consultancy_services()这一行

- 去除首页自动检测更新，避免频繁请求云端：BTPanel/static/js/index.js 文件注释掉bt.system.check_update这一段代码外的setTimeout

- 去除内页广告：BTPanel/templates/default/layout.html 删除getPaymentStatus();这一行

- [可选]去除各种计算题：复制win/bt.js到 BTPanel/static/ ，在 BTPanel/templates/default/layout.html 的尾部加入

  ```javascript
  <script src="/static/bt.js"></script>
  ```

- [可选]去除创建网站自动创建的垃圾文件：class/panelSite.py 文件

  删除 htaccess = self.sitePath + '/.htaccess' 以及下面2行

  删除 index = self.sitePath + '/index.html' 以及下面6行

  删除 doc404 = self.sitePath + '/404.html' 以及下面6行

  删除 if not os.path.exists(self.sitePath + '/.htaccess') 这一行

- [可选]关闭自动生成访问日志：在 BTPanel/\_\_init\_\_.py  删除public.write_request_log()这一行

