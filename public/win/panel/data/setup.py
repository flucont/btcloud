#coding: utf-8
# +-------------------------------------------------------------------
# | 宝塔Windows面板
# +-------------------------------------------------------------------
# | Copyright (c) 2015-2099 宝塔软件(http://bt.cn) All rights reserved.
# +-------------------------------------------------------------------
# | Author: 沐落 <cjx@bt.cn>
# +-------------------------------------------------------------------

import os,chardet,time,sys,re
import win32net, win32api, win32netcon,win32security,win32serviceutil
import traceback,shlex,datetime,subprocess,platform
import sqlite3,shutil

def readReg(path,key):
    import winreg
    try:
        newKey = winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE ,path)
        value,type = winreg.QueryValueEx(newKey, key)
        return value
    except :
        return False

panelPath = readReg(r'SOFTWARE\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall\宝塔面板','PanelPath')
if not panelPath:
    panelPath = os.getenv('BT_PANEL')
    if not panelPath: exit();

setupPath =  os.path.dirname(panelPath)

error_path = '{}/error.log'.format(setupPath)
logPath = panelPath + '/data/panelExec.log'

class Sql():
    #------------------------------
    # 数据库操作类 For sqlite3
    #------------------------------
    __DB_FILE    = None            # 数据库文件
    __DB_CONN    = None            # 数据库连接对象
    __DB_TABLE   = ""              # 被操作的表名称
    __OPT_WHERE  = ""              # where条件
    __OPT_LIMIT  = ""              # limit条件
    __OPT_ORDER  = ""              # order条件
    __OPT_FIELD  = "*"             # field条件
    __OPT_PARAM  = ()              # where值
    __LOCK = panelPath + '/data/sqlite_lock.pl'

    def __init__(self):
        self.__DB_FILE = panelPath + '/data/default.db'

    def __GetConn(self):
        #取数据库对象
        try:
            if self.__DB_CONN == None:
                self.__DB_CONN = sqlite3.connect(self.__DB_FILE)
                self.__DB_CONN.text_factory = str
        except Exception as ex:
            print(str(ex))
            return "error: " + str(ex)

    def table(self,table):
        #设置表名
        self.__DB_TABLE = table
        return self


    def where(self,where,param):
        #WHERE条件
        if where:
            self.__OPT_WHERE = " WHERE " + where
            self.__OPT_PARAM = self.__to_tuple(param)
        return self

    def __to_tuple(self,param):
        #将参数转换为tuple
        if type(param) != tuple:
            if type(param) == list:
                param = tuple(param)
            else:
                param = (param,)
        return param

    #更新数据
    def update(self,pdata):
        if not pdata: return False
        keys,param = self.__format_pdata(pdata)
        return self.save(keys,param)

    #构造数据
    def __format_pdata(self,pdata):
        keys = pdata.keys()
        keys_str = ','.join(keys)
        param = []
        for k in keys: param.append(pdata[k])
        return keys_str,tuple(param)

    def field(self,field):
        #FIELD条件
        if len(field):
            self.__OPT_FIELD = field
        return self

    def getField(self,keyName):
        #取回指定字段

        result = self.field(keyName).select()
        print(result)
        if len(result) != 0:
            return result[0][keyName]
        return result

    def __format_field(self,field):
        import re
        fields = []
        for key in field:
            s_as = re.search(r'\s+as\s+',key,flags=re.IGNORECASE)
            if s_as:
                as_tip = s_as.group()
                key = key.split(as_tip)[1]
            fields.append(key)
        return fields

    def __get_columns(self):
        if self.__OPT_FIELD == '*':
            tmp_cols = self.query('PRAGMA table_info('+self.__DB_TABLE+')',())
            cols = []
            for col in tmp_cols:
                if len(col) > 2: cols.append('`' + col[1] + '`')
            if len(cols) > 0: self.__OPT_FIELD = ','.join(cols)

    def select(self):
        #查询数据集
        self.__GetConn()
        try:
            self.__get_columns()
            sql = "SELECT " + self.__OPT_FIELD + " FROM " + self.__DB_TABLE + self.__OPT_WHERE + self.__OPT_ORDER + self.__OPT_LIMIT
            result = self.__DB_CONN.execute(sql,self.__OPT_PARAM)
            data = result.fetchall()
            #构造字典系列
            if self.__OPT_FIELD != "*":
                fields = self.__format_field(self.__OPT_FIELD.split(','))
                tmp = []
                for row in data:
                    i=0
                    tmp1 = {}
                    for key in fields:
                        tmp1[key.strip('`')] = row[i]
                        i += 1
                    tmp.append(tmp1)
                    del(tmp1)
                data = tmp
                del(tmp)
            else:
                #将元组转换成列表
                tmp = list(map(list,data))
                data = tmp
                del(tmp)
            self.__close()
            return data
        except Exception as ex:
            return "error: " + str(ex)

    def setField(self,keyName,keyValue):
        #更新指定字段
        return self.save(keyName,(keyValue,))

    def commit(self):
        self.__close()
        self.__DB_CONN.commit()


    def save(self,keys,param):
        #更新数据
        self.write_lock()
        self.__GetConn()
        self.__DB_CONN.text_factory = str
        try:
            opt = ""
            for key in keys.split(','):
                opt += key + "=?,"
            opt = opt[0:len(opt)-1]
            sql = "UPDATE " + self.__DB_TABLE + " SET " + opt+self.__OPT_WHERE

            #处理拼接WHERE与UPDATE参数
            tmp = list(self.__to_tuple(param))
            for arg in self.__OPT_PARAM:
                tmp.append(arg)
            self.__OPT_PARAM = tuple(tmp)
            result = self.__DB_CONN.execute(sql,self.__OPT_PARAM)
            self.__close()
            self.__DB_CONN.commit()
            self.rm_lock()
            return result.rowcount
        except Exception as ex:
            return "error: " + str(ex)


    def execute(self,sql,param = ()):
        #执行SQL语句返回受影响行
        self.write_lock()
        self.__GetConn()
        try:
            result = self.__DB_CONN.execute(sql,self.__to_tuple(param))
            self.__DB_CONN.commit()
            self.rm_lock()
            return result.rowcount
        except Exception as ex:
            return "error: " + str(ex)

    #是否有锁
    def is_lock(self):
        n = 0
        while os.path.exists(self.__LOCK):
            n+=1
            if n > 100:
                self.rm_lock()
                break
            time.sleep(0.01)
    #写锁
    def write_lock(self):
        self.is_lock()
        open(self.__LOCK,'wb+').close()

    #解锁
    def rm_lock(self):
        if os.path.exists(self.__LOCK):
            os.remove(self.__LOCK)

    def query(self,sql,param = ()):
        #执行SQL语句返回数据集
        self.__GetConn()
        try:
            result = self.__DB_CONN.execute(sql,self.__to_tuple(param))
            #将元组转换成列表
            data = list(map(list,result))
            return data
        except Exception as ex:
            return "error: " + str(ex)

    def __close(self):
        #清理条件属性
        self.__OPT_WHERE = ""
        self.__OPT_FIELD = "*"
        self.__OPT_ORDER = ""
        self.__OPT_LIMIT = ""
        self.__OPT_PARAM = ()


    def close(self):
        #释放资源
        try:
            self.__DB_CONN.close()
            self.__DB_CONN = None
        except:
            pass


