<?php

namespace Rector\Tests\Php74\Rector\Property\TypedPropertyRector\FixtureImported;

use Rector\Tests\Php74\Rector\Property\TypedPropertyRector\Source\AnotherClass;
use Rector\Tests\Php74\Rector\Property\TypedPropertyRector\Source\AnotherClass as Alias;

final class ConflictWithAlias
{
    /**
     * @var Alias
     */
    private $anotherClass;

    public function __construct(AnotherClass $anotherClass)
    {
        $this->anotherClass = $anotherClass;
    }
}

?>
-----
<?php

namespace Rector\Tests\Php74\Rector\Property\TypedPropertyRector\FixtureImported;

use Rector\Tests\Php74\Rector\Property\TypedPropertyRector\Source\AnotherClass;
use Rector\Tests\Php74\Rector\Property\TypedPropertyRector\Source\AnotherClass as Alias;

final class ConflictWithAlias
{
    private Alias $anotherClass;

    public function __construct(AnotherClass $anotherClass)
    {
        $this->anotherClass = $anotherClass;
    }
}

?>
