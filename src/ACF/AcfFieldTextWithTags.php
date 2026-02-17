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
            $this->label = __('Texte avec support des tags');
            $this->defaults = [
                'default_value' => '',
                'maxlength' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'supported_tags' => array_keys(TextService::getTextReplacements()),
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


   echo sprintf('<p class="description">%s</p>', TextService::getTextReplacementInstructionsHtml(exclude: $field['exclude'] ?? []));

   if (!empty($field['instructions'])) {
       echo '<p class="description">' . $field['instructions'] . '</p>';
   }
        }

        /**
         * @param string|null $value
         * @param int|string  $postId
         * @param array<string, mixed> $field
         */
        public function format_value($value, $postId, $field): string
        {
            return TextService::handleTextReplacements(base: $value, exclude: $field['exclude'] ?? []);
        }
    }
endif;
