<?php

namespace app\lib;

use Exception;
use ZipArchive;

class Plugins
{

    private static function get_btapi($os){
        if(self::is_third($os)){
            return new ThirdPlugins($os);
        }else{
            return new BtPlugins($os);
        }
    }

    private static function is_third($os){
        if($os == 'en'){
            $type = config_get('enbt_type');
        }elseif($os == 'Windows'){
            $type = config_get('wbt_type');
        }else{
            $type = config_get('bt_type');
        }
        return $type == 1;
    }

    //刷新插件列表
    public static function refresh_plugin_list($os = 'Linux'){
        $btapi = self::get_btapi($os);
        $result = $btapi->get_plugin_list();
        self::save_plugin_list($result, $os);
    }

    //保存插件列表
    private static function save_plugin_list($data, $os){
        $data['ip'] = '127.0.0.1';
        if($os == 'en'){
            $data['serverId'] = '';
            $data['aln'] = self::get_aln();
            $data['pro'] = 0;
            $data['pro_authorization_sn'] = '0';
            if(!empty($data['authorization_map'])){
                foreach($data['authorization_map'] as $code => &$plugin){
                    if($code != '0' && isset($plugin['end_time'])) $plugin['end_time'] = 0;
                }
            }
            if(isset($data['expansions']['mail'])){
                $data['expansions']['mail']['total'] = 2000000;
                $data['expansions']['mail']['available'] = 2000000;
            }
        }else{
            $data['serverid'] = '';
            $data['aln'] = self::get_aln();
            $data['beta'] = 0;
            $data['uid'] = 1;
            $data['skey'] = '';
            $data['pro'] = -1;
            $data['ltd'] = strtotime('+10 year');
        }
        foreach($data['list'] as &$plugin){
            if(isset($plugin['endtime'])) $plugin['endtime'] = 0;
        }
        $json_file = get_data_dir($os).'config/plugin_list.json';
        if(!file_put_contents($json_file, json_encode($data))){
            throw new Exception('保存插件列表失败，文件无写入权限');
        }
    }

    //多账号数量
    private static function get_aln($count = '9999'){
        $key = 'FB8upo8XMgP5by54';
        $iv = 'lOrrq3lNEURZNdK7';
        return openssl_encrypt($count, 'aes-128-cbc', $key, 0, $iv);
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
        $btapi = self::get_btapi($os);
        $btapi->download_plugin($plugin_name, $version, $plugin_info);
    }

    //下载插件主程序文件
    public static function download_plugin_main($plugin_name, $version, $os = 'Linux'){
        $btapi = self::get_btapi($os);
        $btapi->download_plugin_main($plugin_name, $version);
    }

    //本地解密插件主程序文件
    public static function decode_plugin_main_local($main_filepath, $os = 'Linux'){
        $btapi = new BtPlugins($os);
        return $btapi->decode_plugin_main_local($main_filepath);
    }

    //本地解密模块文件
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
                if($tmp !== false) $de_text .= $tmp;
            }
        }
        if(!empty($de_text) && strpos($de_text, 'import ')!==false){
            file_put_contents($filepath, $de_text);
            return 1;
        }
        return 2;
    }

    //刷新一键部署列表
    public static function refresh_deplist($os = 'Linux'){
        $btapi = self::get_btapi($os);
        $result = $btapi->get_deplist();
        $json_file = get_data_dir($os).'config/deployment_list.json';
        if(!file_put_contents($json_file, json_encode($result))){
            throw new Exception('保存一键部署列表失败，文件无写入权限');
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

    //获取蜘蛛IP列表
    public static function btwaf_getspiders(){
        $result = cache('btwaf_getspiders');
        if($result){
            return $result;
        }
        $btapi = self::get_btapi('Linux');
        $result = $btapi->btwaf_getspiders();
        cache('btwaf_getspiders', $result, 3600 * 24 * 3);
        return $result;
    }

    //分类获取蜘蛛IP列表
    public static function get_spider($type){
        $result = cache('get_spider_'.$type);
        if($result){
            return $result;
        }
        $url = 'https://www.bt.cn/api/panel/get_spider?spider='.$type;
        $data = get_curl($url);
        $result = json_decode($data, true);
        if(!$result) return [];
        cache('get_spider_'.$type, $result, 3600 * 24);
        return $result;
    }

}