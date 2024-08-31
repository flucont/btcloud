<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use think\facade\Config;
use app\lib\Plugins;

class CleanViteJs extends Command
{
    protected function configure()
    {
        $this->setName('cleanvitejs')
            ->addArgument('dir', Argument::REQUIRED, '/BTPanel/static/vite/js/路径')
            ->setDescription('处理宝塔面板vite/js文件');
    }

    protected function execute(Input $input, Output $output)
    {
        $dir = trim($input->getArgument('dir'));
        if(!file_exists($dir)){
            $output->writeln('目录不存在');
            return;
        }
        //$this->handlefile($dir.'/DockerImages.js');
        $this->checkdir($dir);
    }

    private function getExtendCode($content, $part, $n = 1, $startChar = '{', $endChar = '}'){
        if(!$part) return false;
        $length = strlen($content);
        $start = strpos($content, $part);
        if($start===false)return false;
        $end = $start+strlen($part);
        $start--;
        $c = 0;
        for($i=$start;$i>=0;$i--){
            if(substr($content,$i,1) == $startChar) $c++;
            if(substr($content,$i,1) == $endChar) $c--;
            if($c == $n){
                $start = $i;
                break;
            }
        }
        $c = 0;
        for($i=$end;$i<=$length;$i++){
            if(substr($content,$i,1) == $endChar) $c++;
            if(substr($content,$i,1) == $startChar) $c--;
            if($c == $n){
                $end = $i;
                break;
            }
        }
        return substr($content, $start, $end - $start + 1);
    }
    
    private function getExtendFunction($content, $part, $startChar = '(', $endChar = ')'){
        $code = $this->getExtendCode($content, $part, 1, $startChar, $endChar);
        if(!$code) return false;
        $start = strpos($content, $code) - 1;
        $end = $start + strlen($code);
        for($i=$start;$i>=0;$i--){
            $char = substr($content,$i,1);
            if(!ctype_alpha($char)){
                $start = $i+1;
                break;
            }
        }
        if(substr($content,$start-1,1) == ',') $start--;
        return substr($content, $start, $end - $start + 1);
    }
    
    private function checkdir($basedir){
        if($dh=opendir($basedir)){
            while (($file=readdir($dh)) !== false){
                if($file != '.' && $file != '..'){
                    if(!is_dir($basedir.'/'.$file) && substr($file,-3)=='.js'){
                        $this->handlefile($basedir.'/'.$file);
                    }else if(!is_dir($basedir.'/'.$file) && substr($file,-4)=='.map'){
                        unlink($basedir.'/'.$file);
                    }
                }
            }
            closedir($dh);
        }
    }
    
