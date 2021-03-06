<?php
/**
 * This file is a part of Woketo package.
 *
 * (c) Nekland <dev@nekland.fr>
 *
 * For the full license, take a look to the LICENSE file
 * on the root directory of this project
 */

namespace Nekland\Woketo\Message;


use Nekland\Woketo\Core\AbstractConnection;
use Nekland\Woketo\Exception\UnsupportedException;

abstract class BinaryMessageHandler extends SimpleMessageHandler
{
    public function onMessage(string $data, AbstractConnection $connection)
    {
        throw new UnsupportedException('Only binary is authorized by BinaryMessageHandler.');
    }
}
