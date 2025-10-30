<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Fields\Text\WysiwygField;
use Adeliom\HorizonTools\Services\SeoService;

class WysiwygHooks extends AbstractHook
{
    public const OBFUSCATE_CLASS = 'obfuscated-link';
    public const OBFUSCATE_ATTRIBUTE = 'data-obfuscate';

    public function init(): void
    {
        add_filter('tiny_mce_before_init', [$this, 'removeHeadings']);
        add_filter('mce_buttons', [$this, 'removeButtons']);
        add_filter('mce_buttons_2', [$this, 'removeButtonLine2']);
        add_filter('mce_buttons', [$this, 'addSelect']);
        add_filter('acf/fields/wysiwyg/toolbars', [$this, 'wysiwygToolbars']);

        if (SeoService::isObfuscationEnabled()) {
            add_filter('tiny_mce_before_init', [$this, 'handleTinyMCEObfuscateAttributes']);
            add_action('admin_enqueue_scripts', [$this, 'handleObfuscateLinksInWYSIWYGs']);
            add_filter('acf/format_value/type=wysiwyg', [$this, 'formatWYSIWYGObfuscation'], accepted_args: 3);
            add_action('admin_enqueue_scripts', [$this, 'handleAcfLinkField']);
            add_filter('acf/update_value/type=link', [$this, 'handleLinkUpdate'], accepted_args: 4);
        }
    }

    public static function handleLinkUpdate($value, $postId, $field, $original)
    {
        return $value;
    }

    public static function handleAcfLinkField()
    {
        wp_add_inline_script(
            'acf-input',
            "
(function($){
    function addObfuscateField(){
        // On attend que ACF ait initialisé les champs Lien
        if (typeof acf === 'undefined') {
            setTimeout(addObfuscateField, 100);
            return;
        }
        
        if (typeof window.acf === 'undefined') {
        	return setTimeout(waitForACF, 100);
    	}

        acf.addAction('append_field/type=link', function(field) {
        	var jField = $(field);
            // Si déjà présent, on ne le recrée pas
            if (jField.find('.acf-link-obfuscate').length) return;

            // Récupère l'input 'target' pour insérer après
            var target = $(field.\$el[0].querySelector('input[name$=\"[target]\"]'));

            // Crée le champ hidden
            var fieldName = target[0].name;
            // Remove last [target] part
            fieldName = fieldName.substring(0, fieldName.length - 8);
            fieldName = fieldName + '[obfuscate]';
            
            var hidden = $('<input type=\"hidden\" class=\"input-obfuscate\" data-name=\"obfuscate\" name=\"'+ fieldName +'\" value=\"0\">');
            var defaultChecked = false;
            
            // If target parent doesn't contains element with class input-obfuscate
            if(!target.closest('.acf-hidden').find('.input-obfuscate').length){
            	target.after(hidden);
			}else {
				hidden = target.closest('.acf-hidden').find('.input-obfuscate');
				
				defaultChecked = hidden.val() === '1';
			}
            

            // Ajoute une checkbox visible pour l’utilisateur
            var checkbox = $('<label style=\"display:block;margin-top:4px;\"><input type=\"checkbox\" class=\"acf-link-obfuscate-toggle\"> Obfusquer le lien</label>');
            if (defaultChecked) {
				checkbox.find('input').prop('checked', true);
			}
            target.closest('.acf-input').append(checkbox);

            // Synchronisation checkbox → hidden
            checkbox.on('change', function(e){
                hidden.val(e.target.checked ? '1' : '0');
            });
        });
    }

    addObfuscateField();
})(jQuery);
"
        );
    }

