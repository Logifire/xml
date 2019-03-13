<?php
declare(strict_types=1);
namespace Logifire\XML\Exception;

use RuntimeException;

class ReaderException extends RuntimeException
{

    public const INVALID_PATH = 1;
    public const PATH_NOT_FOUND = 2;
    public const AMBIGUOUS_PATH = 3;
    public const NOT_A_LEAF_NODE = 4;
    public const INVALID_NAMESPACE = 5;

}
