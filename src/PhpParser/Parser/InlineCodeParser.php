<?php

declare(strict_types=1);

namespace Rector\Core\PhpParser\Parser;

use Nette\Utils\Strings;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use Rector\Core\Contract\PhpParser\NodePrinterInterface;
use Rector\Core\Util\StringUtils;
use Rector\NodeTypeResolver\NodeScopeAndMetadataDecorator;
use Symplify\SmartFileSystem\SmartFileSystem;

final class InlineCodeParser
{
    /**
     * @var string
     * @see https://regex101.com/r/dwe4OW/1
     */
    private const PRESLASHED_DOLLAR_REGEX = '#\\\\\$#';

    /**
     * @var string
     * @see https://regex101.com/r/tvwhWq/1
     */
    private const CURLY_BRACKET_WRAPPER_REGEX = "#'{(\\\$.*?)}'#";

    /**
     * @var string
     * @see https://regex101.com/r/TBlhoR/1
     */
    private const OPEN_PHP_TAG_REGEX = '#^\<\?php\s+#';

    /**
     * @var string
     * @see https://regex101.com/r/TUWwKw/1/
     */
    private const ENDING_SEMI_COLON_REGEX = '#;(\s+)?$#';

    /**
     * @var string
     * @see https://regex101.com/r/8fDjnR/1
     */
    private const VARIABLE_IN_SINGLE_QUOTED_REGEX = '#\'(?<variable>\$.*)\'#U';

    /**
     * @var string
     * @see https://regex101.com/r/1lzQZv/1
     */
    private const BACKREFERENCE_NO_QUOTE_REGEX = '#(?<!")(?<backreference>\\\\\d+)(?!")#';

    public function __construct(
        private readonly NodePrinterInterface $nodePrinter,
        private readonly NodeScopeAndMetadataDecorator $nodeScopeAndMetadataDecorator,
        private readonly SimplePhpParser $simplePhpParser,
        private readonly SmartFileSystem $smartFileSystem
    ) {
    }

    /**
     * @return Stmt[]
     */
    public function parse(string $content): array
    {
        // to cover files too
        if (is_file($content)) {
            $content = $this->smartFileSystem->readFile($content);
        }

        // wrap code so php-parser can interpret it
        $content = StringUtils::isMatch($content, self::OPEN_PHP_TAG_REGEX) ? $content : '<?php ' . $content;
        $content = StringUtils::isMatch($content, self::ENDING_SEMI_COLON_REGEX) ? $content : $content . ';';

        $stmts = $this->simplePhpParser->parseString($content);
        return $this->nodeScopeAndMetadataDecorator->decorateStmtsFromString($stmts);
    }

    public function stringify(Expr $expr): string
    {
        if ($expr instanceof String_) {
            if (! StringUtils::isMatch($expr->value, self::BACKREFERENCE_NO_QUOTE_REGEX)) {
                return $expr->value;
            }

            return Strings::replace(
                $expr->value,
                self::BACKREFERENCE_NO_QUOTE_REGEX,
                static fn (array $match): string => '"\\' . $match['backreference'] . '"'
            );
        }

        if ($expr instanceof Encapsed) {
            // remove "
            $expr = trim($this->nodePrinter->print($expr), '""');
            // use \$ → $
            $expr = Strings::replace($expr, self::PRESLASHED_DOLLAR_REGEX, '$');
            // use \'{$...}\' → $...
            return Strings::replace($expr, self::CURLY_BRACKET_WRAPPER_REGEX, '$1');
        }

        if ($expr instanceof Concat) {
            $string = $this->stringify($expr->left) . $this->stringify($expr->right);
            return Strings::replace(
                $string,
                self::VARIABLE_IN_SINGLE_QUOTED_REGEX,
                static fn (array $match) => $match['variable']
            );
        }

        return $this->nodePrinter->print($expr);
    }
}
