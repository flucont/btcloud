<?php
namespace app\controller;

use think\facade\Db;
use app\BaseController;
use app\lib\Plugins;

class Api extends BaseController
{

    //获取插件列表
    public function get_plugin_list(){
        if(!$this->checklist()) return json('你的服务器被禁止使用此云端');
        $record = Db::name('record')->where('ip',$this->clientip)->find();
        if($record){
            Db::name('record')->where('id',$record['id'])->update(['usetime'=>date("Y-m-d H:i:s")]);
        }else{
            Db::name('record')->insert(['ip'=>$this->clientip, 'addtime'=>date("Y-m-d H:i:s"), 'usetime'=>date("Y-m-d H:i:s")]);
        }
        $json_arr = Plugins::get_plugin_list();
        if(!$json_arr) return json((object)[]);
        return json($json_arr);
    }

    //获取插件列表(win)
    public function get_plugin_list_win(){
        if(!$this->checklist()) return json('你的服务器被禁止使用此云端');
        $record = Db::name('record')->where('ip',$this->clientip)->find();
        if($record){
            Db::name('record')->where('id',$record['id'])->update(['usetime'=>date("Y-m-d H:i:s")]);
        }else{
            Db::name('record')->insert(['ip'=>$this->clientip, 'addtime'=>date("Y-m-d H:i:s"), 'usetime'=>date("Y-m-d H:i:s")]);
        }
        $json_arr = Plugins::get_plugin_list('Windows');
        if(!$json_arr) return json((object)[]);
        return json($json_arr);
    }

    //下载插件包
    public function download_plugin(){
        $plugin_name = input('post.name');
        $version = input('post.version');
        $os = input('post.os');
        if(!$plugin_name || !$version){
            return '参数不能为空';
        }
        if(!in_array($os,['Windows','Linux'])) $os = 'Linux';
        if(!preg_match('/^[a-zA-Z0-9_]+$/', $plugin_name) || !preg_match('/^[0-9.]+$/', $version)){
            return '参数不正确';
        }
        if(!$this->checklist()) return '你的服务器被禁止使用此云端';
        $filepath = get_data_dir($os).'plugins/package/'.$plugin_name.'-'.$version.'.zip';
        if(file_exists($filepath)){
            $filename = $plugin_name.'.zip';
            $this->output_file($filepath, $filename);
        }else{
            return '云端不存在该插件包';
        }
    }

    //下载插件主文件
    public function download_plugin_main(){
        $plugin_name = input('post.name');
        $version = input('post.version');
        $os = input('post.os');
        if(!$plugin_name || !$version){
            return '参数不能为空';
        }
        if(!in_array($os,['Windows','Linux'])) $os = 'Linux';
        if(!preg_match('/^[a-zA-Z0-9_]+$/', $plugin_name) || !preg_match('/^[0-9.]+$/', $version)){
            return '参数不正确';
        }
        if(!$this->checklist()) return '你的服务器被禁止使用此云端';
        $filepath = get_data_dir($os).'plugins/package/'.$plugin_name.'-'.$version.'.zip';
        $mainfilepath = get_data_dir($os).'plugins/folder/'.$plugin_name.'-'.$version.'/'.$plugin_name.'/'.$plugin_name.'_main.py';
        if(file_exists($mainfilepath)){
            $filename = $plugin_name.'_main.py';
            $this->output_file($mainfilepath, $filename);
        }elseif(file_exists($filepath)){
            $zip = new \ZipArchive;
            if ($zip->open($filepath) === true){
                echo $zip->getFromName($plugin_name.'/'.$plugin_name.'_main.py');
            }else{
                return '插件包解压缩失败';
            }
        }else{
            return '云端不存在该插件主文件';
        }
    }

    //下载插件其他文件
    public function download_plugin_other(){
        $fname = input('get.fname');
        if(!$fname){
            return json(['status'=>false, 'msg'=>'参数不能为空']);
        }
        if(strpos(dirname($fname),'.')!==false)return json(['status'=>false, 'msg'=>'参数不正确']);
        if(!$this->checklist()) return json(['status'=>false, 'msg'=>'你的服务器被禁止使用此云端']);
        $filepath = get_data_dir().'plugins/other/'.$fname;
        if(file_exists($filepath)){
            $filename = basename($fname);
            $this->output_file($filepath, $filename);
        }else{
            return json(['status'=>false, 'msg'=>'云端不存在该插件文件']);
        }
    }

