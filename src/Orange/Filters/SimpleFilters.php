<?php

namespace Orange\Filters;

class SimpleFilters {

    public static function esc($text){
        return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    }

    public static function escAsText($text, $remove_doubled_empty_lines = false){
        $text = static::esc($text);
        if ($remove_doubled_empty_lines){
            $text = static::removeDoubledEmptyLines($text);
        }
        return nl2br($text);
    }

    public static function escAsTextWithQuotes($text, $remove_doubled_empty_lines = false){
        $lines = explode("\n", $text);
        $text = '';
        $quote_is_opened = false;
        foreach ($lines as $line){
            $line = trim($line);
            if ((strlen($line) > 0) && (mb_substr($line, 0, 1) == '>')){
                if (!$quote_is_opened){
                    $quote_is_opened = true;
                    $text .= '<blockquote>';
                }
                $line = static::esc(trim(mb_substr($line, 1)));
            } else {
                if ($quote_is_opened){
                    $quote_is_opened = false;
                    $text .= '</blockquote>';
                }
            }
            $text .= $line . "\n";
        }
        if ($remove_doubled_empty_lines){
            $text = static::removeDoubledEmptyLines($text);
            $text = str_replace("</blockquote>\n\n", "</blockquote>\n", $text);
        }
        return nl2br($text);
    }

    public static function removeDoubledEmptyLines($text){
        while (strpos($text, "\n\n\n") !== false){
            $text = str_replace("\n\n\n", "\n\n", $text);
        }
        return $text;
    }

    public static function enableURLs($text, $url_callback = null){
        $sp_start = 0;
        while (($sp_start = static::searchNext($text, ['http://', 'https://', 'ftp://', 'www.'], $sp_start)) !== false){
            $sp_end = static::searchNext($text, [' ', "\n", "\r", "\t"], $sp_start);
            if ($sp_end === false){
                $sp_end = mb_strlen($text);
            }
            $url_length = $sp_end - $sp_start;
            $url = mb_substr($text, $sp_start, $url_length);
            $url_tag = '<a href="' . htmlentities(is_callable($url_callback) ? $url_callback($url) : $url, ENT_QUOTES, "UTF-8") . '">' . $url . '</a>';
            $text = mb_substr($text, 0, $sp_start) . $url_tag . mb_substr($text, $sp_end);
            $sp_start = $sp_end + (mb_strlen($url_tag) - mb_strlen($url));
        }
        return $text;
    }

    protected static function searchNext($text, $search, $sp = 0){
        $min = false;
        foreach ($search as $s){
            $sp = strpos($text, $s, $sp);
            if ($sp !== false){
                if ($min === false){
                    $min = $sp;
                } else if ($sp < $min){
                    $min = $sp;
                }
            }
        }
        return $min;
    }

}