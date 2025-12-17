<?php

namespace developion\craft\translations\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251209_122815_create_translation_collection_table migration.
 */
class m251209_122815_create_translation_collection_table extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool
	{
		if (!Craft::$app->getDb()->getTableSchema(Table::CRAFT_TRANSLATION_COLLECTION)) {
			$this->createTable(Table::CRAFT_TRANSLATION_COLLECTION, [
				'id' => $this->primaryKey(),
				'handle' => $this->string(100)->notNull()->unique(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
			]);
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool
	{
		$this->dropTable(Table::CRAFT_TRANSLATION_COLLECTION);
		return true;
	}
}