def GetLocalIp():
    """
    取本地外网IP

    """
    try:
        filename = panelPath + '/data/iplist.txt'
        ipaddress = readFile(filename)
        if not ipaddress:

            url =  'http://www.example.com/api/getIpAddress';
            str = httpGet(url)
            writeFile(filename,ipaddress)

        ipaddress = re.search('\d+.\d+.\d+.\d+',ipaddress).group(0);
        return ipaddress
    except:
        try:
            url =  'https://www.bt.cn/Api/getIpAddress';
            str = httpGet(url)
            writeFile(filename,ipaddress)
            return str
        except:
            pass

def get_error_info():
    errorMsg = traceback.format_exc();
    return errorMsg


def get_server_status(name):
    try:
        serviceStatus = win32serviceutil.QueryServiceStatus(name)
        if serviceStatus[1] == 4:
            return 1
        return 0
    except :
        return -1

def start_service(name):

    try:
        timeout = 0;
        while get_server_status(name) == 0:
            try:
                win32serviceutil.StartService(name)
                time.sleep(1);
            except : time.sleep(1);
            timeout += 1
            if timeout > 10:break

        if get_server_status(name) != 0:
            return True,None
        return False,'操作失败，10秒内未完成启动服务【{}】'.format(name)
    except :
        return False,get_error_info()

