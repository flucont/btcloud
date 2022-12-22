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
    database.del_database = function (wid, dbname, obj, callback) {
        var is_db_type = false, del_data = []
        if (typeof wid === 'object') {
            del_data = wid
            is_db_type = wid.some(function (item) {
                return item.db_type > 0
            })
            var ids = [];
            for (var i = 0; i < wid.length; i++) {
                ids.push(wid[i].id);
            }
            wid = ids
        }
        var type = $('.database-pos .tabs-item.active').data('type'),
            title = '',
            tips = '';
        title = typeof dbname === "function" ? '批量删除数据库' : '删除数据库 - [ ' + dbname + ' ]';
        tips = is_db_type || !recycle_bin_db_open || type !== 'mysql' ? '<span class="color-red">当前列表存在彻底删除后无法恢复的数据库</span>，请仔细查看列表，以防误删，是否继续操作？' : '当前列表数据库将迁移至数据库回收站，如需彻底删除请前往数据库回收站，是否继续操作？'
        var arrs = wid instanceof Array ? wid : [wid]
        var ids = JSON.stringify(arrs),
            countDown = 9;
        if (arrs.length == 1) countDown = 4
        var loadT = bt.load('正在检测数据库数据信息，请稍候...'),
            param = { url: 'database/' + bt.data.db_tab_name + '/check_del_data', data: { data: JSON.stringify({ ids: ids }) } }
        if (bt.data.db_tab_name == 'mysql') param = { url: 'database?action=check_del_data', data: { ids: ids } }
        bt_tools.send(param, function (res) {
            loadT.close()
            layer.open({
                type: 1,
                title: title,
                area: '740px',
                skin: 'verify_site_layer_info',
                closeBtn: 2,
                shadeClose: true,
                content: '<div class="check_delete_site_main hint_confirm pd30">' +
                    "<div class='hint_title'>\
                <i class=\'hint-confirm-icon\'></i>\
                <div class=\'hint_con\'>"+ tips + "</div>\
              </div>"+
                    '<div id="check_layer_content" class="ptb15">' +
                    '</div>' +
                    '<div class="check_layer_message">' +
                    (is_db_type ? '<span class="color-red">注意：远程数据库暂不支持数据库回收站，选中的数据库将彻底删除</span><br>' : '') +
                    (!recycle_bin_db_open ? '<span class="color-red">风险操作：当前数据库回收站未开启，删除数据库将永久消失</span><br>' : '')
                    + '<span class="color-red">请仔细阅读以上要删除信息，防止数据库被误删</span></div>' +
                    '</div>',
                btn: ['下一步', lan.public.cancel],
                success: function (layers) {
                    setTimeout(function () { $(layers).css('top', ($(window).height() - $(layers).height()) / 2); }, 50)
                    var rdata = res.data,
                        newTime = parseInt(new Date().getTime() / 1000),
                        t_icon = ' <span class="glyphicon glyphicon-info-sign" style="color: red;width:15px;height: 15px;;vertical-align: middle;"></span>';
                    for (var j = 0; j < rdata.length; j++) {
                        for (var i = 0; i < del_data.length; i++) {
                            if (rdata[j].id == del_data[i].id) {
                                var is_time_rule = (newTime - rdata[j].st_time) > (86400 * 30) && (rdata[j].total > 1024 * 10),
                                    is_database_rule = res.db_size <= rdata[j].total,
                                    database_time = bt.format_data(rdata[j].st_time, 'yyyy-MM-dd'),
                                    database_size = bt.format_size(rdata[j].total);
                                var f_size = database_size
                                var t_size = '注意：此数据库较大，可能为重要数据，请谨慎操作.\n数据库：' + database_size;
                                if (rdata[j].total < 2048) t_size = '注意事项：当前数据库不为空，可能为重要数据，请谨慎操作.\n数据库：' + database_size;
                                if (rdata[j].total === 0) t_size = '';
                                rdata[j]['t_size'] = t_size
                                rdata[j]['f_size'] = f_size
                                rdata[j]['database_time'] = database_time
                                rdata[j]['is_time_rule'] = is_time_rule
                                rdata[j]['is_database_rule'] = is_database_rule
                                rdata[j]['db_type'] = del_data[i].db_type
                                rdata[j]['conn_config'] = del_data[i].conn_config
                            }
                        }
                    }
                    var filterData = rdata.filter(function (el) {
                        return ids.indexOf(el.id) != -1
                    })
                    bt_tools.table({
                        el: '#check_layer_content',
                        data: filterData,
                        height: '300px',
                        column: [
                            { fid: 'name', title: '数据库名称' },
                            {
                                title: '数据库大小', template: function (row) {
                                    return '<span class="' + (row.is_database_rule ? 'warning' : '') + '" style="width: 110px;" title="' + row.t_size + '">' + row.f_size + (row.is_database_rule ? t_icon : '') + '</span>'
                                }
                            },
                            {
                                title: '数据库位置', template: function (row) {
                                    var type_column = '-'
                                    switch (row.db_type) {
                                        case 0:
                                            type_column = '本地数据库'
                                            break;
                                        case 1:
                                        case 2:
                                            type_column = '远程数据库'
                                            break;
                                    }
                                    return '<span style="width: 110px;" title="' + type_column + '">' + type_column + '</span>'
                                }
                            },
                            {
                                title: '创建时间', template: function (row) {
                                    return '<span ' + (is_time_rule && row.total != 0 ? 'class="warning"' : '') + ' title="' + (row.is_time_rule && row.total != 0 ? '重要：此数据库创建时间较早，可能为重要数据，请谨慎操作.' : '') + '时间：' + row.database_time + '">' + row.database_time + '</span>'
                                }
                            },
                            {
                                title: '删除结果', align: 'right', template: function (row, index, ev, _that) {
                                    var _html = ''
                                    switch (row.db_type) {
                                        case 0:
                                            _html = type !== 'mysql' ? '彻底删除' : (!recycle_bin_db_open ? '彻底删除' : '移至回收站')
                                            break;
                                        case 1:
                                        case 2:
                                            _html = '彻底删除'
                                            break;
                                    }
                                    return '<span style="width: 110px;" class="' + (_html === '彻底删除' ? 'warning' + (row.db_type > 0 ? ' remote_database' : '') : '') + '">' + _html + '</span>'
                                }
                            }
                        ],
                        success: function () {
                            $('#check_layer_content').find('.glyphicon-info-sign').click(function (e) {
                                var msg = $(this).parent().prop('title')
                                msg = msg.replace('数据库：','<br>数据库：')
                                layer.tips(msg, $(this).parent(), { tips: [1, 'red'], time: 3000 })
                                $(document).click(function (ev) {
                                  layer.closeAll('tips');
                                  $(this).unbind('click');
                                  ev.stopPropagation();
                                  ev.preventDefault();
                                });
                                e.stopPropagation();
                                e.preventDefault();
                            });
                            if ($('.remote_database').length) {
                                $('.remote_database').each(function (index, el) {
                                    var id = $(el).parent().parent().parent().index()
                                    $('#check_layer_content tbody tr').eq(id).css('background-color', '#ff00000a')
                                })
                            }
                        }
                    })
                },
                yes: function (indes, layers) {
                    title = typeof dbname === "function" ? '二次验证信息，批量删除数据库' : '二次验证信息，删除数据库 - [ ' + dbname + ' ]';
                    if (type !== 'mysql') {
                        tips = '<span class="color-red">当前数据库暂不支持数据库回收站，删除后将无法恢复</span>，此操作不可逆，是否继续操作？';
                    } else {
                        tips = is_db_type ? '<span class="color-red">远程数据库不支持数据库回收站，删除后将无法恢复</span>，此操作不可逆，是否继续操作？' : recycle_bin_db_open ? '删除后如需彻底删除请前往数据库回收站，是否继续操作？' : '删除后可能会影响业务使用，此操作不可逆，是否继续操作？'
                    }
                    layer.open({
                        type: 1,
                        title: title,
                        icon: 0,
                        skin: 'delete_site_layer',
                        area: "530px",
                        closeBtn: 2,
                        shadeClose: true,
                        content: "<div class=\'bt-form webDelete hint_confirm pd30\' id=\'site_delete_form\'>" +
                            "<div class='hint_title'>\
                      <i class=\'hint-confirm-icon\'></i>\
                      <div class=\'hint_con\'>"+ tips + "</div>\
                    </div>"+
                            "<div style=\'color:red;margin:18px 0 18px 18px;font-size:14px;font-weight: bold;\'>注意：数据无价，请谨慎操作！！！" + (type === 'mysql' && !recycle_bin_db_open ? '<br>风险操作：当前数据库回收站未开启，删除数据库将永久消失！' : '') + "</div>"+
                            "</div>",
                        btn: ['确认删除', '取消删除'],
                        yes: function (indexs) {
                            var data = {
                                id: wid,
                                name: dbname
                            };
                            if (typeof dbname === "function") {
                                delete data.id;
                                delete data.name;
                            }
                            layer.close(indexs)
                            layer.close(indes)
                            if (typeof dbname === "function") {
                                dbname(data)
                            } else {
                                data.id = data.id[0]
                                bt.database.del_database(data, function (rdata) {
                                    layer.closeAll()
                                    if (callback) callback(rdata);
                                    bt.msg(rdata);
                                })
                            }
                        }
                    })
                }
            })
        })
    }
}
if("undefined" != typeof site && site.hasOwnProperty("del_site")){
    site.del_site = function (wid, wname, callback) {
        title = typeof wname === "function" ? '批量删除站点' : '删除站点 [ ' + wname + ' ]';
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
                    if (name === 'database' && !recycle_bin_db_open) {
                        layer.tips('风险操作：当前数据库回收站未开启，删除数据库将永久消失！', this, { tips: [1, 'red'], time: 0 })
                    } else if (name === 'path' && !recycle_bin_open) {
                        layer.tips('风险操作：当前文件回收站未开启，删除站点目录将永久消失！', this, { tips: [1, 'red'], time: 0 })
                    }
                }, function () {
                    layer.closeAll('tips');
                });
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
                var ids = JSON.stringify(wid instanceof Array ? wid : [wid]), countDown = typeof wname === 'string' ? 4 : 9;
                title = typeof wname === "function" ? '二次验证信息，批量删除站点' : '二次验证信息，删除站点 [ ' + wname + ' ]';
                var loadT = bt.load('正在检测站点数据信息，请稍候...')
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
                            '<div class="check_layer_error ' + (is_database && data['database'] && !recycle_bin_db_open ? '' : 'hide') + '"><span class="glyphicon glyphicon-info-sign"></span>风险事项：当前未开启数据库回收站功能，删除数据库后，数据库将永久消失！</div>' +
                            '<div class="check_layer_error ' + (is_path && data['path'] && !recycle_bin_open ? '' : 'hide') + '"><span class="glyphicon glyphicon-info-sign"></span>风险事项：当前未开启文件回收站功能，删除站点目录后，站点目录将永久消失！</div>' +
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

                                    var f_html = '<i ' + (is_path_rule ? 'class="warning"' : '') + ' style = "vertical-align: middle;" > ' + (item.limit ? '大于50MB' : dir_size) + '</i> ' + (is_path_rule ? t_icon : '');
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
                    layer.msg('可用端口范围：1-65535', { icon: 2 });
                    // layer.msg(lan.firewall.port_err, {
                    //   icon: 5
                    // });
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