    private function handlefile($filepath){
        $file = file_get_contents($filepath);
        if(!$file)return;
    
        $flag = false;
    
        if(strpos($file, 'window.location.protocol.indexOf("https")>=0')!==false){ //index
            $file = str_replace('(window.location.protocol.indexOf("https")>=0)', '1', $file);
            $file = preg_replace('!setTimeout\(\(\(\)=>\{\w+\(\)\}\),3e3\)!', '', $file);
            $file = preg_replace('!setTimeout\(\(function\(\)\{\w+\(\)\}\),3e3\)!', '', $file);
            $file = preg_replace('!recommendShow:\w+,!', 'recommendShow:!1,', $file);
            $code = $this->getExtendCode($file, '"需求反馈"', 2);
            if($code){
                $file = str_replace($code, '{}', $file);
            }
            $flag = true;
        }
    
        if(strpos($file, '"WechatOfficial"')!==false){ //main
            $code = $this->getExtendCode($file, '"WechatOfficial"', 5);
            $code = $this->getExtendFunction($file, $code);
            $start = strpos($file, $code) - 1;
            for($i=$start;$i>=0;$i--){
                if(substr($file,$i,1) == ','){
                    $start = $i;
                    break;
                }
            }
            $code = $this->getExtendCode($file, '"/other/customer-service.png"', 2);
            $code = $this->getExtendCode($file, $code, 2, '[', ']');
            $end = strpos($file, $code)+strlen($code);
            $code = substr($file, $start, $end - $start + 1);
            $file = str_replace($code, '', $file);
            $file = str_replace('startNegotiate(),', '', $file);
            $flag = true;
        }

        if(strpos($file, '"calc"') !== false && strpos($file, '"checkConfirm"') !== false){ //main2
            $file = preg_replace('!,isCalc:\w+,isInput:\w+,!', ',isCalc:!1,isInput:!1,', $file);
            $file = preg_replace('!"calc"===\w+\.type!', '!1', $file);
            $file = preg_replace('!\w+\(\(\(\)=>"input"===\w+\.type\)\)!', '!1', $file);
            $file = preg_replace('!"calc"===\w+\.type!', '!1', $file);
            $file = preg_replace('!\w+\(\(function\(\)\{return"input"===\w+\.type\}\)\)!', '!1', $file);
            $flag = true;
        }
    
        if(strpos($file, '请冷静几秒钟，确认以下要删除的数据')!==false && strpos($file, '"计算结果："')!==false){ //site
            $code = $this->getExtendCode($file, '"计算结果："', 2, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '', $file);
            $file = preg_replace('!\w+\.sum===\w+\.addend1\+\w+\.addend2!', '!0', $file);
            $file = preg_replace('!\w+\.sum\!==\w+\.addend1\+\w+\.addend2!', '!1', $file);
            $file = preg_replace('!,disableDeleteButton:\w+,countdown:\w+,!', ',disableDeleteButton:!1,countdown:!1,', $file);
            if(preg_match('/startCountdown:(\w+),/', $file, $matchs)){
                $file = str_replace([';'.$matchs[1].'()', $matchs[1].'(),'], '', $file);
            }
            $flag = true;
        }
    
        if(strpos($file, 'svgtofont-left-waf')!==false){ //site.table
            $code = $this->getExtendCode($file, 'svgtofont-left-waf');
            $code = $this->getExtendCode($file, $code, 1, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '""', $file);
            $flag = true;
        }
    
        if(strpos($file, '"商用SSL证书"')!==false){ //site-ssl
            $code = $this->getExtendFunction($file, '"商用SSL证书"', '{', '}');
            $file = str_replace($code, '', $file);
            $code = $this->getExtendFunction($file, '"测试证书"', '{', '}');
            $file = str_replace($code, '', $file);
            $file = str_replace('"currentCertInfo":"busSslList"', '"currentCertInfo":"currentCertInfo"', $file);
            $file = preg_replace('!\{(\w+)\.value="busSslList",\w+\(\)\}!', '{$1.value="letsEncryptList"}', $file);
            $flag = true;
        }
    
        if(strpos($file, '如果您希望添加其它Docker应用')!==false){
            $code = $this->getExtendCode($file, '如果您希望添加其它Docker应用', 1, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '', $file);
            $flag = true;
        }

        if(strpos($file, '"recom-view"')!==false){ //soft
            $code = $this->getExtendFunction($file, '"recom-view"');
            $file = str_replace($code, 'void(0)', $file);
            $flag = true;
        }

        if(strpos($file, '"打开插件文件目录"')!==false){ //soft.table
            $code = $this->getExtendFunction($file, '"(续费)"');
            $file = str_replace($code, '""', $file);
            $code = $this->getExtendFunction($file, '"(续费)"');
            $file = str_replace($code, '""', $file);
            $flag = true;
        }

        if(strpos($file, '检测到同名文件')!==false){ //file.
            $code = $this->getExtendCode($file, '计算结果：', 3, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '', $file);
            $file = preg_replace('!\w+\.sum===\w+\.addend1\+\w+\.addend2!', '!0', $file);
            $flag = true;
        }
    
        for($i=0;$i<5;$i++){
            $code = $this->getExtendCode($file, 'content:"需求反馈"', 2);
            if($code){
                $code = $this->getExtendFunction($file, $code);
                $start = strpos($file, $code);
                if(substr($file,$start-1,1) == ':'){
                    $file = str_replace($code, '{}', $file);
                }else{
                    $file = str_replace($code, '', $file);
                }
                $flag = true;
            }
        }
        $code = $this->getExtendFunction($file, '("需求反馈")');
        if($code){
            $file = str_replace($code, '', $file);
            $flag = true;
        }
        $code = $this->getExtendFunction($file, '(" 需求反馈 ")');
        if($code){
            $file = str_replace($code, '', $file);
            $flag = true;
        }
        if(strpos('暂无搜索结果，<span class="text-primary cursor-pointer NpsDialog">提交需求反馈</span>', $file)!==false){
            $file = str_replace('暂无搜索结果，<span class="text-primary cursor-pointer NpsDialog">提交需求反馈</span>', '暂无搜索结果', $file);
            $flag = true;
        }
    
        if(!$flag) return;
        if(file_put_contents($filepath, $file)){
            echo '文件：'.$filepath.' 处理成功'."\n";
        }else{
            echo '文件：'.$filepath.' 处理失败，可能无写入权限'."\n";
        }
    }
}
