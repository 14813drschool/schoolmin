<?php

namespace Kotchasan\QueryBuilder;

/**
 * Class UpdateBuilder
 *
 * Builder for UPDATE queries.
 *
 * @package Kotchasan\QueryBuilder
 */
class UpdateBuilder extends QueryBuilder
{
    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        $sqlBuilder = $this->getSqlBuilder();

        // Build UPDATE statement using SqlBuilder
        $query = 'UPDATE '.$sqlBuilder->quoteIdentifier($this->table).' SET ';

        // Add SET clause
        $sets = [];
        foreach ($this->values as $column => $value) {
            $sets[] = $sqlBuilder->quoteIdentifier($column).' = :'.$column;
            $this->namedBindings[':'.$column] = $value;
        }
        $query .= implode(', ', $sets);

        // Add WHERE clauses (use existing logic for now)
        if (!empty($this->wheres)) {
            $query .= ' WHERE '.$this->buildWhereClauses();
        }

        // Add ORDER BY clause using SqlBuilder
        if (!empty($this->orders)) {
            $query .= ' '.$sqlBuilder->buildOrderByClause($this->orders);
        }

        // Add LIMIT clause using SqlBuilder
        if ($this->limit !== null) {
            $query .= ' '.$sqlBuilder->buildLimitClause($this->limit, $this->offset);
        }

        return $query;
    }

    // use parent's buildWhereClauses
}
