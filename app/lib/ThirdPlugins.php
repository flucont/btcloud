<?php

namespace app\lib;

use Exception;
use ZipArchive;

class ThirdPlugins
{
    private $url;
    private $os;

    public function __construct($os)
    {
        $this->os = $os;
        $url = $os == 'Windows' ? config_get('wbt_surl') : config_get('bt_surl');
        if(!$url) throw new Exception('请先配置好第三方云端首页URL');
        $this->url = $url;
    }

    //获取插件列表
    public function get_plugin_list()
    {
        $url = $this->os == 'Windows' ? $this->url . 'api/wpanel/get_soft_list' : $this->url . 'api/panel/get_soft_list';
        $res = $this->curl($url);
        $result = json_decode($res, true);
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取插件列表失败：插件列表为空');
            }
            return $result;
        }else{
            throw new Exception('获取插件列表失败：'.(isset($result['msg'])?$result['msg']:'第三方云端连接失败'));
        }
    }

    //下载插件（自动判断是否第三方）
    public function download_plugin($plugin_name, $version, $plugin_info){
        if($plugin_info['type'] == 10 && isset($plugin_info['versions'][0]['download'])){
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

        $url = $this->url . 'down/download_plugin';
        $post = ['name'=>$plugin_name, 'version'=>$version, 'os'=>$this->os];
        $this->curl_download($url, $post, $filepath);

        if(file_exists($filepath)){
            $handle = fopen($filepath, "rb");
            $file_head = fread($handle, 4);
            fclose($handle);
            if(bin2hex($file_head) === '504b0304'){
                $zip = new ZipArchive;
                if ($zip->open($filepath) === true)
                {
                    $zip->close();
                    return true;
                }else{
                    @unlink($filepath);
                    throw new Exception('插件包解压缩失败');
                }
            }else{
                $handle = fopen($filepath, "rb");
                $errmsg = htmlspecialchars(fgets($handle));
                fclose($handle);
                @unlink($filepath);
                throw new Exception('下载插件包失败：'.($errmsg?$errmsg:'未知错误'));
            }
        }else{
            throw new Exception('下载插件包失败，本地文件不存在');
        }
    }

    //下载插件主程序文件
    public function download_plugin_main($plugin_name, $version){
        $filepath = get_data_dir($this->os).'plugins/main/'.$plugin_name.'-'.$version.'.dat';

        $url = $this->url . 'down/download_plugin_main';
        $post = ['name'=>$plugin_name, 'version'=>$version, 'os'=>$this->os];
        $this->curl_download($url, $post, $filepath);

        if(file_exists($filepath)){
            $line = count(file($filepath));
            if($line > 3) return true;

            $handle = fopen($filepath, "rb");
            $errmsg = htmlspecialchars(fgets($handle));
            fclose($handle);
            @unlink($filepath);
            throw new Exception('下载插件主程序文件失败：'.($errmsg?$errmsg:'未知错误'));
        }else{
            throw new Exception('下载插件主程序文件失败，本地文件不存在');
        }
    }

    //下载插件其他文件
    private function download_plugin_other($fname, $filemd5 = null){
        $filepath = get_data_dir().'plugins/other/'.$fname;
        @mkdir(dirname($filepath), 0777, true);

        $url = $this->url . 'api/Pluginother/get_file?fname='.urlencode($fname);
        $this->curl_download($url, false, $filepath);

        if(file_exists($filepath)){
            $handle = fopen($filepath, "rb");
            $file_head = fread($handle, 15);
            fclose($handle);
            if($file_head === '{"status":false'){
                $res = file_get_contents($filepath);
                $result = json_decode($res, true);
                @unlink($filepath);
                throw new Exception('下载插件文件失败：'.($result?$result['msg']:'未知错误'));
            }
            if($filemd5 && md5_file($filepath) != $filemd5){
                $msg = filesize($filepath) < 300 ? file_get_contents($filepath) : '插件文件MD5校验失败';
                @unlink($filepath);
                throw new Exception($msg);
            }
            return true;
        }else{
            throw new Exception('下载插件文件失败，本地文件不存在');
        }
    }

    //获取一键部署列表
    public function get_deplist(){
        $url = $this->url . 'api/panel/get_deplist';
        $post = ['os' => $this->os];
        $res = $this->curl($url, http_build_query($post));
        $result = json_decode($res, true);
        if($result && isset($result['list']) && isset($result['type'])){
            if(empty($result['list']) || empty($result['type'])){
                throw new Exception('获取一键部署列表失败：一键部署列表为空');
            }
            return $result;
        }else{
            throw new Exception('获取一键部署列表失败：'.(isset($result['msg'])?$result['msg']:'第三方云端连接失败'));
        }
    }

    //获取蜘蛛IP列表
    public function btwaf_getspiders(){
        $url = $this->url . 'api/bt_waf/getSpiders';
        $res = $this->curl($url);
        $result = json_decode($res, true);
        if(isset($result['status']) && !$result['status']){
            throw new Exception(isset($result['msg'])?$result['msg']:'获取失败');
        }else{
            return $result;
        }
    }

    private function curl($url, $post = 0){
        $ua = "Mozilla/5.0 (BtCloud; ".request()->root(true).")";
        return get_curl($url, $post, 0, 0, 0, $ua);
    }

    private function curl_download($url, $post, $localpath, $timeout = 300)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		$fp = fopen($localpath, 'w+');
		curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (BtCloud; ".request()->root(true).")");
        if($post){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        curl_exec($ch);
		if (curl_errno($ch)) {
			$message = curl_error($ch);
			curl_close($ch);
			fclose($fp);
			throw new Exception('下载文件失败：'.$message);
		}
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($httpcode>299){
			curl_close($ch);
			fclose($fp);
			throw new Exception('下载文件失败：HTTPCODE-'.$httpcode);
		}
        curl_close($ch);
		fclose($fp);
        return true;
    }

}