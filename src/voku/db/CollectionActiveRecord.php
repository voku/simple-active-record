<?php

declare(strict_types=1);

namespace voku\db;

/**
 * @template TKey of array-key
 * @template T
 * @extends  \Arrayy\Collection\AbstractCollection<TKey,T>
 */
final class CollectionActiveRecord extends \Arrayy\Collection\AbstractCollection
{
    public function getType(): string
    {
        return ActiveRecord::class;
    }

    /**
     * @return ActiveRecord[]
     * @psalm-return ActiveRecord[]|array<TKey,T>
     */
    public function getAll(): array
    {
        return parent::getAll();
    }

    /**
     * @return ActiveRecord[]|\Generator
     * @psalm-return \Generator<mixed,T>|\Generator<TKey,T>
     */
    public function getGenerator(): \Generator
    {
        yield from parent::getGenerator();
    }
}
