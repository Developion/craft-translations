<?php

declare(strict_types=1);

namespace developion\craft\translations\migrations;

use Craft;
use craft\db\Migration;
use developion\craft\translations\Plugin;

class Install extends Migration
{
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

		if (!Craft::$app->getDb()->getTableSchema(Table::REGISTERED_CRAFT_TRANSLATIONS)) {
			$this->createTable(Table::REGISTERED_CRAFT_TRANSLATIONS, [
				'id' => $this->primaryKey(),
				'collectionId' => $this->integer()->notNull(),
				'siteId' => $this->integer()->notNull(),
				'text' => $this->text(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),
			]);
		}

		if (!Craft::$app->getDb()->getTableSchema(Table::CRAFT_TRANSLATIONS)) {
			$this->createTable(Table::CRAFT_TRANSLATIONS, [
				'id' => $this->primaryKey(),
				'collectionId' => $this->integer()->notNull(),
				'language' => $this->string(),
				'name' => $this->string(),
				'translations' => $this->json(),
				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
			]);
		}

		if (!Craft::$app->getDb()->getTableSchema(Table::CRAFT_TRANSLTAION_SETTINGS)) {
			$this->createTable(Table::CRAFT_TRANSLTAION_SETTINGS, [
				'id' => $this->primaryKey(),
				'siteId' => $this->integer()->notNull(),
				'enableSearch' => $this->boolean(),
			]);
		}

		$this->createIndex(
			null,
			Table::REGISTERED_CRAFT_TRANSLATIONS,
			['collectionId']
		);

		$this->addForeignKey(
			null,
			Table::REGISTERED_CRAFT_TRANSLATIONS,
			['collectionId'],
			Table::CRAFT_TRANSLATION_COLLECTION,
			['id'],
			'RESTRICT',
			'CASCADE'
		);

		$this->createIndex(
			null,
			Table::CRAFT_TRANSLATIONS,
			['collectionId']
		);

		$this->addForeignKey(
			null,
			Table::CRAFT_TRANSLATIONS,
			['collectionId'],
			Table::CRAFT_TRANSLATION_COLLECTION,
			['id'],
			'RESTRICT',
			'CASCADE'
		);

		Plugin::getInstance()->getTranslations()->populateCollection();
		Plugin::getInstance()->getTranslations()->populateRegTrans();
		Plugin::getInstance()->getTranslations()->populateTranslations();

		return true;
	}

	public function safeDown(): bool
	{
		$this->dropTable(Table::CRAFT_TRANSLATION_COLLECTION);
		$this->dropTable(Table::REGISTERED_CRAFT_TRANSLATIONS);
		$this->dropTable(Table::CRAFT_TRANSLATIONS);
		$this->dropTable(Table::CRAFT_TRANSLTAION_SETTINGS);
		$this->truncateTable(Table::REGISTERED_CRAFT_TRANSLATIONS);
		return true;
	}
}
