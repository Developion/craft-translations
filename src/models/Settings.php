<?php

declare(strict_types=1);

namespace developion\craft\translations\models;

use Craft;
use craft\base\Model;

/**
 * Craft Translations settings
 */
class Settings extends Model
{
	public array $translations = [];

	public function getTranslations(): array
	{
		return $this->translations;
	}
}
