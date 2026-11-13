<?php

declare(strict_types=1);

/*
 * This file is part of the Voter Bundle package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\VoterBundle\Exception;

use LogicException;

/**
 * resolveContext() returned no value for a class its voter declares with #[ProvidesArgument].
 */
final class MissingArgumentException extends LogicException implements ExceptionInterface
{
}