def stop_service(name):
    try:
        timeout = 0;
        while get_server_status(name) == 1:
            try:
                win32serviceutil.StopService(name)
                time.sleep(1);
            except : time.sleep(1);
            timeout += 1
            if timeout > 10:break

        if get_server_status(name) != 1:
            return True,None
        return False,'操作失败，10秒内未完成启动服务【{}】'.format(name)
    except :
        return False,get_error_info()

def delete_server(name):
    try:
        stop_service(name)
        win32serviceutil.RemoveService(name)
        return True,''
    except :
        return False,get_error_info()

def get_requests_headers():
    return {"Content-type":"application/x-www-form-urlencoded","User-Agent":"BT-Panel"}

def downloadFile(url,filename):
    try:
        import requests
        res = requests.get(url,verify=False)
        with open(filename,"wb") as f:
            f.write(res.content)
    except:
        import requests
        res = requests.get(url,verify=False)
        with open(filename,"wb") as f:
            f.write(res.content)


def downloadFileByWget(url,filename):
    """
    wget下载文件
    @url 下载地址
    @filename 本地文件路径
    """
    try:
        if os.path.exists(logPath): os.remove(logPath)
    except : pass
    loacl_path =  '{}/script/wget.exe'.format(panelPath)
    if not os.path.exists(loacl_path):  downloadFile(get_url()+'/win/panel/data/wget.exe',loacl_path)

    if os.path.getsize(loacl_path) < 10:
        os.remove(loacl_path)
        downloadFile(url,filename)
    else:
        shell = "{} {} -O {} -t 5 -T 60 --no-check-certificate --auth-no-challenge --force-directorie > {} 2>&1".format(loacl_path,url,filename,logPath)
        os.system(shell)

        num = 0
        re_size = 0
        while num <= 5:
            if os.path.exists(filename):
                cr_size = os.path.getsize(filename)
                if re_size > 0 and re_size == cr_size:
                    break;
                else:
                    re_size = cr_size
            time.sleep(0.5)
            num += 1

        if os.path.exists(filename):
            if os.path.getsize(filename) < 1:
                os.remove(filename)
                downloadFile(url,filename)
        else:
            downloadFile(url,filename)

def writeFile(filename,s_body,mode='w+',encoding = 'utf-8'):
    try:
        fp = open(filename, mode,encoding = encoding);
        fp.write(s_body)
        fp.close()
        return True
    except:
        return False

def readFile(filename,mode = 'r'):

    import os,chardet
    if not os.path.exists(filename): return False
    if not os.path.isfile(filename): return False

    encoding = 'utf-8'
    f_body = '';
    try:
        fp = open(filename, mode,encoding = encoding)
        f_body = fp.read()
    except :
        fp.close()

        try:
            encoding = 'gbk'
            fp = open(filename, mode,encoding = encoding)
            f_body = fp.read()
        except :
            fp.close()

            encoding = 'ansi'
            fp = open(filename, mode,encoding = encoding)
            f_body = fp.read()

    try:
        if f_body[0] == '\ufeff':
            #处理带bom格式
            new_code = chardet.detect(f_body.encode(encoding))["encoding"]
            f_body = f_body.encode(encoding).decode(new_code);
    except : pass

    fp.close()
    return f_body

def httpGet(url,timeout = 60,headers = {}):
    try:
        import urllib.request,ssl
        try:
            ssl._create_default_https_context = ssl._create_unverified_context
        except:pass;
        req = urllib.request.Request(url,headers = headers)
        response = urllib.request.urlopen(req,timeout = timeout)
        result = response.read()
        if type(result) == bytes:
            try:
                result = result.decode('utf-8')
            except :
                result = result.decode('gb2312')
        return result
    except Exception as ex:
        if headers: return False
        return str(ex)

