<?php

namespace Orange\Filters;

class HtmlFilter {

    const TAGS_REMOVE = 1;
    const TAGS_ESCAPE = 2;

    protected $tags_whitelist = array(
        'a', 'abbr', 'acronym', 'address', 'b', 'bdo', 'bis', 'blockquote', 'body', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colsroup', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'map', 'marquee', 'nobr', 'ol', 'p', 'param', 'pre', 'q', 'samp', 'small', 'span', 'strike', 'strons', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'ul', 'var', 'wbr', 'xmp'
    );

    protected $single_tags = ['br', 'hr', 'img', 'input'];

    protected $bad_tags_mode = self::TAGS_REMOVE;

    const ATTRIBUTE_LINK = 1;
    const ATTRIBUTE_ESCAPE = 2;
    const ATTRIBUTE_AS_IS = 3;

    protected $attributes_whitelist = array(
        'a' => array('href' => self::ATTRIBUTE_LINK,'title' => self::ATTRIBUTE_ESCAPE,),
        'img' => array('src' => self::ATTRIBUTE_LINK,'alt' => self::ATTRIBUTE_ESCAPE,'title' => self::ATTRIBUTE_ESCAPE,),
        'th' => array('colspan' => self::ATTRIBUTE_ESCAPE,'rowspan' => self::ATTRIBUTE_ESCAPE,),
        'td' => array('colspan' => self::ATTRIBUTE_ESCAPE,'rowspan' => self::ATTRIBUTE_ESCAPE,),
        'table' => array('rules' => self::ATTRIBUTE_ESCAPE,)
    );

    protected $allowed_protocols = ['http','https','ftp'];

    protected $open_tags = [];

    public function __construct(){}

    public function setTagsWhitelist($whitelists){
        $this->tags_whitelist = $whitelists;
        return $this;
    }

    public function addTagsToWhitelist($tags){
        if (!is_array($tags)){
            $tags = [$tags];
        }
        $this->tags_whitelist = array_merge($this->tags_whitelist, $tags);
        return $this;
    }

    public function setAttributesWhitelist($attributes){
        $this->attributes_whitelist = $attributes;
        return $this;
    }

    public function addAttributesToWhitelist($tag, $attributes){
        if (!array_key_exists($tag, $this->attributes_whitelist)){
            $this->attributes_whitelist[$tag] = [];
        }
        if (!is_array($attributes)){
            $attributes = [$attributes];
        }
        $this->attributes_whitelist[$tag] = array_merge($this->attributes_whitelist[$tag], $attributes);
        return $this;
    }

    public function setAllowedProtocols($protocols){
        $this->allowed_protocols = $protocols;
        return $this;
    }

    public function addAllowedProtocol($protocols){
        if (!is_array($protocols)){
            $protocols = [$protocols];
        }
        $this->allowed_protocols = array_merge($this->allowed_protocols, $protocols);
        return $this;
    }

    public function setBadTagsMode($mode){
        $this->bad_tags_mode = $mode;
        return $this;
    }

    public function parse($text){
        $this->open_tags = [];
        $text = ' '.$text;
        $current_char = 0;
        $full_length = mb_strlen($text);
        $filtered_text = '';
        while ($current_char < $full_length) {
            $tag_start = mb_strpos($text,'<',$current_char);
            if ($tag_start == 0) {
                $filtered_text .= SimpleFilters::esc(mb_substr($text,$current_char,$full_length-$current_char));
                $current_char = $full_length;
            } else {
                if ($tag_start > $current_char) {
                    $filtered_text .= SimpleFilters::esc(mb_substr($text,$current_char,$tag_start-$current_char));
                }
                $current_char = $tag_start;
                $tag_end = mb_strpos($text,'>',$tag_start);
                if ($tag_end == 0) {
                    $filtered_text .= SimpleFilters::esc(mb_substr($text,$current_char,$full_length-$current_char));
                    $current_char = $full_length;
                } else {
                    $tagBody = mb_substr($text,$tag_start+1,$tag_end-$tag_start-1);
                    if (mb_substr($tagBody,0,1) == ' ') {
                        $filtered_text .= SimpleFilters::esc('< ');
                        $current_char += 2;
                    } else {
                        $filtered_text .= $this->processTag($tagBody);
                        $current_char = $tag_end+1;
                    }
                }
            }
        }
        return mb_substr($filtered_text,1,mb_strlen($filtered_text)-1).$this->closeUnclosedTags();
    }

    protected function closeUnclosedTags($final_tag = null){
        $result = '';
        if (is_null($final_tag) || in_array($final_tag,$this->open_tags)){
            $count = count($this->open_tags);
            for ($i = ($count-1); $i >= 0; $i--){
                $tag = $this->open_tags[$i];
                if ($tag){
                    $this->open_tags[$i] = null;
                    $result .= '</'.$tag.'>';
                }
                if ( $final_tag && ($tag == $final_tag) ){
                    $i = -1;
                }
            }
        }
        return $result;
    }

