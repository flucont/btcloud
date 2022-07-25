var location_url = window.location.href;
var parameter_str = location_url.split('?')[1];
if (parameter_str !== undefined) {
    parameter_str = parameter_str.split('#')[0];
    var $_GET = {};
    var parameter_arr = parameter_str.split('&');
    var tmp_arr;
    for (var i = 0, len = parameter_arr.length; i <= len - 1; i++) {
        tmp_arr = parameter_arr[i].split('=');
        $_GET[tmp_arr[0]] = decodeURIComponent(tmp_arr[1]);
    }
    window.$_GET = $_GET;
} else {
    window.$_GET = [];
}

function searchSubmit(){
	$('#listTable').bootstrapTable('refresh');
	return false;
}
function searchClear(){
	$('#searchToolbar').find('input[name]').each(function() {
		$(this).val('');
	});
	$('#searchToolbar').find('select[name]').each(function() {
		$(this).find('option:first').prop("selected", 'selected');
	});
	$('#listTable').bootstrapTable('refresh');
}
function updateToolbar(){
    $('#searchToolbar').find(':input[name]').each(function() {
		var name = $(this).attr('name');
		if(typeof window.$_GET[name] != 'undefined')
			$(this).val(window.$_GET[name]);
	})
}
function updateQueryStr(obj){
	var arr = [];
    for (var p in obj){
		if (obj.hasOwnProperty(p) && typeof obj[p] != 'undefined' && obj[p] != '') {
			arr.push(p + "=" + encodeURIComponent(obj[p]));
		}
	}
	history.replaceState({}, null, '?'+arr.join("&"));
}

if (typeof $.fn.bootstrapTable !== "undefined") {
    $.fn.bootstrapTable.custom = {
        method: 'post',
        contentType: "application/x-www-form-urlencoded",
        sortable: true,
        pagination: true,
        sidePagination: 'server',
        pageNumber: 1,
        pageSize: 20,
        pageList: [10, 15, 20, 30, 50, 100],
		loadingFontSize: '18px',
		toolbar: '#searchToolbar',
		showColumns: true,
		minimumCountColumns: 2,
		showToggle: true,
		showFullscreen: true,
		paginationPreText: '前页',
		paginationNextText: '后页',
		showJumpTo: true,
		paginationLoop: false,
		queryParamsType: '',
		queryParams: function(params) {
			$('#searchToolbar').find(':input[name]').each(function() {
				params[$(this).attr('name')] = $(this).val()
			})
			updateQueryStr(params);
			params.offset = params.pageSize * (params.pageNumber-1);
			params.limit = params.pageSize;
			return params;
		},
        formatLoadingMessage: function(){
			return '';
		},
		formatShowingRows: function(t,n,r,e){
			return '显示第 '+t+' 到第 '+n+' 条, 总共 <b>'+r+'</b> 条';
		},
		formatRecordsPerPage: function(t){
			return '每页显示 '+t+' 条';
		},
		formatNoMatches: function(){
			return '没有找到匹配的记录';
		}
    };
    $.extend($.fn.bootstrapTable.defaults, $.fn.bootstrapTable.custom);
}

function httpGet(url, callback){
	$.ajax({
		url: url,
		type: 'get',
		dataType: 'json',
		success: function (res) {
			callback(res)
		},
		error: function () {
			if (typeof layer !== "undefined") {
				layer.closeAll();
				layer.msg('服务器错误');
			}
		}
	});
}

function httpPost(url, data, callback){
	$.ajax({
		url: url,
		type: 'post',
		data: data,
		dataType: 'json',
		success: function (res) {
			callback(res)
		},
		error: function () {
			if (typeof layer !== "undefined") {
				layer.closeAll();
				layer.msg('服务器错误');
			}
		}
	});
}

var isMobile = function(){
	if( /Android|SymbianOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone|Midp/i.test(navigator.userAgent)) {
		return true;
	}
	return false;
}