def httpPost(url, data, timeout=60, headers={}):

    try:
        import urllib.request,ssl
        try:
            ssl._create_default_https_context = ssl._create_unverified_context
        except:pass;
        data2 = urllib.parse.urlencode(data).encode('utf-8')
        req = urllib.request.Request(url, data2,headers = headers)
        response = urllib.request.urlopen(req,timeout = timeout)
        result = response.read()
        if type(result) == bytes: result = result.decode('utf-8')

        return result
    except Exception as ex:

        return str(ex);


def get_timeout(url,timeout=3):

    try:
        start = time.time()
        result = int(httpGet(url,timeout))
        return result,int((time.time() - start) * 1000 - 500)
    except: return 0,False

def get_url(timeout = 0.5):
    import json
    try:
        #
        node_list = [{"protocol":"http://","address":"dg2.bt.cn","port":"80","ping":500},{"protocol":"http://","address":"dg1.bt.cn","port":"80","ping":500},{"protocol":"http://","address":"download.bt.cn","port":"80","ping":500},{"protocol":"http://","address":"hk1-node.bt.cn","port":"80","ping":500},{"protocol":"http://","address":"na1-node.bt.cn","port":"80","ping":500},{"protocol":"http://","address":"jp1-node.bt.cn","port":"80","ping":500}]

        mnode1 = []
        mnode2 = []
        mnode3 = []
        for node in node_list:
            node['net'],node['ping'] = get_timeout(node['protocol'] + node['address'] + ':' + node['port'] + '/net_test',1)
            if not node['ping']: continue
            if node['ping'] < 100:      #当响应时间<100ms且可用带宽大于1500KB时
                if node['net'] > 1500:
                    mnode1.append(node)
                elif node['net'] > 1000:
                    mnode3.append(node)
            else:
                if node['net'] > 1000:  #当响应时间>=100ms且可用带宽大于1000KB时
                    mnode2.append(node)
            if node['ping'] < 100:
                if node['net'] > 3000: break #有节点可用带宽大于3000时，不再检查其它节点
        if mnode1: #优选低延迟高带宽
            mnode = sorted(mnode1,key= lambda  x:x['net'],reverse=True)
        elif mnode3: #备选低延迟，中等带宽
            mnode = sorted(mnode3,key= lambda  x:x['net'],reverse=True)
        else: #终选中等延迟，中等带宽
            mnode = sorted(mnode2,key= lambda  x:x['ping'],reverse=False)

        if not mnode: return 'https://download.bt.cn'
        #return mnode[0]['protocol'] + mnode[0]['address'] + ':' + mnode[0]['port']
        return "https://" + mnode[0]['address']
    except:
        return 'https://download.bt.cn'



#删除文件权限
def del_file_access(filename,user):
    try:

        if filename.lower() in ["c:/","c:","c:\\","c"]:
            return True
        import win32security
        sd = win32security.GetFileSecurity(filename, win32security.DACL_SECURITY_INFORMATION)
        dacl = sd.GetSecurityDescriptorDacl()
        ace_count = dacl.GetAceCount()

        for i in range(ace_count ,0 ,-1):
            try:
                data = {}
                data['rev'], data['access'], usersid = dacl.GetAce(i-1)
                data['user'],data['group'], data['type'] = win32security.LookupAccountSid('', usersid)
                if data['user'].lower() == user.lower(): dacl.DeleteAce(i-1) #删除旧的dacl
                if data['user'].lower() == 'users': dacl.DeleteAce(i-1) #删除旧的dacl

            except :
                try:
                    #处理拒绝访问
                    dacl.DeleteAce(i-1)
                except : pass
        sd.SetSecurityDescriptorDacl(1, dacl, 0)
        win32security.SetFileSecurity(filename, win32security.DACL_SECURITY_INFORMATION, sd)
    except :
        pass
    return True

