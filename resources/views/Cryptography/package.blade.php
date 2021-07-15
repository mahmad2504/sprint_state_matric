<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Package - OSS Governance</title>

<link rel="stylesheet" href="{{ asset('libs/tabulator/css/tabulator.min.css') }}" />

<style>
.progress 
{
    height: 20px;
}
.progress > svg 
{
	height: 100%;
	display: block;
}

.flex-container 
{
	height: 100%;
	padding: 0;
	margin: 0;
	display: -webkit-box;
	display: -moz-box;
	display: -ms-flexbox;
	display: -webkit-flex;
	display: flex;
	align-items: center;
	justify-content: center;
}
.row {
	width: 80%;
	
}
.flex-item {
	text-align: center;
}
.box {
      border:2px solid #4FFFA1;
      padding:10px;
      background:#F6FFA1;
      width:100px;
      border-radius:25px;
    }
</style
</head>
<body>

	<div class="flex-container">
		<div class="row"> 
			<br>
			<div style="font-weight:bold;font-size:20px;line-height: 50px;height:50px;background-color:#0099FF;color:white;" class="flex-item"> 
			<img style="float:left;" height="50px" src="{{ asset('apps/ishipment/images/mentor.png') }}"></img>
			<div style="margin-right:150px;"> OSS Governance [ Package - <span style=""> {{$package_name}}]</span></div>
			</div>
			<div class="flex-item"> 
			<small class="flex-item" style="font-size:12px;"><a id="" href="#"></a></small>
			</div>
			<hr>
			<div class="flex-item"> 
				<table>
					<tr>
						<td>
							<div class="box">Hits <span id="hits"></span></div>
						</td>
						<td>
						    <div class="box">Triaged <span id="triaged"></span></div>
						</td>
						<td>
						    <div class="box">Suspicios <span id="suspicios"></span></div>
						</td>
						<td>
							<div style="background-color:khaki;" class="progress" id="progress"></div>
						</td>
					</tr>
				</table>
			</div>
			<div class="flex-item"> 
			<div id="tabulator-table"></div>
			</div>
		</div>
	</div>
	<div>	
	<script src="{{ asset('apps/cryptography/js/progressbar.min.js') }}"></script>
	<script src="{{ asset('libs/jquery/jquery.min.js') }}"></script>
	<script src="{{ asset('libs/tabulator/js/tabulator.min.js') }}" ></script>
	<script>
	var package = @json($package);
	var project = @json($project);
	console.log(package);
	$('#hits').text(package.hits);
	$('#triaged').text(package.triaged);
	$('#suspicios').text(package.suspicios);
		
	for(var i=0;i<package.files.length;i++)
	{
		var file = package.files[i];
		file.progress = Math.round((file.triaged/file.hits) * 100);
	}
	columns = 
	[
        {title:"File", field:"path", sorter:"string", width:750},
        {title:"Hits", field:"hits", sorter:"number"},
		{title:"Triaged", field:"triaged", sorter:"number"},
		{title:"Suspicios", field:"suspicios", sorter:"number"},
		{title:"Progress", field:"progress", width:120,formatter:"progress", 
			formatterParams:function(cell)
			{
				return {
					min:0,
					max:100,
					color:["green", "green", "green"],
					legendColor:"#000000",
					legendAlign:"center",
					legend:cell.getValue()+"%"
					}
			}
		}
	];
	var progress = 0;
	$(document).ready(function()
	{
		console.log("Cryptography Page Loaded");
		progress = 0;
		if(package.triaged > 0)
			progress=Math.round((package.triaged/package.hits) * 100);
			
		DrawProgressBar(progress);
	});
	function DrawProgressBar(progress)
	{
		var circle = new ProgressBar.Line('#progress', {
			color: '#00FF00',
			duration: 3000,
			easing: 'easeInOut',
			svgStyle: {
				display: 'block',
				// Important: make sure that your container has same
				// aspect ratio as the SVG canvas. See SVG canvas sizes above.
				width: progress+'%',
			},
			text: 
			{
				// Initial value for text.
				// Default: null
				value: progress+'% completed',
				style: 
				{
					// Text color.
					// Default: same as stroke color (options.color)
					color: '#000',
					position: 'absolute',
					left: '50%',
					top: '50%',
					'font-size':'15px',
					padding: 0,
					margin: 0,
					// You can specify styles which will be browser prefixed
					transform: 
					{
						prefix: true,
						value: 'translate(-50%, -50%)'
					}
				},
			}
		});
		circle.animate(1);
		var last_row_clicked = null;
		
		var table = new Tabulator("#tabulator-table", {
			data:package.files,
			columns:columns,
			pagination:"local",
			paginationSize:50,
			layout:"fitDataFill",
			paginationSizeSelector: [10, 25, 50, 100],
			rowClick:function(e,row)
			{
				var cells = row.getCells();
				if(last_row_clicked != null)
				{
					var last_row_cells = last_row_clicked.getCells();
					last_row_cells[0].getElement().style.color  = "black";
					last_row_cells[0].getElement().style.fontWeight = "normal";
				}
				
				cells[0].getElement().style.color  = "DarkGreen";
				cells[0].getElement().style.fontWeight = "Bold";
				last_row_clicked = row;
				
				var file = row.getData();
				console.log(file);
				var url = '/oss/hits/'+project.name+"/"+file.file_identifier+'?package='+package.name;
				window.open(url, '_blank');
				//console.log(package);
			
				//row.getElement().style.backgroundColor  = "lightgreen";	
				
			//setTimeout(function(){ rows[0].getElement().style.backgroundColor  = "white";}, 1000);
				//var package = row.getData();
				//var url = window.location.href+"/"+package.name;
				//window.open(url, '_blank');
				
			}, 
		});
	}
	</script>
</body>
</html>