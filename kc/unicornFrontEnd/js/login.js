// JavaScript Document
$(function(){
	$(".deal").on('click',function(){
		if($(this).hasClass("on")){
			$(this).removeClass("on");
		}else{
			$(this).addClass("on");
		}
	});
	$(".tcbox .iknow").on("click",function(){
		$(".tcbox").hide();
	});
	$(".registerbox input,.loginbox input").on("click",function(){
		$(".bor").removeClass("bor");
		$(this).parent().addClass("bor");
		
	});
	$(".project input,.project2 input,.project3 input,.project2 textarea,.project3 textarea").on("click",function(){
		$(".bor").removeClass("bor");
		$(this).addClass("bor");
	});
	//点击查看放大图片
	$(".opbgbox").height($(window).height());
	$(".opbgbox").width($(window).width());
	$(".right").on('click',function(){
		$(".opbg,.opbginner,.opbginner img").show();
	});
	$(".opbg ,.opbginner").on('click',function(){
		$(".opbg,.opbginner").hide();
	});
	$(".applynow").on('click',function(){
		var system = {
            win: false,
            mac: false,
            xll: false,
            ipad:false
        };
        //检测平台
        var p = navigator.platform;
        system.win = p.indexOf("Win") == 0;
        system.mac = p.indexOf("Mac") == 0;
        system.x11 = (p == "X11") || (p.indexOf("Linux") == 0);
        system.ipad = (navigator.userAgent.match(/iPad/i) != null)?true:false;
        //跳转语句，如果是手机访问就自动跳转到wap.baidu.com页面
        if (system.win || system.mac || system.xll||system.ipad) {
			  window.location.href = "/Data/stepOne";
            return false;
        } else {
			alert("请用电脑访问www.123123.com进行申请操作");
            return false;
        }
	});
})