def set_file_access(filename,user,access):
    try:
        sd = win32security.GetFileSecurity(filename, win32security.DACL_SECURITY_INFORMATION)
        dacl = sd.GetSecurityDescriptorDacl()
        ace_count = dacl.GetAceCount()

        for i in range(ace_count, 0,-1):
            try:
                data = {}
                data['rev'], data['access'], usersid = dacl.GetAce(i-1)
                data['user'],data['group'], data['type'] = win32security.LookupAccountSid('', usersid)
                if data['user'].lower() == user.lower(): dacl.DeleteAce(i-1) #删除旧的dacl
                if data['user'].lower() == 'users': dacl.DeleteAce(i-1) #删除旧的dacl

            except :
                pass
        try:
            userx, domain, type = win32security.LookupAccountName("", user)
        except :
            userx, domain, type = win32security.LookupAccountName("", 'IIS APPPOOL\\' + user)
        if access > 0:  dacl.AddAccessAllowedAceEx(win32security.ACL_REVISION, 3, access, userx)

        sd.SetSecurityDescriptorDacl(1, dacl, 0)
        win32security.SetFileSecurity(filename, win32security.DACL_SECURITY_INFORMATION, sd)
        return True,None
    except :
        return False,get_error_info()

def ExecShell(cmdstring, cwd=None, timeout=None, shell=True):
    if shell:
        cmdstring_list = cmdstring
    else:
        cmdstring_list = shlex.split(cmdstring)

    if timeout:
        end_time = datetime.datetime.now() + datetime.timedelta(seconds=timeout)

    sub = subprocess.Popen(cmdstring_list, cwd=cwd, stdin=subprocess.PIPE,shell=shell,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    while sub.poll() is None:
        time.sleep(0.1)
        if timeout:
            if end_time <= datetime.datetime.now():
                raise Exception("Timeout：%s"%cmdstring)
    a,e = sub.communicate()
    if type(a) == bytes:
        try:
            a = a.decode('utf-8')
        except :
            a = a.decode('gb2312','ignore')

    if type(e) == bytes:
        try:
            e = e.decode('utf-8')
        except :
            e = e.decode('gb2312','ignore')
    return a,e

def GetRandomString(length):
    from random import Random
    strings = ''
    chars = 'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz0123456789'
    chrlen = len(chars) - 1
    random = Random()
    for i in range(length):
        strings += chars[random.randint(0, chrlen)]
    return strings

def GetRandomString1(length):
    from random import Random
    strings = ''
    chars = '0123456789'
    chrlen = len(chars) - 1
    random = Random()
    for i in range(length):
        strings += chars[random.randint(0, chrlen)]
    return strings

def GetRandomString2(length):
    from random import Random
    strings = ''
    chars = '!@#$%^&*()_+.,?[]-='
    chrlen = len(chars) - 1
    random = Random()
    for i in range(length):
        strings += chars[random.randint(0, chrlen)]
    return strings

def chdck_salt():

    sql = Sql()
    sql.table('users').execute("ALTER TABLE 'users' ADD 'salt' TEXT",())

    u_list = sql.table('users').field('id,username,password,salt').select()
    for u_info in u_list:
        salt = GetRandomString(12) #12位随机
        pdata = {}
        pdata['password'] = md5(md5(u_info['password']+'_bt.cn') + salt)
        pdata['salt'] = salt
        sql.table('users').where('id=?',(u_info['id'],)).update(pdata)

def md5(strings):
    """
    生成MD5
    @strings 要被处理的字符串
    return string(32)
    """
    import hashlib
    m = hashlib.md5()

    m.update(strings.encode('utf-8'))
    return m.hexdigest()

def password_salt(password,username=None,uid=None):

    chdck_salt()
    sql = Sql()

    if not uid:
        if not username:
            raise Exception('username或uid必需传一项')
        uid = sql.table('users').where('username=?',(username,)).getField('id')
    salt = sql.table('users').where('id=?',(uid,)).getField('salt')
    return md5(md5(password+'_bt.cn')+salt)

def check_user(username):
    resume = 0
    while True:
        data, total, resume = win32net.NetUserEnum(None, 3, win32netcon.FILTER_NORMAL_ACCOUNT, resume)
        for user in data:
            if user['name'] == username: return True
        if not resume: break
    return False

def add_user(username,password,ps):
    try:
        if not check_user(username):
            d = {}
            d['name'] = username
            d['password'] = password
            d['comment'] = ps
            d['flags'] = win32netcon.UF_NORMAL_ACCOUNT | win32netcon.UF_SCRIPT
            d['priv'] = win32netcon.USER_PRIV_USER
            win32net.NetUserAdd(None, 1, d)

            #设置用户允许登录服务
            handle = win32security.LsaOpenPolicy(None, win32security.POLICY_ALL_ACCESS)
            sid_obj, domain, tmp = win32security.LookupAccountName(None, username)
            win32security.LsaAddAccountRights(handle, sid_obj, ('SeServiceLogonRight',) )
            win32security.LsaClose( handle)

            if not check_user(username): return False, '添加用户[{}]失败.'.format(username)
            writeFile('{}/data/{}'.format(panelPath,username),password)
            return True , None
        else:
            ExecShell('net user "{}" "{}"'.format(username,password))
            writeFile('{}/data/{}'.format(panelPath,username),password)
            return True , None
    except :
        return False,get_error_info()

def add_user_bywww():

    pwd = GetRandomString(64) + GetRandomString1(32) + GetRandomString2(32)
    status,error = add_user('www',pwd,'用于启动宝塔安装的程序,删除后会导致部分软件无法启动,请勿删除')
    if not status:
        writeFile(error_path,error)
        return False
    return True

def add_user_bymysql():

    pwd = GetRandomString(64) + GetRandomString1(32) + GetRandomString2(32)
    status,error = add_user('mysql',pwd,'用于启动宝塔安装的程序,删除后会导致部分软件无法启动,请勿删除')
    if not status:
        writeFile(error_path,error)
        return False
    return True

def getIP(url):
    import socket,re

    tmp = re.search('http://(.+)\:\d*',url)
    if tmp:
        domain = tmp.groups()[0]
        myaddr = socket.getaddrinfo(domain, 'http')
        return myaddr[0][4][0]
    return ''


def add_panel_dir():
    try:
        slist = [
                    [panelPath , [] ],
                    ['{}/data'.format(panelPath) , [] ],
                    ['{}/script'.format(panelPath) , [] ],
                    ['{}/backup'.format(panelPath) , [] ],
                    ['{}/backup/database/sqlserver'.format(setupPath[:2]) , [ 'Authenticated Users']],
                    ['{}/wwwroot'.format(setupPath[:2]) , [ 'IIS_IUSRS','www'] ],
                    ['{}/wwwlogs'.format(setupPath) , [ 'IIS_IUSRS','www'] ],
                    ['{}/php'.format(setupPath) , [ 'IIS_IUSRS','www'] ],
                    ['{}/mysql'.format(setupPath) , [ 'mysql'] ],
                    ['{}/temp'.format(setupPath) , [ 'IIS_IUSRS','www'] ],
                    ['{}/temp/session'.format(setupPath) , [ 'IIS_IUSRS','www'] ],
                    ['C:/Temp' , [ 'IIS_IUSRS','www'] ],
                ]

        is_break = False
        for sobj in slist:
            if not os.path.exists(sobj[0]):
                os.makedirs(sobj[0])
                n = 0
                while n < 5:
                    if os.path.exists(sobj[0]): break

                    os.makedirs(sobj[0])
                    time.sleep(0.5)
                    n += 1

                if not os.path.exists(sobj[0]):
                    writeFile(error_path,"自动创建目录【{}】失败，已重试最大次数 5 次，请手动创建该目录后重新安装".format(sobj[0]))
                    return False

                del_file_access(sobj[0],'users')

                for user in sobj[1]:
                    n = 0
                    while n < 3:
                        status,error = set_file_access(sobj[0],user,2032127)
                        if status: break
                        time.sleep(0.5)

                    if not status:
                        writeFile(error_path,"目录{}设置{}权限设置错误 -> {}".format(sobj[0],user,error))
                        break

        del_file_access(setupPath,'users')
        url = get_url()

        files = ['default.db','session.db','system.db','phplib.win','defaultDoc.html','404.html']
        for f_name in files:
            local_path = '{}/data/{}'.format(panelPath,f_name)
            download_url = '{}/win/panel/data/{}'.format(url,f_name)

            n = 0
            while n < 10:
                n += 1;

                try:
                    if os.path.exists(local_path) and os.path.getsize(local_path) < 10: os.remove(local_path)
                    if not os.path.exists(local_path): downloadFileByWget(download_url,local_path)
                    if os.path.getsize(local_path) and os.path.getsize(local_path) > 10: break;

                    writeFile(error_path,'download {} error ->> {} \r\n {}'.format(f_name,download_url,""))
                except :
                    ip = getIP(url)
                    writeFile(error_path,'download {} error ->> {}  \r\n connect {} \r\n {}'.format(ip,f_name,download_url,get_error_info()))

                if n > 5: return False
                time.sleep(0.2)

        return True
    except :
        writeFile(error_path,get_error_info())
        return False

def unzip(src_path,dst_path):
    import zipfile
    zip_file = zipfile.ZipFile(src_path)
    for names in zip_file.namelist():
        zip_file.extract(names,dst_path)
    zip_file.close()
    return True

def to_path(path):
    return path.replace('/','\\')

def download_panel(file_list = []):
    try:
        url = 'http://www.example.com'

        ExecShell("taskkill /f /t /im BtTools.exe")

        #下载面板
        loacl_path = setupPath + '/panel.zip'
        tmpPath = "{}/temp/panel".format(setupPath)
        if os.path.exists(loacl_path): os.remove(loacl_path)
        if os.path.exists(tmpPath): shutil.rmtree(tmpPath,True)
        if not os.path.exists(tmpPath): os.makedirs(tmpPath)

        p_ver = sys.argv[2]
        downUrl =  url + '/win/panel/panel_' + p_ver + '.zip';
        downloadFileByWget(downUrl,loacl_path);
        unzip(loacl_path,tmpPath)

        for ff_path in file_list:
            if os.path.exists(tmpPath + '/' + ff_path): os.remove(tmpPath + '/' + ff_path)

        tcPath = '{}\class'.format(tmpPath)
        for name in os.listdir(tcPath):
            try:
                if name.find('win_amd64.pyd') >=0:
                    oldName = os.path.join(tcPath,name);
                    lName = name.split('.')[0] + '.pyd'
                    newName = os.path.join(tcPath,lName)
                    if not os.path.exists(newName):os.rename(oldName,newName)
            except :pass

        cPath = '{}/panel/class'.format(setupPath)

        if os.path.exists(cPath):
            os.system("del /s {}\*.pyc".format(to_path(cPath)))
            os.system("del /s {}\*.pyt".format(to_path(cPath)))
            for name in os.listdir(cPath):
                try:
                    if name.find('.pyd') >=0:
                        oldName = os.path.join(cPath,name)
                        newName = os.path.join(cPath,GetRandomString(8) + '.pyt')
                        os.rename(oldName,newName)
                except : pass
            os.system("del /s {}\*.pyc".format(to_path(cPath)))
            os.system("del /s {}\*.pyt".format(to_path(cPath)))

        os.system("xcopy /s /c /e /y /r {} {}".format(to_path(tmpPath),to_path(panelPath)))
        try:
            os.remove(loacl_path)
        except : pass

        try:
            shutil.rmtree(tmpPath,True)
        except : pass

        s_ver = platform.platform()
        net_v = '45'
        if s_ver.find('2008') >= 0: net_v = '20'
        writeFile('{}/data/net'.format(setupPath),net_v)

        local_path = '{}/script/BtTools.exe'.format(panelPath)
        downloadFileByWget('{}/win/panel/BtTools{}.exe'.format(url,net_v),local_path)
        if os.path.getsize(local_path) > 128:
            return True
        return False
        downloadFileByWget('{}/win/panel/data/softList.conf'.format(url),'{}/data/softList.conf'.format(panelPath))
        try:
            from gevent import monkey
        except :
            os.system('"C:\Program Files\python\python.exe" -m pip install gevent')
    except :
        writeFile(error_path,get_error_info())

def update_panel():

    file_list = ['config/config.json','config/index.json','data/libList.conf','data/plugin.json']
    download_panel(file_list)

    py_path = 'C:/Program Files/python/python.exe'

    ExecShell("\"{}\" {}/panel/runserver.py --startup auto install".format(py_path,setupPath))
    ExecShell("\"{}\" {}/panel/task.py --startup auto install".format(py_path,setupPath))

    print("升级成功，重启面板后生效..")

def init_panel_data():
    try:
        sql =  Sql()
        username = sql.table('users').where('id=?',(1,)).getField('username')
        if username == 'admin':
            username = GetRandomString(8)
            password = GetRandomString(8)
            writeFile(panelPath + '/data/default.pl',password)

            sql.table('users').where('id=?',(1,)).setField('username',username)
            pwd = password_salt(md5(password),uid=1)

            result = sql.table('users').where('id=?',(1,)).setField('password',pwd)

            backup_path = panelPath[:2] + '/backup'
            www_path = panelPath[:2] + '/wwwroot'

            if not os.path.exists(backup_path): os.makedirs(backup_path)
            if not os.path.exists(www_path): os.makedirs(www_path)

            sql.table('config').where('id=?',(1,)).setField('backup_path',backup_path)
            sql.table('config').where('id=?',(1,)).setField('sites_path',www_path)

            bind_path = panelPath+ '/data/bind_path.pl'
            if not os.path.exists(bind_path): writeFile(bind_path,'True')

        admin_path = panelPath+ '/data/admin_path.pl'
        if not os.path.exists(admin_path): writeFile(admin_path,"/" + GetRandomString(8))

        port_path = panelPath+ '/data/port.pl'
        if not os.path.exists(port_path): writeFile(port_path,'8888')

        recycle_bin_db = panelPath+ '/data/recycle_bin_db.pl'
        if not os.path.exists(recycle_bin_db): writeFile(recycle_bin_db,'True')

        recycle_bin = panelPath+ '/data/recycle_bin.pl'
        if not os.path.exists(recycle_bin): writeFile(recycle_bin,'True')

        conf_path = panelPath + '/config/config.json'
        if os.path.exists(conf_path):
            conf = readFile(conf_path).replace('[PATH]',setupPath.replace('\\','/'))
            writeFile(conf_path,conf)

        GetLocalIp()

        return True
    except :
        writeFile(error_path,get_error_info())
        return False

def add_panel_services(num = 0):
   try:
        py_path = 'C:/Program Files/python/python.exe'

        delete_server('btPanel')
        ret = ExecShell("\"{}\" {}/panel/runserver.py --startup auto install".format(py_path,setupPath))

        delete_server('btTask')
        ret1 = ExecShell("\"{}\" {}/panel/task.py --startup auto install".format(py_path,setupPath))

        if get_server_status('btPanel') < 0 or get_server_status('btTask') < 0:
            if num <= 0 :
                localPath = setupPath + "/temp/Time_Zones.reg";
                downloadFileByWget(get_url() + '/win/panel/data/Time_Zones.reg',localPath)
                ExecShell("regedit /s " + localPath)

                add_panel_services(1)
            else:
                writeFile(error_path,ret[0] + ret[1] + ret1[0] + ret1[1])
        else:
            os.system('sc failure btPanel reset=1800 actions=restart/60000/restart/120000/restart/30000')
            os.system('sc failure btTask reset=1800 actions=restart/60000/restart/120000/restart/30000')
            start_service('btPanel')
            start_service('btTask')
   except :
       writeFile(error_path,get_error_info())


def add_firewall_byport():

    conf = ExecShell('netsh advfirewall firewall show rule "宝塔面板"')[0]
    if conf.lower().find('tcp') == -1:
        ExecShell("netsh advfirewall firewall add rule name=宝塔面板 dir=in action=allow protocol=tcp localport=8888");
        ExecShell("netsh advfirewall firewall add rule name=网站访问端口 dir=in action=allow protocol=tcp localport=80");
        ExecShell("netsh advfirewall firewall add rule name=远程桌面 dir=in action=allow protocol=tcp localport=3389");
        ExecShell("netsh advfirewall firewall add rule name=HTTPS端口 dir=in action=allow protocol=tcp localport=443");
        ExecShell("netsh advfirewall firewall add rule name=FTP主动端口 dir=in action=allow protocol=tcp localport=21");
        ExecShell("netsh advfirewall firewall add rule name=FTP被动端口 dir=in action=allow protocol=tcp localport=3000-4000");

def get_error_log():
    error = readFile(error_path)
    try:
        data = {}
        data['msg'] = 'setup'
        data['os'] = 'Windows'
        data['error'] = error
        data['version'] = ''
        httpPost('http://www.example.com/api/wpanel/PanelBug',data)
    except :
        pass
    return error

if __name__ == "__main__":
    stype = sys.argv[1];
    if not stype in ['get_error_log']:
        if os.path.exists(error_path): os.remove(error_path)
    result = eval('{}()'.format(stype))
    print(result)




