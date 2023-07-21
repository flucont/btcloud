#coding: utf-8
import public,os,sys,json

#获取插件列表(0/1)
def get_plugin_list(force = 0):
    api_root_url = 'https://api.bt.cn'
    api_url = api_root_url+ '/panel/get_plugin_list'
    cache_file = 'data/plugin_list.json'
    
    if force==0 and os.path.exists(cache_file):
        jsonData = public.readFile(cache_file)
        softList = json.loads(jsonData)
    else:
        try:
            jsonData = public.HttpGet(api_url)
        except Exception as ex:
            raise public.error_conn_cloud(str(ex))
        softList = json.loads(jsonData)
        if type(softList)!=dict or 'list' not in softList: raise Exception('云端插件列表获取失败')
        public.writeFile(cache_file, jsonData)
    return softList

#获取授权状态() 返回：0.免费版 1.专业版 2.企业版 -1.获取失败
def get_auth_state():
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

#执行插件方法(插件名,方法名,参数)
def plugin_run(plugin_name, def_name, args):
    if not plugin_name or not def_name: return public.returnMsg(False,'插件名称和插件方法名称不能为空!')
    if not path_check(plugin_name) or not path_check(def_name): return public.returnMsg(False,'插件名或方法名不能包含特殊符号!')
    p_path = public.get_plugin_path(plugin_name)
    if not os.path.exists(p_path + '/index.php') and not os.path.exists(p_path + '/%s_main.py' % plugin_name): return public.returnMsg(False,'插件不存在!')
    
    is_php = os.path.exists(p_path + '/index.php')
    if not is_php:
        public.package_path_append(p_path)
        plugin_main = __import__(plugin_name + '_main')
        try:
            if sys.version_info[0] == 2:
                reload(plugin_main)
            else:
                from imp import reload
                reload(plugin_main)
        except:
            pass
        plu = eval('plugin_main.' + plugin_name + '_main()')
        if not hasattr(plu, def_name):
            return public.returnMsg(False,'在[%s]插件中找不到[%s]方法' % (plugin_name,def_name))

    if 'plugin_get_object' in args and args.plugin_get_object == 1:
        if not is_php:
            return getattr(plu, def_name)
        else:
            return None
    else:
        if not is_php:
            data = eval('plu.' + def_name + '(args)')
        else:
            import panelPHP
            args.s = def_name
            args.name = plugin_name
            data = panelPHP.panelPHP(plugin_name).exec_php_script(args)
        return data

#执行模块方法(模块名,方法名,参数)
def module_run(mod_name, def_name, args):
    if not mod_name or not def_name: return public.returnMsg(False,'模块名称和模块方法名称不能为空!')
    if not path_check(mod_name) or not path_check(def_name): return public.returnMsg(False,'模块名或方法名不能包含特殊符号!')

    if 'model_index' in args:
        if args.model_index:
            mod_file = "{}/{}Model/{}Model.py".format(public.get_class_path(),args.model_index,mod_name)
        else:
            mod_file = "{}/projectModel/{}Model.py".format(public.get_class_path(),mod_name)
    else:
        module_list = get_module_list()
        for module_dir in module_list:
            mod_file = "{}/{}/{}Model.py".format(public.get_class_path(),module_dir,mod_name)
            if os.path.exists(mod_file): break

    if not os.path.exists(mod_file):
        return public.returnMsg(False,'模块[%s]不存在' % mod_name)

    def_object = public.get_script_object(mod_file)
    if not def_object: return public.returnMsg(False,'模块[%s]不存在!' % mod_name)
    try:
        run_object = getattr(def_object.main(),def_name,None)
    except:
        return public.returnMsg(False,'模块入口实例化失败' % mod_name)
    if not run_object: return public.returnMsg(False,'在[%s]模块中找不到[%s]方法' % (mod_name,def_name))
    if 'module_get_object' in args and args.module_get_object == 1:
        return run_object
    result = run_object(args)
    return result

#获取模块文件夹列表
def get_module_list():
    list = []
    class_path = public.get_class_path()
    f_list = os.listdir(class_path)
    for fname in f_list:
        f_path = class_path+'/'+fname
        if os.path.isdir(f_path) and len(fname) > 6 and fname.find('.') == -1 and fname.find('Model') != -1:
            list.append(fname)
    return list

#检查路径是否合法
def path_check(path):
    list = ["./","..",",",";",":","?","'","\"","<",">","|","\\","\n","\r","\t","\b","\a","\f","\v","*","%","&","$","#","@","!","~","`","^","(",")","+","=","{","}","[","]"]
    for i in path:
        if i in list:
            return False
    return True

#数据加密
def db_encrypt(data):
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

#数据解密
def db_decrypt(data):
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
