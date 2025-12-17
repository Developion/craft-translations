<?php

declare(strict_types=1);

namespace developion\craft\translations\models;

use craft\base\Model;

class GeneralSettings extends Model
{
	public int $siteId;
	public string $original;
	public string $translate;
	public string $pluginHandle;
}