    public function get_update_logs(){
        $type = input('get.type');
        if($type == 'Windows'){
            $version = config_get('new_version_win');
            $data = [
                [
                    'title' => 'Linux面板'.$version,
                    'body' => config_get('update_msg_win'),
                    'addtime' => config_get('update_date_win')
                ]
            ];
        }else{
            $version = config_get('new_version');
            $data = [
                [
                    'title' => 'Linux面板'.$version,
                    'body' => config_get('update_msg'),
                    'addtime' => config_get('update_date')
                ]
            ];
        }
        return jsonp($data);
    }

    public function get_version(){
        $version = config_get('new_version');
        return $version;
    }

    public function get_version_win(){
        $version = config_get('new_version_win');
        return $version;
    }

    //安装统计
    public function setup_count(){
        return 'ok';
    }

    //检测更新
    public function check_update(){
        $version = config_get('new_version');
        $down_url = request()->root(true).'/install/update/LinuxPanel-'.$version.'.zip';
        $data = [
            'force' => false,
            'version' => $version,
            'downUrl' => $down_url,
            'updateMsg' => config_get('update_msg'),
            'uptime' => config_get('update_date'),
            'is_beta' => 0,
            'adviser' => -1,
            'btb' => '',
            'beta' => [
                'version' => $version,
                'downUrl' => $down_url,
                'updateMsg' => config_get('update_msg'),
                'uptime' => config_get('update_date'),
            ]
        ];
        return json($data);
    }

    //检测更新(win)
    public function check_update_win(){
        $version = config_get('new_version_win');
        $down_url = request()->root(true).'/win/panel/panel_'.$version.'.zip';
        $data = [
            'force' => false,
            'version' => $version,
            'downUrl' => $down_url,
            'updateMsg' => config_get('update_msg_win'),
            'uptime' => config_get('update_date_win'),
            'is_beta' => 0,
            'py_version' => '3.8.6',
            'adviser' => -1,
            'is_rec' => -1,
            'btb' => '',
            'beta' => [
                'py_version' => '3.8.6',
                'version' => $version,
                'downUrl' => $down_url,
                'updateMsg' => config_get('update_msg_win'),
                'uptime' => config_get('update_date_win'),
            ]
        ];
        return json($data);
    }

    //宝塔云监控获取最新版本
    public function btm_latest_version(){
        $data = [
            'version' => config_get('new_version_btm'),
            'description' => config_get('update_msg_btm'),
            'create_time' => config_get('update_date_btm')
        ];
        return json($data);
    }

    //宝塔云监控更新日志
    public function btm_update_history(){
        $data = [
            [
                'version' => config_get('new_version_btm'),
                'description' => config_get('update_msg_btm'),
                'create_time' => config_get('update_date_btm')
            ]
        ];
        return json($data);
    }

    //宝塔云WAF最新版本
    public function btwaf_latest_version(){
        $type = input('?post.type') ? input('post.type') : 0;
        if($type == 1){
            $data = [
                'version' => '1.1',
                'description' => '暂无更新日志',
                'create_time' => 1705315163,
            ];
        }else{
            $data = [
                'version' => '3.0',
                'description' => '暂无更新日志',
                'create_time' => 1705315163,
            ];
        }
        $data = bin2hex(json_encode($data));
        return json(['status'=>true,'err_no'=>0,'msg'=>'获取成功','data'=>$data]);
    }

    //获取内测版更新日志
    public function get_beta_logs(){
        return json(['beta_ps'=>'当前暂无内测版', 'list'=>[]]);
    }

    //检查用户绑定是否正确
    public function check_auth_key(){
        return '1';
    }

    //从云端验证域名是否可访问
    public function check_domain(){
        $domain = input('post.domain',null,'trim');
        $ssl = input('post.ssl/d');
        if(!$domain) return json(['status'=>false, 'msg'=>'域名不能为空']);
        if(!strpos($domain,'.')) return json(['status'=>false, 'msg'=>'域名格式不正确']);
        $domain = str_replace('*.','',$domain);
        $ip = gethostbyname($domain);
        if(!$ip || $ip == $domain){
            return json(['status'=>false, 'msg'=>'无法访问']);
        }else{
            return json(['status'=>true, 'msg'=>'访问正常']);
        }
    }

    //同步时间
    public function get_time(){
        return time();
    }

    //同步时间
    public function get_win_date(){
        return date("Y-m-d H:i:s");
    }

    //查询是否专业版（废弃）
    public function is_pro(){
        return json(['endtime'=>true, 'code'=>1]);
    }

    //获取产品推荐信息
    public function get_plugin_remarks(){
        return json(['list'=>[], 'pro_list'=>[], 'kfqq'=>'', 'kf'=>'', 'qun'=>'']);
    }

