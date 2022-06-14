#coding: utf-8
import public,os,sys,json

class Plugin:
    name = False
    p_path = None
    is_php = False
    plu = None
    __api_root_url = 'https://api.bt.cn'
    __api_url = __api_root_url+ '/wpanel/get_plugin_list'
    __cache_file = 'data/plugin_list.json'

    def __init__(self, name):
        self.name = name
        self.p_path = public.get_plugin_path(name)
        self.is_php = os.path.exists(self.p_path + '/index.php')

    def get_plugin_list(self, force = False):
        if force==False and os.path.exists(self.__cache_file):
            jsonData = public.readFile(self.__cache_file)
            softList = json.loads(jsonData)
        else:
            try:
                jsonData = public.HttpGet(self.__api_url)
            except Exception as ex:
                raise public.error_conn_cloud(str(ex))
            softList = json.loads(jsonData)
            if type(softList)!=dict or 'list' not in softList: raise Exception('云端插件列表获取失败')
            public.writeFile(self.__cache_file, jsonData)

        return softList
        
    def isdef(self, fun):
        if not self.is_php:
            sys.path.append(self.p_path)
            plugin_main = __import__(self.name + '_main')
            try:
                from imp import reload
                reload(plugin_main)
            except:
                pass
            self.plu = eval('plugin_main.' + self.name + '_main()')
            if not hasattr(self.plu, fun):
                if self.name == 'btwaf' and fun == 'index':
                    raise Exception("未购买")
                return False
        return True

    def exec_fun(self, args):
        fun = args.s
        if not self.is_php:
            plu = self.plu
            data = eval('plu.' + fun + '(args)')
        else:
            import panelPHP
            args.s = fun
            args.name = self.name
            data = panelPHP.panelPHP(self.name).exec_php_script(args)
        return data

