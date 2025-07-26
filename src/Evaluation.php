<?php

namespace Featurevisor;

class Evaluation
{
    // Evaluation reasons
    public const FEATURE_NOT_FOUND = 'feature_not_found';
    public const DISABLED = 'disabled';
    public const REQUIRED = 'required';
    public const OUT_OF_RANGE = 'out_of_range';
    public const NO_VARIATIONS = 'no_variations';
    public const VARIATION_DISABLED = 'variation_disabled';
    public const VARIABLE_NOT_FOUND = 'variable_not_found';
    public const VARIABLE_DEFAULT = 'variable_default';
    public const VARIABLE_DISABLED = 'variable_disabled';
    public const VARIABLE_OVERRIDE = 'variable_override';
    public const NO_MATCH = 'no_match';
    public const FORCED = 'forced';
    public const STICKY = 'sticky';
    public const RULE = 'rule';
    public const ALLOCATED = 'allocated';
    public const ERROR = 'error';

    // Evaluation types
    public const TYPE_FLAG = 'flag';
    public const TYPE_VARIATION = 'variation';
    public const TYPE_VARIABLE = 'variable';

    public string $type;
    public string $featureKey;
    public string $reason;
    public ?string $bucketKey = null;
    public ?int $bucketValue = null;
    public ?string $ruleKey = null;
    public ?string $error = null;
    public ?bool $enabled = null;
    public ?array $traffic = null;
    public ?int $forceIndex = null;
    public ?array $force = null;
    public ?array $required = null;
    public ?array $sticky = null;
    public ?array $variation = null;
    public $variationValue = null;
    public ?string $variableKey = null;
    public $variableValue = null;
    public ?array $variableSchema = null;

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'featureKey' => $this->featureKey,
            'reason' => $this->reason,
            'bucketKey' => $this->bucketKey,
            'bucketValue' => $this->bucketValue,
            'ruleKey' => $this->ruleKey,
            'error' => $this->error,
            'enabled' => $this->enabled,
            'traffic' => $this->traffic,
            'forceIndex' => $this->forceIndex,
            'force' => $this->force,
            'required' => $this->required,
            'sticky' => $this->sticky,
            'variation' => $this->variation,
            'variationValue' => $this->variationValue,
            'variableKey' => $this->variableKey,
            'variableValue' => $this->variableValue,
            'variableSchema' => $this->variableSchema
        ];
    }
}
