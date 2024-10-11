var adminactivationreport={
		init:function(){
			this.getAPIPath();
			this.timelineRefresh();
			//this.listActivation();
			this.pageEvent();
			$(".employeeWrap").css("min-height",$(window).height()+"px");	
			this.customerIdSearchEvent();
			this.usernameSearchEvent();
			this.productIdSearchEvent();
			this.productNameSearchEvent();
			this.statusChangeEvent();
			this.typeChangeEvent();
			this.downloadSubmit();
			this.initiatorChangeEvent();
			this.getTableCount();
			this.postfixEvent();
			this.arcChecker();
			this.arcdownload();
			},
		page:1,
		productname:"",
		customerid:"",
		initiatorid:"",
		productid:"",
		activationstatus:"",
		activationtype:"",
		initiatortype:"",
		postfix:"",
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
			if($("#usernamesearch input[name=username]").val())
			{
				data.username = $("#usernamesearch input[name=username]").val();
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
			if(this.initiatortype)
			{
				data.initiatortype = this.initiatortype;
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
			
			if(_this.postfix)
			{
				data.postfix=_this.postfix;

			}
					
			showLoadingDiv(".activationlist");
			
			console.log(data);
			var inputdata = data;
			$.ajax({
				url:_this.apipath+"/admin/v1/activation.php",
				data:inputdata,
				success:function(data){
					stopLoadingDiv(".activationlist");
					if(!data.error)
					{
						console.log(data);
						
						_this.renderActivation(data);	
						_this.renderCount(inputdata);						
					
						if(data.length>=15)
						{
							$(".next .btn-link").prop("disabled",false);
						}
						else
						{
							$(".next .btn-link").prop("disabled",true);
						}
					}
					else
					{
						$("span#totalcount").html(0);
						$(".next .btn-link").prop("disabled",true);
						$(".activationlist tbody").html("<tr><td colspan='12'>"+data.error+"</td></tr>");
					}
				},
				error:function(data){
					$("span#totalcount").html(0);
					$(".next .btn-link").prop("disabled",true);
					stopLoadingDiv(".activationlist");
					if(data.status == 401)
					{
						showError(data.responseText);
						window.setTimeout(function(){ window.location.href = "login.php?exp=1";}, 2000);
					}
					else
					{
						$(".activationlist tbody").html("<tr><td colspan='12'>No Activation available.</td></tr>");
					}
				}
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
				
				$(".activationlist tbody").append("<tr><td>"+data[i]["customerid"]+"</td><td>"+data[i]["productid"]+"</td><td>"+data[i]["productname"]+"</td><td>"+data[i]["activationstatus"]+"</td><td>"+data[i]["activationtype"]+"</td><td>"+data[i]["expiry"]+"</td><td>"+data[i]["tariffperiod"]+"</td><td>"+data[i]["initiatortype"]+"</td><td>"+data[i]["reason"]+"</td><td>"+data[i]["created"]+"</td></tr>");
			}
		},			
		renderCount:function(inputdata){
			_this=this;
			
			$.ajax({
				url:_this.apipath+"/admin/v1/activation.php/count",
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
			$(".next").off("click").on("click",function(){
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
				_this.page=1;
				_this.listActivation();
				return false;
			});
		},
		usernameSearchEvent:function(){
			_this=this;
			$("#usernamesearch").submit(function(){
				//_this.initiatorid = $("#initiatoridsearch input[name=initiatorid]").val();
				_this.page=1;
				_this.listActivation();
				return false;
			});
		},
		productIdSearchEvent:function(){
			_this=this;
			$("#productidsearch").submit(function(){
				//_this.productid = $("#productidsearch input[name=productid]").val();
				_this.page=1;
				_this.listActivation();
				return false;
			});
		},
		productNameSearchEvent:function(){
			_this=this;
			$("#productnamesearch").submit(function(){
				//_this.productname = $("#productnamesearch input[name=productname]").val();
				_this.page=1;
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
				_this.page=1;
				_this.listActivation();
			});
		},
		initiatorChangeEvent:function(){
			_this=this;
			$("#initiatortype").change(function(){
				thisstatus=$(this).val();
				
				if(thisstatus=="ALL")
				{
					thisstatus="";				
				}
				
				_this.initiatortype=thisstatus;
				_this.page=1;
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
				_this.page=1;
				_this.listActivation();
			});
		},

		downloadSubmit:function(){
			_this=this;
			$("#download").submit(function(e){
				$("#download input[name=customerid]").remove();
				$("#download input[name=productid]").remove();
				$("#download input[name=username]").remove();
				$("#download input[name=productname]").remove();
				$("#download input[name=activationstatus]").remove();
				$("#download input[name=activationtype]").remove();
				$("#download input[name=initiatortype]").remove();
				$("#download input[name=todate]").remove();
				$("#download input[name=fromdate]").remove();
				$("#download input[name=postfix]").remove();
				
				if(!_this.postfix)
				{
					showError("Please Select A Table ");
					return false;
				}
				
				
				if($("input[name=start]").val())
				{
					$("#download").append("<input type='hidden' name='fromdate' value='"+$("input[name=start]").val()+"'/>");
					$("#download").append("<input type='hidden' name='todate' value='"+$("input[name=end]").val()+"'/>");
					$("#download").append("<input type='hidden' name='postfix' value='"+_this.postfix+"'/>");
									
					if($("#activationtype").val())
					{
						$("#download").append("<input type='hidden' name='activationtype' value='"+$("#activationtype").val()+"'/>");
					}

					if($("#initiatortype").val())
					{
						$("#download").append("<input type='hidden' name='initiatortype' value='"+$("#initiatortype").val()+"'/>");
					}

					if($("#activationstatus").val())
					{
						$("#download").append("<input type='hidden' name='activationstatus' value='"+$("#activationstatus").val()+"'/>");
					}
					
					if($("input[name=customerid]").val())
					{
						$("#download").append("<input type='hidden' name='customerid' value='"+$("input[name=customerid]").val()+"'/>");
					}

					if($("input[name=username]").val())
					{
						$("#download").append("<input type='hidden' name='username' value='"+$("input[name=username]").val()+"'/>");
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
		},
		getTableCount:function(){
			showLoadingDiv(".activationlist");
			$.ajax({
				url:_this.apipath+"/admin/v1/activation.php/tablecount",
				success:function(data){
					stopLoadingDiv(".activationlist");
					console.log(data);
					if(!data.error)
					{
						//_this.renderPayments(data);
						if(data.success<=1)
						{
							console.log("single table");
							$('#postfix').prop('selectedIndex',0);
							$(".postfix").addClass("hidden");
							_this.postfix = "";
							_this.listActivations();
						}else
						{
							console.log("multiple tables");
//							_this.listActivations();
							var count = data.success;
							$("#postfix").empty();
							$('#postfix').html('<option value="">Select</option>');
							for(i=1;i<=count;i++)
							{
								$("#postfix").append('<option>'+i+'</option>');
							}
							$(".postfix").removeClass("hidden");
							$('#postfix').prop('selectedIndex',0);
							showError("Please select table");
							$(".activationlist tbody").html("<tr><td colspan='10'>Please select table</td></tr>");
							//_this.postfixEvent();
						}
					}
					else
					{
						$(".activationlist tbody").html("<tr><td colspan='10'>Failed to get table count.</td></tr>");
					}
				},
				error:function(data){
					console.log(data);
					stopLoadingDiv(".activationlist");
					$(".activationlist tbody").html("<tr><td colspan='10'>Failed to get table count.</td></tr>");
				}
			});
		},
		postfixEvent:function(){
			_this=this;
			$("#postfix").change(function(){
				console.log($(this).val());
				if($(this).val() != "")
				{
					_this.postfix = $(this).val();
				}else
				{
					_this.postfix = "";
					showError("Please select table");
					return false;
				}
				_this.listActivation();
			});
		},
	
		arcChecker:function(){
			$("#arcdownload").click(function (e) {
				if(!_this.postfix)
				{
					showError("Please Select A Table ");
					return false;
				}
			});
		},
		
		arcdownload: function () {
			$("#downloadarc").submit(function (e) {
				
				
				$("#downloadarc input[name=start]").remove();
				$("#downloadarc input[name=end]").remove();
				$("#downloadarc input[name=archive]").remove();	
				$("#download input[name=postfix]").remove();

				if ($("input[name=startt]").val()) {
					$("#downloadarc").append("<input type='hidden' name='start' value='" + $("#startt").val() + "'/>");
					$("#downloadarc").append("<input type='hidden' name='end' value='" + $("#endd").val() + "'/>");
					$("#downloadarc").append("<input type='hidden' name='archive' value='TRUE'/>");
				}
				
				if(_this.postfix)
				{
					$("#downloadarc").append("<input type='hidden' name='postfix' value='"+_this.postfix+"'/>");
				}

			});
		},
		
	
};
$(document).ready(function(){adminactivationreport.init();});