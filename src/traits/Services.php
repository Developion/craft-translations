<?php

namespace developion\craft\translations\traits;

use developion\craft\translations\services\Translations;

trait Services
{
	public function getTranslations(): Translations
	{
		return $this->get('translations');
	}
}
