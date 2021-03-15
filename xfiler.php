<?php

	/*
		Funciones AJAX del filer. Ejemplo de uso:
		
		axfiler(Array(
			""=>Array(
				"root"=>"class/",
				"ext"=>Array(
					""=>"Todos los archivos",
					"php"=>"Archivos PHP",
				),
				"upload"=>Array(
					"xxx/",
				),
			),
		));
		
	*/
	function axfiler($dirs) {
		global $ajax,$adata,$me,$kernel,$view;
		switch ($ajax) {
		case "filer:capture":
			$filer=$_REQUEST["filer"];
			$path=utf8_decode($_REQUEST["path"]);
			if (strpos($path,"..")!==false) die("Acceso denegado a ruta.");
			if (substr($path,0,1)=="/") die("Acceso denegado a ruta.");
			$filename=$_REQUEST["file"];
			if (!$filename) die("Se debe especificar una carpeta/archivo.");
			if (strpos($filename,"..")!==false || strpos($filename,"/")!==false) die("Nombre de fichero incorrecto");
			$w=intval($_REQUEST["w"]);
			$h=intval($_REQUEST["h"]);
			if ($w<16 || $w>1900 || $h<16 || $h>1080) die("Resolución no soportada o fuera de rango.");
			$o=$dirs[$filer];
			if ($o) {
				include_once("class/ximage.php");
				$im=new xImage();
				$im->Load($o["root"].$path.$filename);
				$im->Scale(128,96,true);
				$im->Output();
			}
			exit;
		
		case "filer:newfolder":
			$filer=$adata["filer"];
			$path=utf8_decode($adata["path"]);
			if (strpos($path,"..")!==false) ajax(Array("err"=>"Acceso denegado a ruta."));
			if (substr($path,0,1)=="/") ajax(Array("err"=>"Acceso denegado a ruta."));
			$folder=utf8_decode($adata["folder"]);
			if (strpos($folder,"..")!==false || strpos($folder,"/")!==false) ajax(Array("err"=>"Nombre de carpeta incorrecto"));
			$o=$dirs[$filer];
			if ($o["upload"]) {
				$rpath=null;
				if ($o["upload"]===true) $o["upload"]=Array("");
				foreach ($o["upload"] as $p) {
					if (substr($path,0,strlen($p))==$p) {
						$rpath=$o["root"].$path;
						break;
					}
				}
				if ($rpath===null) ajax(Array("err"=>"No se permite escribir en esta carpeta."));
				mkdir($rpath.$folder,0770,true);
				ajax(Array("ok"=>true));
			}
			ajax(Array("err"=>"Acceso denegado a escritura."));

		case "filer:delete":
			$filer=$adata["filer"];
			$path=utf8_decode($adata["path"]);
			if (strpos($path,"..")!==false) ajax(Array("err"=>"Acceso denegado a ruta."));
			if (substr($path,0,1)=="/") ajax(Array("err"=>"Acceso denegado a ruta."));
			if (!$adata["items"]) ajax(Array("err"=>"No ha indicado ningún elemento a borrar."));
			foreach ($adata["items"] as $item) {
				$filename=trim(utf8_decode($item));
				if (!$filename) ajax(Array("err"=>"Se debe especificar una carpeta/archivo."));
				if (strpos($filename,"..")!==false || strpos($filename,"/")!==false) ajax(Array("err"=>"Nombre de fichero incorrecto"));
				$o=$dirs[$filer];
				if ($o["upload"]) {
					$rpath=null;
					if ($o["upload"]===true) $o["upload"]=Array("");
					foreach ($o["upload"] as $p) {
						if (substr($path,0,strlen($p))==$p) {
							$rpath=$o["root"].$path;
							break;
						}
					}
					if ($rpath===null) ajax(Array("err"=>"No se permite escribir en esta carpeta."));
					if (!file_exists($rpath.$filename)) ajax(Array("err"=>"El fichero/carpeta no existe."));
					// borrar todos los archivos y carpetas de forma recursiva / individual
					if (is_dir($rpath.$filename)) {
						foreach ($tipos=Array(false,true) as $tipo) {
							foreach ($files=$kernel->dir_recursive($rpath.$filename."/",$tipo) as $f) {
								if ($tipo) {
									if (!(@rmdir($f))) {
										$lasterr=error_get_last();
										ajax(Array("err"=>"Error borrando la carpeta ".$f.": ".$lasterr["message"]));
									}
								} else {
									if (!(@unlink($f))) {
										$lasterr=error_get_last();
										ajax(Array("err"=>"Error borrando el archivo ".$f.": ".$lasterr["message"]));
									}
								}
							}
						}
						if (!(@rmdir($rpath.$filename))) {
							$lasterr=error_get_last();
							ajax(Array("err"=>"Error borrando la carpeta ".utf8_encode($rpath.$filename).": ".$lasterr["message"]));
						}
					} else {
						if (!(@unlink($rpath.$filename))) {
							$lasterr=error_get_last();
							ajax(Array("err"=>"Error borrando el archivo ".utf8_encode($filename).": ".$lasterr["message"]));
						}
					}
				} else {
					ajax(Array("err"=>"Acceso denegado a escritura."));
				}
			}
			ajax(Array("ok"=>true));

		case "filer:upload":
			$filer=$_REQUEST["filer"];
			$path=utf8_decode($_REQUEST["path"]);
			if (strpos($path,"..")!==false) die("Access forbidden.");
			if (substr($path,0,1)=="/") die("Location forbidden.");
			$o=$dirs[$filer];
			if ($o["upload"]) {
				$name=utf8_decode(str_replace("'","_",str_replace("\"","_",basename($_FILES["file"]["name"]))));
				$size=filesize($_FILES["file"]["tmp_name"]);
				$rpath=null;
				if ($o["upload"]===true) $o["upload"]=Array("");
				foreach ($o["upload"] as $p) {
					if (substr($path,0,strlen($p))==$p) {
						$rpath=$o["root"].$path;
						break;
					}
				}
				if ($rpath===null) {
					?><html><head><script>
						parent.filer.uploaded({
							"file":"<?=utf8_encode($name)?>",
							"path":"<?=utf8_encode($path)?>",
							"ok":false
						});
					</script></head>
					<body>Error de acceso</body>
					</html><?
					exit;
				} else {
					move_uploaded_file($_FILES["file"]["tmp_name"],$rpath.$name);
					?><html><head><script>
						parent.filer.uploaded({
							"path":"<?=utf8_encode($path)?>",
							"file":"<?=utf8_encode($name)?>",
							"size":"<?=$size?>",
							"sizet":"<?=$view->bytesToString($size)?>",
							"ok":true
						});
					</script></head>
					<body>Subido correctamente</body>
					</html><?
				}
			} else {
				die("En esta carpeta no se puede escribir.");
			}
			exit;
		
		case "filer:uploader":
			$filer=$_REQUEST["filer"];
			$path=utf8_decode($_REQUEST["path"]);
			if (strpos($path,"..")!==false) die("Access forbidden.");
			if (substr($path,0,1)=="/") die("Location forbidden.");
			$o=$dirs[$filer];
			if (!$o["upload"]) die("En esta carpeta no se puede escribir.");
			?><!doctype html>
			<html lang="es">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<script>
					function gid(id) { return document.getElementById(id); }
					function gidval(id) { return gid(id).value; }
					function upload() {
						if (!gidval("file")) return false;
						setTimeout(function(){
							gid('fileform').submit();
						},1);
						return true;
					}
					window.onload=function(){
						//document.getElementById("");
					}
				</script>
				<style>
					body {
						margin: 0px;
					}
				</style>
			</head>
			<body>
				<form id='fileform' method='post' enctype="multipart/form-data">
					<input type='hidden' name='ajax' value='filer:upload' />
					<input type='hidden' id='path' name='path' value='<?=utf8_encode($path)?>' />
					<input type='hidden' name='filer' value='<?=$filer?>' />
					<input id='file' name='file' type='file' />
				</form>
			</body>
			</html><?
			exit;
		
		case "filer:newfolder":
			ajax(Array("ok"=>true));

		case "filer:list":
			
			// filtrar parámetros y obtener opciones
			$o=$dirs[$adata["filer"]];
			$raiz=utf8_decode($o["root"]);
			$path=trim(utf8_decode($adata["path"]));
			$ext=($adata["ext"]?$adata["ext"]:"");
			if (strpos($path,"..")!==false) ajax(Array("err"=>"Access forbidden."));
			if (substr($path,0,1)=="/") ajax(Array("err"=>"Location forbidden."));
			if ($o["ext"]) {
				if (!$o["ext"][$ext])
					ajax(Array("err"=>"Extension ".$ext." not allowed."));
				$ext_allowed=Array();
				if ($ext) {
					foreach ($i=explode("|",$ext) as $e)
						$ext_allowed[$e]=$o["ext"][$ext];
				} else {
					foreach ($o["ext"] as $extensions=>$name)
						foreach ($i=explode("|",$extensions) as $e)
							$ext_allowed[$e]=$name;
				}
			}

			// localizar ficheros
			$directorio=Array();
			if (file_exists($raiz.$path)) {
				$i=0;
				$all=Array();
				$d=dir($raiz.$path);
				while ($e=$d->read()) {
					if ($e=="." || $e=="..") continue;
					$isdir=(is_dir($raiz.$path.$e)?true:false);
					if (!$all[($isdir?"dirs":"files")])
						$all[($isdir?"dirs":"files")]=Array();
					$p=strrpos($e,".");
					$extension=strtolower($p?substr($e,$p+1):"");
					if (!$isdir && $ext_allowed && !$ext_allowed[$extension] && !$ext_allowed[""]) continue;
					$ico="images/ext16/".$extension.".png";
					$ico=(file_exists($ico)?$ico:($isdir?"images/ext16/folder.png":"images/ext16/file.png"));
					switch ($extension) {
					case "jpg": case "jpeg": case "png": case "gif":
						$imagen
							="?ajax=filer:capture"
							."&w=128"
							."&h=96"
							."&filer=".urlencode($adata["filer"])
							."&path=".urlencode(utf8_encode($path))
							."&file=".urlencode($e);
						break;
					default:
						$imagen="images/ext48/".$extension.".png";
						$imagen=(file_exists($imagen)?$imagen:($isdir?"images/ext48/folder.png":"images/ext48/file.png"));
					}
					$size=($isdir?"":@filesize($raiz.$path.$e));
					$mtime=(@filemtime($raiz.$path.$e));
					$all[($isdir?"dirs":"files")][$i++]=Array(
						"imagen"=>$imagen,
						"ico"=>$ico,
						"file"=>utf8_encode($e),
						"path"=>$raiz,
						"dir"=>$isdir,
						"size"=>$size,
						"sizet"=>(!$isdir?number_format($size,0,0,"."):""),
						"mtime"=>$mtime,
						"mtimet"=>date("d/m/Y H:i",$mtime),
					);
				}
				$d->close();
				$directorio=array_merge(($all["dirs"]?$all["dirs"]:Array()),($all["files"]?$all["files"]:Array()));
			}
			
			// ordenar
			function sortdir($a,$b) {
				if ($a["dir"] && !$b["dir"]) return -1;
				if ($a["dir"] && $b["dir"] && strtolower($a["file"])<strtolower($b["file"])) return -1;
				if (!$a["dir"] && !$b["dir"] && strtolower($a["file"])<strtolower($b["file"])) return -1;
				return 1;
			}
			usort($directorio,"sortdir");
			//debug($directorio);
			
			// devolver AJAX
			ajax(Array(
				"root"=>utf8_encode($raiz),
				"path"=>utf8_encode($path),
				"dir"=>$directorio,
				"count"=>count($directorio),
				"exts"=>$o["ext"],
				"ext"=>$ext,
				"upload"=>($o["upload"]?true:false),
				"ok"=>true,
			));
			
		}
	}
