<?php

declare(strict_types=1);

namespace voku\db;

/**
 * Class Expressions Wrap via Arrayy.
 *
 * @property string $start     (option)
 * @property string $end       (option)
 * @property string $delimiter (option) <p>default is ","</p>
 */
class ActiveRecordExpressionsWrap extends ActiveRecordExpressions
{
    const DELIMITER = 'delimiter';

    const START = 'start';

    const END = 'end';

    /**
     * @return string
     */
    public function __toString(): string
    {
        // init
        $str = '';

        if ($this->delimiter) {
            $delimiter = (string) $this->delimiter;
        } else {
            $delimiter = ',';
        }

        // build the string

        if ($this->start) {
            $str .= $this->start;
        } else {
            $str .= '(';
        }

        $str .= $this->target->implode($delimiter);

        if ($this->end) {
            $str .= $this->end;
        } else {
            $str .= ')';
        }

        return $str;
    }
}
