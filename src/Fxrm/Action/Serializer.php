<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Action;

interface Serializer {
    function export($ctx, $object);

    function import($ctx, $class, $value);
}

?>
