<?php

$input = '> It could be quote...

<b>Not bold</b> text.
http://mikhail.kelner.ru - just link to my site




End of text';

require_once __DIR__ . '/../src/Orange/Filters/SimpleFilters.php';

use \Orange\Filters\SimpleFilters;

echo '<h4>Htmlspecialshars</h4>' . SimpleFilters::esc($input);
echo '<h4>As text</h4>' . SimpleFilters::escAsText($input);
echo '<h4>Quotes</h4>' . SimpleFilters::escAsTextWithQuotes($input, true);
echo '<h4>Htmlspecialshars with links</h4>' . SimpleFilters::enableURLs(SimpleFilters::esc($input));
echo '<h4>As text with links (and links callback)</h4>' . SimpleFilters::enableURLs(SimpleFilters::escAsText($input), function($url){ return '#GO-TO:'.$url; });
