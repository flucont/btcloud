#coding: utf-8
# +-------------------------------------------------------------------
# | 宝塔Windows面板
# +-------------------------------------------------------------------
# | Copyright (c) 2015-2020 宝塔软件(http://www.bt.cn) All rights reserved.
# +-------------------------------------------------------------------
# | Author: 沐落 <cjx@bt.cn>
# | 面板升级安装公共类
# +-------------------------------------------------------------------

import os, sys
panelPath = os.getenv('BT_PANEL')
os.chdir(panelPath)
sys.path.insert(0,panelPath + "/class/")
import public,time,re,shutil,platform,socket
try:
    import ctypes
except ImportError:
    ctypes = None

class panel_update:

    __cloud_url = 'http://www.example.com'

    def __init__(self):
        pass

    def _check_admin_privileges(self):
        try:
            # 方法1: 使用ctypes检查管理员权限
            if ctypes:
                is_admin = ctypes.windll.shell32.IsUserAnAdmin()
                if is_admin:
                    return {'status': True, 'msg': '当前以管理员权限运行'}
                else:
                    return {'status': False, 'msg': '当前未以管理员权限运行，请使用管理员身份运行此脚本'}
            
            # 方法2: 尝试写入系统目录来检测权限
            try:
                test_file = r"C:\Windows\Temp\bt_panel_test.tmp"
                with open(test_file, 'w') as f:
                    f.write('test')
                os.remove(test_file)
                return {'status': True, 'msg': '当前以管理员权限运行'}
            except (IOError, OSError):
                return {'status': False, 'msg': '当前未以管理员权限运行，请使用管理员身份运行此脚本'}
                
        except Exception as e:
            return {'status': False, 'msg': f'检测管理员权限时发生错误: {str(e)}'}

    def _pre_update_checks(self):
        try:
            ip_address = self._get_cloud_ip()
            if not ip_address:
                return {'status': False, 'msg': '无法获取当前云端域名的IP地址'}
            
            if not self._verify_api(ip_address):
                return {'status': False, 'msg': '当前云端无法访问，可能未绑定api.bt.cn和www.bt.cn域名'}

            if not self._update_hosts(ip_address):
                return {'status': False, 'msg': '修改hosts文件失败'}

            return {'status': True, 'msg': '升级前检查通过'}

        except Exception as e:
            return {'status': False, 'msg': f'升级前检查异常: {str(e)}'}

    def _get_cloud_ip(self):
        domain = re.findall(r'://([^/:]+)', self.__cloud_url)[0]
        try:
            ip_address = socket.gethostbyname(domain)
            return ip_address
        except Exception as e:
            print(f"获取{domain} IP失败: {str(e)}")
            return None

    def _verify_api(self, ip_address):
        try:
            api_url = f"http://{ip_address}/api/SetupCount"
            headers = {"Host": "api.bt.cn", "User-Agent": "BT-Panel"}
            response = public.HttpGet(api_url, headers=headers, timeout=10)
            if response and response.strip() == "ok":
                return True
            else:
                print(f"请求云端验证失败，响应: {response}")
                return False
        except Exception as e:
            print(f"请求云端验证异常: {str(e)}")
            return False

    def _update_hosts(self, ip_address):
        hosts_path = r"C:\Windows\System32\drivers\etc\hosts"
        
        try:
            if os.path.exists(hosts_path):
                content = public.readFile(hosts_path)
            else:
                content = ""

            lines = content.split('\n')
            new_lines = []
            
            for line in lines:
                stripped_line = line.strip()
                if not stripped_line or stripped_line.startswith('#'):
                    new_lines.append(line)
                    continue

                if 'api.bt.cn' in line or 'www.bt.cn' in line:
                    continue
                
                new_lines.append(line)

            new_lines.append(f"{ip_address} api.bt.cn")
            new_lines.append(f"{ip_address} www.bt.cn")

            new_content = '\n'.join(new_lines)
            result = public.writeFile(hosts_path, new_content)
            
            if result:
                print(f"修改hosts文件成功")
                return True
            else:
                print("修改hosts文件失败")
                return False
                
        except Exception as e:
            print(f"修改hosts文件异常: {str(e)}")
            return False

    def UpdatePanel(self,version):
        """
        更新Go面板到指定版本
        @version 面板版本号
        """

        import public

        admin_check = self._check_admin_privileges()
        if not admin_check['status']:
            return public.returnMsg(False, admin_check['msg'])

        result = self._pre_update_checks()
        if not result['status']:
            return public.returnMsg(False, result['msg'])

        setupPath = os.getenv('BT_SETUP')
        loacl_path = setupPath + '/panel.zip'
        tmpPath = "{}/temp/panel".format(setupPath)

        try:
            downUrl =  self.__cloud_url + '/win/panel/panel_' + version + '.zip';
            if os.path.exists(loacl_path): os.remove(loacl_path)

            public.downloadFileByWget(downUrl,loacl_path);

            if os.path.getsize(loacl_path) < 1048576: return public.returnMsg(False,"PANEL_UPDATE_ERR_DOWN");

        except :

            print(public.get_error_info())
            return public.returnMsg(False,"更新失败，无法连接到下载节点.");


        #处理临时文件目录
        tcPath = '{}\class'.format(tmpPath)
        if os.path.exists(tmpPath): shutil.rmtree(tmpPath,True)
        if not os.path.exists(tmpPath): os.makedirs(tmpPath)

        import zipfile
        zip_file = zipfile.ZipFile(loacl_path)
        for names in zip_file.namelist():
            zip_file.extract(names,tmpPath)
        zip_file.close()

        os.system('net stop btPanel')
        #过滤文件
        file_list = ['config/config.json','config/index.json','data/libList.conf','data/plugin.json']
        for ff_path in file_list:
            if os.path.exists(tmpPath + '/' + ff_path): os.remove(tmpPath + '/' + ff_path)

        if self.is_2008():
            public.rmdir("{}/class/public".format(tmpPath))
            public.rmdir("{}/class/BTPanel.py".format(tmpPath))
            return public.returnMsg(False,"Windows 2008无法使用最新版本。")

        public.mod_reload(public)
        import public

        #兼容不同版本工具箱
        public.kill('BtTools.exe')
        toolPath = tmpPath + '/script/BtTools.exe'
        if os.path.exists(toolPath):os.remove(toolPath)

        s_ver = platform.platform()

        cPath = '{}/panel/class'.format(setupPath)
        os.system("del /s {}\*.pyc".format(public.to_path(cPath)))
        os.system("del /s {}\*.pyt".format(public.to_path(cPath)))
        os.system("del /s {}\*_amd64.pyd".format(public.to_path(cPath)))

        for name in os.listdir(cPath):
            try:
                if name.find('.pyd') >=0:
                    oldName = os.path.join(cPath,name)
                    newName = os.path.join(cPath,public.GetRandomString(8) + '.pyt')
                    os.rename(oldName,newName)
                if name.find('.dll') >= 0:
                    oldName = os.path.join(cPath,name)
                    public.rmdir(oldName)
            except : pass

        #处理面板程序目录文件
        os.system("del /s {}\*.pyc".format(public.to_path(cPath)))
        os.system("del /s {}\*.pyt".format(public.to_path(cPath)))
        os.system("del /s {}\*.del".format(public.to_path(panelPath)))

        for name in os.listdir(panelPath):
            try:
                if name.find('.exe') >=0:
                    oldName = os.path.join(panelPath,name)
                    newName = oldName + '.del'
                    os.rename(oldName,newName)
            except : pass

        os.system("echo f|xcopy /s /c /e /y /r {} {}".format(public.to_path(tmpPath),public.to_path(panelPath)))

        panel_file = '{}/btPanel.exe'.format(panelPath)
        if os.path.exists(panel_file):
            os.system("sc stop btPanel")
            os.system("sc stop btTask")
            time.sleep(2)
            os.system("sc delete btPanel")
            os.system("sc delete btTask")

            os.system("{} --services install".format(public.to_path(panel_file)))
            time.sleep(2)
            os.system("{} --task install".format(public.to_path(panel_file)))

            os.system("sc start btPanel")
            os.system("sc start btTask")

        if os.path.exists('C:/update.py'): os.remove('C:/update.py')

        return public.returnMsg(True,"升级面板成功.")

    def is_2008(self):
        """
        判断是否2008系统
        """
        os_ver = public.ReadReg("SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion", "ProductName")
        if os_ver.find('2008') >= 0: return True
        return False


if __name__ == "__main__":
    version = sys.argv[1]
    if not version:
        version = "8.4.6"
    result = panel_update().UpdatePanel(version)
    print(result['msg'])
