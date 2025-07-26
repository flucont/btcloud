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
            if(!ctype_alpha($char)&&$char!='_'){
                $start = $i+1;
                break;
            }
        }
        if(substr($content,$start-1,1) == ',') $start--;
        else if(substr($content,$end+1,1) == ',') $end++;
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

    private function str_replace_once($needle, $replace, $haystack) {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }
        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }
    
    private function handlefile($filepath){
        $file = file_get_contents($filepath);
        if(!$file)return;
    
        $flag = false;
    
        if(strpos($file, 'window.location.protocol.indexOf("https")>=0')!==false){ //index
            $file = str_replace('window.location.protocol.indexOf("https")>=0', '!0', $file);
            $code = $this->getExtendCode($file, 'isGetCoupon:', 2);
            if($code){
                $file = str_replace($code, '{}', $file);
            }
            $file = preg_replace('!recommendShow:\w+,!', 'recommendShow:!1,', $file, 1);
            $code = $this->getExtendCode($file, '"打开需求反馈"', 1, '[', ']');
            if($code){
                $file = str_replace($code, '[]', $file);
            }
            $flag = true;
        }
    
        if(strpos($file, '论坛求助')!==false){ //main
            $code = $this->getExtendCode($file, '"微信公众号"', 1);
            $code = $this->getExtendFunction($file, $code);
            $start = strpos($file, $code) - 1;
            for($i=$start;$i>=0;$i--){
                if(substr($file,$i,1) == ','){
                    $start = $i;
                    break;
                }
            }
            $code = $this->getExtendCode($file, '"/other/customer-qrcode.png"', 2);
            $code = $this->getExtendFunction($file, $code);
            $end = strpos($file, $code)+strlen($code);
            $code = substr($file, $start, $end - $start);
            $file = str_replace($code, '', $file);
            $flag = true;
        }
    
        if(strpos($file, 'useNegotiate')!==false){ //utils
            $code = $this->getExtendCode($file, 'createPeerConnection()', 1);
            if($code){
                $file = str_replace($code, '{}', $file);
            }
            $file = preg_replace('!\w+\(\(\(\)=>"calc"===\w+\.\w+\.type\)\)!', '!1', $file);
            $file = preg_replace('!\w+\(\(\(\)=>"input"===\w+\.\w+\.type\)\)!', '!1', $file);
            $file = preg_replace('!\w+\(\(function\(\)\{return"calc"===\w+\.\w+\.type\}\)\)!', '!1', $file);
            $file = preg_replace('!\w+\(\(function\(\)\{return"input"===\w+\.\w+\.type\}\)\)!', '!1', $file);
            $code = $this->getExtendCode($file, '"自动部署"', 2);
            if($code){
                $file = str_replace($code, '', $file);
            }
            $flag = true;
        }
    
        if(strpos($file, '请冷静几秒钟，确认以下要删除的数据')!==false && strpos($file, '"计算结果："')!==false){ //site
            $code = $this->getExtendCode($file, '"计算结果："', 1, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '', $file);
            $file = preg_replace('!\w+\.sum===\w+\.addend1\+\w+\.addend2!', '!0', $file);
            $file = preg_replace('!value=\!0,(\w+)\.value=5;!', 'value=!1,$1.value=0;', $file);
            $flag = true;
        }
    
        if(strpos($file, '"left-waf"')!==false && strpos($file, '"iconWaf"')!==false){ //site.table
            $code = $this->getExtendCode($file, '"left-waf"');
            $code = $this->getExtendCode($file, $code, 1, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '""', $file);
            $flag = true;
        }
    
        if(strpos($file, 'svgtofont-left-waf')!==false && strpos($file, '"iconWaf"')!==false){ //site.table
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
            $code = $this->getExtendCode($file, '"购买商业证书"', 2);
            if($code){
                $code2 = str_replace('"busSslList"', '"letsEncryptList"', $code);
                $code2 = str_replace($this->getExtendFunction($code, '"购买商业证书"'), '', $code2);
                $file = str_replace($code, $code2, $file);
            }
            $file = preg_replace('!(\w+)\("sslCertificate"\)!', '$1("EncryptCertificate")', $file);
            $flag = true;
        }
        if(strpos($file, '"busSslList"')!==false && strpos($filepath, '/useStore')){ //site-ssl
            $file = str_replace('"busSslList"', '"currentCertInfo"', $file);
            $flag = true;
        }

        if(strpos($file, '"商用SSL"')!==false){ //ssl
            $code = $this->getExtendFunction($file, '"商用SSL"', '{', '}');
            $file = str_replace($code, '', $file);
            $code = $this->getExtendFunction($file, '"测试证书"', '{', '}');
            $file = str_replace($code, '', $file);
            $code = $this->getExtendCode($file, ',"联系客服"', 2, '[', ']');
            if($code){
                $file = str_replace($code, '[]', $file);
            }
            $code = $this->getExtendCode($file, ',"联系客服"', 2, '[', ']');
            if($code){
                $file = str_replace($code, '[]', $file);
            }
        }
        if(strpos($file, '"SSL-CERTIFICATE-STORE"')!==false){ //ssl
            $file = str_replace('("ssl")', '("encrypt")', $file);
            $flag = true;
        }
    
        if(strpos($file, '如果您希望添加其它Docker应用')!==false){
            $code = $this->getExtendCode($file, '如果您希望添加其它Docker应用', 1, '[', ']');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '', $file);
            $flag = true;
        }

        if(strpos($file, '"recom-view"')!==false){ //soft
            $code = $this->getExtendCode($file, '"recom-view"');
            $code = $this->getExtendFunction($file, $code);
            $file = str_replace($code, '', $file);
            $flag = true;
        }

        if(strpos($file, '"打开插件文件目录"')!==false){ //soft.table
            $code = $this->getExtendFunction($file, '"(续费)"');
            $file = str_replace($code, '""', $file);
            $flag = true;
        }

        for($i=0;$i<5;$i++){
            $code = $this->getExtendCode($file, ',"需求反馈"', 1, '[', ']');
            if($code){
                if(strpos($code, 'svgtofont-desired')){
                    $file = str_replace($code, '[]', $file);
                }else{
                    $code = $this->getExtendFunction($code, ',"需求反馈"');
                    $file = str_replace($code, '', $file);
                }
                $flag = true;
            }
        }
        $code = $this->getExtendCode($file, '("需求反馈")', 1, '[', ']');
        if($code){
            $file = str_replace($code, '[]', $file);
            $flag = true;
        }
        $code = $this->getExtendCode($file, '(" 需求反馈 ")', 1, '[', ']');
        if($code && strpos($filepath, 'vue_vue_type_') === false){
            $file = str_replace($code, '[]', $file);
            $flag = true;
        }
        $code = $this->getExtendFunction($file, 'label:"需求反馈",', '{', '}');
        if($code){
            $file = str_replace($code, '', $file);
            $flag = true;
        }

        if(strpos('暂无搜索结果，<span class="text-primary cursor-pointer NpsDialog">提交需求反馈</span>', $file)!==false){
            $file = str_replace('暂无搜索结果，<span class="text-primary cursor-pointer NpsDialog">提交需求反馈</span>', '暂无搜索结果', $file);
            $flag = true;
        }

        if(strpos($file, 'getReceiveCoupon()')!==false){ //aapanel-优惠券
            $code = $this->getExtendCode($file, 'getReceiveCoupon()');
            $file = str_replace($code, '{}', $file);
            $flag = true;
        }
    
        if(strpos($file, '"Site.DelSite.index_1"')!==false){ //aapanel-site
            $code = $this->getExtendCode($file, '"Site.DelSite.index_10"', 3, '(', ')');
            if($code){
                $code = $this->getExtendFunction($file, $code);
                $file = str_replace($code, '', $file);
                $file = preg_replace('@\w+\.value!==\w+\.value\+\w+\.value@', '!1', $file);
                $file = preg_replace('@null==\w+\.value\|\|null==\w+\.value@', '!1', $file);
                $file = str_replace('disabled:!0', 'disabled:!1', $file);
                $flag = true;
            }
        }
    
        if(strpos($file, '"Component.Confirm.index_4"')!==false){ //aapanel-public
            $code = $this->getExtendCode($file, '"Component.Confirm.index_4"', 2, '(', ')');
            if($code){
                $code = $this->getExtendFunction($file, $code);
                $file = str_replace($code, '', $file);
                $file = preg_replace('@\w+\.value===\w+\.value\+\w+\.value@', '!0', $file);
                $flag = true;
            }
            $code = $this->getExtendCode($file, '"Component.Confirm.index_1"', 1, '(', ')');
            if($code){
                $code = $this->getExtendFunction($file, $code);
                $file = str_replace($code, '', $file);
                $file = preg_replace('@\w+\.value===\w+\.value\?@', '!0?', $file);
                $flag = true;
            }
        }
        
        if(strpos($file, '"Component.Feedback.index_7"')!==false){ //aapanel-需求反馈
            $code = $this->getExtendCode($file, '"Component.Feedback.index_7"', 2);
            if($code){
                $code = $this->getExtendFunction($file, $code);
                $file = str_replace($code, '', $file);
                $flag = true;
            }
        }
    
        if(strpos($file, '"Soft.index_16"')!==false){ //aapanel-soft
            $code = $this->getExtendCode($file, '"Soft.index_16"', 2);
            if($code){
                $file = str_replace($code, '{}', $file);
                $flag = true;
            }
        }
    
        if(!$flag) return;
        if(file_put_contents($filepath, $file)){
            echo '文件：'.$filepath.' 处理成功'."\n";
        }else{
            echo '文件：'.$filepath.' 处理失败，可能无写入权限'."\n";
        }
    }
}
