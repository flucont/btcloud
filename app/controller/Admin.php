<?php

namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\View;
use think\facade\Request;
use think\facade\Cache;
use app\lib\Btapi;
use app\lib\Plugins;

class Admin extends BaseController
{
    public function verifycode()
    {
        return captcha();
    }

    public function login(){
        if(request()->islogin){
            return redirect('/admin');
        }
        if(request()->isAjax()){
            $username = input('post.username',null,'trim');
            $password = input('post.password',null,'trim');
            $code = input('post.code',null,'trim');
    
            if(empty($username) || empty($password)){
                return json(['code'=>-1, 'msg'=>'用户名或密码不能为空']);
            }
            if(!captcha_check($code)){
                return json(['code'=>-1, 'msg'=>'验证码错误']);
            }
            if($username == config_get('admin_username') && $password == config_get('admin_password')){
                Db::name('log')->insert(['uid' => 0, 'action' => '登录后台', 'data' => 'IP:'.$this->clientip, 'addtime' => date("Y-m-d H:i:s")]);
                $session = md5($username.config_get('admin_password'));
                $expiretime = time()+2562000;
                $token = authcode("{$username}\t{$session}\t{$expiretime}", 'ENCODE', config_get('syskey'));
                cookie('admin_token', $token, ['expire' => $expiretime, 'httponly' => true]);
                config_set('admin_lastlogin', date('Y-m-d H:i:s'));
                return json(['code'=>0]);
            }else{
                return json(['code'=>-1, 'msg'=>'用户名或密码错误']);
            }
        }
        return view();
    }

    public function logout()
    {
        cookie('admin_token', null);
        return redirect('/admin/login');
    }

    public function index()
    {
        $stat = ['total'=>0, 'free'=>0, 'pro'=>0, 'ltd'=>0, 'third'=>0];
        $json_arr = Plugins::get_plugin_list();
        if($json_arr){
            foreach($json_arr['list'] as $plugin){
                $stat['total']++;
                if($plugin['type']==10) $stat['third']++;
                elseif($plugin['type']==12) $stat['ltd']++;
                elseif($plugin['type']==8) $stat['pro']++;
                elseif($plugin['type']==5 || $plugin['type']==6 || $plugin['type']==7) $stat['free']++;
            }
        }
        $stat['runtime'] = Db::name('config')->where('key','runtime')->value('value') ?? '<font color="red">未运行</font>';
        $stat['record_total'] = Db::name('record')->count();
        $stat['record_isuse'] = Db::name('record')->whereTime('usetime', '>=', strtotime('-7 days'))->count();
        View::assign('stat', $stat);

        $tmp = 'version()';
        $mysqlVersion = Db::query("select version()")[0][$tmp];
        $info = [
            'framework_version' => app()::VERSION,
            'php_version' => PHP_VERSION,
            'mysql_version' => $mysqlVersion,
            'software' => $_SERVER['SERVER_SOFTWARE'],
            'os' => php_uname(),
            'date' => date("Y-m-d H:i:s"),
        ];
        View::assign('info', $info);
        return view();
    }

    public function set(){
        if(request()->isAjax()){
            $params = Request::param();

            foreach ($params as $key => $value) {
                config_set($key, $value);
            }
            cache('configs', NULL);
            return json(['code'=>0]);
        }
        $mod = input('param.mod', 'sys');
        View::assign('mod', $mod);
        View::assign('conf', config('sys'));
        $runtime = Db::name('config')->where('key','runtime')->value('value') ?? '<font color="red">未运行</font>';
        View::assign('runtime', $runtime);
        return view();
    }

    public function setaccount(){
        $params = Request::param();
        if(isset($params['username']))$params['username']=trim($params['username']);
        if(isset($params['oldpwd']))$params['oldpwd']=trim($params['oldpwd']);
        if(isset($params['newpwd']))$params['newpwd']=trim($params['newpwd']);
        if(isset($params['newpwd2']))$params['newpwd2']=trim($params['newpwd2']);

        if(empty($params['username'])) return json(['code'=>-1, 'msg'=>'用户名不能为空']);

        config_set('admin_username', $params['username']);

        if(!empty($params['oldpwd']) && !empty($params['newpwd']) && !empty($params['newpwd2'])){
            if(config_get('admin_password') != $params['oldpwd']){
                return json(['code'=>-1, 'msg'=>'旧密码不正确']);
            }
            if($params['newpwd'] != $params['newpwd2']){
                return json(['code'=>-1, 'msg'=>'两次新密码输入不一致']);
            }
            config_set('admin_password', $params['newpwd']);
        }
        cache('configs', NULL);
        cookie('admin_token', null);
        return json(['code'=>0]);
    }

