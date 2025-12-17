<?php

namespace developion\craft\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251209_122814_create_craft_translation_settings_table migration.
 */
class m251209_122814_create_craft_translation_settings_table extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool
	{
		if (!Craft::$app->getDb()->getTableSchema(Table::CRAFT_TRANSLTAION_SETTINGS)) {
			$this->createTable(Table::CRAFT_TRANSLTAION_SETTINGS, [
				'id' => $this->primaryKey(),
				'siteId' => $this->integer()->notNull(),
				'enableSearch' => $this->boolean(),
			]);
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool
	{
		$this->dropTable(Table::CRAFT_TRANSLTAION_SETTINGS);
		return true;
	}
}
