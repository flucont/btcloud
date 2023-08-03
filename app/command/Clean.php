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

class Clean extends Command
{
    protected function configure()
    {
        $this->setName('clean')
            ->setDescription('the clean command');
    }

    protected function execute(Input $input, Output $output)
    {
        $res = Db::name('config')->cache('configs',0)->column('value','key');
        Config::set($res, 'sys');
        
        if(config_get('bt_url')){
            $this->clean_plugins($input, $output, 'Linux');
        }
        if(config_get('wbt_url')){
            $this->clean_plugins($input, $output, 'Windows');
        }

        config_set('cleantime', date('Y-m-d H:i:s'));
    }

    private function clean_plugins(Input $input, Output $output, $os){
        $data_dir = get_data_dir($os) . 'plugins/';
        $file_list = [];
        $json_arr = Plugins::get_plugin_list($os);
        if(count($json_arr['list']) == 0) return;
        foreach($json_arr['list'] as $plugin){
            foreach($plugin['versions'] as $version){
                $ver = $version['m_version'].'.'.$version['version'];
                if(!isset($version['download'])){
                    $file_list[] = $plugin['name'].'-'.$ver;
                }
            }
        }

        $count = 0;
        $dir = opendir($data_dir.'package');
        while(false !== ( $file = readdir($dir)) ) {
            if($file == '.' || $file == '..') continue;
            $name = str_replace('.zip', '', $file);
            if(!in_array($name, $file_list)){
                $filepath = $data_dir . 'package/' . $file;
                unlink($filepath);
                $count++;
            }
        }
        $output->writeln($os.'成功清理'.$count.'个历史版本插件包');

        $count = 0;
        $dir = opendir($data_dir.'folder');
        while(false !== ( $file = readdir($dir)) ) {
            if($file == '.' || $file == '..') continue;
            if(!in_array($file, $file_list)){
                $filepath = $data_dir . 'folder/' . $file;
                $this->delete_dir($filepath);
                $count++;
            }
        }
        $output->writeln($os.'成功清理'.$count.'个历史版本插件目录');
    }

    // 删除文件夹
    private function delete_dir($dir){
        $rd = opendir($dir);
        if (!$rd) {
            return false;
        }

        while (($file = readdir($rd)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $file = $dir . '/' . $file;

            if (is_dir($file)) {
                $this->delete_dir($file);
            }
            else {
                unlink($file);
            }
        }

        closedir($rd);
        rmdir($dir);
        return true;
    }
}