    //获取指定插件评分
    public function get_plugin_socre(){
        return json(['total'=>0, 'split'=>[0,0,0,0,0],'page'=>"<div><span class='Pcurrent'>1</span><span class='Pcount'>共计0条数据</span></div>",'data'=>[]]);
    }

    //提交插件评分
    public function plugin_score(){
        return json(['status'=>true, 'msg'=>'您的评分已成功提交，感谢您的支持!']);
    }

    //获取IP地址
    public function get_ip_address(){
        return $this->clientip;
    }

    //绑定账号
    public function get_auth_token(){
        if(!input('?post.data')) return json(['status'=>false, 'msg'=>'参数不能为空']);
        $reqData = hex2bin(input('post.data'));
        parse_str($reqData, $arr);
        $serverid = $arr['serverid'];
        $userinfo = ['uid'=>1, 'username'=>'Administrator', 'address'=>'127.0.0.1', 'serverid'=>$serverid, 'access_key'=>random(48), 'secret_key'=>random(48), 'ukey'=>md5(time()), 'state'=>1];
        $data = bin2hex(json_encode($userinfo));
        return json(['status'=>true, 'msg'=>'登录成功！', 'data'=>$data]);
    }

    //绑定账号新
    public function authorization_login(){
        if(!input('?post.data')) return json(['status'=>false, 'msg'=>'参数不能为空']);
        $reqData = hex2bin(input('post.data'));
        parse_str($reqData, $arr);
        $serverid = $arr['serverid'];
        $userinfo = ['uid'=>1, 'username'=>'Administrator', 'ip'=>'127.0.0.1', 'server_id'=>$serverid, 'access_key'=>random(48), 'secret_key'=>random(48)];
        $data = bin2hex(json_encode($userinfo));
        return json(['status'=>true, 'err_no'=>0, 'msg'=>'账号绑定成功', 'data'=>$data]);
    }

    //刷新授权信息
    public function authorization_info(){
        if(!input('?post.data')) return json(['status'=>false, 'msg'=>'参数不能为空']);
        $reqData = hex2bin(input('post.data'));
        parse_str($reqData, $arr);
        $id = isset($arr['id'])&&$arr['id']>0?$arr['id']:1;
        $userinfo = ['id'=>$id, 'product'=>$arr['product'], 'status'=>2, 'clients'=>9999, 'durations'=>0, 'end_time'=>strtotime('+10 year')];
        $data = bin2hex(json_encode($userinfo));
        return json(['status'=>true, 'err_no'=>0, 'data'=>$data]);
    }

    //刷新授权信息
    public function update_license(){
        if(!input('?post.data')) return json(['status'=>false, 'msg'=>'参数不能为空']);
        $reqData = hex2bin(input('post.data'));
        parse_str($reqData, $arr);
        if(!isset($arr['product']) || !isset($arr['serverid'])) return json(['status'=>false, 'msg'=>'缺少参数']);

        $license_data = ['product'=>$arr['product'], 'uid'=>random(32), 'phone'=>'138****8888', 'auth_id'=>random(32), 'server_id'=>substr($arr['serverid'], 0, 32), 'auth'=>['apis'=>[], 'menu'=>[], 'extra'=>['type'=>3,'location'=>-1,'smart_cc'=>-1,'site'=>0]], 'pages'=>[], 'end_time'=>strtotime('+10 year')];
        $json = json_encode($license_data);

        [$public_key, $private_key] = generateKeyPairs();
        $public_key = pemToBase64($public_key);

        $key1 = random(32);
        $key2 = substr($public_key, 0, 32);
        $encrypted1 = licenseEncrypt($json, $key1);
        $encrypted2 = licenseEncrypt($key1, $key2);
        $sign_data = $encrypted1.'.'.$encrypted2;
        openssl_sign($sign_data, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);

        $license = base64_encode($sign_data.'.'.$signature);
        $data = bin2hex(json_encode(['public_key'=>$public_key, 'license'=>$license]));
        return json(['status'=>true, 'err_no'=>0, 'msg'=>'授权获取成功', 'data'=>$data]);
    }

    public function is_obtained_btw_trial(){
        $data = ['is_obtained'=>0];
        $data = bin2hex(json_encode($data));
        return json(['status'=>true, 'err_no'=>0, 'data'=>$data, 'msg'=>'检测成功']);
    }

    //一键部署列表
    public function get_deplist(){
        $os = input('post.os');
        $json_arr = Plugins::get_deplist($os);
        if(!$json_arr) return json([]);
        return json($json_arr);
    }

