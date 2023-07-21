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

class DecryptFile extends Command
{
    protected function configure()
    {
        $this->setName('decrypt')
            ->addArgument('type', Argument::REQUIRED, '文件类型,plugin:插件文件,module:模块文件,classdir:宝塔class目录,all:所有py文件')
            ->addArgument('file', Argument::REQUIRED, '文件路径')
            ->addArgument('os', Argument::OPTIONAL, '操作系统:Windows/Linux')
            ->setDescription('解密宝塔面板python文件');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = trim($input->getArgument('type'));
        $file = trim($input->getArgument('file'));

        if(!file_exists($file)){
            $output->writeln('文件不存在');
            return;
        }
        
        if($type == 'plugin'){
            $os = trim($input->getArgument('os'));
            try{
                if(Plugins::decode_plugin_main_local($file, $os)){
                    $output->writeln('文件解密成功！');
                }else{
                    $output->writeln('文件解密失败！');
                }
            }catch(\Exception $e){
                $output->writeln($e->getMessage());
            }
        }elseif($type == 'module'){
            try{
                $res = Plugins::decode_module_file($file);
                if($res == 2){
                    $output->writeln('文件解密失败！');
                }elseif($res == 1){
                    $output->writeln('文件解密成功！');
                }
            }catch(\Exception $e){
                $output->writeln($e->getMessage());
            }
        }elseif($type == 'classdir'){
            $file = rtrim($file, '/');
            if(!file_exists($file.'/common.py')){
                $output->writeln('当前路径非宝塔面板class目录');
                return;
            }
            $dirs = glob($file.'/*Model');
            foreach($dirs as $dir){
                if(!is_dir($dir))continue;
                $files = glob($dir.'/*Model.py');
                foreach($files as $file){
                    try{
                        $res = Plugins::decode_module_file($file);
                        if($res == 2){
                            $output->writeln('文件解密失败：'.$file);
                        }elseif($res == 1){
                            $output->writeln('文件解密成功：'.$file);
                        }
                    }catch(\Exception $e){
                        $output->writeln($e->getMessage().'：'.$file);
                    }
                }
            }
        }elseif($type == 'all'){
            $file = rtrim($file, '/');
            $this->scan_all_file($input, $output, $file);
        }else{
            $output->writeln('未知文件类型');
        }
    }

    private function scan_all_file(Input $input, Output $output, $path) {
        $dir = opendir($path);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $filepath = $path . '/' . $file;
                if ( is_dir($filepath) ) {
                    $this->scan_all_file($input, $output, $filepath);
                }
                elseif(substr($filepath, -3) == '.py') {
                    try{
                        $res = Plugins::decode_module_file($filepath);
                        if($res == 2){
                            $output->writeln('文件解密失败：'.$filepath);
                        }elseif($res == 1){
                            $output->writeln('文件解密成功：'.$filepath);
                        }
                    }catch(\Exception $e){
                        $output->writeln($e->getMessage().'：'.$filepath);
                    }
                }
            }
        }
        closedir($dir);
    }

    
}
