<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2014, Nick Matantsev
 */

namespace Fxrm\Action;

interface NullableSerializer extends Serializer {
    function importNullable($class, $value);
}

?>
