<?php

namespace Featurevisor;

function createLogger(array $options = []): Logger
{
    return new Logger($options);
}
