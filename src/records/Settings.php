<?php

declare(strict_types=1);

namespace developion\craft\translations\records;

use craft\db\ActiveRecord;
use developion\craft\translations\migrations\Table;

class Settings extends ActiveRecord
{

	public static function tableName(): string
	{
		return Table::CRAFT_TRANSLTAION_SETTINGS;
	}
}
