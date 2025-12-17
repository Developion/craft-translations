<?php

declare(strict_types=1);

namespace developion\craft\translations\models;

use craft\base\Model;

class Translations extends Model
{
	public ?int $id = null;
	public ?int $siteId = null;
	public string $name = '';
	public string $category = '';
	public array $translations = [];

	public function rules(): array
	{
		return [
			[['siteId', 'name', 'category', 'translations'], 'required'],

			[['id', 'siteId'], 'integer'],

			[['name', 'category'], 'string', 'max' => 255],

			[['translations'], 'string'],

			[['name', 'category'], 'trim'],
			[['translations'], 'trim'],
		];
	}
}
