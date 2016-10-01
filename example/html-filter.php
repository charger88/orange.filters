<?php

$input = <<<EOT
<div id="oops-it-is-not-closed">
	<p style="color: green">
		<b>Bold</b> text is funny, but it is not allowed.
		Allowed javascript in <a href="#DONT-GO-THIS" onclick="alert(\'test\');">link</a> is much better (see console)...
		Escpecially, than <a href="javascrip:alert(\'test\');">not allowed</a>.
		But the best link - is <a href="http://mikhail.kelner.ru">workable one</a>!
	</p>
EOT;

require_once __DIR__ . '/../src/Orange/Filters/SimpleFilters.php';
require_once __DIR__ . '/../src/Orange/Filters/HtmlFilter.php';

use \Orange\Filters\HtmlFilter;

$f = new \Orange\Filters\HtmlFilter();
echo $f
    ->setTagsWhitelist(['div','p','a'])
    ->setBadTagsMode(HtmlFilter::TAGS_ESCAPE)
    ->addAttributesToWhitelist('a', ['onclick' => function($attr){ return rtrim(str_replace('alert', 'console.log', $attr), ';').'; return false;'; }])
    ->parse($input)
;