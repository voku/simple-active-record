<?php

declare(strict_types=1);

namespace voku\db;

use Arrayy\Arrayy;

/**
 * Class Expressions via Arrayy.
 *
 * - Every SQL can be split into multiple expressions.
 * - Each expression contains three parts: "$source, $operator, $target"
 *
 * @property ActiveRecordExpressions|string $source   (option) <p>Source of this expression.</p>
 * @property string                         $operator (required)
 * @property ActiveRecordExpressions|string $target   (required) <p>Target of this expression.</p>
 */
class ActiveRecordExpressions extends Arrayy
{
    const SOURCE = 'source';

    const OPERATOR = 'operator';

    const TARGET = 'target';

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->source . ' ' . $this->operator . ' ' . $this->target;
    }
}
