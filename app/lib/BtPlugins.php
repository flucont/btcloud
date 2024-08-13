<?php

namespace app\lib;

use Exception;
use ZipArchive;

class BtPlugins
{
    private $btapi;
    private $os;
    
    //需屏蔽的插件名称列表
    private static $block_plugins = ['dns','bt_boce','ssl_verify'];

    public function __construct($os){
        $this->os = $os;
        if($os == 'Windows'){
            $bt_url = config_get('wbt_url');
            $bt_key = config_get('wbt_key');
        }else{
            $bt_url = config_get('bt_url');
            $bt_key = config_get('bt_key');
        }
        if(!$bt_url || !$bt_key) throw new Exception('请先配置好宝塔面板接口信息');
        $this->btapi = new Btapi($bt_url, $bt_key);
    }

    //获取插件列表
    public function get_plugin_list(){
        $result = $this->btapi->get_plugin_list();
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取插件列表失败：插件列表为空');
            }
            foreach($result['list'] as $k=>$v){
                if(in_array($v['name'], self::$block_plugins)) unset($result['list'][$k]);
            }
            return $result;
        }else{
            throw new Exception('获取插件列表失败：'.(isset($result['msg'])?$result['msg']:'面板连接失败'));
        }
    }

    //下载插件（自动判断是否第三方）
    public function download_plugin($plugin_name, $version, $plugin_info){
        if($plugin_info['type'] == 10 && isset($plugin_info['versions'][0]['download'])){
            if($plugin_info['price'] == 0){
                $this->btapi->create_plugin_other_order($plugin_info['id']);
            }
            $fname = $plugin_info['versions'][0]['download'];
            $filemd5 = $plugin_info['versions'][0]['md5'];
            $this->download_plugin_other($fname, $filemd5);
            if(isset($plugin_info['min_image']) && strpos($plugin_info['min_image'], 'fname=')){
                $fname = substr($plugin_info['min_image'], strpos($plugin_info['min_image'], '?fname=')+7);
                $this->download_plugin_other($fname);
            }
        }else{
            $this->download_plugin_package($plugin_name, $version);
        }
    }

    //下载插件包
    private function download_plugin_package($plugin_name, $version){
        $filepath = get_data_dir($this->os).'plugins/package/'.$plugin_name.'-'.$version.'.zip';
        $result = $this->btapi->get_plugin_filename($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                $this->download_file($filename, $filepath);
                if(file_exists($filepath)){
                    $zip = new ZipArchive;
                    if ($zip->open($filepath) === true)
                    {
                        $plugins_dir = get_data_dir($this->os).'plugins/folder/'.$plugin_name.'-'.$version;
                        $zip->extractTo($plugins_dir, $plugin_name.'/'.$plugin_name.'_main.py');
                        $zip->close();
                        $main_filepath = $plugins_dir.'/'.$plugin_name.'/'.$plugin_name.'_main.py';
                        if(file_exists($main_filepath) && filesize($main_filepath)>10){
                            if(!strpos(file_get_contents($main_filepath), 'import ')){ //加密py文件，需要解密
                                $this->decode_plugin_main($plugin_name, $version, $main_filepath);
                                $this->noauth_plugin_main($main_filepath);
                                $zip->open($filepath, ZipArchive::CREATE);
                                $zip->addFile($main_filepath, $plugin_name.'/'.$plugin_name.'_main.py');
                                $zip->close();
                            }
                        }
                        deleteDir($plugins_dir);
                    }else{
                        unlink($filepath);
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
    public function download_plugin_main($plugin_name, $version){
        $filepath = get_data_dir($this->os).'plugins/main/'.$plugin_name.'-'.$version.'.dat';
        $result = $this->btapi->get_plugin_main_filename($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                $this->download_file($filename, $filepath);
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
    private function decode_plugin_main($plugin_name, $version, $main_filepath){
        if($this->decode_plugin_main_local($main_filepath)) return true;
        $result = $this->btapi->get_decode_plugin_main($plugin_name, $version);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                $this->download_file($filename, $main_filepath);
                return true;
            }else{
                throw new Exception('解密插件主程序文件失败：'.($result['msg']?$result['msg']:'未知错误'));
            }
        }else{
            throw new Exception('解密插件主程序文件失败，接口返回错误');
        }
    }

    //本地解密插件主程序文件
    public function decode_plugin_main_local($main_filepath){
        $userinfo = $this->btapi->get_user_info();
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

    //去除插件主程序文件授权校验
    private function noauth_plugin_main($main_filepath){
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

        $data = str_replace('\'http://www.bt.cn/api/bt_waf/getSpiders', 'public.GetConfigValue(\'home\')+\'/api/bt_waf/getSpiders', $data);
        $data = str_replace('\'https://www.bt.cn/api/bt_waf/getSpiders', 'public.GetConfigValue(\'home\')+\'/api/bt_waf/getSpiders', $data);
        $data = str_replace('\'http://www.bt.cn/api/bt_waf/addSpider', 'public.GetConfigValue(\'home\')+\'/api/bt_waf/addSpider', $data);
        $data = str_replace('\'https://www.bt.cn/api/bt_waf/addSpider', 'public.GetConfigValue(\'home\')+\'/api/bt_waf/addSpider', $data);
        $data = str_replace('\'https://www.bt.cn/api/bt_waf/getVulScanInfoList', 'public.GetConfigValue(\'home\')+\'/api/bt_waf/getVulScanInfoList', $data);
        $data = str_replace('\'https://www.bt.cn/api/bt_waf/reportInterceptFail', 'public.GetConfigValue(\'home\')+\'/api/bt_waf/reportInterceptFail', $data);
        $data = str_replace('\'https://www.bt.cn/api/v2/contact/nps/questions', 'public.GetConfigValue(\'home\')+\'/panel/notpro', $data);
        $data = str_replace('\'https://www.bt.cn/api/v2/contact/nps/submit', 'public.GetConfigValue(\'home\')+\'/panel/notpro', $data);
        $data = str_replace('\'http://www.bt.cn/api/Auth', 'public.GetConfigValue(\'home\')+\'/api/Auth', $data);
        $data = str_replace('\'https://www.bt.cn/api/Auth', 'public.GetConfigValue(\'home\')+\'/api/Auth', $data);

        file_put_contents($main_filepath, $data);
    }

    //下载插件其他文件
    private function download_plugin_other($fname, $filemd5 = null){
        $filepath = get_data_dir().'plugins/other/'.$fname;
        @mkdir(dirname($filepath), 0777, true);
        $result = $this->btapi->get_plugin_other_filename($fname);
        if($result && isset($result['status'])){
            if($result['status'] == true){
                $filename = $result['filename'];
                $this->download_file($filename, $filepath);
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
    private function download_file($filename, $filepath){
        try{
            $this->btapi->download($filename, $filepath);
        }catch(Exception $e){
            @unlink($filepath);
            //宝塔bug小文件下载失败，改用base64下载
            $result = $this->btapi->get_file($filename);
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

    //获取一键部署列表
    public function get_deplist(){
        $result = $this->btapi->get_deplist();
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取一键部署列表失败：一键部署列表为空');
            }
            return $result;
        }else{
            throw new Exception('获取一键部署列表失败：'.(isset($result['msg'])?$result['msg']:'面板连接失败'));
        }
    }

    //获取蜘蛛IP列表
    public function btwaf_getspiders(){
        $result = $this->btapi->btwaf_getspiders();
        if(isset($result['status']) && $result['status']){
            return $result['data'];
        }else{
            throw new Exception(isset($result['msg'])?$result['msg']:'获取失败');
        }
    }

}