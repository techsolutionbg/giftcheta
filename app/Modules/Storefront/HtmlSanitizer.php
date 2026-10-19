<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Storefront;

final class HtmlSanitizer
{
    private const ALLOWED=['p','h2','h3','ul','ol','li','strong','b','em','i','u','s','blockquote','pre','code','br','hr','a'];

    public static function clean(?string $html): string
    {
        if($html===null||trim($html)==='')return '';
        $doc=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="storefront-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD|LIBXML_NONET);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        $root=$doc->getElementById('storefront-root');if(!$root)return htmlspecialchars($html,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        self::sanitizeChildren($root);
        $output='';foreach($root->childNodes as $node)$output.=$doc->saveHTML($node);return $output;
    }

    private static function sanitizeChildren(\DOMNode $parent): void
    {
        for($node=$parent->firstChild;$node;){$next=$node->nextSibling;
            if($node instanceof \DOMElement){$tag=strtolower($node->tagName);
                if(!in_array($tag,self::ALLOWED,true)){while($node->firstChild)$parent->insertBefore($node->firstChild,$node);$parent->removeChild($node);$node=$next;continue;}
                $href=$tag==='a'?$node->getAttribute('href'):'';while($node->attributes->length>0)$node->removeAttributeNode($node->attributes->item(0));
                if($tag==='a'&&self::safeHref($href)){$node->setAttribute('href',$href);$node->setAttribute('rel','nofollow noopener noreferrer');}
                self::sanitizeChildren($node);
            }elseif(!$node instanceof \DOMText)$parent->removeChild($node);
            $node=$next;
        }
    }

    private static function safeHref(string $href): bool
    { $href=trim($href);if($href===''||str_starts_with($href,'//'))return false;$scheme=parse_url($href,PHP_URL_SCHEME);return $scheme===null||in_array(strtolower((string)$scheme),['https','http','mailto'],true); }
}
