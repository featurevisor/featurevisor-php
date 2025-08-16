<?php

namespace Featurevisor;

function createInstance(array $options = []): Instance
{
    return new Instance($options);
}

function createLogger(array $options = []): Logger
{
    return new Logger($options);
}
