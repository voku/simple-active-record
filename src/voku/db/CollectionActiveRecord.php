<?php

declare(strict_types=1);

namespace voku\db;

/**
 * @internal
 */
final class CollectionActiveRecord extends \Arrayy\Collection\AbstractCollection
{
    public function getType(): string
    {
        return ActiveRecord::class;
    }

    /**
     * @return ActiveRecord[]
     */
    public function getAll(): array
    {
        return parent::getAll();
    }

    /**
     * @return ActiveRecord[]|\Generator
     */
    public function getGenerator(): \Generator
    {
        yield from parent::getGenerator();
    }
}
