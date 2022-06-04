/*
 *宝塔面板去除各种计算题与延时等待
*/
if("undefined" != typeof bt && bt.hasOwnProperty("show_confirm")){
    bt.show_confirm = function(title, msg, callback, error) {
        layer.open({
            type: 1,
            title: title,
            area: "365px",
            closeBtn: 2,
            shadeClose: true,
            btn: [lan['public'].ok, lan['public'].cancel],
            content: "<div class='bt-form webDelete pd20'>\
					<p style='font-size:13px;word-break: break-all;margin-bottom: 5px;'>" + msg + "</p>" + (error || '') + "\
				</div>",
            yes: function (index, layero) {
                layer.close(index);
                if (callback) callback();
            }
        });
    }
}
if("undefined" != typeof bt && bt.hasOwnProperty("prompt_confirm")){
    bt.prompt_confirm = function (title, msg, callback) {
        layer.open({
            type: 1,
            title: title,
            area: "350px",
            closeBtn: 2,
            btn: ['确认', '取消'],
            content: "<div class='bt-form promptDelete pd20'>\
            	<p>" + msg + "</p>\
            	</div>",
            yes: function (layers, index) {
                layer.close(layers)
                if (callback) callback()
            }
        });
    }
}
if("undefined" != typeof database && database.hasOwnProperty("del_database")){
    database.del_database = function (wid, dbname,obj, callback) {
        title = '',
        tips = '是否确认【删除数据库】，删除后可能会影响业务使用！';
        if(obj && obj.db_type > 0) tips = '远程数据库不支持数据库回收站，删除后将无法恢复，请谨慎操作';
        var title = typeof dbname === "function" ?'批量删除数据库':'删除数据库 [ '+ dbname +' ]';
        layer.open({
            type:1,
            title:title,
            icon:0,
            skin:'delete_site_layer',
            area: "530px",
            closeBtn: 2,
            shadeClose: true,
            content:"<div class=\'bt-form webDelete pd30\' id=\'site_delete_form\'>" +
                "<i class=\'layui-layer-ico layui-layer-ico0\'></i>" +
                "<div class=\'f13 check_title\' style=\'margin-bottom: 20px;\'>"+tips+"</div>" +
                "<div style=\'color:red;margin:18px 0 18px 18px;font-size:14px;font-weight: bold;\'>注意：数据无价，请谨慎操作！！！"+(!recycle_bin_db_open?'<br>风险操作：当前数据库回收站未开启，删除数据库将永久消失！':'')+"</div>" +
                "</div>",
            btn:[lan.public.ok,lan.public.cancel],
            yes:function(indexs){
                var data = {id: wid,name: dbname};
                if(typeof dbname === "function"){
                    delete data.id;
                    delete data.name;
                }
                layer.close(indexs)
                if(typeof dbname === "function"){
                    dbname(data)
                }else{
                    bt.database.del_database(data, function (rdata) {
                        layer.closeAll()
                        if (callback) callback(rdata);
                        bt.msg(rdata);
                    })
                }
            }
        })
    }
}
if("undefined" != typeof site && site.hasOwnProperty("del_site")){
    site.del_site = function(wid, wname, callback) {
        var title = typeof wname === "function" ?'批量删除站点':'删除站点 [ '+ wname +' ]';
        layer.open({
            type:1,
            title:title,
            icon:0,
            skin:'delete_site_layer',
            area: "440px",
            closeBtn: 2,
            shadeClose: true,
            content:"<div class=\'bt-form webDelete pd30\' id=\'site_delete_form\'>" +
                '<i class="layui-layer-ico layui-layer-ico0"></i>' +
                "<div class=\'f13 check_title\'>是否要删除关联的FTP、数据库、站点目录！</div>" +
                "<div class=\"check_type_group\">" +
                "<label><input type=\"checkbox\" name=\"ftp\"><span>FTP</span></label>" +
                "<label><input type=\"checkbox\" name=\"database\"><span>数据库</span>"+ (!recycle_bin_db_open?'<span class="glyphicon glyphicon-info-sign" style="color: red"></span>':'') +"</label>" +
                "<label><input type=\"checkbox\"  name=\"path\"><span>站点目录</span>"+ (!recycle_bin_open?'<span class="glyphicon glyphicon-info-sign" style="color: red"></span>':'') +"</label>" +
                "</div>"+
                "</div>",
            btn:[lan.public.ok,lan.public.cancel],
            success:function(layers,indexs){
                $(layers).find('.check_type_group label').hover(function(){
                    var name = $(this).find('input').attr('name');
                    if(name === 'data' && !recycle_bin_db_open){
                        layer.tips('风险操作：当前数据库回收站未开启，删除数据库将永久消失！', this, {tips: [1, 'red'],time:0})
                    }else if(name === 'path' && !recycle_bin_open){
                        layer.tips('风险操作：当前文件回收站未开启，删除站点目录将永久消失！', this, {tips: [1, 'red'],time:0})
                    }
                },function(){
                    layer.closeAll('tips');
                })
            },
            yes:function(indexs){
                var data = {id: wid,webname: wname};
                $('#site_delete_form input[type=checkbox]').each(function (index, item) {
                    if($(item).is(':checked')) data[$(item).attr('name')] = 1
                })
                var is_database = data.hasOwnProperty('database'),is_path = data.hasOwnProperty('path'),is_ftp = data.hasOwnProperty('ftp');
                if((!is_database && !is_path) && (!is_ftp || is_ftp)){
                    if(typeof wname === "function"){
                        wname(data)
                        return false;
                    }
                    bt.site.del_site(data, function (rdata) {
                        layer.close(indexs);
                        if (callback) callback(rdata);
                        bt.msg(rdata);
                    })
                    return false
                }
                if(typeof wname === "function"){
                    delete data.id;
                    delete data.webname;
                }
                layer.close(indexs)
                if(typeof wname === "function"){
                    console.log(data)
                    wname(data)
                }else{
                    bt.site.del_site(data, function (rdata) {
                        layer.closeAll()
                        if (rdata.status) site.get_list();
                        if (callback) callback(rdata);
                        bt.msg(rdata);
                    })
                }
            }
        })
    }
}
if("undefined" != typeof bt && bt.hasOwnProperty("firewall") && bt.firewall.hasOwnProperty("add_accept_port")){
    bt.firewall.add_accept_port = function(type, port, ps, callback) {
        var action = "AddDropAddress";
        if (type == 'port') {
            ports = port.split(':');
            if (port.indexOf('-') != -1) ports = port.split('-');
            for (var i = 0; i < ports.length; i++) {
                if (!bt.check_port(ports[i])) {
                    layer.msg(lan.firewall.port_err, { icon: 5 });
                    return;
                }
            }
            action = "AddAcceptPort";
        }

        loading = bt.load();
        bt.send(action, 'firewall/' + action, { port: port, type: type, ps: ps }, function(rdata) {
            loading.close();
            if (callback) callback(rdata);
        })
    }
}
function SafeMessage(j, h, g, f) {
	if(f == undefined) {
		f = ""
	}
	var mess = layer.open({
		type: 1,
		title: j,
		area: "350px",
		closeBtn: 2,
		shadeClose: true,
		content: "<div class='bt-form webDelete pd20 pb70'><p>" + h + "</p>" + f + "<div class='bt-form-submit-btn'><button type='button' class='btn btn-danger btn-sm bt-cancel'>"+lan.public.cancel+"</button> <button type='button' id='toSubmit' class='btn btn-success btn-sm' >"+lan.public.ok+"</button></div></div>"
	});
	$(".bt-cancel").click(function(){
		layer.close(mess);
	});
	$("#toSubmit").click(function() {
		layer.close(mess);
		g();
	})
}
$(document).ready(function () {
    if($('#updata_pro_info').length>0){
        $('#updata_pro_info').html('');
        bt.set_cookie('productPurchase', 1);
    }
})