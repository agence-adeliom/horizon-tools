<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\Settings\ConditionalLogic;
use Extended\ACF\Fields\Settings\HelperText;
use Extended\ACF\Fields\Settings\Required;
use Extended\ACF\Fields\Settings\Wrapper;

class FontAwesomeIcon extends Field
{
	use ConditionalLogic;
	use HelperText;
	use Required;
	use Wrapper;

	protected string|null $type = 'font-awesome';

    public function format(string $format): static
    {
        if (!in_array($format, ['element', 'unicode', 'object'])) {
            throw new InvalidArgumentException("Invalid argument format [$format].");
        }

        $this->settings['save_format'] = $format;

        return $this;
    }

}