    protected function processTag($tag){
        $close = (mb_substr($tag,0,1) == '/');
        $start = $close ? 1 : 0;
        $tb_end = mb_strpos($tag,' ');
        if ($tb_end < 1) {
            $tb_end = mb_strlen($tag);
        }
        $tag_body = mb_strtolower(mb_substr($tag,$start,$tb_end-$start));
        if (in_array($tag_body, $this->tags_whitelist)) {
            if ($close) {
                return $this->closeUnclosedTags($tag_body);
            } else {
                if (!in_array($tag_body, $this->single_tags)) {
                    $this->open_tags[] = $tag_body;
                }
                $args = $this->parseAttributes(mb_substr($tag,mb_strlen($tag_body)),$tag_body);
                return in_array($tag_body, $this->single_tags)
                    ? '<' . $tag_body . $args . ' />'
                    : '<' . $tag_body . $args . '>'
                    ;
            }
        } else {
            return $this->bad_tags_mode == self::TAGS_REMOVE ? '' : SimpleFilters::esc('<' . $tag . '>');
        }
    }

    protected function parseAttributes($attrs,$tag){
        $attrs = trim(trim($attrs),'/');
        $pairs = array();
        $current_char = 0;
        $full_length = mb_strlen($attrs);
        $string = ''; $open = true; $eqnum = 0; $last = ''; $qopenchar = '';
        while ($current_char <= $full_length) {
            $char = mb_substr($attrs, $current_char, 1);
            if (($char == ' ') && ($qopenchar == '')){
                $open = (!$open);
                if (!$open && $string) {
                    $pairs[] = $string;
                    $eqnum = 0;
                    $qopenchar = $last = $string = '';
                } else {
                    $string .= $last = ' ';
                }
            } else {
                $open = true;
                if (($char == $qopenchar) && ($last != "\\")){
                    $qopenchar = '';
                }
                if ( ($last == '=') && ($eqnum == 1) ){
                    $qopenchar = $char;
                }
                if ($char == '='){
                    $eqnum++;
                }
                $last = $char;
                $string .= $char;
            }
            $current_char++;
        }
        if ($string){
            $pairs[] = $string;
        }
        $attributes = [];
        if ($pairs){
            foreach ($pairs as $pair){
                if ($attribute = $this->parseAttributePair($pair, $tag)){
                    $attributes[] = $attribute[0] . '="' . $attribute[1] . '"';
                }
            }
        }
        return $attributes ? ' ' . implode(' ', $attributes) : '';
    }

    protected function parseAttributePair($pair, $tag){
        $eq_pos = mb_strpos($pair,'=');
        if ($eq_pos > 0) {
            $attribute_name = mb_strtolower(mb_substr($pair,0,$eq_pos));
            $attribute_value = trim(mb_substr($pair,$eq_pos+1,mb_strlen($pair)-($eq_pos+1)),"'\" ");
        } else {
            $attribute_value = $attribute_name = $pair;
        }
        if (!empty($this->attributes_whitelist[$tag])) {
            if (array_key_exists($attribute_name,$this->attributes_whitelist[$tag])) {
                if ($this->attributes_whitelist[$tag][$attribute_name] === self::ATTRIBUTE_LINK) {
                    $attribute_value = $this->filterURLAttribute($attribute_value);
                } else if ($this->attributes_whitelist[$tag][$attribute_name] === self::ATTRIBUTE_LINK) {
                    $attribute_value = SimpleFilters::esc($attribute_value);
                } else if ($this->attributes_whitelist[$tag][$attribute_name] === self::ATTRIBUTE_AS_IS) {
                    //Do nothing
                } else if (is_callable($this->attributes_whitelist[$tag][$attribute_name])){
                    $attribute_value = $this->attributes_whitelist[$tag][$attribute_name]($attribute_value);
                } else {
                    throw new \Exception('Incorrect attribute handling type');
                }
                return [$attribute_name, $attribute_value];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    protected function filterURLAttribute($attribute_value){
        if (mb_substr($attribute_value, 0, 1) == '#'){
            return $attribute_value;
        } else {
            $protocol = mb_substr($attribute_value,0,mb_strpos($attribute_value,':'));
            return in_array($protocol,$this->allowed_protocols)
                ? $protocol.':'.str_replace(':','%3A',SimpleFilters::esc(mb_substr($attribute_value,mb_strlen($protocol)+1)))
                : '#BLOCKED-' . str_replace(':','%3A',SimpleFilters::esc($attribute_value))
                ;
        }
    }

}