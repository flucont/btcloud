#coding: utf-8
import os,sys,json

#执行模块方法(模块名,方法名,参数)
def module_run(module_name, def_name, def_args):
    if not module_name or not def_name: return returnMsg(False,'模块名称和模块方法名称不能为空!')
    if not path_check(module_name) or not path_check(def_name): return returnMsg(False,'模块名或方法名不能包含特殊符号!')

    panel_path = get_panel_path()
    filename = "{}/modules/{}Module/{}Module.py".format(panel_path,module_name,module_name)
    if not os.path.exists(filename):
        filename = "{}/modules/{}Module/main.py".format(panel_path,module_name)
        if not os.path.exists(filename):
            filename = "{}/plugin/{}/main.py".format(panel_path,module_name)
            if not os.path.exists(filename):
                filename = "{}/plugin/{}/{}Plugin.py".format(panel_path,module_name,module_name)
                if not os.path.exists(filename):
                    return returnMsg(False,'指定模块或插件不存在')

    _obj = get_script_object(filename)
    if not _obj: return returnMsg(False,'模块加载失败: %s' % module_name)
    if hasattr(_obj, "items") and hasattr(_obj, "setdefault"):
        return _obj
    
    class_name = "main"
    if not hasattr(_obj, class_name):
        return returnMsg(False,'找不到入口类: %s' % class_name)
    
    class_obj = getattr(_obj,class_name, None)
    if not class_obj:
        return returnMsg(False,'获取入口类失败' % module_name)

    try:
        class_func = class_obj()
    except:
        return returnMsg(False,'模块入口实例化失败' % module_name)

    if not hasattr(class_func, def_name):
        return returnMsg(False,'在[%s]模块中找不到[%s]方法' % (class_name,def_name))
    
    def_func = getattr(class_func, def_name, None)
    if not def_func:
        return returnMsg(False,'获取方法失败')

    if 'module_get_object' in def_args:
        return def_func
    
    result = def_func(def_args)
    return result

#获取指定模块对象(文件全路径)
def get_module(filename):
    if not filename: return returnMsg(False,'模块路径不能为空!')
    if "./" in filename: return returnMsg(False,'模块路径不能为相对路径')
    return get_script_object(filename)

def get_panel_path():
    return '/www/server/bt-monitor'

def returnMsg(status,msg,args = ()):
    return {'status':status,'msg':msg}

def get_script_object(filename):
    _obj =  sys.modules.get(filename,None)
    if _obj: return _obj
    from types import ModuleType
    _obj = sys.modules.setdefault(filename, ModuleType(filename))
    _code = readFile(filename)
    _code_object = compile(_code,filename, 'exec')
    _obj.__file__ = filename
    _obj.__package__ = ''
    exec(_code_object, _obj.__dict__)
    return _obj

def readFile(filename,mode = 'r'):
    import os
    if not os.path.exists(filename): return False
    fp = None
    try:
        fp = open(filename, mode)
        f_body = fp.read()
    except Exception as ex:
        if sys.version_info[0] != 2:
            try:
                fp = open(filename, mode,encoding="utf-8")
                f_body = fp.read()
            except:
                fp = open(filename, mode,encoding="GBK")
                f_body = fp.read()
        else:
            return False
    finally:
        if fp and not fp.closed:
            fp.close()
    return f_body

#检查路径是否合法
def path_check(path):
    list = ["./","..",",",";",":","?","'","\"","<",">","|","\\","\n","\r","\t","\b","\a","\f","\v","*","%","&","$","#","@","!","~","`","^","(",")","+","=","{","}","[","]"]
    for i in path:
        if i in list:
            return False
    return True
