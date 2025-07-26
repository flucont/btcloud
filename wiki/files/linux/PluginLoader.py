#coding: utf-8
# +-------------------------------------------------------------------
# | 宝塔Linux面板
# +-------------------------------------------------------------------
# | Copyright (c) 2015-2099 宝塔软件(http://bt.cn) All rights reserved.
# +-------------------------------------------------------------------
# | Author: hwliang <hwl@bt.cn>
# +-------------------------------------------------------------------

#+--------------------------------------------------------------------
#|   插件和模块加载器
#+--------------------------------------------------------------------

import public,os,sys,json,hashlib

def plugin_run(plugin_name,def_name,args):
    '''
        @name 执行插件方法
        @param plugin_name<string> 插件名称
        @param def_name<string> 方法名称
        @param args<dict_obj> 参数对像
        @return mixed
    '''
    if not plugin_name or not def_name: return public.returnMsg(False,'插件名称和插件方法名称不能为空!')
    
    # 获取插件目录
    plugin_path = public.get_plugin_path(plugin_name)
    is_php = os.path.exists(os.path.join(plugin_path,'index.php'))

    # 检查插件目录是否合法
    if is_php:
        plugin_file = os.path.join(plugin_path,'index.php')
    else:
        plugin_file = os.path.join(plugin_path, plugin_name + '_main.py')
    if not public.path_safe_check(plugin_file): return public.returnMsg(False,'插件路径不合法')

    # 检查插件入口文件是否存在
    if not os.path.exists(plugin_file): return public.returnMsg(False,'指定插件入口文件不存在')

    # 添加插件目录到系统路径
    public.sys_path_append(plugin_path)

    if not is_php:
        # 引用插件入口文件
        _name = "{}_main".format(plugin_name)
        plugin_main = __import__(_name)

        # 检查类名是否符合规范
        if not hasattr(plugin_main,_name):
            return public.returnMsg(False,'指定插件入口文件不符合规范')
        
        try:
            if sys.version_info[0] == 2:
                reload(plugin_main)
            else:
                from imp import reload
                reload(plugin_main)
        except:
            pass
        
        # 实例化插件类
        plugin_obj = getattr(plugin_main,_name)()

        # 检查方法是否存在
        if not hasattr(plugin_obj,def_name):
            return public.returnMsg(False,'在[%s]插件中找不到[%s]方法' % (plugin_name,def_name))
        
        if args is not None and 'plugin_get_object' in args and args.plugin_get_object == 1:
            return getattr(plugin_obj, def_name)
        
        # 执行方法
        return getattr(plugin_obj,def_name)(args)
    else:
        if args is not None and 'plugin_get_object' in args and args.plugin_get_object == 1:
            return None
        import panelPHP
        args.s = def_name
        args.name = plugin_name
        return panelPHP.panelPHP(plugin_name).exec_php_script(args)
    

def get_module_list():
    '''
        @name 获取模块列表
        @return list    
    '''
    module_list = []
    class_path = public.get_class_path()
    for name in os.listdir(class_path):
        path = os.path.join(class_path,name)
        # 过滤无效文件
        if not name or name.endswith('.py') or name[0] == '.' or not name.endswith('Model') or os.path.isfile(path):continue
        module_list.append(name)
    return module_list