    public static function formatWYSIWYGObfuscation($value, $postId, $field)
    {
        if (empty($value)) {
            return $value;
        }

        // Utilisation de DOMDocument pour parser le HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $value, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $links = $dom->getElementsByTagName('a');
        $obfuscatedLinks = [];

        foreach ($links as $link) {
            if ($link->hasAttribute(self::OBFUSCATE_ATTRIBUTE) && $link->getAttribute(self::OBFUSCATE_ATTRIBUTE) === '1') {
                // Récupérer le lien dans une variable
                $linkHtml = $dom->saveHTML($link);
                $obfuscatedLinks[] = $linkHtml;

                // Supprimer l'attribut data-obfuscate
                $link->removeAttribute(self::OBFUSCATE_ATTRIBUTE);
                // Supprimer la classe obfuscated-link
                $class = $link->getAttribute('class');
                if ($class) {
                    $class = preg_replace('/\b' . preg_quote(self::OBFUSCATE_CLASS, '/') . '\b/', '', $class);
                    $class = trim(preg_replace('/\s+/', ' ', $class));
                    if ($class) {
                        $link->setAttribute('class', $class);
                    } else {
                        $link->removeAttribute('class');
                    }
                }

                // Récupérer le href et le remplacer par l'attribut obfusqué
                $href = $link->getAttribute('href');

                $attr = SeoService::getHrefAttribute(url: $href, obfuscate: true);
                [$attrName, $attrValue] = explode('=', $attr, 2);
                $attrValue = trim($attrValue, '"');

                $link->removeAttribute('href');
                $link->setAttribute($attrName, $attrValue);

                $obfuscationTag = SeoService::getObfuscationTag();

                if ($link->nodeName !== $obfuscationTag) {
                    // Remplacer la balise du lien par celle d'obfuscation
                    $clonedLink = $link->cloneNode(true);
                    $obfuscationElement = $dom->createElement($obfuscationTag);

                    // Copier les attributs
                    foreach ($clonedLink->attributes as $attr) {
                        $obfuscationElement->setAttribute($attr->nodeName, $attr->nodeValue);
                    }

                    // Copier les enfants
                    while ($clonedLink->firstChild) {
                        $obfuscationElement->appendChild($clonedLink->firstChild);
                    }

                    $link->parentNode->replaceChild($obfuscationElement, $link);
                }
            }
        }

        // Réinjecter le HTML modifié
        $newValue = $dom->saveHTML();
        return $newValue;
    }

    public static function handleTinyMCEObfuscateAttributes($init)
    {
        // On récupère la config actuelle pour les <a>
        $valid = isset($init['extended_valid_elements']) ? $init['extended_valid_elements'] : '';

        // Ajouter data-obfuscate à ce qui existe déjà pour <a>
        if (preg_match('/a\[([^\]]*)\]/', $valid, $matches)) {
            // Si <a> existe déjà, ajouter data-obfuscate
            $aAttrs = $matches[1];
            if (strpos($aAttrs, self::OBFUSCATE_ATTRIBUTE) === false) {
                $aAttrs .= '|' . self::OBFUSCATE_ATTRIBUTE;
                $valid = preg_replace('/a\[([^\]]*)\]/', 'a[' . $aAttrs . ']', $valid);
            }
        } else {
            // Si <a> n'existe pas encore, on l'ajoute avec href, target, class + data-obfuscate
            $valid .= ',a[href|target|class|' . self::OBFUSCATE_ATTRIBUTE . ']';
        }

        $init['extended_valid_elements'] = $valid;

        return $init;
    }

