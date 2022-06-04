<?php

namespace app\lib;

use Exception;
use ZipArchive;

class Plugins
{

    private static function get_btapi(){
        $bt_url = config_get('bt_url');
        $bt_key = config_get('bt_key');
        if(!$bt_url || !$bt_key) throw new Exception('请先配置好宝塔面板接口信息');
        $btapi = new Btapi($bt_url, $bt_key);
        return $btapi;
    }

    //刷新插件列表
    public static function refresh_plugin_list(){
        $btapi = self::get_btapi();
        $result = $btapi->get_plugin_list();
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取插件列表失败：插件列表为空');
            }
            self::save_plugin_list($result);
        }else{
            throw new Exception('获取插件列表失败：'.($result['msg']?$result['msg']:'面板连接失败'));
        }
    }

    //保存插件列表
    private static function save_plugin_list($data){
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
        if($data['pro']>-1) $data['pro'] = 0;
        if($data['ltd']>-1) $data['ltd'] = strtotime('+1 year');
        $json_file = get_data_dir().'config/plugin_list.json';
        if(!file_put_contents($json_file, json_encode($data))){
            throw new Exception('保存插件列表失败，文件无写入权限');
        }
    }

    //获取插件列表
    public static function get_plugin_list(){
        $json_file = get_data_dir().'config/plugin_list.json';
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
    public static function get_plugin_info($name){
        $json_arr = self::get_plugin_list();
        if(!$json_arr) return null;
        foreach($json_arr['list'] as $plugin){
            if($plugin['name'] == $name){
                return $plugin;
            }
        }
        return null;
    }

    //下载插件（自动判断是否第三方）
    public static function download_plugin($plugin_name, $version){
        $plugin_info = Plugins::get_plugin_info($plugin_name);
        if(!$plugin_info) throw new Exception('未找到该插件信息');
        if($plugin_info['type'] == 10 && isset($plugin_info['versions'][0]['download'])){
            $fname = $plugin_info['versions'][0]['download'];
            $filemd5 = $plugin_info['versions'][0]['md5'];
            Plugins::download_plugin_other($fname, $filemd5);
            if(isset($plugin_info['min_image']) && strpos($plugin_info['min_image'], 'fname=')){
                $fname = substr($plugin_info['min_image'], strpos($plugin_info['min_image'], '?fname=')+7);
                Plugins::download_plugin_other($fname);
            }
        }else{
            Plugins::download_plugin_package($plugin_name, $version);
        }
    }

    //下载插件包
    public static function download_plugin_package($plugin_name, $version){
        $filepath = get_data_dir().'plugins/package/'.$plugin_name.'-'.$version.'.zip';
        $btapi = self::get_btapi();
        $result = $btapi->get_plugin_filename($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                self::download_file($btapi, $filename, $filepath);
                if(file_exists($filepath)){
                    $zip = new ZipArchive;
                    if ($zip->open($filepath) === true)
                    {
                        $zip->extractTo(get_data_dir().'plugins/folder/'.$plugin_name.'-'.$version);
                        $zip->close();
                        $main_filepath = get_data_dir().'plugins/folder/'.$plugin_name.'-'.$version.'/'.$plugin_name.'/'.$plugin_name.'_main.py';
                        if(file_exists($main_filepath) && filesize($main_filepath)>10){
                            if(!strpos(file_get_contents($main_filepath), 'import ')){ //加密py文件，需要解密
                                self::decode_plugin_main($plugin_name, $version, $main_filepath);
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
    public static function download_plugin_main($plugin_name, $version){
        $filepath = get_data_dir().'plugins/main/'.$plugin_name.'-'.$version.'.dat';
        $btapi = self::get_btapi();
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
    public static function decode_plugin_main($plugin_name, $version, $main_filepath){
        $btapi = self::get_btapi();
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

    //下载插件其他文件
    public static function download_plugin_other($fname, $filemd5 = null){
        $filepath = get_data_dir().'plugins/other/'.$fname;
        @mkdir(dirname($filepath), 0777, true);
        $btapi = self::get_btapi();
        $result = $btapi->get_plugin_other_filename($fname);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                self::download_file($btapi, $filename, $filepath);
                if(file_exists($filepath)){
                    if($filemd5 && md5_file($filepath) != $filemd5){
                        unlink($filepath);
                        throw new Exception('插件文件MD5校验失败');
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
            unlink($filepath);
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

}