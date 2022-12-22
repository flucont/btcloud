/*
 *宝塔面板去除各种计算题与延时等待
*/
if("undefined" != typeof bt && bt.hasOwnProperty("show_confirm")){
    bt.show_confirm = function(title, msg, fun, error) {
        if (error == undefined) {
            error = ""
        }
        var mess = layer.open({
            type: 1,
            title: title,
            area: "350px",
            closeBtn: 2,
            shadeClose: true,
            content: "<div class='bt-form webDelete pd20 pb70'><p>" + msg + "</p>" + error + "<div class='bt-form-submit-btn'><button type='button' class='btn btn-danger btn-sm bt-cancel'>" + lan.public.cancel + "</button> <button type='button' id='toSubmit' class='btn btn-success btn-sm' >" + lan.public.ok + "</button></div></div>"
        });
        $(".bt-cancel").click(function () {
            layer.close(mess);
        });
        $("#toSubmit").click(function () {
            layer.close(mess);
            fun();
        })
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
        var title = '',
			tips = '是否确认【删除数据库】，删除后可能会影响业务使用！';
		if(obj && obj.db_type > 0) tips = '远程数据库不支持数据库回收站，删除后将无法恢复，请谨慎操作';
		title = typeof dbname === "function" ?'批量删除数据库':'删除数据库 [ '+ dbname +' ]';
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
				var arrs = wid instanceof Array ? wid : [wid]
				var ids = JSON.stringify(arrs), countDown = 9;
				if (arrs.length == 1) countDown = 4
				title = typeof dbname === "function" ?'二次验证信息，批量删除数据库':'二次验证信息，删除数据库 [ ' + dbname + ' ]';
				var loadT = bt.load('正在检测数据库数据信息，请稍后...')

				bt_tools.send({url:'database/'+bt.data.db_tab_name+'/check_del_data',data:{data:JSON.stringify({ids: ids})}},function(res){
					loadT.close()
					layer.open({
						type:1,
						title:title,
						closeBtn: 2,
						skin: 'verify_site_layer_info',
						area: '740px',
						content: '<div class="check_delete_site_main pd30">' +
							'<i class="layui-layer-ico layui-layer-ico0"></i>' +
							'<div class="check_layer_title">堡塔温馨提示您，请冷静几秒钟，确认是否要删除以下数据。</div>' +
							'<div class="check_layer_content">' +
							'<div class="check_layer_item">' +
							'<div class="check_layer_site"></div>' +
							'<div class="check_layer_database"></div>' +
							'</div>' +
							'</div>' +
							'<div class="check_layer_error ' + (recycle_bin_db_open ? 'hide' : '') + '"><span class="glyphicon glyphicon-info-sign"></span>风险事项：当前未开启数据库回收站功能，删除数据库后，数据库将永久消失！</div>' +
							'<div class="check_layer_message"><span style="color:red">注意：请仔细阅读以上要删除信息，防止数据库被误删</span></div>' +
							'</div>',
						btn: ['确认删除', '取消删除'],
						success: function (layers) {
							var html = '', rdata = res.data;
							var filterData = rdata.filter(function(el){
								return  ids.indexOf(el.id) != -1
							})
							for (var i = 0; i < filterData.length; i++) {
								var item = filterData[i], newTime = parseInt(new Date().getTime() / 1000),
									t_icon = '<span class="glyphicon glyphicon-info-sign" style="color: red;width:15px;height: 15px;;vertical-align: middle;"></span>';

								database_html = (function(item){
									var is_time_rule = (newTime - item.st_time) > (86400 * 30)  && (item.total > 1024 * 10),
										is_database_rule = res.db_size <= item.total,
										database_time = bt.format_data(item.st_time, 'yyyy-MM-dd'),
										database_size = bt.format_size(item.total);

									var f_size = '<i ' + (is_database_rule ? 'class="warning"' : '') + ' style = "vertical-align: middle;" > ' + database_size + '</i> ' + (is_database_rule ? t_icon : '');
									var t_size = '注意：此数据库较大，可能为重要数据，请谨慎操作.\n数据库：' + database_size;

									return '<div class="check_layer_database">' +
										'<span title="数据库：' + item.name + '">数据库：' + item.name + '</span>' +
										'<span title="' + t_size+'">大小：' + f_size +'</span>' +
										'<span title="' + (is_time_rule && item.total != 0 ? '重要：此数据库创建时间较早，可能为重要数据，请谨慎操作.' : '') + '时间：' + database_time+'">创建时间：<i ' + (is_time_rule && item.total != 0 ? 'class="warning"' : '') + '>' + database_time + '</i></span>' +
										'</div>'
								}(item))
								if(database_html !== '') html += '<div class="check_layer_item">' + database_html +'</div>';
							}
							if(html === '') html = '<div style="text-align: center;width: 100%;height: 100%;line-height: 300px;font-size: 15px;">无数据</div>'
							$('.check_layer_content').html(html)
						},
						yes:function(indes,layers){
							if(typeof dbname === "function"){
								dbname(data)
							}else{
								bt.database.del_database(data, function (rdata) {
									layer.closeAll()
									if (rdata.status) database_table.$refresh_table_list(true);
									if (callback) callback(rdata);
									bt.msg(rdata);
								})
							}
						}
					})
				})
			}
		})
    }
}
if("undefined" != typeof site && site.hasOwnProperty("del_site")){
    site.del_site = function(wid, wname, callback) {
        var title = typeof wname === "function" ? '批量删除站点' : '删除站点 [ ' + wname + ' ]';
        recycle_bin_open = bt.get_cookie("is_recycle") || bt.get_cookie("is_recycle") == null ? true : false
        layer.open({
            type: 1,
            title: title,
            icon: 0,
            skin: 'delete_site_layer',
            area: "440px",
            closeBtn: 2,
            shadeClose: true,
            content: "<div class=\'bt-form webDelete pd30\' id=\'site_delete_form\'>" +
                '<i class="layui-layer-ico layui-layer-ico0"></i>' +
                "<div class=\'f13 check_title\'>是否要删除关联的FTP、数据库、站点目录！</div>" +
                "<div class=\"check_type_group\">" +
                "<label><input type=\"checkbox\" name=\"ftp\"><span>FTP</span></label>" +
                "<label><input type=\"checkbox\" name=\"database\"><span>数据库</span>" + (!recycle_bin_db_open ? '<span class="glyphicon glyphicon-info-sign" style="color: red"></span>' : '') + "</label>" +
                "<label><input type=\"checkbox\"  name=\"path\"><span>站点目录</span>" + (!recycle_bin_open ? '<span class="glyphicon glyphicon-info-sign" style="color: red"></span>' : '') + "</label>" +
                "</div>" +
                "</div>",
            btn: [lan.public.ok, lan.public.cancel],
            success: function (layers, indexs) {
                $(layers).find('.check_type_group label').hover(function () {
                    var name = $(this).find('input').attr('name');
                    if (name === 'data' && !recycle_bin_db_open) {
                        layer.tips('风险操作：当前数据库回收站未开启，删除数据库将永久消失！', this, { tips: [1, 'red'], time: 0 })
                    } else if (name === 'path' && !recycle_bin_open) {
                        layer.tips('风险操作：当前文件回收站未开启，删除站点目录将永久消失！', this, { tips: [1, 'red'], time: 0 })
                    }
                }, function () {
                    layer.closeAll('tips');
                })
            },
            yes: function (indexs) {
                var data = { id: wid, webname: wname };
                $('#site_delete_form input[type=checkbox]').each(function (index, item) {
                    if ($(item).is(':checked')) data[$(item).attr('name')] = 1
                })
                var is_database = data.hasOwnProperty('database'), is_path = data.hasOwnProperty('path'), is_ftp = data.hasOwnProperty('ftp');
                if ((!is_database && !is_path) && (!is_ftp || is_ftp)) {
                    if (typeof wname === "function") {
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
                if (typeof wname === "function") {
                    delete data.id;
                    delete data.webname;
                }
                layer.close(indexs)
                var arrs = wid instanceof Array ? wid : [wid]
                var ids = JSON.stringify(arrs), countDown = 9;
                if (arrs.length == 1) countDown = 4
                title = typeof wname === "function" ? '二次验证信息，批量删除站点' : '二次验证信息，删除站点 [ ' + wname + ' ]';
                var loadT = bt.load('正在检测站点数据信息，请稍后...')
                bt.send('check_del_data', 'site/check_del_data', { ids: ids }, function (res) {
                    loadT.close()
                    layer.open({
                        type: 1,
                        title: title,
                        closeBtn: 2,
                        skin: 'verify_site_layer_info',
                        area: '740px',
                        content: '<div class="check_delete_site_main pd30">' +
                            '<i class="layui-layer-ico layui-layer-ico0"></i>' +
                            '<div class="check_layer_title">堡塔温馨提示您，请冷静几秒钟，确认以下要删除的数据。</div>' +
                            '<div class="check_layer_content">' +
                            '<div class="check_layer_item">' +
                            '<div class="check_layer_site"></div>' +
                            '<div class="check_layer_database"></div>' +
                            '</div>' +
                            '</div>' +
                            '<div class="check_layer_error ' + (data.database && recycle_bin_db_open ? 'hide' : '') + '"><span class="glyphicon glyphicon-info-sign"></span>风险事项：当前未开启数据库回收站功能，删除数据库后，数据库将永久消失！</div>' +
                            '<div class="check_layer_error ' + (data.path && recycle_bin_open ? 'hide' : '') + '"><span class="glyphicon glyphicon-info-sign"></span>风险事项：当前未开启文件回收站功能，删除站点目录后，站点目录将永久消失！</div>' +
                            '<div class="check_layer_message"><span style="color:red">注意：请仔细阅读以上要删除信息，防止网站数据被误删</span></div>' +
                            '</div>',
                        // recycle_bin_db_open &&
                        // recycle_bin_open &&
                        btn: ['确认删除', '取消删除'],
                        success: function (layers) {
                            var html = '', rdata = res.data;
                            for (var i = 0; i < rdata.length; i++) {
                                var item = rdata[i], newTime = parseInt(new Date().getTime() / 1000),
                                    t_icon = '<span class="glyphicon glyphicon-info-sign" style="color: red;width:15px;height: 15px;;vertical-align: middle;"></span>';

                                site_html = (function (item) {
                                    if (!is_path) return ''
                                    var is_time_rule = (newTime - item.st_time) > (86400 * 30) && (item.total > 1024 * 10),
                                        is_path_rule = res.file_size <= item.total,
                                        dir_time = bt.format_data(item.st_time, 'yyyy-MM-dd'),
                                        dir_size = bt.format_size(item.total);

                                    var f_html = '<i ' + (is_path_rule ? 'class="warning"' : '') + ' style = "vertical-align: middle;" > ' + dir_size + '</i> ' + (is_path_rule ? t_icon : '');
                                    var f_title = (is_path_rule ? '注意：此目录较大，可能为重要数据，请谨慎操作.\n' : '') + '目录：' + item.path + '(' + (item.limit ? '大于' : '') + dir_size + ')';

                                    return '<div class="check_layer_site">' +
                                        '<span title="站点：' + item.name + '">站点名：' + item.name + '</span>' +
                                        '<span title="' + f_title + '" >目录：<span style="vertical-align: middle;max-width: 160px;width: auto;">' + item.path + '</span> (' + f_html + ')</span>' +
                                        '<span title="' + (is_time_rule ? '注意：此站点创建时间较早，可能为重要数据，请谨慎操作.\n' : '') + '时间：' + dir_time + '">创建时间：<i ' + (is_time_rule ? 'class="warning"' : '') + '>' + dir_time + '</i></span>' +
                                        '</div>'
                                }(item)),
                                    database_html = (function (item) {
                                        if (!is_database || !item.database) return '';
                                        var is_time_rule = (newTime - item.st_time) > (86400 * 30) && (item.total > 1024 * 10),
                                            is_database_rule = res.db_size <= item.database.total,
                                            database_time = bt.format_data(item.database.st_time, 'yyyy-MM-dd'),
                                            database_size = bt.format_size(item.database.total);

                                        var f_size = '<i ' + (is_database_rule ? 'class="warning"' : '') + ' style = "vertical-align: middle;" > ' + database_size + '</i> ' + (is_database_rule ? t_icon : '');
                                        var t_size = '注意：此数据库较大，可能为重要数据，请谨慎操作.\n数据库：' + database_size;

                                        return '<div class="check_layer_database">' +
                                            '<span title="数据库：' + item.database.name + '">数据库：' + item.database.name + '</span>' +
                                            '<span title="' + t_size + '">大小：' + f_size + '</span>' +
                                            '<span title="' + (is_time_rule && item.database.total != 0 ? '重要：此数据库创建时间较早，可能为重要数据，请谨慎操作.' : '') + '时间：' + database_time + '">创建时间：<i ' + (is_time_rule && item.database.total != 0 ? 'class="warning"' : '') + '>' + database_time + '</i></span>' +
                                            '</div>'
                                    }(item))
                                if ((site_html + database_html) !== '') html += '<div class="check_layer_item">' + site_html + database_html + '</div>';
                            }
                            if (html === '') html = '<div style="text-align: center;width: 100%;height: 100%;line-height: 300px;font-size: 15px;">无数据</div>'
                            $('.check_layer_content').html(html)
                        },
                        yes: function (indes, layers) {
                            if (typeof wname === "function") {
                                wname(data)
                            } else {
                                bt.site.del_site(data, function (rdata) {
                                    layer.closeAll()
                                    if (rdata.status) site.get_list();
                                    if (callback) callback(rdata);
                                    bt.msg(rdata);
                                })
                            }
                        }
                    })
                })
            }
        })
        if(bt.get_cookie("is_recycle") || bt.get_cookie("is_recycle")==null){
            $('[name="path"]').attr('checked',true)
        }else{
            $('[name="path"]').removeProp('checked');
        }
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