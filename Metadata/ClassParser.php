<?php
/*
 * This file is part of the Koded package.
 *
 * (c) Mihail Binev <mihail@kodeart.com>
 *
 * Please view the LICENSE distributed with this source code
 * for the full copyright and license information.
 */

namespace Koded\Serializer\Metadata;

/**
 * This code is taken from "Doctrine\Common\Annotations\TokenParser"
 * available at <https://github.com/doctrine/annotations>
 * and modified for the Koded Serializer library.
 */
final class ClassParser
{
    private $pointer = 0;
    private $counter = 0;
    private $tokens  = [];

    public function __construct(string $content)
    {
        $this->tokens = token_get_all($content);
//        token_get_all("<?php\n/**\n *\n */");
        $this->counter = count($this->tokens);
    }

    public function getFQCN(string $class, string $namespace): ?string
    {
        foreach ($this->getUses($namespace) as $className => $fqcn) {
            if ($className === $class) {
                return $fqcn;
            }
        }

        if (class_exists($fqcn = $namespace . '\\' . $class, false)) {
            return $fqcn;
        }

        return null;
    }

    public function getUses(string $namespace): array
    {
        $this->pointer = 0;
        $statements = [];
        while ($token = $this->token()) {
            if ($token[0] === T_USE) {
                $statements = array_merge($statements, $this->getUse());
                continue;
            }
            if ($token[0] !== T_NAMESPACE || $this->getNamespace() != $namespace) {
                continue;
            }
            $statements = [];
        }
        return $statements;
    }

    private function getUse()
    {
        $groupRoot = '';
        $class = '';
        $alias = '';
        $explicitAlias = false;
        $statements = [];
        while (($token = $this->token())) {
            $isNameToken = $token[0] === T_STRING || $token[0] === T_NS_SEPARATOR;
            if (!$explicitAlias && $isNameToken) {
                $class .= $token[1];
                $alias = $token[1];
            } else if ($explicitAlias && $isNameToken) {
                $alias .= $token[1];
            } else if ($token[0] === T_AS) {
                $alias = '';
                $explicitAlias = true;
            } else if ($token === ',') {
                $statements[$alias] = $groupRoot . $class;
                $class = '';
                $alias = '';
                $explicitAlias = false;
            } else if ($token === ';') {
                $statements[$alias] = $groupRoot . $class;
                break;
            } else if ($token === '{' ) {
                $groupRoot = $class;
                $class = '';
            } else if ($token === '}' ) {
                continue;
            } else {
                break;
            }
        }
        return $statements;
    }

    private function getNamespace()
    {
        $name = '';
        while (($token = $this->token()) && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR)) {
            $name .= $token[1];
        }
        return $name;
    }

    private function token()
    {
        for ($i = $this->pointer; $i < $this->counter; $i++) {
            $this->pointer++;
            $t = $this->tokens[$i];
            if ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                continue;
            }
            return $t;
        }
    }
}
