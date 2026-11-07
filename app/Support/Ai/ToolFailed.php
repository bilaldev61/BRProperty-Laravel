<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * A tool the model called could not do what it asked.
 *
 * Not an outage: the message goes back to the model as the tool's result, so
 * it can correct itself ("no listing has that slug") or tell the visitor. Write
 * the message for the model to read.
 */
class ToolFailed extends RuntimeException
{
}
