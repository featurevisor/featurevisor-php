<?php

namespace Featurevisor;

class Events
{
    public static function getParamsForStickySetEvent(array $previousStickyFeatures = [], array $newStickyFeatures = [], bool $replace = false): array
    {
        $keysBefore = array_keys($previousStickyFeatures);
        $keysAfter = array_keys($newStickyFeatures);

        $allKeys = array_merge($keysBefore, $keysAfter);
        $uniqueFeaturesAffected = array_unique($allKeys);

        return [
            'features' => array_values($uniqueFeaturesAffected),
            'replaced' => $replace
        ];
    }

    public static function getParamsForDatafileSetEvent(DatafileReader $previousDatafileReader, DatafileReader $newDatafileReader): array
    {
        $previousRevision = $previousDatafileReader->getRevision();
        $previousFeatureKeys = $previousDatafileReader->getFeatureKeys();

        $newRevision = $newDatafileReader->getRevision();
        $newFeatureKeys = $newDatafileReader->getFeatureKeys();

        // results
        $removedFeatures = [];
        $changedFeatures = [];
        $addedFeatures = [];

        // checking against existing datafile
        foreach ($previousFeatureKeys as $previousFeatureKey) {
            if (!in_array($previousFeatureKey, $newFeatureKeys)) {
                // feature was removed in new datafile
                $removedFeatures[] = $previousFeatureKey;
                continue;
            }

            // feature exists in both datafiles, check if it was changed
            $previousFeature = $previousDatafileReader->getFeature($previousFeatureKey);
            $newFeature = $newDatafileReader->getFeature($previousFeatureKey);

            if (($previousFeature['hash'] ?? null) !== ($newFeature['hash'] ?? null)) {
                // feature was changed in new datafile
                $changedFeatures[] = $previousFeatureKey;
            }
        }

        // checking against new datafile
        foreach ($newFeatureKeys as $newFeatureKey) {
            if (!in_array($newFeatureKey, $previousFeatureKeys)) {
                // feature was added in new datafile
                $addedFeatures[] = $newFeatureKey;
            }
        }

        // combine all affected feature keys
        $allAffectedFeatures = array_unique(array_merge($removedFeatures, $changedFeatures, $addedFeatures));

        return [
            'revision' => $newRevision,
            'previousRevision' => $previousRevision,
            'revisionChanged' => $previousRevision !== $newRevision,
            'features' => array_values($allAffectedFeatures)
        ];
    }
}
