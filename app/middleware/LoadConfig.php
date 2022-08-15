<?php
declare (strict_types = 1);

namespace app\middleware;

use think\facade\Db;
use think\facade\Config;

class LoadConfig
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        if (!file_exists(app()->getRootPath().'.env')){
            if(strpos(request()->url(),'/installapp')===false){
                return redirect((string)url('/installapp'))->header([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                    'Pragma' => 'no-cache',
                ]);
            }else{
                return $next($request);
            }
        }

        $res = Db::name('config')->cache('configs',0)->column('value','key');
        Config::set($res, 'sys');

        return $next($request)->header([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
