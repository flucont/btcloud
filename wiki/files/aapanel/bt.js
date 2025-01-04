/*
 *宝塔面板去除各种计算题与延时等待
*/
if("undefined" != typeof bt && bt.hasOwnProperty("show_confirm")){
    bt.show_confirm = function(title, msg, fun, error) {
        layer.open({
            type: 1,
            title: title,
            area: "350px",
            closeBtn: 2,
            shadeClose: true,
            btn: [lan['public'].ok, lan['public'].cancel],
            content: "<div class='bt-form webDelete pd20'>\
                    <p>" + msg + "</p>" + (error || '') + "\
                </div>",
            yes: function (index, layero) {
                layer.close(index);
                if (fun) fun();
            }
        });
    }
}
if("undefined" != typeof bt && bt.hasOwnProperty("prompt_confirm")){
    bt.prompt_confirm = function (title, msg, callback) {
        layer.open({
            type: 1,
            title: title,
            area: "480px",
            closeBtn: 2,
            btn: ['OK', 'Cancel'],
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
if("undefined" != typeof bt && bt.hasOwnProperty("compute_confirm")){
    bt.compute_confirm = function (config, callback) {
        layer.open({
            type: 1,
            title: config.title,
            area: '430px',
            closeBtn: 2,
            shadeClose: true,
            btn: [lan['public'].ok, lan['public'].cancel],
            content:
                '<div class="bt-form hint_confirm pd30">\
          <div class="hint_title">\
            <i class="hint-confirm-icon"></i>\
            <div class="hint_con">' +
                config.msg +
                '</div>\
          </div>\
      </div>',
            yes: function (layers, index) {
                layer.close(layers)
                if (callback) callback()
            }
        });
    }
}
function SafeMessage(j, h, g, f) {
    if (f == undefined)  f = '';
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