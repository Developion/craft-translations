<?php

declare(strict_types=1);

namespace developion\craft\translations\models;

use craft\base\Model;

class RegisteredTranslations extends Model
{
	public int $siteId;
	public string $text;
}
