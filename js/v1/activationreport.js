var activationreport={
		init:function(){
			this.getAPIPath();
			this.timelineRefresh();
			this.listActivation();
			this.pageEvent();
			$(".employeeWrap").css("min-height",$(window).height()+"px");	
			this.customerIdSearchEvent();
			this.productIdSearchEvent();
			this.productNameSearchEvent();
			this.statusChangeEvent();
			this.typeChangeEvent();
			this.downloadSubmit();
			},
		page:1,
		productname:"",
		customerid:"",
		productid:"",
		activationstatus:"",
		activationtype:"",
		getAPIPath:function(){
			_this = this;
			 var thispath = document.location.pathname;
             thispath = thispath.substring(0,thispath.lastIndexOf("/"));
             thispath = thispath.substring(0,thispath.lastIndexOf("/"));
             console.log(thispath);
             _this.apipath = thispath;
		},

		timelineRefresh:function(){
			_this=this;
			$("#refresh").off("click").on("click",function(){
				console.log("Go clicked");
				_this.page=1;
				_this.listActivation();
			});
		},
		
		listActivation:function(){
			_this=this;
			data={};
			data.page = this.page;
			if($("#productnamesearch input[name=productname]").val())
			{
				data.productname = $("#productnamesearch input[name=productname]").val();
			}
			if($("#customeridsearch input[name=customerid]").val())
			{
				data.customerid = $("#customeridsearch input[name=customerid]").val();
			}
			if($("#productidsearch input[name=productid]").val())
			{
				data.productid = $("#productidsearch input[name=productid]").val();
			}
			if(this.activationstatus)
			{
				data.activationstatus = this.activationstatus;
			}
			if(this.activationtype)
			{
				data.activationtype = this.activationtype;
			}

			if($("input[name=start]").val())
			{
				data.fromdate=$("input[name=start]").val();
			}
			
			if($("input[name=end]").val())
			{
				data.todate=$("input[name=end]").val();
			}
			
			if(_this.page<=1)
			{
				$(".prev .btn-link").prop("disabled",true);
			}
			else
			{
				$(".prev .btn-link").prop("disabled",false);
			}
					
			showLoadingDiv(".activationlist");
			var inputdata = data;
			console.log(inputdata);
			
			$.ajax({
				url:_this.apipath+"/lco/v1/activation.php",
				data:inputdata,
				success:function(data){
					stopLoadingDiv(".activationlist");
					if(!data.error)
					{
						console.log(data);
						
						_this.renderActivation(data);
					
						if(data.length>=15)
						{
							$(".nxt .btn-link").prop("disabled",false);
						}
						else
						{
							$(".nxt .btn-link").prop("disabled",true);
						}
					}
					else
					{
						$(".nxt .btn-link").prop("disabled",true);
						$(".activationlist tbody").html("<tr><td colspan='12'>"+data.error+"</td></tr>");
						$("span#totalcount").html(0);
					}
				},
				error:function(data){
					$(".nxt .btn-link").prop("disabled",true);
					stopLoadingDiv(".activationlist");
					if(data.status == 401)
					{
						showError(data.responseText);
						window.setTimeout(function(){ window.location.href = "login.php?exp=1";}, 2000);
					}
					else
					{
						$(".activationlist tbody").html("<tr><td colspan='12'>No Activation available.</td></tr>");
						$("span#totalcount").html(0);
					}
				}
			});

			$("#refresh").off("click").on("click",function(){
				_this.page=1;
				_this.listActivation();
			});
		},
		renderActivation:function(data){
			_this=this;
			
			console.log(data);
			
			html="";
			$(".activationlist tbody").empty();
					
			for(i in data)
			{
				for(j in data[i])
				{
					if((data[i][j]=='') || (data[i][j]==null))
					{
						data[i][j] = '---';
					}
				}
				
				$(".activationlist tbody").append("<tr><td>"+data[i]['activationid']+"</td><td>"+data[i]["customerid"]+"</td><td>"+data[i]["productid"]+"</td><td>"+data[i]["productname"]+"</td><td>"+data[i]["activationstatus"]+"</td><td>"+data[i]["activationtype"]+"</td><td>"+data[i]["tariffperiod"]+"</td><td>"+data[i]["created"]+"</td></tr>");
			}
		},
		renderCount:function(inputdata){
			_this=this;
			
			$.ajax({
				url:_this.apipath+"/lco/v1/activation.php/count",
				data:inputdata,
				success:function(data){
					if(!data.error)
					{
						console.log(data);
						$("span#totalcount").html(data.count);
					}else
					{
						$("span#totalcount").html(0);
					}
				},
				error:function(data){
					$("span#totalcount").html(0);
				}
			});

		},			
		pageEvent:function(){
			_this=this;
			$(".nxt").off("click").on("click",function(){
				_this.page++;
				_this.listActivation();
				
			});
			$(".prev").off("click").on("click",function(){
				_this.page--;
				_this.listActivation();
			});
		},
		
		customerIdSearchEvent:function(){
			_this=this;
			$("#customeridsearch").submit(function(){
				//_this.customerid = $("#customeridsearch input[name=customerid]").val();
				_this.listActivation();
				return false;
			});
		},
		productIdSearchEvent:function(){
			_this=this;
			$("#productidsearch").submit(function(){
				//_this.productid = $("#productidsearch input[name=productid]").val();
				_this.listActivation();
				return false;
			});
		},
		productNameSearchEvent:function(){
			_this=this;
			$("#productnamesearch").submit(function(){
				//_this.productname = $("#productnamesearch input[name=productname]").val();
				_this.listActivation();
				return false;
			});
		},
		statusChangeEvent:function(){
			_this=this;
			$("#activationstatus").change(function(){
				thisstatus=$(this).val();
				
				if(thisstatus=="ALL")
				{
					thisstatus="";				
				}
				
				_this.activationstatus=thisstatus;
				_this.listActivation();
			});
		},
		typeChangeEvent:function(supervisorid){
			_this=this;
			$("#activationtype").change(function(){
				thisstatus=$(this).val();
				
				if(thisstatus=="ALL")
				{
					thisstatus="";				
				}
				
				_this.activationtype=thisstatus;
				_this.listActivation();
			});
		},

		downloadSubmit:function(){
			$("#download").submit(function(e){
				$("#download input[name=customerid]").remove();
				$("#download input[name=productid]").remove();
				$("#download input[name=productname]").remove();
				$("#download input[name=activationstatus]").remove();
				$("#download input[name=activationtype]").remove();
				$("#download input[name=todate]").remove();
				$("#download input[name=fromdate]").remove();
				
				if($("input[name=start]").val())
				{
					$("#download").append("<input type='hidden' name='fromdate' value='"+$("input[name=start]").val()+"'/>");
					$("#download").append("<input type='hidden' name='todate' value='"+$("input[name=end]").val()+"'/>");
									
					if($("#activationtype").val())
					{
						$("#download").append("<input type='hidden' name='activationtype' value='"+$("#activationtype").val()+"'/>");
					}

					if($("#activationstatus").val())
					{
						$("#download").append("<input type='hidden' name='activationstatus' value='"+$("#activationstatus").val()+"'/>");
					}
					
					if($("input[name=customerid]").val())
					{
						$("#download").append("<input type='hidden' name='customerid' value='"+$("input[name=customerid]").val()+"'/>");
					}

					if($("input[name=productid]").val())
					{
						$("#download").append("<input type='hidden' name='productid' value='"+$("input[name=productid]").val()+"'/>");
					}

					if($("input[name=productname]").val())
					{
						$("#download").append("<input type='hidden' name='productname' value='"+$("input[name=productname]").val()+"'/>");
					}
					
				}
				
			});
		}	
};
$(document).ready(function(){activationreport.init();});
