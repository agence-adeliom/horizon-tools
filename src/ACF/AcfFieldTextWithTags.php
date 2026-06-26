<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ACF;

use Adeliom\HorizonTools\Services\TextService;

if (!class_exists('AcfFieldTextWithTags')):
    /**
     * Champ ACF texte avec support des tags de remplacement.
     *
     * Étend le champ texte natif d'ACF pour permettre l'utilisation
     * de balises configurables (ex: {bold}texte{/bold}) qui seront
     * automatiquement remplacées à l'affichage.
     */
    class AcfFieldTextWithTags extends \acf_field_text
    {
        public function initialize(): void
        {
            parent::initialize();
            $this->name = 'text_with_tags';
            $this->label = __('Texte avec support des tags', 'horizon-tools');
            $this->defaults = [
                'default_value' => '',
                'maxlength' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'supported_tags' => array_keys(TextService::getTextReplacements()),
                'include' => [],
            ];
        }

        /**
         * @param array<string, mixed> $field
         */
        public function render_field($field): void
        {
            ?>
			<input
				type="text"
				name="<?php echo esc_attr($field['name']); ?>"
				value="<?php echo esc_attr($field['value']); ?>"
				placeholder="<?php echo esc_attr($field['placeholder']); ?>"
				maxlength="<?php echo esc_attr($field['maxlength']); ?>"
				class="<?php echo esc_attr($field['class']); ?>"
			/>
			<?php // Afficher les instructions si elles existent


   echo sprintf('<div class="acf-text-tags-toolbar" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;">%s</div>', TextService::getTextReplacementInstructionsHtml(exclude: $field['exclude'] ?? [], include: $field['include'] ?? []));

   ?>
   <script>
   if (!window._acfTextTagsInit) {
       window._acfTextTagsInit = true;
       document.addEventListener('click', function(e) {
           var tag = e.target.closest('.acf-text-tag');
           if (!tag) return;

           var wrapper = tag.closest('.acf-input');
           if (!wrapper) return;

           var input = wrapper.querySelector('input[type="text"]');
           if (!input) return;

           var open = tag.dataset.open;
           var close = tag.dataset.close;
           var start = input.selectionStart;
           var end = input.selectionEnd;
           var val = input.value;

           if (start !== end) {
               var selected = val.substring(start, end);
               input.value = val.substring(0, start) + open + selected + close + val.substring(end);
               input.selectionStart = start;
               input.selectionEnd = start + open.length + selected.length + close.length;
           } else {
               input.value = val.substring(0, start) + open + close + val.substring(start);
               input.selectionStart = input.selectionEnd = start + open.length;
           }

           input.focus();
           input.dispatchEvent(new Event('input', { bubbles: true }));
           input.dispatchEvent(new Event('change', { bubbles: true }));
           if (window.jQuery) {
               jQuery(input).trigger('input').trigger('change');
           }
       });
   }
   </script>
   <?php
        }

        /**
         * @param string|null $value
         * @param int|string  $postId
         * @param array<string, mixed> $field
         */
        public function format_value($value, $postId, $field): string
        {
            return TextService::handleTextReplacements(base: $value, exclude: $field['exclude'] ?? [], include: $field['include'] ?? []);
        }
    }
endif;
