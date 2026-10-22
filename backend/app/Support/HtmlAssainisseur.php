<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Assainit du HTML importé (liste blanche de balises, aucun attribut sauf href http(s)).
 * Empêche l'injection de scripts (XSS) lorsque le contenu est affiché dans le front.
 */
class HtmlAssainisseur
{
    private const BALISES = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'a', 'br', 'blockquote'];

    public static function nettoyer(string $html): string
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="racine">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $racine = $doc->getElementById('racine');
        if (! $racine) {
            return '';
        }
        self::parcourir($racine);

        $sortie = '';
        foreach ($racine->childNodes as $enfant) {
            $sortie .= $doc->saveHTML($enfant);
        }

        return trim(preg_replace('/(<p>\s*<\/p>|\n{3,})/', '', $sortie));
    }

    private static function parcourir(DOMNode $noeud): void
    {
        foreach (iterator_to_array($noeud->childNodes) as $enfant) {
            if ($enfant instanceof DOMElement) {
                $balise = strtolower($enfant->tagName);
                if (in_array($balise, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg'], true)) {
                    $noeud->removeChild($enfant);
                    continue;
                }
                self::parcourir($enfant);
                if (! in_array($balise, self::BALISES, true)) {
                    while ($enfant->firstChild) {           // on garde le texte, on retire la balise
                        $noeud->insertBefore($enfant->firstChild, $enfant);
                    }
                    $noeud->removeChild($enfant);
                    continue;
                }
                $href = $balise === 'a' ? $enfant->getAttribute('href') : null;
                foreach (iterator_to_array($enfant->attributes) as $attribut) {
                    $enfant->removeAttribute($attribut->name);
                }
                if ($href && preg_match('#^https?://#i', $href)) {
                    $enfant->setAttribute('href', $href);
                    $enfant->setAttribute('rel', 'noopener noreferrer');
                    $enfant->setAttribute('target', '_blank');
                }
            } elseif ($enfant->nodeType === XML_COMMENT_NODE) {
                $noeud->removeChild($enfant);
            }
        }
    }
}
