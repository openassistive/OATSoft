<?php
	require 'vendor/autoload.php';
	use League\HTMLToMarkdown\HtmlConverter;

	set_time_limit(0);
	$path = dirname(__FILE__);
	
	$FILE_OUT = $path.DIRECTORY_SEPARATOR."result.csv";
	
	function is_link_404($url)
	{
		$handle = curl_init($url);
		curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($handle, CURLOPT_NOBODY, 1); // and *only* get the header 
		/* Get the HTML or whatever is linked in $url. */
		$response = curl_exec($handle);
		/* Check for 404 (file not found). */
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if($httpCode == 404) {
			return False;
		} else {
			return True;
		}
		curl_close($handle);
	}

	function csv_start()
	{
		global $FILE_OUT;
		
		if (file_exists($FILE_OUT))
			unlink($FILE_OUT);
		
		file_put_contents($FILE_OUT, "Title;Short;Long;Links;Categories;Download\r\n", FILE_APPEND);
	}
	function get_http($curl, $url, $post_fields=null)
	{
		global $path;
		
		$ok = false;
		while (!$ok)
		{
			curl_setopt($curl, CURLOPT_URL, $url); 
			curl_setopt($curl, CURLOPT_COOKIEFILE, $path.DIRECTORY_SEPARATOR."cookies.txt");
			curl_setopt($curl, CURLOPT_COOKIEJAR, $path.DIRECTORY_SEPARATOR."cookies.txt");
			curl_setopt($curl, CURLOPT_USERAGENT, "User-Agent: Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; GTB7.4; InfoPath.2; SV1; .NET CLR 3.3.69573; WOW64; en-US)");
			if ($post_fields != null)
			{
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
			}
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$res = curl_exec($curl);
			if (!$res)
			{
				sleep(5);
				echo "Some get_http error";
				continue;
			}
			else
			{
				$ok = true;
			}
		}
		
		sleep(10);
		return $res;
	}
	
	$curl = curl_init();
	
	$res = get_http($curl, "http://web.archive.org/web/20160201183815/http://www.oatsoft.org/Software/listing/Repository");
	
	$pos = strpos($res, "Full listing of software");
	$res = substr($res, $pos);
	if (preg_match_all("/<a href=\\\"([^\\\"]+)\\\">([^<]+)<\\/a>\\s+<\\/dt>/ims", $res, $m))
	{
		for($i=0; $i<count($m[0]); $i++)
		{
			echo ($i+1)." from ".count($m[0])." - ".$m[2][$i]."...";
			$url = "http://web.archive.org".$m[1][$i];
			$res = get_http($curl, $url);
			/**/$title = '"'.str_replace('"', '""', trim($m[2][$i])).'"';
			if (preg_match("/<div class=\\\"documentDescription\\\">([^<]+)<\\/div>/ims", $res, $m2))
			/**/$short = '"'.str_replace("\r", " ", str_replace("\n", " ", str_replace("\r\n", " ", str_replace('"', '""', trim($m2[1]))))).'"';
			else
				$short = "";
			if  (preg_match("/<\\/dl>\\s+<div class=\\\"visualClear\\\"><\\/div>\\s+(.+)<div class=\\\"visualClear\\\"><\\/div>\\s+<div class=\\\"visualClear\\\"><\\/div>\\s+<div class=\\\"visualClear\\\"><\\/div>/ims", $res, $m3))
			/**/$long = '"'.str_replace("\r", " ", str_replace("\n", " ", str_replace("\r\n", " ", str_replace('"', '""', trim($m3[1]))))).'"';
			else
				$long = "";
			/**/$links = "";
			if  (preg_match_all("/<a href=\\\"([^\\\"]+)\\\"\\s+class=\\\"link-plain\\\"[^>]+>([^<]+)</ims", $res, $m4))
			{
				$slinks = '';
				for($j=0; $j<count($m4[0]); $j++)
				{
					$add = false;
					$s = $m4[1][$j];
					$titl = str_replace(" ", "_", strtolower($m4[2][$j]))."-";
					if ($s == "Poll")
						continue;
					if (strpos($s, "@") !== FALSE)
					{
						$add = true;
					}
					else
					{
						if (strpos($s, "http://") !== FALSE)
						{
							$add = true;
							$s = substr($s, strpos($s, "http://"));
						}	
					}
					if ($add)
					{
						if ($links != "")
							$links .= ",";
						$links .= $titl;
						$links .= $s;	
						$slinks .= '- <a href="'.$s.'">'.ucwords(str_replace("_"," ",trim($titl,"-"))).'</a>'."\r\n";					
					}
				}
			}
			$links = '"'.$links.'"';
			/**/$categories = "";
			if  (preg_match_all("/title=\\\"[^\\\"]+\\\">([^\\\"]+)<\\/a>( -|)\\s+<\\/span>/ims", $res, $m5))
			{
				for($k=0; $k<count($m5[0]); $k++)
				{
					$s = $m5[1][$k];
					if ($categories != "")
						$categories .= ",";
					$categories .= $s;						
				}
			}
			/**/$download="";
			if  (preg_match("/class=\\\"link-plain\\\"\\s+href=\\\"([^\\\"]+)\\\">\\s+<span>Download<\\/span>/ims", $res, $m6))
			{
				if (strpos($m6[1], "http://") !== FALSE)
				{
					$download = substr($m6[1], strpos($m6[1], "http://"));
				}	
			}
			
			$tags = '- '.str_replace(',',"\r\n".'- ',$categories);
			$ttitle = trim($title,'"');
			echo 'title:'.$ttitle."\r\n";
			$stitle = str_replace("_","",$ttitle);
			$stitle = str_replace("'","",$ttitle);
			$stitle = str_replace(" ","",$ttitle);
			echo 'stitle:'.$stitle."\r\n";
			$slong = trim($long, '"');
			$sshort = trim($short, '"');
			
			$linkalive = (is_link_404($download)) ? '' : '(Warning: possible dead-link)';
			$linkaliveTag = ($linkalive=='') ? '' : '- Possible-404';
			
			$converter = new HtmlConverter();
			$smarkdown = $converter->convert($slong);
			
			$hexo_FILE_OUT = '../source/_posts/'.$stitle.'.md';
			echo 'file:'.$hexo_FILE_OUT;
			$template = <<<EOT
---
title: $ttitle
date: 2016-06-21 17:46:46
tags: 
$tags
$linkaliveTag
---

{% alert info no-icon %}
$sshort
{% endalert %}

<!-- more -->

$smarkdown

### Links:
$slinks
### Download: $download $linkalive
EOT;

			file_put_contents($hexo_FILE_OUT, $template);
			$template = '';
					
			file_put_contents($FILE_OUT, "$title;$short;$long;$links;$categories;$download\r\n", FILE_APPEND);
			echo "Ok.\n";
		}
	}
		
	curl_close($curl);

?>