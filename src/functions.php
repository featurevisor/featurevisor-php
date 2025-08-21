<?php

namespace Featurevisor;

function createInstance(array $options = []): Featurevisor
{
    return Featurevisor::createInstance($options);
}

function createLogger(array $options = []): Logger
{
    return new Logger($options);
}
