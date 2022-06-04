<?php
use think\facade\Route;


Route::get('/', 'index/index');
Route::get('/download', 'index/download');


Route::any('/panel/get_plugin_list', 'api/get_plugin_list');
Route::post('/down/download_plugin', 'api/download_plugin');
Route::post('/down/download_plugin_main', 'api/download_plugin_main');
Route::post('/panel/get_soft_list_status', 'api/return_success');
Route::post('/panel/get_unbinding', 'api/return_success');
Route::post('/bt_cert', 'api/return_error');

Route::group('api', function () {
    Route::get('/getUpdateLogs', 'api/get_update_logs');
    Route::get('/panel/get_version', 'api/get_version');
    Route::get('/SetupCount', 'api/setup_count');
    Route::any('/panel/updateLinux', 'api/check_update');
    Route::post('/panel/check_auth_key', 'api/check_auth_key');
    Route::post('/panel/check_domain', 'api/check_domain');
    Route::get('/index/get_time', 'api/get_time');
    Route::get('/panel/is_pro', 'api/is_pro');
    Route::get('/getIpAddress', 'api/get_ip_address');
    Route::post('/Auth/GetAuthToken', 'api/get_auth_token');
    Route::get('/Pluginother/get_file', 'api/download_plugin_other');

    Route::post('/Pluginother/create_order', 'api/return_error');
    Route::post('/Pluginother/renew_order', 'api/return_error');
    Route::post('/Pluginother/order_stat', 'api/return_empty');
    Route::post('/Pluginother/re_order_stat', 'api/return_empty');
    Route::post('/Pluginother/create_order_okey', 'api/return_empty');

    Route::post('/Plugin/check_order_pay_status', 'api/return_error');
    Route::post('/Plugin/get_product_discount', 'api/return_error');
    Route::post('/Plugin/get_order_list_byuser', 'api/return_page_data');
    Route::post('/Plugin/create_order', 'api/return_error');
    Route::post('/Plugin/check_product_pays', 'api/return_error');
    Route::post('/Plugin/get_product_list', 'api/return_empty_array');
    Route::post('/Plugin/get_re_order_status', 'api/return_error');
    Route::post('/Plugin/create_order_voucher', 'api/return_error');
    Route::post('/Plugin/get_voucher', 'api/return_empty_array');

    Route::post('/invite/get_voucher', 'api/return_empty_array');
    Route::post('/invite/get_order_status', 'api/return_error');
    Route::post('/invite/get_product_discount_by', 'api/return_error');
    Route::post('/invite/get_re_order_status', 'api/return_error');
    Route::post('/invite/create_order_voucher', 'api/return_error');
    Route::post('/invite/create_order', 'api/return_error');

    Route::post('/panel/get_plugin_remarks', 'api/get_plugin_remarks');
    Route::post('/panel/set_user_adviser', 'api/return_success');

    Route::post('/wpanel/get_messages', 'api/return_empty_array');
    Route::post('/panel/plugin_total', 'api/return_empty');
    Route::post('/panel/plugin_score', 'api/plugin_score');
    Route::post('/panel/get_plugin_socre', 'api/get_plugin_socre');
    Route::get('/panel/s_error', 'api/return_empty');
    Route::post('/panel/get_py_module', 'api/return_error');
    Route::post('/panel/total_keyword', 'api/return_empty');
    Route::post('/panel/model_total', 'api/return_empty');
    Route::post('/wpanel/model_click', 'api/return_empty');
    Route::post('/v2/statistics/report_plugin_daily', 'api/return_error');
    Route::post('/panel/notpro', 'api/return_empty');

    Route::post('/LinuxBeta', 'api/return_error');
    Route::post('/panel/apple_beta', 'api/return_error');
    Route::post('/panel/to_not_beta', 'api/return_error');
    Route::post('/panel/to_beta', 'api/return_error');
    Route::get('/panel/get_beta_logs', 'api/get_beta_logs');

    Route::miss('api/return_error');
});

Route::get('/admin/verifycode', 'admin/verifycode')->middleware(\think\middleware\SessionInit::class);
Route::any('/admin/login', 'admin/login')->middleware(\think\middleware\SessionInit::class);
Route::get('/admin/logout', 'admin/logout');

Route::group('admin', function () {
    Route::get('/', 'admin/index');
    Route::any('/set', 'admin/set');
    Route::post('/setaccount', 'admin/setaccount');
    Route::post('/testbturl', 'admin/testbturl');
    Route::get('/plugins', 'admin/plugins');
    Route::post('/plugins_data', 'admin/plugins_data');
    Route::post('/download_plugin', 'admin/download_plugin');
    Route::get('/refresh_plugins', 'admin/refresh_plugins');
    Route::get('/record', 'admin/record');
    Route::post('/record_data', 'admin/record_data');
    Route::get('/log', 'admin/log');
    Route::post('/log_data', 'admin/log_data');
    Route::get('/list', 'admin/list');
    Route::post('/list_data', 'admin/list_data');
    Route::post('/list_op', 'admin/list_op');

})->middleware(\app\middleware\CheckAdmin::class);

Route::miss(function() {
    return response('404 Not Found')->code(404);
});