def module_run(module_name,def_name,args):
    '''
        @name 执行模块方法
        @param module_name<string> 模块名称
        @param def_name<string> 方法名称
        @param args<dict_obj> 参数对像
        @return mixed
    '''
    if not module_name or not def_name: return public.returnMsg(False,'模块名称和模块方法名称不能为空!')
    model_index = args.get('model_index',None)
    class_path = public.get_class_path()
    panel_path = public.get_panel_path()
    
    module_file = None
    if 'model_index' in args:
        # 新模块目录
        if model_index in ['mod']:
            module_file = os.path.join(panel_path,'mod','project',module_name + 'Mod.py')
        elif model_index:
            # 旧模块目录
            module_file = os.path.join(class_path,model_index+"Model",module_name + 'Model.py')
        else:
            module_file = os.path.join(class_path,"projectModel",module_name + 'Model.py')
    else:
        # 如果没指定模块名称，则遍历所有模块目录
        module_list = get_module_list()
        for name in module_list:
            module_file = os.path.join(class_path,name,module_name + 'Model.py')
            if os.path.exists(module_file): break
    
    # 判断模块入口文件是否存在
    if not os.path.exists(module_file):
        return public.returnMsg(False,'模块[%s]不存在' % module_name)
    
    # 判断模块路径是否合法
    if not public.path_safe_check(module_file):
        return public.returnMsg(False,'模块路径不合法')
    
    def_object = public.get_script_object(module_file)
    if not def_object: return public.returnMsg(False,'模块[%s]不存在' % module_name)

    # 模块实例化并返回方法对象
    try:
        run_object = getattr(def_object.main(),def_name,None)
    except:
        return public.returnMsg(False,'模块[%s]入口实例化失败' % module_name)
    if not run_object: return public.returnMsg(False,'在[%s]模块中找不到[%s]方法' % (module_name,def_name))

    if 'module_get_object' in args and args.module_get_object == 1:
        return run_object
    
    # 执行方法
    result = run_object(args)
    return result
    
def get_module(filename: str):
    '''
        @name 获取模块对象
        @param filename<string> 模块文件名
        @return object
    '''
    if not filename: return None
    
    if filename[0:2] == './':
        return public.returnMsg(False,'不能是相对路径')
    
    if not public.path_safe_check(filename):
        return public.returnMsg(False,'模块路径不合法')

    if not os.path.exists(filename):
        return public.returnMsg(False,'模块文件不存在' % filename)
    
    def_object = public.get_script_object(filename)
    if not def_object: return public.returnMsg(False,'模块[%s]不存在' % filename)

    return def_object.main()

def get_plugin_list(upgrade_force = False):
    '''
        @name 获取插件列表
        @param upgrade_force<bool> 是否强制重新获取列表
        @return dict
    '''

    api_root_url = 'https://api.bt.cn'
    api_url = api_root_url+ '/panel/get_plugin_list'
    panel_path = public.get_panel_path()
    data_path = os.path.join(panel_path,'data')

    if not os.path.exists(data_path):
        os.makedirs(data_path,384)

    plugin_list = {}
    plugin_list_file = os.path.join(data_path,'plugin_list.json')
    if os.path.exists(plugin_list_file) and not upgrade_force:
        plugin_list_body = public.readFile(plugin_list_file)
        try:
            plugin_list = json.loads(plugin_list_body)
        except:
            plugin_list = {}
    
    if not os.path.exists(plugin_list_file) or upgrade_force or not plugin_list:
        try:
            res = public.HttpGet(api_url)
        except Exception as ex:
            raise public.error_conn_cloud(str(ex))
        if not res: raise Exception(False,'云端插件列表获取失败')

        plugin_list = json.loads(res)
        if type(plugin_list)!=dict or 'list' not in plugin_list:
            if type(plugin_list)==str:
                raise Exception(plugin_list)
            else:
                raise Exception('云端插件列表获取失败')
        content = json.dumps(plugin_list)
        public.writeFile(plugin_list_file,content)

        plugin_bin_file = os.path.join(data_path,'plugin_bin.pl')
        encode_content = __encode_plugin_list(content)
        if encode_content:
            public.writeFile(plugin_bin_file,encode_content)
        
    return plugin_list

def __encode_plugin_list(content):
    try:
        userInfo = public.get_user_info()
        if not userInfo or 'serverid' not in userInfo: return None
        block_size = 51200
        uid = str(userInfo['uid'])
        server_id = userInfo['serverid']
        key = server_id[10:26] + uid + server_id
        key = hashlib.md5(key.encode()).hexdigest()
        iv = key + server_id
        iv = hashlib.md5(iv.encode()).hexdigest()
        key = key[8:24]
        iv = iv[8:24]
        blocks = [content[i:i + block_size] for i in range(0, len(content), block_size)]
        encrypted_content = ''
        for block in blocks:
            encrypted_content += __aes_encrypt(block, key, iv) + '\n'
        return encrypted_content
    except:
        pass
    return None