    public function testbturl(){
        $bt_url = input('post.bt_url');
        $bt_key = input('post.bt_key');
        if(!$bt_url || !$bt_key)return json(['code'=>-1, 'msg'=>'参数不能为空']);
        $btapi = new Btapi($bt_url, $bt_key);
        $result = $btapi->get_config();
        if($result && isset($result['status']) && ($result['status']==1 || isset($result['sites_path']))){
            $result = $btapi->get_user_info();
            if($result && isset($result['username'])){
                return json(['code'=>0, 'msg'=>'面板连接测试成功！']);
            }else{
                return json(['code'=>-1, 'msg'=>'面板连接测试成功，但未安装专用插件']);
            }
        }else{
            return json(['code'=>-1, 'msg'=>isset($result['msg'])?$result['msg']:'面板地址无法连接']);
        }
    }

    public function plugins(){
        $typelist = [];
        $json_arr = Plugins::get_plugin_list();
        if($json_arr){
            foreach($json_arr['type'] as $type){
                $typelist[$type['id']] = $type['title'];
            }
        }
        View::assign('typelist', $typelist);
        return view();
    }

    public function pluginswin(){
        $typelist = [];
        $json_arr = Plugins::get_plugin_list('Windows');
        if($json_arr){
            foreach($json_arr['type'] as $type){
                $typelist[$type['id']] = $type['title'];
            }
        }
        View::assign('typelist', $typelist);
        return view();
    }

    public function plugins_data(){
        $type = input('post.type/d');
        $keyword = input('post.keyword', null, 'trim');
        $os = input('get.os');
        if(!$os) $os = 'Linux';

        $json_arr = Plugins::get_plugin_list($os);
        if(!$json_arr) return json([]);

        $typelist = [];
        foreach($json_arr['type'] as $row){
            $typelist[$row['id']] = $row['title'];
        }

        $list = [];
        foreach($json_arr['list'] as $plugin){
            if($type > 0 && $plugin['type']!=$type) continue;
            if(!empty($keyword) && $keyword != $plugin['name'] && stripos($plugin['title'], $keyword)===false) continue;
            $versions = [];
            foreach($plugin['versions'] as $version){
                $ver = $version['m_version'].'.'.$version['version'];
                if(isset($version['download'])){
                    $status = false;
                    if(file_exists(get_data_dir().'plugins/other/'.$version['download'])){
                        $status = true;
                    }
                    $versions[] = ['status'=>$status, 'type'=>1, 'version'=>$ver, 'download'=>$version['download'], 'md5'=>$version['md5']];
                }else{
                    $status = false;
                    if(file_exists(get_data_dir($os).'plugins/package/'.$plugin['name'].'-'.$ver.'.zip')){
                        $status = true;
                    }
                    $versions[] = ['status'=>$status, 'type'=>0, 'version'=>$ver];
                }
            }
            if($plugin['name'] == 'obs') $plugin['ps'] = substr($plugin['ps'],0,strpos($plugin['ps'],'<a '));
            $list[] = [
                'id' => $plugin['id'],
                'name' => $plugin['name'],
                'title' => $plugin['title'],
                'type' => $plugin['type'],
                'typename' => $typelist[$plugin['type']],
                'desc' => str_replace('target="_blank"','target="_blank" rel="noopener noreferrer"',$plugin['ps']),
                'price' => $plugin['price'],
                'author' => isset($plugin['author']) ? $plugin['author'] : '官方',
                'versions' => $versions
            ];
        }
        return json($list);
    }

    public function download_plugin(){
        $name = input('post.name', null, 'trim');
        $version = input('post.version', null, 'trim');
        $os = input('post.os');
        if(!$os) $os = 'Linux';
        if(!$name || !$version) return json(['code'=>-1, 'msg'=>'参数不能为空']);
        try{
            Plugins::download_plugin($name, $version, $os);
            Db::name('log')->insert(['uid' => 0, 'action' => '下载插件', 'data' => $name.'-'.$version.' os:'.$os, 'addtime' => date("Y-m-d H:i:s")]);
            return json(['code'=>0,'msg'=>'下载成功']);
        }catch(\Exception $e){
            return json(['code'=>-1, 'msg'=>$e->getMessage()]);
        }
    }

    public function refresh_plugins(){
        $os = input('get.os');
        if(!$os) $os = 'Linux';
        try{
            Plugins::refresh_plugin_list($os);
            Db::name('log')->insert(['uid' => 0, 'action' => '刷新插件列表', 'data' => '刷新'.$os.'插件列表成功', 'addtime' => date("Y-m-d H:i:s")]);
            return json(['code'=>0,'msg'=>'获取最新插件列表成功！']);
        }catch(\Exception $e){
            return json(['code'=>-1, 'msg'=>$e->getMessage()]);
        }
    }

    public function record(){
        return view();
    }

