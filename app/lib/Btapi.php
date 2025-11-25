<?php

namespace app\lib;

use Exception;

class Btapi
{
    private $BT_KEY; //接口密钥
  	private $BT_PANEL; //面板地址
	
	public function __construct($bt_panel, $bt_key){
		$this->BT_PANEL = $bt_panel;
		$this->BT_KEY = $bt_key;
	}
	
    //获取面板配置信息
	public function get_config(){
		$url = $this->BT_PANEL.'/config?action=get_config';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	public function get_config_go(){
		$url = $this->BT_PANEL.'/panel/get_config';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//获取已登录用户信息
	public function get_user_info(){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=get_user_info';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//从云端获取插件列表
	public function get_plugin_list(){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=get_plugin_list';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//下载插件包，返回文件路径
	public function get_plugin_filename($plugin_name, $version){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=download_plugin';
		
		$p_data = $this->GetKeyData();
		$p_data['plugin_name'] = $plugin_name;
		$p_data['version'] = $version;
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//下载插件主程序文件，返回文件路径
	public function get_plugin_main_filename($plugin_name, $version){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=download_plugin_main';
		
		$p_data = $this->GetKeyData();
		$p_data['plugin_name'] = $plugin_name;
		$p_data['version'] = $version;
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//解密插件主程序py代码，返回文件路径
	public function get_decode_plugin_main($plugin_name, $version){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=decode_plugin_main';
		
		$p_data = $this->GetKeyData();
		$p_data['plugin_name'] = $plugin_name;
		$p_data['version'] = $version;
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//下载插件其他文件，返回文件路径
	public function get_plugin_other_filename($fname){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=download_plugin_other';
		
		$p_data = $this->GetKeyData();
		$p_data['fname'] = $fname;
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//下载文件
	public function download($filename, $localpath){
		$url = $this->BT_PANEL.'/download';
		
		$p_data = $this->GetKeyData();
		$p_data['filename'] = $filename;

		$result = $this->curl_download($url.'?'.http_build_query($p_data), $localpath);

      	return $result;
	}

	//获取文件base64
	public function get_file($filename){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=get_file';
		
		$p_data = $this->GetKeyData();
		$p_data['filename'] = $filename;
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//购买第三方插件
	public function create_plugin_other_order($pid){
		$url = $this->BT_PANEL.'/auth?action=create_plugin_other_order';
		
		$p_data = $this->GetKeyData();
		$p_data['pid'] = $pid;
		$p_data['cycle'] = '999';
		$p_data['type'] = '0';
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//获取一键部署列表
	public function get_deplist(){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=get_deplist';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//BTWAF-获取蜘蛛列表
	public function btwaf_getspiders(){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=btwaf_getspiders';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		$result = str_replace("\u0000", '', $result);
		
		$data = json_decode($result,true);
      	return $data;
	}

	//BTWAF-获取堡塔恶意情报IP库
	public function btwaf_getmalicious(){
		$url = $this->BT_PANEL.'/plugin?action=a&name=kaixin&s=btwaf_getmalicious';
		
		$p_data = $this->GetKeyData();
		
		$result = $this->curl($url,$p_data);
		
		$data = json_decode($result,true);
      	return $data;
	}
	

  	private function GetKeyData(){
  		$now_time = time();
    	$p_data = array(
			'request_token'	=>	md5($now_time.''.md5($this->BT_KEY)),
			'request_time'	=>	$now_time
		);
    	return $p_data;    
    }
  	
  
    private function curl($url, $data = null, $timeout = 60)
    {
    	//定义cookie保存位置
        $cookie_file=app()->getRuntimePath().md5($this->BT_PANEL).'.cookie';
        if(!file_exists($cookie_file)){
            touch($cookie_file);
        }
		
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		if($data){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

	private function curl_download($url, $localpath, $timeout = 300)
    {
    	//定义cookie保存位置
        $cookie_file=app()->getRuntimePath().md5($this->BT_PANEL).'.cookie';
        if(!file_exists($cookie_file)){
            touch($cookie_file);
        }
		
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		$fp = fopen($localpath, 'w+');
		curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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