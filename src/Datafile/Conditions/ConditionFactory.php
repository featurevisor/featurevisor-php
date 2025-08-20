<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use DateTimeImmutable;
use InvalidArgumentException;

final class ConditionFactory
{
    public function create(array $conditions): ConditionInterface
    {
        if (array_is_list($conditions) === false) {
            return $this->map($conditions);
        }

        $mappedConditions = array_map(fn ($condition) => $this->map($condition), $conditions);

        if (count($mappedConditions) === 1) {
            return $mappedConditions[0];
        }

        return new AndCondition(...$mappedConditions);
    }

    /**
     * @param array $condition
     * @return ConditionInterface
     */
    private function map(array $condition): ConditionInterface
    {
        if (array_key_exists('and', $condition)) {
            return $this->createLogicOperator('and', $condition['and']);
        }

        if (array_key_exists('or', $condition)) {
            return $this->createLogicOperator('or', $condition['or']);
        }

        if (array_key_exists('not', $condition)) {
            return $this->createLogicOperator('not', $condition['not']);
        }

        return $this->createCondition($condition);
    }

    private function createCondition(array $condition): ConditionInterface
    {
        if (!isset($condition['attribute']) || !isset($condition['operator'])) {
            throw new InvalidArgumentException('Invalid condition format');
        }

        $attribute = $condition['attribute'];
        $value = $condition['value'] ?? null;

        switch ($condition['operator']) {
            case 'after':
                return new AfterCondition($attribute, new DateTimeImmutable($value));
            case 'before':
                return new BeforeCondition($attribute, new DateTimeImmutable($value));
            case 'contains':
                return new ContainsCondition($attribute, $value);
            case 'notContains':
                return new NotCondition(new ContainsCondition($attribute, $value));
            case 'endsWith':
                return new EndsWithCondition($attribute, $value);
            case 'equals':
                return new EqualsCondition($attribute, $value);
            case 'notEquals':
                return new NotCondition(new EqualsCondition($attribute, $value));
            case 'exists':
                return new ExistsCondition($attribute);
            case 'notExists':
                return new NotCondition(new ExistsCondition($attribute));
            case 'greaterThan':
                return new GreaterThanCondition($attribute, $value);
            case 'greaterThanOrEquals':
                return new GreaterThanOrEqualsCondition($attribute, $value);
            case 'includes':
                return new IncludesCondition($attribute, $value);
            case 'notIncludes':
                return new NotCondition(new IncludesCondition($attribute, $value));
            case 'in':
                return new InCondition($attribute, $value);
            case 'notIn':
                return new NotCondition(new InCondition($attribute, $value));
            case 'lessThan':
                return new LessThanCondition($attribute, $value);
            case 'lessThanOrEquals':
                return new LessThanOrEqualsCondition($attribute, $value);
            case 'matches':
                return new MatchesCondition($attribute, sprintf('/%s/%s', $value, $condition['regexFlags'] ?? ''));
            case 'notMatches':
                return new NotCondition(new MatchesCondition($attribute, sprintf('/%s/%s', $value, $condition['regexFlags'] ?? '')));
            case 'semverEquals':
                return new SemverEqualsCondition($attribute, $value);
            case 'semverNotEquals':
                return new NotCondition(new SemverEqualsCondition($attribute, $value));
            case 'semverGreaterThan':
                return new SemverGreaterThanCondition($attribute, $value);
            case 'semverGreaterThanOrEquals':
                return new SemverGreaterThanOrEqualsCondition($attribute, $value);
            case 'semverLessThan':
                return new SemverLessThanCondition($attribute, $value);
            case 'semverLessThanOrEquals':
                return new SemverLessThanOrEqualsCondition($attribute, $value);
            case 'startsWith':
                return new StartsWithCondition($attribute, $value);
            default:
                throw new InvalidArgumentException('Unknown operator: ' . $condition['operator']);
        }
    }

    private function createLogicOperator(string $operator, array $conditions): ConditionInterface
    {
        $mappedConditions = array_map(fn ($condition) => $this->map($condition), $conditions);

        switch ($operator) {
            case 'and':
                return new AndCondition(...$mappedConditions);
            case 'or':
                return new OrCondition(...$mappedConditions);
            case 'not':
                if (count($mappedConditions) > 1) {
                    $mappedConditions = new AndCondition(...$mappedConditions);
                } else {
                    $mappedConditions = $mappedConditions[0];
                }
                return new NotCondition($mappedConditions);
            default:
                throw new InvalidArgumentException('Unknown logical operator: ' . $operator);
        }
    }
}