    public static function handleObfuscateLinksInWYSIWYGs()
    {
        $attribute = self::OBFUSCATE_ATTRIBUTE;
        $class = self::OBFUSCATE_CLASS;

        wp_add_inline_script(
            'jquery-core',
            "
(function($){
    function initObfuscateCheckbox(){
        if (typeof wpLink === 'undefined') {
            // Si wpLink pas encore défini, on réessaie dans 50ms
            setTimeout(initObfuscateCheckbox, 50);
            return;
        }

        // On sauvegarde la fonction originale
        var originalUpdate = wpLink.update;
        wpLink.update = function() {
            originalUpdate.apply(this, arguments);

            setTimeout(function(){
                var editor = window.tinymce.activeEditor;
                if (!editor) return;

                var node = editor.selection.getNode();
if (!node || node.nodeName !== 'A') {
    node = editor.dom.getParent(editor.selection.getNode(), 'a');
}

// Si toujours null, essayer de retrouver par href via WP Link
if ((!node || node.nodeName !== 'A') && typeof wpLink.getAttrs === 'function') {
    var attrs = wpLink.getAttrs();
    if (attrs && attrs.href) {
        var anchors = editor.dom.select('a[href=\"' + attrs.href + '\"]');
        if (anchors.length) {
            node = anchors[0]; // le lien correspondant à l'URL éditée
        }
    }
}

                if (node && node.nodeName === 'A') {
                    var checked = $('#wp-link-obfuscate').is(':checked');

                    if (checked) {
                        node.setAttribute('$attribute', '1');
                        node.classList.add('$class');
                    } else {
                        node.removeAttribute('$attribute');
                        node.classList.remove('$class');
                    }
                }
            }, 10);
        };

        // Ajouter la checkbox à l'ouverture de la modal
        $(document).on('wplink-open', function() {
    		setTimeout(function() {
    			var isOpenedFromAcfLinkField = wpLink.textarea?.id === 'acf-link-textarea';
    			
        		$('#wp-link-obfuscate').closest('label').remove(); // supprimer si elle existe

				if(!isOpenedFromAcfLinkField) {
                    $('#wp-link .link-target').after(
                        '<div style=\"display:block;\"><label><span></span>&nbsp;<input type=\"checkbox\" id=\"wp-link-obfuscate\"> Obfusquer le lien</label></div>'
                    );
    
                    // Pré-remplir checkbox si le lien sélectionné a déjà l'attribut
                    var editor = window.tinymce.activeEditor;
    
                    if (editor) {
                        var node = editor.selection.getNode();
    
                        if (!node || node.nodeName !== 'A') {
                            node = editor.dom.getParent(editor.selection.getNode(), 'a');
                        }
    
                        if (node && node.nodeName === 'A' && node.getAttribute('$attribute') === '1') {
                            $('#wp-link-obfuscate').prop('checked', true);
                        }
                    }
				}
    		}, 50);
		});
    }

    initObfuscateCheckbox();
})(jQuery);
"
        );
    }

    public static function removeHeadings($headings): array
    {
        $headings['block_formats'] = 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;';
        return $headings;
    }

    public static function wysiwygToolbars(array $toolbars): array
    {
        $toolbars[WysiwygField::TOOLBAR_DEFAULT] = [];
        $toolbars[WysiwygField::TOOLBAR_DEFAULT][1] = [
            'formatselect',
            'styleselect',
            'bold',
            'italic',
            'underline',
            '|',
            'bullist',
            'numlist',
            '|',
            'link',
            '|',
            'removeformat',
        ];

        $toolbars[WysiwygField::TOOLBAR_SIMPLE] = [];
        $toolbars[WysiwygField::TOOLBAR_SIMPLE][1] = [
            'formatselect',
            'styleselect',
            'bold',
            'italic',
            'underline',
            'bullist',
            'numlist',
            'link',
            'removeformat',
        ];

        $toolbars[WysiwygField::TOOLBAR_MINIMAL] = [];
        $toolbars[WysiwygField::TOOLBAR_MINIMAL][1] = ['bold', 'link', 'removeformat'];

        return $toolbars;
    }

    public static function removeButtons($buttons): array
    {
        $remove_buttons = [
            //'strikethrough',
            //'blockquote',
            'hr',
            // horizontal line
            'alignleft',
            'aligncenter',
            'alignright',
            'wp_more',
            // read more link
            'spellchecker',
            // 'dfw',
            // distraction free writing mode
            'wp_adv',
            // kitchen sink toggle (if removed, kitchen sink will always display)
        ];
        foreach ($buttons as $button_key => $button_value) {
            if (in_array($button_value, $remove_buttons, true)) {
                unset($buttons[$button_key]);
            }
        }

        return $buttons;
    }

    public static function removeButtonLine2($buttons): array
    {
        $remove_buttons = ['formatselect', 'underline', 'strikethrough', 'alignjustify', 'forecolor', 'outdent', 'indent', 'hr'];
        foreach ($buttons as $button_key => $button_value) {
            if (in_array($button_value, $remove_buttons)) {
                unset($buttons[$button_key]);
            }
        }

        return $buttons;
    }

    public static function addSelect($buttons): array
    {
        array_unshift($buttons, 'styleselect');
        return $buttons;
    }
}
