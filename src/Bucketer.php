<?php

namespace Featurevisor;

class Bucketer
{
    private const HASH_SEED = 1;
    private const MAX_HASH_VALUE = 4294967296; // 2^32
    public const MAX_BUCKETED_NUMBER = 100000; // 100% * 1000 to include three decimal places in the same integer value
    private const DEFAULT_BUCKET_KEY_SEPARATOR = '.';

    public static function getBucketedNumber(string $bucketKey): int
    {
        $hashValue = MurmurHash::v3($bucketKey, self::HASH_SEED);
        $ratio = $hashValue / self::MAX_HASH_VALUE;

        return (int) floor($ratio * self::MAX_BUCKETED_NUMBER);
    }

    public static function getBucketKey(array $options): string
    {
        $featureKey = $options['featureKey'];
        $bucketBy = $options['bucketBy'];
        $context = $options['context'];
        $logger = $options['logger'];

        $type = null;
        $attributeKeys = null;

        if (is_string($bucketBy)) {
            $type = 'plain';
            $attributeKeys = [$bucketBy];
        } elseif (is_array($bucketBy) && !isset($bucketBy['or'])) {
            $type = 'and';
            $attributeKeys = $bucketBy;
        } elseif (is_array($bucketBy) && isset($bucketBy['or']) && is_array($bucketBy['or'])) {
            $type = 'or';
            $attributeKeys = $bucketBy['or'];
        } else {
            $logger->error('invalid bucketBy', ['featureKey' => $featureKey, 'bucketBy' => $bucketBy]);
            throw new \Exception('invalid bucketBy');
        }

        $bucketKey = [];

        foreach ($attributeKeys as $attributeKey) {
            $attributeValue = self::getValueFromContext($context, $attributeKey);

            if ($attributeValue === null) {
                continue;
            }

            if ($type === 'plain' || $type === 'and') {
                $bucketKey[] = $attributeValue;
            } else {
                // or
                if (empty($bucketKey)) {
                    $bucketKey[] = $attributeValue;
                }
            }
        }

        $bucketKey[] = $featureKey;

        return implode(self::DEFAULT_BUCKET_KEY_SEPARATOR, $bucketKey);
    }

    private static function getValueFromContext(array $context, string $attributeKey)
    {
        return $context[$attributeKey] ?? null;
    }
}