    //获取宝塔SSL列表
    public function get_ssl_list(){
        $data = bin2hex('[]');
        return json(['status'=>true, 'msg'=>'', 'data'=>$data]);
    }

    public function return_success(){
        return json(['status'=>true, 'msg'=>1, 'data'=>(object)[]]);
    }

    public function return_error(){
        return json(['status'=>false, 'msg'=>'不支持当前操作']);
    }

    public function return_error2(){
        return json(['success'=>false, 'res'=>'不支持当前操作']);
    }
    
    public function return_empty(){
        return '';
    }

    public function return_empty_array(){
        return json([]);
    }

    public function return_page_data(){
        return json(['page'=>"<div><span class='Pcurrent'>1</span><span class='Pnumber'>1/0</span><span class='Pline'>从1-1000条</span><span class='Pcount'>共计0条数据</span></div>", 'data'=>[]]);
    }

    //获取所有蜘蛛IP列表
    public function btwaf_getspiders(){
        try{
            $result = Plugins::btwaf_getspiders();
            return json($result);
        }catch(\Exception $e){
            return json(['status'=>false, 'msg'=>$e->getMessage()]);
        }
    }

    //分类获取蜘蛛IP列表
    public function get_spider(){
        $type = input('get.spider/d');
        if(!$type) return json([]);
        $result = Plugins::get_spider($type);
        return json($result);
    }

    //检查黑白名单
    private function checklist(){
        if(config_get('whitelist') == 1){
            if(Db::name('white')->where('ip', $this->clientip)->where('enable', 1)->find()){
                return true;
            }
            return false;
        }else{
            if(Db::name('black')->where('ip', $this->clientip)->where('enable', 1)->find()){
                return false;
            }
            return true;
        }
    }

    //下载大文件
    private function output_file($filepath, $filename){
        $filesize = filesize($filepath);
        $filemd5 = md5_file($filepath);

        ob_clean();
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename={$filename}.zip");
        header("Content-Length: {$filesize}");
        header("File-size: {$filesize}");
        header("Content-md5: {$filemd5}");

        $read_buffer = 1024 * 100;
        $handle = fopen($filepath, 'rb');
        $sum_buffer = 0;
        while(!feof($handle) && $sum_buffer<$filesize) {
            echo fread($handle, min($read_buffer, ($filesize - $sum_buffer) + 1));
            $sum_buffer += $read_buffer;
            flush();
        }
        fclose($handle);
        exit;
    }

    public function logerror(){
        $content = date('Y-m-d H:i:s')."\r\n";
        $content.=$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']."\r\n";
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            $content.=file_get_contents('php://input')."\r\n";
        }
        $handle = fopen(app()->getRootPath()."record.txt", 'a');
        fwrite($handle, $content."\r\n");
        fclose($handle);
        return json(['status'=>false, 'msg'=>'不支持当前操作']);
    }

    //生成自签名SSL证书
    public function bt_cert(){
        $data = input('post.data');
        $param = json_decode($data, true);
        if(!$param || !isset($param['action']) || !isset($param['domain'])) return json(['status'=>false, 'msg'=>'参数错误']);

        $dir = app()->getBasePath().'script/';
        $ssl_path = app()->getRootPath().'public/ssl/baota_root.pfx';
        $isca = file_exists($dir.'ca.crt') && file_exists($dir.'ca.key') && file_exists($ssl_path);
        if(!$isca) return json(['status'=>false, 'msg'=>'CA证书不存在']);

        if($param['action'] == 'get_domain_cert'){
            if(!$this->checklist()) return json(['status'=>false, 'msg'=>'你的服务器被禁止使用此云端']);
            $domain = $param['domain'];
            if(empty($domain)) return json(['status'=>false, 'msg'=>'域名不能为空']);
            $domain_list = explode(',', $domain);
            foreach($domain_list as $d){
                if(!checkDomain($d)) return json(['status'=>false, 'msg'=>'域名或IP格式不正确:'.$d]);
            }
            $common_name = $domain_list[0];
            $validity = 3650;
            $result = makeSelfSignSSL($common_name, $domain_list, $validity);
            if(!$result){
                return json(['status'=>false, 'msg'=>'生成证书失败']);
            }
            $ca_pfx = base64_encode(file_get_contents($ssl_path));
            return json(['status'=>true, 'msg'=>'生成证书成功', 'cert'=>$result['cert'], 'key'=>$result['key'], 'pfx'=>$ca_pfx, 'password'=>'']);
        }else{
            return json(['status'=>false, 'msg'=>'不支持当前操作']);
        }
    }
}