def start_total():
    '''
        @name 启动统计服务
        @return dict
    '''
    pass

def get_soft_list(args):
    '''
        @name 获取软件列表
        @param args<dict_obj> 参数对像
        @return dict
    '''
    pass

def db_encrypt(data):
    '''
        @name 数据库加密
        @param args<dict_obj> 参数对像
        @return dict
    '''
    try:
        key = __get_db_sgin()
        iv = __get_db_iv()
        str_arr = data.split('\n')
        res_str = ''
        for data in str_arr:
            if not data: continue
            res_str += __aes_encrypt(data, key, iv)
    except:
        res_str = data
    result = {
        'status' : True,
        'msg' : res_str
    }
    return result

def db_decrypt(data):
    '''
        @name 数据库解密
        @param args<dict_obj> 参数对像
        @return dict
    '''
    try:
        key = __get_db_sgin()
        iv = __get_db_iv()
        str_arr = data.split('\n')
        res_str = ''
        for data in str_arr:
            if not data: continue
            res_str += __aes_decrypt(data, key, iv)
    except:
        res_str = data
    result = {
        'status' : True,
        'msg' : res_str
    }
    return result

def __get_db_sgin():
    keystr = '3gP7+k_7lSNg3$+Fj!PKW+6$KYgHtw#R'
    key = ''
    for i in range(31):
        if i & 1 == 0:
            key += keystr[i]
    return key

def __get_db_iv():
    div_file = "{}/data/div.pl".format(public.get_panel_path())
    if not os.path.exists(div_file):
        str = public.GetRandomString(16)
        str = __aes_encrypt_module(str)
        div = public.get_div(str)
        public.WriteFile(div_file, div)
    if os.path.exists(div_file):
        div = public.ReadFile(div_file)
        div = __aes_decrypt_module(div)
    else:
        keystr = '4jHCpBOFzL4*piTn^-4IHBhj-OL!fGlB'
        div = ''
        for i in range(31):
            if i & 1 == 0:
                div += keystr[i]
    return div

def __aes_encrypt_module(data):
    key = 'Z2B87NEAS2BkxTrh'
    iv = 'WwadH66EGWpeeTT6'
    return __aes_encrypt(data, key, iv)

def __aes_decrypt_module(data):
    key = 'Z2B87NEAS2BkxTrh'
    iv = 'WwadH66EGWpeeTT6'
    return __aes_decrypt(data, key, iv)

def __aes_decrypt(data, key, iv):
    from Crypto.Cipher import AES
    import base64
    encodebytes = base64.decodebytes(data.encode('utf-8'))
    aes = AES.new(key.encode('utf-8'), AES.MODE_CBC, iv.encode('utf-8'))
    de_text = aes.decrypt(encodebytes)
    unpad = lambda s: s[0:-s[-1]]
    de_text = unpad(de_text)
    return de_text.decode('utf-8')

def __aes_encrypt(data, key, iv):
    from Crypto.Cipher import AES
    import base64
    data = (lambda s: s + (16 - len(s) % 16) * chr(16 - len(s) % 16).encode('utf-8'))(data.encode('utf-8'))
    aes = AES.new(key.encode('utf8'), AES.MODE_CBC, iv.encode('utf8'))
    encryptedbytes = aes.encrypt(data)
    en_text = base64.b64encode(encryptedbytes)
    return en_text.decode('utf-8')

def plugin_end():
    '''
        @name 插件到期处理
        @return dict
    '''
    pass

def daemon_task():
    '''
        @name 后台任务守护
        @return dict
    '''
    pass

def daemon_panel():
    '''
        @name 面板守护
        @return dict
    '''
    pass

def flush_auth_key():
    '''
        @name 刷新授权密钥
        @return dict
    '''
    pass

def get_auth_state():
    '''
        @name 获取授权状态
        @return 返回：0.免费版 1.专业版 2.企业版 -1.获取失败
    '''
    try:
        softList = get_plugin_list()
        if softList['ltd'] > -1:
            return 2
        elif softList['pro'] > -1:
            return 1
        else:
            return 0
    except:
        return -1

 