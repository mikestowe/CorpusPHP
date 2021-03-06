<?

$_meta['title'] = 'XML Sitemap';
$_meta['sitemap'] = false;
$_meta['raw'] = true;

if( !$shutup ) {
	header("Content-type: application/xml; charset=UTF-8"); 

	$doc = new DOMDocument('1.0');
	$doc->formatOutput = true;

	$urlset = $doc->createElement('urlset');
	$urlset->setAttribute('xmlns','http://www.sitemaps.org/schemas/sitemap/0.9');
	$urlset->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
	$urlset->setAttribute('xsi:schemaLocation','http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
	$urlset = $doc->appendChild($urlset);

	$qry = db::query("select categories_id from categories where categories_id > 0 and list = 1 and template > 0");

	//each database page
	while($row = mysql_fetch_array($qry)) {
		$url = $doc->createElement('url');
		$url->appendChild($doc->createElement('loc', href($row['categories_id']) ));
		$url->appendChild($doc->createElement( 'lastmod', date('Y-m-d', strtotime('Last Week') ) ) );
		$urlset->appendChild($url);
	}
	
	//each hardcoded page not excludded from the sitemap
	$info = co::content_info();
	foreach($info as $file => $data) {
		if($data['sitemap'] !== false) {
			$url = $doc->createElement('url');
			$url->appendChild($doc->createElement('loc', href( $file ) ));
			$url->appendChild($doc->createElement( 'lastmod', date('Y-m-d', filemtime( DWS_CONTENT . $file ) ) ) );
			$urlset->appendChild($url);
		}
	}

	echo $doc->saveXML();
}