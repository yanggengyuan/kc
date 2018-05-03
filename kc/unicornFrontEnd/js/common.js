// JavaScript Document
$(function(){
	$(window).on("load",function(){
	
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
		 $(".pcbox").removeClass("hidden");
	} else {
		 $(".phone").removeClass("hidden");
         // $(".applynow").on("click",function(){
         //    alert("请用电脑访问www.123123.com进行申请操作");
         // });
	}
	});
	$(".menu p").on("click",function(){
		$(this).addClass("cheon").siblings().removeClass("cheon");
		$('#'+$(this).attr('tag')).show().siblings().hide();
	});
	$(".applypro,.download1").on("click",function(){
		alert("请用电脑访问www.123123.com进行申请操作");
	});

	$("tr:nth-child(odd)").css("background","#f8f6fe");
});