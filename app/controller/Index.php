<?php
namespace app\controller;

use app\BaseController;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        return 'Server is ok';
    }

    public function download()
    {
        if(config_get('download_page') == '0' && !request()->islogin){
            return redirect('/admin/login');
        }
        View::assign('siteurl', request()->root(true));
        return view();
    }

    
}
