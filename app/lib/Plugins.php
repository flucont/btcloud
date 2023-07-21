<?php

namespace app\lib;

use Exception;
use ZipArchive;

class Plugins
{

    private static function get_btapi($os){
        if($os == 'Windows'){
            $bt_url = config_get('wbt_url');
            $bt_key = config_get('wbt_key');
        }else{
            $bt_url = config_get('bt_url');
            $bt_key = config_get('bt_key');
        }
        if(!$bt_url || !$bt_key) throw new Exception('请先配置好宝塔面板接口信息');
        $btapi = new Btapi($bt_url, $bt_key);
        return $btapi;
    }

    //刷新插件列表
    public static function refresh_plugin_list($os = 'Linux'){
        $btapi = self::get_btapi($os);
        $result = $btapi->get_plugin_list();
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取插件列表失败：插件列表为空');
            }
            self::save_plugin_list($result, $os);
        }else{
            throw new Exception('获取插件列表失败：'.(isset($result['msg'])?$result['msg']:'面板连接失败'));
        }
    }

    //保存插件列表
    private static function save_plugin_list($data, $os){
        $data['ip'] = '127.0.0.1';
        $data['serverid'] = '';
        $data['beta'] = 0;
        $data['uid'] = 1;
        $data['skey'] = '';
        $list = [];
        foreach($data['list'] as $plugin){
            if(isset($plugin['endtime'])) $plugin['endtime'] = 0;
            $list[] = $plugin;
        }
        $data['list'] = $list;
        $data['ltd'] = strtotime('+10 year');
        $json_file = get_data_dir($os).'config/plugin_list.json';
        if(!file_put_contents($json_file, json_encode($data))){
            throw new Exception('保存插件列表失败，文件无写入权限');
        }
    }

    //获取插件列表
    public static function get_plugin_list($os = 'Linux'){
        $json_file = get_data_dir($os).'config/plugin_list.json';
        if(file_exists($json_file)){
            $data = file_get_contents($json_file);
            $json_arr = json_decode($data, true);
            if($json_arr){
                return $json_arr;
            }
        }
        return false;
    }

    //获取一个插件信息
    public static function get_plugin_info($name, $os = 'Linux'){
        $json_arr = self::get_plugin_list($os);
        if(!$json_arr) return null;
        foreach($json_arr['list'] as $plugin){
            if($plugin['name'] == $name){
                return $plugin;
            }
        }
        return null;
    }

    //下载插件（自动判断是否第三方）
    public static function download_plugin($plugin_name, $version, $os = 'Linux'){
        $plugin_info = Plugins::get_plugin_info($plugin_name, $os);
        if(!$plugin_info) throw new Exception('未找到该插件信息');
        if($plugin_info['type'] == 10 && isset($plugin_info['versions'][0]['download'])){
            if($plugin_info['price'] == 0){
                $btapi = self::get_btapi($os);
                $btapi->create_plugin_other_order($plugin_info['id']);
            }
            $fname = $plugin_info['versions'][0]['download'];
            $filemd5 = $plugin_info['versions'][0]['md5'];
            Plugins::download_plugin_other($fname, $filemd5, $os);
            if(isset($plugin_info['min_image']) && strpos($plugin_info['min_image'], 'fname=')){
                $fname = substr($plugin_info['min_image'], strpos($plugin_info['min_image'], '?fname=')+7);
                Plugins::download_plugin_other($fname, null, $os);
            }
        }else{
            Plugins::download_plugin_package($plugin_name, $version, $os);
        }
    }

    //下载插件包
    public static function download_plugin_package($plugin_name, $version, $os = 'Linux'){
        $filepath = get_data_dir($os).'plugins/package/'.$plugin_name.'-'.$version.'.zip';
        $btapi = self::get_btapi($os);
        $result = $btapi->get_plugin_filename($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                self::download_file($btapi, $filename, $filepath);
                if(file_exists($filepath)){
                    $zip = new ZipArchive;
                    if ($zip->open($filepath) === true)
                    {
                        $zip->extractTo(get_data_dir($os).'plugins/folder/'.$plugin_name.'-'.$version);
                        $zip->close();
                        $main_filepath = get_data_dir($os).'plugins/folder/'.$plugin_name.'-'.$version.'/'.$plugin_name.'/'.$plugin_name.'_main.py';
                        if(file_exists($main_filepath) && filesize($main_filepath)>10){
                            if(!strpos(file_get_contents($main_filepath), 'import ')){ //加密py文件，需要解密
                                self::decode_plugin_main($plugin_name, $version, $main_filepath, $os);
                                self::noauth_plugin_main($main_filepath);
                                $zip->open($filepath, ZipArchive::CREATE);
                                $zip->addFile($main_filepath, $plugin_name.'/'.$plugin_name.'_main.py');
                                $zip->close();
                            }
                        }
                    }else{
                        throw new Exception('插件包解压缩失败');
                    }
                    return true;
                }else{
                    throw new Exception('下载插件包失败，本地文件不存在');
                }
            }else{
                throw new Exception('下载插件包失败：'.($result['msg']?$result['msg']:'未知错误'));
            }
        }else{
            throw new Exception('下载插件包失败，接口返回错误');
        }
    }

    //下载插件主程序文件
    public static function download_plugin_main($plugin_name, $version, $os = 'Linux'){
        $filepath = get_data_dir($os).'plugins/main/'.$plugin_name.'-'.$version.'.dat';
        $btapi = self::get_btapi($os);
        $result = $btapi->get_plugin_main_filename($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                self::download_file($btapi, $filename, $filepath);
                if(file_exists($filepath)){
                    return true;
                }else{
                    throw new Exception('下载插件主程序文件失败，本地文件不存在');
                }
            }else{
                throw new Exception('下载插件主程序文件失败：'.($result['msg']?$result['msg']:'未知错误'));
            }
        }else{
            throw new Exception('下载插件主程序文件失败，接口返回错误');
        }
    }

    //解密并下载插件主程序文件
    public static function decode_plugin_main($plugin_name, $version, $main_filepath, $os = 'Linux'){
        if(self::decode_plugin_main_local($main_filepath, $os)) return true;
        $btapi = self::get_btapi($os);
        $result = $btapi->get_decode_plugin_main($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                self::download_file($btapi, $filename, $main_filepath);
                return true;
            }else{
                throw new Exception('解密插件主程序文件失败：'.($result['msg']?$result['msg']:'未知错误'));
            }
        }else{
            throw new Exception('解密插件主程序文件失败，接口返回错误');
        }
    }

    //本地解密插件主程序文件
    public static function decode_plugin_main_local($main_filepath, $os = 'Linux'){
        $btapi = self::get_btapi($os);
        $userinfo = $btapi->get_user_info();
        if(isset($userinfo['uid'])){
            $src = file_get_contents($main_filepath);
            if($src===false)throw new Exception('文件打开失败');
            if(!$src || strpos($src, 'import ')!==false)return true;
            $uid = $userinfo['uid'];
            $serverid = $userinfo['serverid'];
            $key = md5(substr($serverid, 10, 16).$uid.$serverid);
            $iv = md5($key.$serverid);
            $key = substr($key, 8, 16);
            $iv = substr($iv, 8, 16);
            $data_arr = explode("\n", $src);
            $de_text = '';
            foreach($data_arr as $data){
                $data = trim($data);
                if(!empty($data) && strlen($data)!=24){
                    $tmp = openssl_decrypt($data, 'aes-128-cbc', $key, 0, $iv);
                    if($tmp) $de_text .= $tmp;
                }
            }
            if(!empty($de_text) && strpos($de_text, 'import ')!==false){
                file_put_contents($main_filepath, $de_text);
                return true;
            }
            return false;
        }else{
            throw new Exception('解密插件主程序文件失败，获取用户信息失败');
        }
    }

    public static function decode_module_file($filepath){
        $src = file_get_contents($filepath);
        if($src===false)throw new Exception('文件打开失败');
        if(!$src || strpos($src, 'import ')!==false)return 0;
        $key = 'Z2B87NEAS2BkxTrh';
        $iv = 'WwadH66EGWpeeTT6';
        $data_arr = explode("\n", $src);
        $de_text = '';
        foreach($data_arr as $data){
            $data = trim($data);
            if(!empty($data)){
                $tmp = openssl_decrypt($data, 'aes-128-cbc', $key, 0, $iv);
                if($tmp) $de_text .= $tmp;
            }
        }
        if(!empty($de_text) && strpos($de_text, 'import ')!==false){
            file_put_contents($filepath, $de_text);
            return 1;
        }
        return 2;
    }

    //去除插件主程序文件授权校验
    public static function noauth_plugin_main($main_filepath){
        $data = file_get_contents($main_filepath);
        if(!$data) return false;

        $data = str_replace('\'http://www.bt.cn/api/panel/get_soft_list_test', 'public.GetConfigValue(\'home\')+\'/api/panel/get_soft_list_test', $data);
        $data = str_replace('\'https://www.bt.cn/api/panel/get_soft_list_test', 'public.GetConfigValue(\'home\')+\'/api/panel/get_soft_list_test', $data);
        $data = str_replace('\'http://www.bt.cn/api/panel/get_soft_list', 'public.GetConfigValue(\'home\')+\'/api/panel/get_soft_list', $data);
        $data = str_replace('\'https://www.bt.cn/api/panel/get_soft_list', 'public.GetConfigValue(\'home\')+\'/api/panel/get_soft_list', $data);
        $data = str_replace('\'http://www.bt.cn/api/panel/notpro', 'public.GetConfigValue(\'home\')+\'/api/panel/notpro', $data);
        $data = str_replace('\'https://www.bt.cn/api/panel/notpro', 'public.GetConfigValue(\'home\')+\'/api/panel/notpro', $data);

        $data = str_replace('\'http://www.bt.cn/api/wpanel/get_soft_list_test', 'public.GetConfigValue(\'home\')+\'/api/wpanel/get_soft_list_test', $data);
        $data = str_replace('\'https://www.bt.cn/api/wpanel/get_soft_list_test', 'public.GetConfigValue(\'home\')+\'/api/wpanel/get_soft_list_test', $data);
        $data = str_replace('\'http://www.bt.cn/api/wpanel/get_soft_list', 'public.GetConfigValue(\'home\')+\'/api/wpanel/get_soft_list', $data);
        $data = str_replace('\'https://www.bt.cn/api/wpanel/get_soft_list', 'public.GetConfigValue(\'home\')+\'/api/wpanel/get_soft_list', $data);
        $data = str_replace('\'http://www.bt.cn/api/wpanel/notpro', 'public.GetConfigValue(\'home\')+\'/api/wpanel/notpro', $data);
        $data = str_replace('\'https://www.bt.cn/api/wpanel/notpro', 'public.GetConfigValue(\'home\')+\'/api/wpanel/notpro', $data);

        file_put_contents($main_filepath, $data);
    }

    //下载插件其他文件
    public static function download_plugin_other($fname, $filemd5 = null, $os = 'Linux'){
        $filepath = get_data_dir().'plugins/other/'.$fname;
        @mkdir(dirname($filepath), 0777, true);
        $btapi = self::get_btapi($os);
        $result = $btapi->get_plugin_other_filename($fname);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                self::download_file($btapi, $filename, $filepath);
                if(file_exists($filepath)){
                    if($filemd5 && md5_file($filepath) != $filemd5){
                        $msg = filesize($filepath) < 300 ? file_get_contents($filepath) : '插件文件MD5校验失败';
                        @unlink($filepath);
                        throw new Exception($msg);
                    }
                    return true;
                }else{
                    throw new Exception('下载插件文件失败，本地文件不存在');
                }
            }else{
                throw new Exception('下载插件文件失败：'.($result['msg']?$result['msg']:'未知错误'));
            }
        }else{
            throw new Exception('下载插件文件失败，接口返回错误');
        }
    }

    //下载文件
    private static function download_file($btapi, $filename, $filepath){
        try{
            $btapi->download($filename, $filepath);
        }catch(Exception $e){
            @unlink($filepath);
            //宝塔bug小文件下载失败，改用base64下载
            $result = $btapi->get_file($filename);
            if($result && isset($result['status']) && $result['status']==true){
                $filedata = base64_decode($result['data']);
                if(strlen($filedata) < 4096 && substr($filedata,0,1)=='{' && substr($filedata,-1,1)=='}'){
                    $arr = json_decode($filedata, true);
                    if($arr){
                        throw new Exception('获取文件失败：'.($arr['msg']?$arr['msg']:'未知错误'));
                    }
                }
                if(!$filedata){
                    throw new Exception('获取文件失败：文件内容为空');
                }
                file_put_contents($filepath, $filedata);
            }elseif($result){
                throw new Exception('获取文件失败：'.($result['msg']?$result['msg']:'未知错误'));
            }else{
                throw new Exception('获取文件失败：未知错误');
            }
        }
    }

    //刷新一键部署列表
    public static function refresh_deplist($os = 'Linux'){
        $btapi = self::get_btapi($os);
        $result = $btapi->get_deplist();
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取一键部署列表失败：一键部署列表为空');
            }
            $json_file = get_data_dir($os).'config/deployment_list.json';
            if(!file_put_contents($json_file, json_encode($result))){
                throw new Exception('保存一键部署列表失败，文件无写入权限');
            }
        }else{
            throw new Exception('获取一键部署列表失败：'.(isset($result['msg'])?$result['msg']:'面板连接失败'));
        }
    }

    //获取一键部署列表
    public static function get_deplist($os = 'Linux'){
        $json_file = get_data_dir($os).'config/deployment_list.json';
        if(file_exists($json_file)){
            $data = file_get_contents($json_file);
            $json_arr = json_decode($data, true);
            if($json_arr){
                return $json_arr;
            }
        }
        return false;
    }

}