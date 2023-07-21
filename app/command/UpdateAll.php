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

class UpdateAll extends Command
{
    protected function configure()
    {
        $this->setName('updateall')
            ->setDescription('the updateall command');
    }

    protected function execute(Input $input, Output $output)
    {
        $res = Db::name('config')->cache('configs',0)->column('value','key');
        Config::set($res, 'sys');
        
        if(config_get('bt_url')){
            $this->process_plugins($input, $output, 'Linux');
        }
        if(config_get('wbt_url')){
            $this->process_plugins($input, $output, 'Windows');
        }

        config_set('runtime', date('Y-m-d H:i:s'));
    }

    private function process_plugins(Input $input, Output $output, $os){
        //刷新插件列表
        if(!$this->refresh_plugin_list($input, $output, $os)){
            return;
        }

        $count = 0;

        $type = intval(config_get($os=='Windows'?'updateall_type_win':'updateall_type'));

        $json_arr = Plugins::get_plugin_list($os);
        //循环下载缺少的插件
        foreach($json_arr['list'] as $plugin){
            if($type == 0 && ($plugin['type']==8 || $plugin['type']==12) || $type == 1 && $plugin['type']==12 || $plugin['type']==10 || $plugin['type']==5) continue;

            foreach($plugin['versions'] as $version){
                $ver = $version['m_version'].'.'.$version['version'];
                if(isset($version['download'])){
                    if(!file_exists(get_data_dir().'plugins/other/'.$version['download'])){
                        if(!$this->download_plugin($input, $output, $plugin['name'], $ver, $os)){
                            sleep(1);
                            $this->download_plugin($input, $output, $plugin['name'], $ver, $os);
                        }
                        sleep(1);
                        $count++;
                    }
                }else{
                    if(!file_exists(get_data_dir($os).'plugins/package/'.$plugin['name'].'-'.$ver.'.zip')){
                        if(!$this->download_plugin($input, $output, $plugin['name'], $ver, $os)){
                            sleep(1);
                            $this->download_plugin($input, $output, $plugin['name'], $ver, $os);
                        }
                        sleep(1);
                        $count++;
                    }
                }
            }
        }
        
        $output->writeln($os.'本次成功下载'.$count.'个插件');
    }

    private function refresh_plugin_list(Input $input, Output $output, $os){
        try{
            Plugins::refresh_plugin_list($os);
            Db::name('log')->insert(['uid' => 1, 'action' => '刷新插件列表', 'data' => '刷新'.$os.'插件列表成功', 'addtime' => date("Y-m-d H:i:s")]);
            $output->writeln('刷新'.$os.'插件列表成功');
            return true;
        }catch(\Exception $e){
            $output->writeln($e->getMessage());
            errorlog($e->getMessage());
            return false;
        }
    }

    private function download_plugin(Input $input, Output $output, $plugin_name, $version, $os){
        $fullname = $plugin_name.'-'.$version;
        try{
            Plugins::download_plugin($plugin_name, $version, $os);
            Db::name('log')->insert(['uid' => 1, 'action' => '下载插件', 'data' => $fullname.' os:'.$os, 'addtime' => date("Y-m-d H:i:s")]);
            $output->writeln('下载'.$os.'插件: '.$fullname.' 成功');
            return true;
        }catch(\Exception $e){
            $output->writeln($fullname.'  '.$e->getMessage());
            errorlog($fullname.'  '.$e->getMessage());
            return false;
        }
    }

    private function download_plugin_image(Input $input, Output $output, $fname){
        try{
            Plugins::download_plugin_other($fname);
            $output->writeln('下载图片: '.$fname.' 成功');
            return true;
        }catch(\Exception $e){
            $output->writeln($fname.'  '.$e->getMessage());
            errorlog($fname.'  '.$e->getMessage());
            return false;
        }
    }
}
