<?php

declare(strict_types=1);

namespace Data2Md\Convert;

/** The request is not a single-file multipart upload this endpoint can read. */
final class MalformedUploadException extends \RuntimeException
{
}
