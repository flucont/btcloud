# 宝塔云监控安装包修改记录

查询最新版本号：https://api.bt.cn/bt_monitor/latest_version

安装包下载链接：http://download.bt.cn/install/src/bt-monitor-版本号.zip

- 删除core/include/c_loader/PluginLoader.so，hook_import/PluginLoader.so，将btmonitor/PluginLoader.py复制到这个文件夹

- 批量解密源码：执行 php think decrypt all <源码根目录>

  极少数文件解密失败是正常现象可无视

- 全局搜索替换 https://api.bt.cn => http://www.example.com（需排除/bt_monitor/latest_agent_version、/bt_monitor/ip_info）

- 全局搜索替换 https://www.bt.cn/api/ => http://www.example.com/api/

- core/include/public.py 在 

  ```python
  def GetConfigValue(key):
  ```

  这一行下面加上

  ```python
  if key == 'home': return 'http://www.example.com'
  ```

  def write_request_log(reques = None): 这一行下面加上 return

- core/include/basic_monitor.py

  在 def report_module_logs(self, force=False): 这一行下面加上 return

  在 def report_daily_active(self, force=False): 这一行下面加上 return

- modules/configModule/main.py

  https://download.bt.cn => http://www.example.com

- update/update_btmonitor.sh 修改Install_Monitor方法内的download_Url变量

- init.sh   https://download.bt.cn => http://www.example.com

- 减少压缩包体积：删除 static/js/ 目录下的map文件
