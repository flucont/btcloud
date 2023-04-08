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
import public,time,re,shutil,platform
class panel_update:

    def __init__(self):
        pass

    def UpdatePanel(self,version):
        """
        更新面板到指定版本
        @version 面板版本号
        """
        import public
        
        setupPath = os.getenv('BT_SETUP')
        loacl_path = setupPath + '/panel.zip'
        tmpPath = "{}/temp/panel".format(setupPath)

        httpUrl = 'http://www.example.com'
        try:
            downUrl =  httpUrl + '/win/panel/panel_' + version + '.zip';
            if os.path.exists(loacl_path): os.remove(loacl_path)
            
            public.downloadFileByWget(downUrl,loacl_path);
           
            if os.path.getsize(loacl_path) < 1048576: return public.returnMsg(False,"PANEL_UPDATE_ERR_DOWN");

        except :

            print(public.get_error_info())
            return public.returnMsg(False,"修复失败，无法连接到下载节点.");
        
        #处理临时文件目录
        tcPath = '{}\class'.format(tmpPath)
        if os.path.exists(tmpPath): shutil.rmtree(tmpPath,True)
        if not os.path.exists(tmpPath): os.makedirs(tmpPath)
        
        import zipfile
        zip_file = zipfile.ZipFile(loacl_path)  
        for names in zip_file.namelist():
            zip_file.extract(names,tmpPath)
        zip_file.close()

        for name in os.listdir(tcPath): 
            try:
                if name.find('win_amd64.pyd') >=0:
                    oldName = os.path.join(tcPath,name);
                    lName = name.split('.')[0] + '.pyd'
                    newName = os.path.join(tcPath,lName)
                    if not os.path.exists(newName):os.rename(oldName,newName)

            except :pass

        #过滤文件
        file_list = ['config/config.json','config/index.json','data/libList.conf','data/plugin.json']
        for ff_path in file_list:
            if os.path.exists(tmpPath + '/' + ff_path): os.remove(tmpPath + '/' + ff_path)  
      
        public.mod_reload(public)
        import public            

        #兼容不同版本工具箱
        public.kill('BtTools.exe')        
        toolPath = tmpPath + '/script/BtTools.exe'
        if os.path.exists(toolPath):os.remove(toolPath)       
        
        s_ver = platform.platform()        
        net_v = '45'
        if s_ver.find('2008') >= 0: net_v = '20'
        public.writeFile('{}/data/net'.format(panelPath),net_v)
        public.downloadFileByWget(httpUrl + '/win/panel/BtTools' + net_v + '.exe',toolPath);

        cPath = '{}/panel/class'.format(setupPath)
        os.system("del /s {}\*.pyc".format(public.to_path(cPath)))
        os.system("del /s {}\*.pyt".format(public.to_path(cPath)))     
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

        os.system("echo f|xcopy /s /c /e /y /r {} {}".format(public.to_path(tmpPath),public.to_path(panelPath)))

        if os.path.exists('C:/update.py'): os.remove('C:/update.py')
        os.system('bt restart')

        return public.returnMsg(True,"升级面板成功.");
        
