<?php

declare(strict_types=1);

namespace Logifire\XML\Exception;

use RuntimeException;

class WriterException extends RuntimeException {

    public const INVALID_PATH = 1;
    public const INVALID_XML = 2;
    public const INVALID_NAMESPACE = 3;

}