    public function record_data(){
        $ip = input('post.ip', null, 'trim');
        $offset = input('post.offset/d');
        $limit = input('post.limit/d');

        $select = Db::name('record');
        if(!empty($ip)){
            $select->where('ip', $ip);
        }
        $total = $select->count();
        $rows = $select->order('id','desc')->limit($offset, $limit)->select();

        return json(['total'=>$total, 'rows'=>$rows]);
    }

    public function log(){
        return view();
    }

    public function log_data(){
        $action = input('post.action', null, 'trim');
        $offset = input('post.offset/d');
        $limit = input('post.limit/d');

        $select = Db::name('log');
        if(!empty($action)){
            $select->where('action', $action);
        }
        $total = $select->count();
        $rows = $select->order('id','desc')->limit($offset, $limit)->select();

        return json(['total'=>$total, 'rows'=>$rows]);
    }

    public function list(){
        $type = input('param.type', 'black');
        View::assign('type', $type);
        View::assign('typename', $type=='white'?'白名单':'黑名单');
        return view();
    }

    public function list_data(){
        $type = input('param.type', 'black');
        $ip = input('post.ip', null, 'trim');
        $offset = input('post.offset/d');
        $limit = input('post.limit/d');

        $tablename = $type == 'black' ? 'black' : 'white';
        $select = Db::name($tablename);
        if(!empty($ip)){
            $select->where('ip', $ip);
        }
        $total = $select->count();
        $rows = $select->order('id','desc')->limit($offset, $limit)->select();

        return json(['total'=>$total, 'rows'=>$rows]);
    }

    public function list_op(){
        $type = input('param.type', 'black');
        $tablename = $type == 'black' ? 'black' : 'white';
        $act = input('post.act', null);
        if($act == 'get'){
            $id = input('post.id/d');
            if(!$id) return json(['code'=>-1, 'msg'=>'no id']);
            $data = Db::name($tablename)->where('id', $id)->find();
            return json(['code'=>0, 'data'=>$data]);
        }elseif($act == 'add'){
            $ip = input('post.ip', null, 'trim');
            if(!$ip) return json(['code'=>-1, 'msg'=>'IP不能为空']);
            if(Db::name($tablename)->where('ip', $ip)->find()){
                return json(['code'=>-1, 'msg'=>'该IP已存在']);
            }
            Db::name($tablename)->insert([
                'ip' => $ip,
                'enable' => 1,
                'addtime' => date("Y-m-d H:i:s")
            ]);
            return json(['code'=>0, 'msg'=>'succ']);
        }elseif($act == 'edit'){
            $id = input('post.id/d');
            $ip = input('post.ip', null, 'trim');
            if(!$id || !$ip) return json(['code'=>-1, 'msg'=>'IP不能为空']);
            if(Db::name($tablename)->where('ip', $ip)->where('id', '<>', $id)->find()){
                return json(['code'=>-1, 'msg'=>'该IP已存在']);
            }
            Db::name($tablename)->where('id', $id)->update([
                'ip' => $ip
            ]);
            return json(['code'=>0, 'msg'=>'succ']);
        }elseif($act == 'enable'){
            $id = input('post.id/d');
            $enable = input('post.enable/d');
            if(!$id) return json(['code'=>-1, 'msg'=>'no id']);
            Db::name($tablename)->where('id', $id)->update([
                'enable' => $enable
            ]);
            return json(['code'=>0, 'msg'=>'succ']);
        }elseif($act == 'del'){
            $id = input('post.id/d');
            if(!$id) return json(['code'=>-1, 'msg'=>'no id']);
            Db::name($tablename)->where('id', $id)->delete();
            return json(['code'=>0, 'msg'=>'succ']);
        }
        return json(['code'=>-1, 'msg'=>'no act']);
    }

    public function deplist(){
        $deplist_linux = get_data_dir().'config/deployment_list.json';
        $deplist_win = get_data_dir('Windows').'config/deployment_list.json';
        $deplist_linux_time = file_exists($deplist_linux) ? date("Y-m-d H:i:s", filemtime($deplist_linux)) : '不存在';
        $deplist_win_time = file_exists($deplist_win) ? date("Y-m-d H:i:s", filemtime($deplist_win)) : '不存在';
        View::assign('deplist_linux_time', $deplist_linux_time);
        View::assign('deplist_win_time', $deplist_win_time);
        return view();
    }

    public function refresh_deplist(){
        $os = input('get.os');
        if(!$os) $os = 'Linux';
        try{
            Plugins::refresh_deplist($os);
            Db::name('log')->insert(['uid' => 0, 'action' => '刷新一键部署列表', 'data' => '刷新'.$os.'一键部署列表成功', 'addtime' => date("Y-m-d H:i:s")]);
            return json(['code'=>0,'msg'=>'获取最新一键部署列表成功！']);
        }catch(\Exception $e){
            return json(['code'=>-1, 'msg'=>$e->getMessage()]);
        }
    }

    public function cleancache(){
        Cache::clear();
        return json(['code'=>0,'msg'=>'succ']);
    }
}