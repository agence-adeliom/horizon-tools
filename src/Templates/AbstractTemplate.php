<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Templates;

abstract class AbstractTemplate
{
	abstract public function getPostTypes(): array;

	abstract public function getBlocks(): array;
}