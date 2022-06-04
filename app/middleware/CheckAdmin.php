<?php
declare (strict_types=1);

namespace app\middleware;


class CheckAdmin
{
    public function handle($request, \Closure $next)
    {
        if (!request()->islogin) {
            if ($request->isAjax() || !$request->isGet()) {
                return json(['code'=>-1, 'msg'=>'未登录'])->code(401);
            }
            return redirect((string)url('/admin/login'));
        }
        return $next($request);
    }
}
