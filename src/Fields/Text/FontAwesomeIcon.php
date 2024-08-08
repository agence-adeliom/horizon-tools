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
}